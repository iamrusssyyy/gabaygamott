<?php
// =====================================================================
// locator/search.php — PUBLIC Medicine Locator (Module 2).
// Layout follows wireframe 2e: filters on the left, results on the right.
//
// The filters are sent in the URL (a GET form), e.g.
//   search.php?q=losartan&city=Manila&max_price=7&in_stock=1&sort=price
// so a search can be bookmarked or shared.
//
// Stock is NOT live: it is whatever each pharmacy last saved, so every
// result shows "Updated ... ago" and the page shows a notice.
// =====================================================================

require_once __DIR__ . '/../includes/init.php';

// ---------------------------------------------------------------------
// 1. Read and check the filters from the URL
// ---------------------------------------------------------------------
$q    = mb_substr(get('q'), 0, 100);
$city = in_array(get('city'), METRO_MANILA_CITIES, true) ? get('city') : '';

$max_price = get('max_price');
if (!preg_match('/^\d{1,6}(\.\d{1,2})?$/', $max_price)) {
    $max_price = '';   // ignore anything that isn't a peso amount
}

// "In stock only" is ON by default. An unticked checkbox sends nothing, so
// the form also sends f=1 to tell "unticked" apart from "first visit".
$filters_sent  = get('f') === '1';
$in_stock_only = $filters_sent ? get('in_stock') === '1' : true;

$view = get('view') === 'map' ? 'map' : 'list';

// The visitor's location (only if they pressed "Use my location")
$user_lat = null;
$user_lng = null;
if (is_numeric(get('lat')) && is_numeric(get('lng'))) {
    $lat = (float) get('lat');
    $lng = (float) get('lng');
    if ($lat >= 4 && $lat <= 21 && $lng >= 116 && $lng <= 127) { // roughly the Philippines
        $user_lat = $lat;
        $user_lng = $lng;
    }
}
$has_location = $user_lat !== null;

// Sorting: only these 3 choices are allowed (a "whitelist")
$sort_options = ['nearest' => 'Nearest', 'price' => 'Lowest price', 'updated' => 'Recently updated'];
$sort = get('sort');
if (!isset($sort_options[$sort])) {
    $sort = $has_location ? 'nearest' : 'price';
}
$sort_note = '';
if ($sort === 'nearest' && !$has_location) {
    $sort = 'price';
    $sort_note = 'Press "Use my location" to sort by nearest.';
}

// ---------------------------------------------------------------------
// 2. Search the database
// ---------------------------------------------------------------------
$results = [];
if ($q !== '') {
    // Every word typed must appear in the generic name, brand name, or
    // strength. "Losartan 50mg" finds "Losartan Potassium" + "50 mg".
    $where  = [];
    $params = [];
    $words  = array_slice(preg_split('/\s+/', $q), 0, 5);  // at most 5 words
    foreach ($words as $word) {
        // addcslashes() makes % and _ count as normal characters in LIKE
        $like = '%' . addcslashes($word, '%_\\') . '%';
        $where[]  = "(m.generic_name LIKE ? OR m.brand_name LIKE ? OR REPLACE(m.strength, ' ', '') LIKE ?)";
        $params[] = $like;
        $params[] = $like;
        $params[] = '%' . addcslashes(str_replace(' ', '', $word), '%_\\') . '%';
    }
    if ($city !== '') {
        $where[]  = 'p.city = ?';
        $params[] = $city;
    }
    if ($max_price !== '') {
        $where[]  = 's.price <= ?';
        $params[] = $max_price;
    }
    if ($in_stock_only) {
        $where[] = 's.quantity > 0';
    }

    // The SQL is built only from our own fixed pieces above; everything the
    // visitor typed goes through "?" placeholders.
    $stmt = db()->prepare(
        "SELECT s.quantity, s.price, s.low_stock_threshold, s.last_updated_at,
                p.id AS pharmacy_id, p.name AS pharmacy_name, p.branch, p.address, p.city,
                p.latitude, p.longitude, p.phone, p.opening_hours,
                m.generic_name, m.brand_name, m.strength, m.dosage_form, m.unit
         FROM pharmacy_stock s
         JOIN pharmacies p ON p.id = s.pharmacy_id
         JOIN users u      ON u.id = p.user_id AND u.status = 'active'   -- approved pharmacies only
         JOIN medicines m  ON m.id = s.medicine_id AND m.is_active = 1
         WHERE " . implode(' AND ', $where) . "
         LIMIT 300"
    );
    $stmt->execute($params);
    $results = $stmt->fetchAll();
}

