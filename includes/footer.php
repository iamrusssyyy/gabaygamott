<?php
// =====================================================================
// includes/footer.php — bottom part of every page.
// =====================================================================
?>
</main>

<footer class="border-top py-3 mt-5">
    <div class="container small text-muted">
        <?= e(APP_NAME) ?> · Medicine Locator and Donation Management System · Metro Manila
    </div>
</footer>

<!-- Bootstrap JavaScript (needed for the mobile menu button) -->
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
