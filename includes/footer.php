<?php
// =====================================================================
// includes/footer.php — bottom part of every page. Closes what
// includes/header.php opened (container, main, admin layout).
// =====================================================================
?>
<?php if (!($full_width ?? false) || ($is_admin_layout ?? false)): ?>
</div>
<?php endif; ?>
</main>

<?php if ($is_admin_layout ?? false): ?>
</div><!-- /.admin-shell -->
<?php else: ?>
<footer class="site-footer">
    <div class="container d-flex flex-wrap gap-3 gap-lg-5">
        <strong><?= e(APP_NAME) ?></strong>
        <span>Pilot area: Metro Manila</span>
        <span>Protected under the Data Privacy Act of 2012 (RA 10173). <a href="<?= e(url('privacy.php')) ?>">Privacy notice</a></span>
        <span>Capstone project · pharmacies and prices are sample data</span>
    </div>
</footer>
<?php endif; ?>

<!-- Bootstrap JavaScript (mobile menu button and the avatar dropdown) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>

<?php if ($use_map ?? false): ?>
<!-- Leaflet map library (only on pages that show a map) -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
        crossorigin="anonymous"></script>
<?php endif; ?>

<?php if ($use_charts ?? false): ?>
<!-- Chart.js library (only on pages that show charts) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"
        integrity="sha384-9nhczxUqK87bcKHh20fSQcTGD4qq5GhayNYSYWqwBkINBhOfQLg/P5HG5lF1urn4"
        crossorigin="anonymous"></script>
<?php endif; ?>

<?php foreach ($page_scripts ?? [] as $script): ?>
<script src="<?= e(url($script)) ?>"></script>
<?php endforeach; ?>
</body>
</html>
