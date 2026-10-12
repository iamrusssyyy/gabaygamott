<?php
// =====================================================================
// locator/search.php — PUBLIC Medicine Locator (Module 2).
// Layout follows the prototype "Find medicine (filters, list, map)":
//   filters on the left · best picks + results in the middle · map on the
//   right (on phones the map is one tap away with the List/Map switch).
//
// The filters are sent in the URL (a GET form), e.g.
//   search.php?q=losartan&city=Manila&min_price=0&max_price=7&in_stock=1&sort=price
// so a search can be bookmarked or shared.
//
// Stock is NOT live: it is whatever each pharmacy last saved, so every
// result shows "Last updated ... ago" and the page shows a notice.
// =====================================================================

require_once __DIR__ . '/../includes/init.php';

// ---------------------------------------------------------------------
// 1. Read and check the filters from the URL
// ---------------------------------------------------------------------
$q    = mb_substr(get('q'), 0, 100);
$city = in_array(get('city'), METRO_MANILA_CITIES, true) ? get('city') : '';

// Price range: ignore anything that isn't a peso amount like 6 or 6.50
$min_price = preg_match('/^\d{1,6}(\.\d{1,2})?$/', get('min_price')) ? get('min_price') : '';
$max_price = preg_match('/^\d{1,6}(\.\d{1,2})?$/', get('max_price')) ? get('max_price') : '';

// "In stock only" is ON by default. An unticked checkbox sends nothing, so
// the form also sends f=1 to tell "unticked" apart from "first visit".
$filters_sent  = get('f') === '1';
$in_stock_only = $filters_sent ? get('in_stock') === '1' : true;

// On phones: show the list or the map
$view = get('view') === 'map' ? 'map' : 'list';

// The visitor's location (only if they pressed "Use my current location")
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