// ---------------------------------------------------------------------
// 3. Distance, sorting, and "best picks" (done in PHP — easy to follow)
// ---------------------------------------------------------------------
foreach ($results as $i => $row) {
    $results[$i]['distance_km'] = $has_location
        ? distance_km($user_lat, $user_lng, (float) $row['latitude'], (float) $row['longitude'])
        : null;
}

// usort() sorts with our own compare function. "<=>" (spaceship) returns
// -1, 0, or 1 depending on which value is smaller.
usort($results, function ($a, $b) use ($sort) {
    // In-stock results always come before out-of-stock ones
    $stock_order = ($b['quantity'] > 0) <=> ($a['quantity'] > 0);
    if ($stock_order !== 0) {
        return $stock_order;
    }
    if ($sort === 'nearest') {
        return $a['distance_km'] <=> $b['distance_km'];
    }
    if ($sort === 'updated') {
        return strcmp($b['last_updated_at'], $a['last_updated_at']); // newest first
    }
    return (float) $a['price'] <=> (float) $b['price'];
});

// Best picks are chosen from results that are in stock
$in_stock = array_values(array_filter($results, fn($row) => $row['quantity'] > 0));
$best = ['cheapest' => null, 'nearest' => null, 'recent' => null];
foreach ($in_stock as $row) {
    if ($best['cheapest'] === null || (float) $row['price'] < (float) $best['cheapest']['price']) {
        $best['cheapest'] = $row;
    }
    if ($has_location && ($best['nearest'] === null || $row['distance_km'] < $best['nearest']['distance_km'])) {
        $best['nearest'] = $row;
    }
    if ($best['recent'] === null || $row['last_updated_at'] > $best['recent']['last_updated_at']) {
        $best['recent'] = $row;
    }
}

// Count pharmacies, and list the brand names found ("also sold as")
$pharmacy_count = count(array_unique(array_column($results, 'pharmacy_id')));
$brands = array_unique(array_filter(array_column($results, 'brand_name')));
sort($brands);

// Data for the map: one pin per pharmacy, listing its matching medicines
$map_pharmacies = [];
foreach ($results as $row) {
    $id = $row['pharmacy_id'];
    if (!isset($map_pharmacies[$id])) {
        $map_pharmacies[$id] = [
            'name'       => $row['pharmacy_name'] . ($row['branch'] ? ' · ' . $row['branch'] : ''),
            'address'    => $row['address'] . ', ' . $row['city'],
            'lat'        => (float) $row['latitude'],
            'lng'        => (float) $row['longitude'],
            'phone'      => $row['phone'],
            'directions' => directions_url($row['latitude'], $row['longitude']),
            'min_price'  => null,
            'items'      => [],
        ];
    }
    $status = stock_status((int) $row['quantity'], (int) $row['low_stock_threshold']);
    $map_pharmacies[$id]['items'][] = [
        'name'    => medicine_name($row),
        'price'   => peso($row['price']) . ' per ' . $row['unit'],
        'status'  => $status['label'],
        'color'   => $status['color'],
        'updated' => 'Updated ' . time_ago($row['last_updated_at']),
    ];
    if ($row['quantity'] > 0 && ($map_pharmacies[$id]['min_price'] === null || (float) $row['price'] < $map_pharmacies[$id]['min_price'])) {
        $map_pharmacies[$id]['min_price'] = (float) $row['price'];
    }
}
$map_data = [
    'user'       => $has_location ? ['lat' => $user_lat, 'lng' => $user_lng] : null,
    'pharmacies' => array_values($map_pharmacies),
];

// ---------------------------------------------------------------------
// 4. Show the page
// ---------------------------------------------------------------------
$page_title   = $q !== '' ? $q . ' — Find medicine' : 'Find medicine';
$use_map      = true;
$page_scripts = ['assets/js/locator.js'];
require __DIR__ . '/../includes/header.php';

$popular = ['Losartan 50mg', 'Metformin 500mg', 'Amlodipine 5mg', 'Paracetamol'];
?>

<!-- One GET form wraps the search box AND the filter sidebar,
     so pressing "Search" or "Apply" sends everything together. -->
