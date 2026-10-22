</main>

<div class="toast-container"></div>

<script>
  window.BASE_URL = <?= json_encode(base_url()) ?>;
  window.CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;
  window.IS_LOGGED_IN = true;
</script>
<script src="<?= url('assets/js/app.js') ?>?v=<?= filemtime(__DIR__ . '/../assets/js/app.js') ?>"></script>
<?= $pageScripts ?? '' ?>
</body>
</html>