// Sorting: only these 2 choices are allowed (a "whitelist")
$sort_options = ['nearest' => 'Nearest', 'price' => 'Lowest price'];
$sort = get('sort');
if (!isset($sort_options[$sort])) {
    $sort = $has_location ? 'nearest' : 'price';
}
if ($sort === 'nearest' && !$has_location) {
    $sort = 'price';   // can't sort by distance without a location
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
    if ($min_price !== '') {
        $where[]  = 's.price >= ?';
        $params[] = $min_price;
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
            'cheapest'   => false,
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
// The cheapest pharmacy gets an amber pin, like the "Cheapest in stock" pick
if ($best['cheapest']) {
    $map_pharmacies[$best['cheapest']['pharmacy_id']]['cheapest'] = true;
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

$popular = ['Paracetamol', 'Amlodipine', 'Metformin', 'Losartan'];

// Short helper for "Name · Branch"
$pharmacy_label = fn($row) => $row['pharmacy_name'] . ($row['branch'] ? ' · ' . $row['branch'] : '');
?>

<div class="page-head">
    <h1>Find medicine</h1>
    <div class="subtitle">Maghanap ng gamot · Metro Manila pharmacies</div>
</div>

<!-- One GET form wraps the search box AND the filters,
     so pressing "Search" or "Apply" sends everything together. -->
<form method="get" action="<?= e(url('locator/search.php')) ?>" id="filter-form">
    <input type="hidden" name="f" value="1">
    <input type="hidden" name="view" value="<?= e($view) ?>">
    <input type="hidden" name="lat" id="lat" value="<?= $has_location ? e((string) $user_lat) : '' ?>">
    <input type="hidden" name="lng" id="lng" value="<?= $has_location ? e((string) $user_lng) : '' ?>">

    <!-- Search bar -->
    <div class="d-flex gap-2 mb-4">
        <div class="search-field">
            <i class="bi bi-search"></i>
            <label for="q" class="visually-hidden">Medicine name</label>
            <input type="search" id="q" name="q" class="form-control" value="<?= e($q) ?>"
                   placeholder="Brand or generic name, e.g. Amlodipine 5 mg" autocomplete="off">
        </div>
        <button type="submit" class="btn btn-primary px-4">Search</button>
    </div>

    <div class="row g-4">
        <!-- ===== Left: filters (collapsible on phones) ===== -->
        <div class="col-12 col-lg-3">
            <button class="btn btn-outline-primary w-100 d-lg-none mb-2" type="button"
                    data-bs-toggle="collapse" data-bs-target="#filters" aria-expanded="false" aria-controls="filters">
                <i class="bi bi-sliders"></i> Filters
            </button>
            <div class="collapse d-lg-block" id="filters">
                <div class="card filters-card">
                    <div class="card-body">
                        <h2 class="h5 mb-3">Filters</h2>
                        <div class="mb-3">
                            <label for="city" class="form-label">City</label>
                            <select id="city" name="city" class="form-select">
                                <option value="">All Metro Manila</option>
                                <?php foreach (METRO_MANILA_CITIES as $option): ?>
                                    <option value="<?= e($option) ?>" <?= $city === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <span class="form-label d-block">Price per unit (₱)</span>
                            <div class="d-flex align-items-center gap-2">
                                <input type="number" name="min_price" min="0" step="0.01" class="form-control"
                                       value="<?= e($min_price) ?>" placeholder="0" aria-label="Minimum price">
                                <span>–</span>
                                <input type="number" name="max_price" min="0" step="0.01" class="form-control"
                                       value="<?= e($max_price) ?>" placeholder="Any" aria-label="Maximum price">
                            </div>
                        </div>
                        <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" id="in_stock" name="in_stock" value="1"
                                   <?= $in_stock_only ? 'checked' : '' ?>>
                            <label class="form-check-label" for="in_stock">In stock only</label>
                        </div>

                        <span class="form-label d-block">Sort by</span>
                        <!-- Two radio buttons that look like one switch -->
                        <div class="segmented w-100 mb-4">
                            <?php foreach ($sort_options as $value => $label): ?>
                                <input type="radio" class="btn-check" name="sort" id="sort-<?= e($value) ?>" value="<?= e($value) ?>"
                                       <?= $sort === $value ? 'checked' : '' ?> data-autosubmit>
                                <label for="sort-<?= e($value) ?>" class="flex-fill"><?= e($label) ?></label>
                            <?php endforeach; ?>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Apply filters</button>
                        <a class="btn btn-link w-100" href="<?= e(url('locator/search.php?q=' . urlencode($q))) ?>">Clear filters</a>

                        <hr>
                        <div class="fw-semibold mb-1">Your location</div>
                        <div class="small text-muted mb-2">
                            <?= $has_location ? 'Using your current location (approximate)' : 'Not set — results are not sorted by distance' ?>
                        </div>
                        <button type="button" class="btn btn-outline-primary w-100" data-use-location>
                            <i class="bi bi-crosshair"></i> Use my current location
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== Middle: results ===== -->
        <div class="col-12 col-lg-6">
            <div class="notice notice-amber mb-4">
                <i class="bi bi-info-circle"></i>
                <span>Stock reflects each pharmacy's latest update, not live inventory. Call ahead before you go.</span>
            </div>

            <?php if ($q === ''): ?>
                <h2 class="h4">Find your medicine nearby</h2>
                <p class="text-muted">Hanapin ang gamot na malapit sa iyo. Type a brand or generic name above.</p>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="small text-muted">Popular:</span>
                    <?php foreach ($popular as $term): ?>
                        <a class="chip" href="<?= e(url('locator/search.php?q=' . urlencode($term))) ?>"><?= e($term) ?></a>
                    <?php endforeach; ?>
                </div>

            <?php elseif (!$results): ?>
                <h2 class="h5">No pharmacies found for “<?= e($q) ?>”</h2>
                <p class="text-muted">Try another spelling, the generic name, or remove some filters.</p>

            <?php else: ?>
                <!-- Phone only: List / Map switch -->
                <div class="segmented mb-3 d-lg-none">
                    <a href="<?= e(current_url_with(['view' => 'list'])) ?>" class="btn btn-sm <?= $view === 'list' ? 'btn-primary' : '' ?> rounded-pill px-3">
                        <i class="bi bi-list-ul"></i> List</a>
                    <a href="<?= e(current_url_with(['view' => 'map'])) ?>" class="btn btn-sm <?= $view === 'map' ? 'btn-primary' : '' ?> rounded-pill px-3">
                        <i class="bi bi-map"></i> Map</a>
                </div>

                <!-- Best picks -->
                <?php if ($in_stock): ?>
                    <h2 class="h5 mb-3">Best picks</h2>
                    <div class="row g-2 mb-4">
                        <div class="col-12 col-md-4">
                            <div class="card h-100 best-pick"><div class="card-body">
                                <span class="pill pill-solid-amber mb-2">Cheapest in stock</span>
                                <div class="fw-semibold"><?= e($pharmacy_label($best['cheapest'])) ?></div>
                                <div class="fs-4 fw-bold"><?= e(peso($best['cheapest']['price'])) ?></div>
                                <div class="small text-muted">
                                    <?= $best['cheapest']['distance_km'] !== null ? e(number_format($best['cheapest']['distance_km'], 1)) . ' km · ' : '' ?>
                                    <?= e(time_ago($best['cheapest']['last_updated_at'])) ?>
                                </div>
                            </div></div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="card h-100 best-pick"><div class="card-body">
                                <span class="pill pill-green mb-2">Nearest in stock</span>
                                <?php if ($best['nearest']): ?>
                                    <div class="fw-semibold"><?= e($pharmacy_label($best['nearest'])) ?></div>
                                    <div class="fs-4 fw-bold"><?= e(number_format($best['nearest']['distance_km'], 1)) ?> km</div>
                                    <div class="small text-muted"><?= e(peso($best['nearest']['price'])) ?> · <?= e(time_ago($best['nearest']['last_updated_at'])) ?></div>
                                <?php else: ?>
                                    <p class="small text-muted mb-2">Share your location to see the nearest pharmacy.</p>
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-use-location>
                                        <i class="bi bi-crosshair"></i> Use my location</button>
                                <?php endif; ?>
                            </div></div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="card h-100 best-pick"><div class="card-body">
                                <span class="pill pill-blue mb-2">Recently updated</span>
                                <div class="fw-semibold"><?= e($pharmacy_label($best['recent'])) ?></div>
                                <div class="fs-4 fw-bold"><?= e(time_ago($best['recent']['last_updated_at'])) ?></div>
                                <div class="small text-muted"><?= e(peso($best['recent']['price'])) ?><?= $best['recent']['distance_km'] !== null ? ' · ' . e(number_format($best['recent']['distance_km'], 1)) . ' km' : '' ?></div>
                            </div></div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="d-flex justify-content-between align-items-baseline mb-2 <?= $view === 'map' ? 'd-none d-lg-flex' : '' ?>">
                    <h2 class="h5 mb-0"><?= $pharmacy_count ?> <?= $pharmacy_count === 1 ? 'pharmacy' : 'pharmacies' ?></h2>
                    <span class="small text-muted">Sorted by <?= $sort === 'nearest' ? 'distance' : 'lowest price' ?></span>
                </div>
                <?php if ($brands): ?>
                    <div class="small text-muted mb-3 <?= $view === 'map' ? 'd-none d-lg-block' : '' ?>">Brands found: <?= e(implode(', ', $brands)) ?></div>
                <?php endif; ?>

                <!-- Result cards (hidden on phones while the map is shown) -->
                <div class="d-grid gap-3 <?= $view === 'map' ? 'd-none d-lg-grid' : '' ?>">
                    <?php foreach ($results as $row): ?>
                        <div class="card result-card">
                            <div class="card-body d-flex justify-content-between gap-3">
                                <div>
                                    <h3 class="h6 fw-bold mb-1"><?= e($pharmacy_label($row)) ?></h3>
                                    <div class="small text-muted">
                                        <?= e($row['address']) ?>, <?= e($row['city']) ?>
                                        <?php if ($row['distance_km'] !== null): ?> · <?= e(number_format($row['distance_km'], 1)) ?> km<?php endif; ?>
                                    </div>
                                    <div class="small text-muted mt-1">
                                        <?= e(medicine_name($row)) ?> · Last updated <?= e(time_ago($row['last_updated_at'])) ?>
                                    </div>
                                    <div class="small mt-2 d-flex flex-wrap gap-3">
                                        <?php if ($row['phone']): ?>
                                            <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $row['phone'])) ?>"><i class="bi bi-telephone"></i> Call</a>
                                        <?php endif; ?>
                                        <a target="_blank" rel="noopener" href="<?= e(directions_url($row['latitude'], $row['longitude'])) ?>"><i class="bi bi-sign-turn-right"></i> Directions</a>
                                        <?php if ($row['opening_hours']): ?><span class="text-muted"><i class="bi bi-clock"></i> <?= e($row['opening_hours']) ?></span><?php endif; ?>
                                    </div>
                                </div>
                                <div class="text-end d-flex flex-column align-items-end justify-content-between gap-2">
                                    <div>
                                        <div class="price"><?= e(peso($row['price'])) ?></div>
                                        <div class="small text-muted">per <?= e($row['unit']) ?></div>
                                    </div>
                                    <?= stock_pill((int) $row['quantity'], (int) $row['low_stock_threshold']) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- ===== Right: map (always on desktop; on phones only in Map view) ===== -->
        <div class="col-12 col-lg-3 <?= $view === 'map' && $results ? '' : 'd-none d-lg-block' ?>">
            <div class="map-sticky">
                <!-- locator.js reads the pins from this JSON block.
                     JSON_HEX_TAG turns < and > into codes so text can't close the tag. -->
                <div id="results-map"></div>
                <script type="application/json" id="map-data"><?= json_encode($map_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
                <p class="small text-muted mt-2">Tap a pin to see prices, stock, and directions.</p>
            </div>
        </div>
    </div>
</form>

<?php require __DIR__ . '/../includes/footer.php'; ?>
