<?php
// =====================================================================
// index.php — home page (prototype "Home"):
//   * mint hero with the medicine search and "How GabayGamot helps"
//   * "What do you need?" — Find medicine / Request assistance / Donate
//   * stock notice, partner programs, and a banner for organizations
// =====================================================================

require_once __DIR__ . '/includes/init.php';

$programs = db()->query(
    'SELECT name, description FROM partner_programs WHERE is_active = 1 ORDER BY id'
)->fetchAll();

$popular = ['Paracetamol', 'Amlodipine', 'Metformin', 'Losartan'];

$page_title = 'Home';
$full_width = true;   // this page draws its own full-width hero band
require __DIR__ . '/includes/header.php';
?>

<!-- ===== Hero ===== -->
<section class="hero">
    <div class="container">
        <div class="row g-4 align-items-center">
            <div class="col-12 col-lg-7">
                <h1 class="mb-3">Find medicine. Get help.</h1>
                <p class="lead mb-4">Check which Metro Manila pharmacies have your medicine and at what price,
                    or ask partner programs for help paying for it.</p>

                <form method="get" action="<?= e(url('locator/search.php')) ?>">
                    <label for="home-q" class="form-label">Search medicine</label>
                    <div class="d-flex flex-column flex-sm-row gap-2">
                        <div class="search-field">
                            <i class="bi bi-search"></i>
                            <input type="search" id="home-q" name="q" class="form-control form-control-lg"
                                   placeholder="Brand or generic name, e.g. Amlodipine or Norvasc" required>
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg px-4">Search</button>
                    </div>
                </form>
                <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                    <span class="small text-muted">Popular:</span>
                    <?php foreach ($popular as $term): ?>
                        <a class="chip" href="<?= e(url('locator/search.php?q=' . urlencode($term))) ?>"><?= e($term) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="col-12 col-lg-4 offset-lg-1">
                <div class="card p-2">
                    <div class="card-body">
                        <h2 class="h5 mb-4">How GabayGamot helps</h2>
                        <div class="d-flex gap-3 mb-3">
                            <span class="step-number">1</span>
                            <div><strong>Find it</strong><div class="small text-muted">See stock and prices at nearby pharmacies.</div></div>
                        </div>
                        <div class="d-flex gap-3 mb-3">
                            <span class="step-number">2</span>
                            <div><strong>Ask for help</strong><div class="small text-muted">Send your prescription and indigency certificate once.</div></div>
                        </div>
                        <div class="d-flex gap-3">
                            <span class="step-number">3</span>
                            <div><strong>Get matched</strong><div class="small text-muted">We route your request to PCS, PCSO, DSWD or Malasakit.</div></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="container py-5">

    <!-- ===== What do you need? ===== -->
    <h2 class="section-title">What do you need? <span class="small text-muted fw-normal">· Ano ang kailangan mo?</span></h2>
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="card h-100"><div class="card-body p-4">
                <span class="icon-tile mb-3"><i class="bi bi-geo-alt"></i></span>
                <h3 class="h5">Find medicine</h3>
                <p class="text-muted">Search by brand or generic name, filter by city and price, and see it on a map. No account needed.</p>
                <a class="link-arrow" href="<?= e(url('locator/search.php')) ?>">Start searching →</a>
            </div></div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card h-100"><div class="card-body p-4">
                <span class="icon-tile mb-3"><i class="bi bi-file-earmark-text"></i></span>
                <h3 class="h5">Request assistance</h3>
                <p class="text-muted">Four short steps: patient, medicine, documents, review. Track every status change.</p>
                <a class="link-arrow" href="<?= e(url('beneficiary/request_new.php')) ?>">Start a request →</a>
            </div></div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card h-100"><div class="card-body p-4">
                <span class="icon-tile icon-tile-amber mb-3"><i class="bi bi-heart"></i></span>
                <h3 class="h5">Donate</h3>
                <p class="text-muted">Individuals, pharmacies and organizations can give sealed, unexpired medicines.</p>
                <a class="link-arrow" href="<?= e(url('donor/donation_new.php')) ?>">Donate now →</a>
            </div></div>
        </div>
    </div>

    <!-- ===== Stock notice ===== -->
    <div class="notice notice-amber mb-5">
        <i class="bi bi-info-circle"></i>
        <span>Stock and prices reflect each pharmacy's latest update, not live inventory. Every result shows when it was last updated.</span>
    </div>

    <!-- ===== Partner programs ===== -->
    <h2 class="section-title">Partner programs</h2>
    <div class="row g-3 mb-5">
        <?php foreach ($programs as $program): ?>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card h-100"><div class="card-body">
                    <h3 class="h6 mb-1"><?= e($program['name']) ?></h3>
                    <p class="small text-muted mb-0"><?= e($program['description']) ?></p>
                </div></div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- ===== Banner for pharmacies and partner organizations ===== -->
    <div class="cta-banner d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
        <div>
            <h2 class="h4 mb-1">Pharmacy or partner organization?</h2>
            <p class="mb-0">List your stock and prices, or receive requests matched to your program.
                Accounts are activated after an admin review.</p>
        </div>
        <a href="<?= e(url('register.php')) ?>" class="btn btn-light btn-lg text-nowrap" style="color: var(--gg-green-dark)">Register your organization</a>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
