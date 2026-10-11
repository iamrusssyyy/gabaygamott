<?php
// =====================================================================
// index.php — home page, wireframe 1b/2b "Two doors":
//   Door 1: Find medicine (search box)
//   Door 2: Get help paying (start an assistance request)
// plus the partner program chips from wireframe 1d.
// =====================================================================

require_once __DIR__ . '/includes/init.php';

$programs = db()->query(
    'SELECT name FROM partner_programs WHERE is_active = 1 ORDER BY id'
)->fetchAll();

$popular = ['Losartan 50mg', 'Metformin 500mg', 'Amlodipine 5mg'];

$page_title = 'Home';
require __DIR__ . '/includes/header.php';
?>

<div class="text-center my-4">
    <h1 class="display-6 fw-semibold mb-1">What do you need today?</h1>
    <p class="text-muted fs-5">Ano ang kailangan mo?</p>
</div>

<div class="row g-4 mb-4">
    <!-- Door 1: Find medicine -->
    <div class="col-12 col-lg-6">
        <div class="card door h-100">
            <div class="card-body p-4">
                <h2 class="h4">Find medicine</h2>
                <p class="text-muted">See which pharmacies have it, and how much.</p>
                <form method="get" action="<?= e(url('locator/search.php')) ?>" class="d-flex gap-2 mb-3">
                    <label for="home-q" class="visually-hidden">Brand or generic name</label>
                    <input type="search" id="home-q" name="q" class="form-control form-control-lg"
                           placeholder="Brand or generic name" required>
                    <button type="submit" class="btn btn-primary btn-lg">Search</button>
                </form>
                <p class="small mb-0">Popular:
                    <?php foreach ($popular as $i => $term): ?>
                        <a href="<?= e(url('locator/search.php?q=' . urlencode($term))) ?>"><?= e($term) ?></a><?= $i < count($popular) - 1 ? ' · ' : '' ?>
                    <?php endforeach; ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Door 2: Get help paying -->
    <div class="col-12 col-lg-6">
        <div class="card door h-100">
            <div class="card-body p-4">
                <h2 class="h4">Get help paying</h2>
                <p class="text-muted">Request medicine assistance · Humingi ng tulong sa gamot</p>
                <p class="mb-3">You'll need: <strong>prescription</strong> · <strong>certificate of indigency</strong></p>
                <a href="<?= e(url('beneficiary/request_new.php')) ?>" class="btn btn-primary btn-lg">Start a request →</a>
            </div>
        </div>
    </div>
</div>

<!-- Secondary tasks as a row of buttons -->
<div class="d-flex flex-wrap justify-content-center gap-2 mb-4">
    <a href="<?= e(url('beneficiary/dashboard.php')) ?>" class="btn btn-outline-secondary">Track a request</a>
    <a href="<?= e(url('donor/donation_new.php')) ?>" class="btn btn-outline-secondary">Donate medicine</a>
    <a href="<?= e(url('register.php')) ?>" class="btn btn-outline-secondary">For pharmacies</a>
</div>

<p class="text-center small text-muted mb-5">
    ⓘ Stock &amp; prices are updated by each pharmacy — always check “Last updated”.
</p>

<!-- Partner programs (from wireframe 1d) -->
<div class="text-center">
    <h2 class="h6 text-muted">We connect you with</h2>
    <?php foreach ($programs as $program): ?>
        <span class="program-chip"><?= e($program['name']) ?></span>
    <?php endforeach; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