<form method="get" action="<?= e(url('locator/search.php')) ?>" id="filter-form">
    <input type="hidden" name="f" value="1">
    <input type="hidden" name="view" value="<?= e($view) ?>">
    <input type="hidden" name="lat" id="lat" value="<?= $has_location ? e((string) $user_lat) : '' ?>">
    <input type="hidden" name="lng" id="lng" value="<?= $has_location ? e((string) $user_lng) : '' ?>">

    <!-- Search bar -->
    <div class="d-flex flex-wrap gap-2 mb-3">
        <label for="q" class="visually-hidden">Medicine name</label>
        <input type="search" id="q" name="q" class="form-control form-control-lg flex-grow-1 search-box"
               value="<?= e($q) ?>" placeholder="Brand or generic name, e.g. Losartan 50mg" autocomplete="off">
        <button type="submit" class="btn btn-primary btn-lg">Search</button>
        <button type="button" class="btn btn-outline-secondary btn-lg" data-use-location>
            ◎ <?= $has_location ? 'Location on' : 'Use my location' ?>
        </button>
    </div>

    <div class="row g-4">
        <!-- ===== Filter sidebar (collapsible on phones) ===== -->
        <div class="col-12 col-lg-3">
            <button class="btn btn-outline-secondary w-100 d-lg-none mb-2" type="button"
                    data-bs-toggle="collapse" data-bs-target="#filters" aria-expanded="false" aria-controls="filters">
                Filters
            </button>
            <div class="collapse d-lg-block" id="filters">
                <div class="card">
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="city" class="form-label fw-semibold">City</label>
                            <select id="city" name="city" class="form-select">
                                <option value="">All Metro Manila</option>
                                <?php foreach (METRO_MANILA_CITIES as $option): ?>
                                    <option value="<?= e($option) ?>" <?= $city === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="max_price" class="form-label fw-semibold">Max price per unit</label>
                            <div class="input-group">
                                <span class="input-group-text">₱</span>
                                <input type="number" id="max_price" name="max_price" min="0" step="0.01"
                                       class="form-control" value="<?= e($max_price) ?>" placeholder="Any">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="sort" class="form-label fw-semibold">Sort by</label>
                            <select id="sort" name="sort" class="form-select" data-autosubmit>
                                <?php foreach ($sort_options as $value => $label): ?>
                                    <option value="<?= e($value) ?>" <?= $sort === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="in_stock" name="in_stock" value="1"
                                   <?= $in_stock_only ? 'checked' : '' ?>>
                            <label class="form-check-label" for="in_stock">In stock only</label>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Apply filters</button>
                        <a class="btn btn-link w-100" href="<?= e(url('locator/search.php?q=' . urlencode($q))) ?>">Clear filters</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== Results ===== -->
        <div class="col-12 col-lg-9">

            <?php if ($q === ''): ?>
                <h1 class="h3">Find your medicine nearby</h1>
                <p class="text-muted">Hanapin ang gamot na malapit sa iyo</p>
                <p>Try:
                    <?php foreach ($popular as $term): ?>
                        <a class="btn btn-sm btn-outline-secondary me-1 mb-1"
                           href="<?= e(url('locator/search.php?q=' . urlencode($term))) ?>"><?= e($term) ?></a>
                    <?php endforeach; ?>
                </p>

            <?php else: ?>
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                    <div>
                        <h1 class="h4 mb-1">
                            <?= $pharmacy_count ?> <?= $pharmacy_count === 1 ? 'pharmacy has' : 'pharmacies have' ?>
                            “<?= e($q) ?>”
                        </h1>
                        <?php if ($brands): ?>
                            <div class="small text-muted">Brands found: <?= e(implode(', ', $brands)) ?></div>
                        <?php endif; ?>
                    </div>
                    <!-- List / Map switch (keeps all other filters) -->
                    <div class="btn-group" role="group" aria-label="View">
                        <a class="btn btn-outline-secondary<?= $view === 'list' ? ' active' : '' ?>"
                           href="<?= e(current_url_with(['view' => 'list'])) ?>">List</a>
                        <a class="btn btn-outline-secondary<?= $view === 'map' ? ' active' : '' ?>"
                           href="<?= e(current_url_with(['view' => 'map'])) ?>">Map</a>
                    </div>
                </div>

                <div class="alert alert-info py-2 small">
                    ⓘ Stock is as of each pharmacy's last update — <strong>not live</strong>. Call before you go.
                </div>
                <?php if ($sort_note !== ''): ?>
                    <p class="small text-muted"><?= e($sort_note) ?></p>
                <?php endif; ?>

                <?php if (!$results): ?>
                    <p>No pharmacies found. Try another spelling, the generic name, or remove some filters.</p>
                <?php else: ?>

                    <!-- Best picks -->
                    <?php if ($in_stock): ?>
                        <div class="row g-2 mb-3">
                            <?php if ($best['cheapest']): ?>
                                <div class="col-12 col-md-4">
                                    <div class="card h-100 best-pick"><div class="card-body py-2">
                                        <div class="small text-muted">Cheapest in stock</div>
                                        <div class="fs-5 fw-semibold"><?= e(peso($best['cheapest']['price'])) ?></div>
                                        <div class="small"><?= e($best['cheapest']['pharmacy_name']) ?><?= $best['cheapest']['branch'] ? ' · ' . e($best['cheapest']['branch']) : '' ?></div>
                                    </div></div>
                                </div>
                            <?php endif; ?>
                            <div class="col-12 col-md-4">
                                <div class="card h-100 best-pick"><div class="card-body py-2">
                                    <div class="small text-muted">Nearest in stock</div>
                                    <?php if ($best['nearest']): ?>
                                        <div class="fs-5 fw-semibold"><?= e(number_format($best['nearest']['distance_km'], 1)) ?> km</div>
                                        <div class="small"><?= e($best['nearest']['pharmacy_name']) ?><?= $best['nearest']['branch'] ? ' · ' . e($best['nearest']['branch']) : '' ?></div>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-sm btn-outline-primary mt-1" data-use-location>◎ Use my location</button>
                                    <?php endif; ?>
                                </div></div>
                            </div>
                            <?php if ($best['recent']): ?>
                                <div class="col-12 col-md-4">
                                    <div class="card h-100 best-pick"><div class="card-body py-2">
                                        <div class="small text-muted">Most recently updated</div>
                                        <div class="fs-5 fw-semibold"><?= e(time_ago($best['recent']['last_updated_at'])) ?></div>
                                        <div class="small"><?= e($best['recent']['pharmacy_name']) ?><?= $best['recent']['branch'] ? ' · ' . e($best['recent']['branch']) : '' ?></div>
                                    </div></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($view === 'map'): ?>
                        <!-- Map view. The data goes into a JSON block that locator.js reads.
                             JSON_HEX_TAG turns < and > into codes so text can't close the tag. -->
                        <div id="results-map" class="border"></div>
                        <script type="application/json" id="map-data"><?= json_encode($map_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
                        <p class="small text-muted mt-2">Tap a pin to see prices, stock, and directions.</p>
                    <?php else: ?>
                        <!-- List view -->
                        <div class="d-grid gap-2">
                            <?php foreach ($results as $row): ?>
                                <div class="card result-card">
                                    <div class="card-body d-flex justify-content-between gap-3">
                                        <div>
                                            <h2 class="h6 mb-1">
                                                <?= e($row['pharmacy_name']) ?><?= $row['branch'] ? ' · ' . e($row['branch']) : '' ?>
                                            </h2>
                                            <div class="small text-muted">
                                                <?= e($row['address']) ?>, <?= e($row['city']) ?>
                                                <?php if ($row['distance_km'] !== null): ?> · <?= e(number_format($row['distance_km'], 1)) ?> km<?php endif; ?>
                                                <?php if ($row['opening_hours']): ?> · <?= e($row['opening_hours']) ?><?php endif; ?>
                                            </div>
                                            <div class="small mt-1"><?= e(medicine_name($row)) ?></div>
                                            <div class="small mt-1">
                                                <?= stock_dot((int) $row['quantity'], (int) $row['low_stock_threshold']) ?>
                                                · <span class="text-muted">Updated <?= e(time_ago($row['last_updated_at'])) ?></span>
                                            </div>
                                        </div>
                                        <div class="text-end text-nowrap">
                                            <div class="fs-4 fw-semibold"><?= e(peso($row['price'])) ?></div>
                                            <div class="small text-muted">per <?= e($row['unit']) ?></div>
                                        </div>
                                    </div>
                                    <div class="card-footer bg-white d-flex gap-2">
                                        <?php if ($row['phone']): ?>
                                            <a class="btn btn-sm btn-outline-secondary" href="tel:<?= e(preg_replace('/[^0-9+]/', '', $row['phone'])) ?>">Call <?= e($row['phone']) ?></a>
                                        <?php endif; ?>
                                        <a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener"
                                           href="<?= e(directions_url($row['latitude'], $row['longitude'])) ?>">Directions</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</form>

<?php require __DIR__ . '/../includes/footer.php'; ?>
