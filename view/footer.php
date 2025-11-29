<?php
// footer.php - modular footer
// Uses BASE_URL constant (defined in index.php)
$baseUrl = defined('BASE_URL') ? BASE_URL : '/';
?>
</main>
<footer class="container-fluid py-3">
  <div class="text-center text-muted-custom small">
    Tp-Link (Tapo) PHP Live Web UI with PTZ — experimental. Keep cameras behind VPN or local network.
  </div>
</footer>
<!-- Bootstrap JS bundle (local submodule) -->
<script src="<?= htmlspecialchars($baseUrl) ?>view/external/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>