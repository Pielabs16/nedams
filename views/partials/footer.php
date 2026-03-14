<?php
// views/partials/footer.php
?>
</div><!-- .page-content -->
</div><!-- .main-content -->
</div><!-- .app-wrapper -->

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<!-- Marker Clusterer -->
<script src="https://unpkg.com/@googlemaps/markerclusterer/dist/index.min.js"></script>
<!-- Main app JS -->
<script src="<?= appUrl() ?>/assets/js/app.js"></script>

<?php if (!empty($loadGoogleMaps)): ?>
<script>
    window.NEDAMS_BASE_URL = '<?= appUrl() ?>';
    window.MAP_LAT         = <?= setting('general.map_default_lat', 0.3476) ?>;
    window.MAP_LNG         = <?= setting('general.map_default_lng', 32.6152) ?>;
    window.MAP_ZOOM        = <?= setting('general.map_default_zoom', 15) ?>;
    <?php if (!empty($addrLat)): ?>
    window.ADDR_LAT = <?= (float)$addrLat ?>;
    window.ADDR_LNG = <?= (float)$addrLng ?>;
    <?php endif; ?>
</script>
<script async defer
    src="https://maps.googleapis.com/maps/api/js?key=<?= htmlspecialchars(gmapsKey()) ?>&callback=initMap&libraries=places">
</script>
<?php endif; ?>

<?php if (!empty($inlineJs)): ?>
<script><?= $inlineJs ?></script>
<?php endif; ?>

</body>
</html>
