<?php require_once __DIR__ . '/functions.php'; ?>
</main>

<footer class="bw-footer">
  <div class="bw-footer-inner">
    <div class="bw-footer-brand">
      <span class="bw-logo-text"><?= e(setting('site_name', 'Baltic Wave')) ?></span>
      <p><?= e(setting('footer_text', 'A real-time global music event.')) ?></p>
    </div>
    <div class="bw-footer-links">
      <?php foreach (menu_tree() as $item): ?>
        <a href="<?= e(menu_item_url($item)) ?>"><?= e($item['label']) ?></a>
      <?php endforeach; ?>
    </div>
    <div class="bw-footer-social">
      <?php if (setting('youtube_url') !== ''): ?>
        <a href="<?= e(setting('youtube_url')) ?>" target="_blank" rel="noopener" aria-label="YouTube">YouTube</a>
      <?php endif; ?>
      <?php if (setting('facebook_url') !== ''): ?>
        <a href="<?= e(setting('facebook_url')) ?>" target="_blank" rel="noopener" aria-label="Facebook">Facebook</a>
      <?php endif; ?>
      <?php if (setting('contact_email') !== ''): ?>
        <a href="mailto:<?= e(setting('contact_email')) ?>"><?= e(setting('contact_email')) ?></a>
      <?php endif; ?>
    </div>
  </div>
  <div class="bw-footer-bottom">
    &copy; <?= date('Y') ?> <?= e(setting('site_name', 'Baltic Wave')) ?>. All rights reserved.
  </div>
</footer>

<script src="<?= e(base_url()) ?>/assets/js/main.js?v=<?= e(BW_VERSION) ?>"></script>
</body>
</html>
