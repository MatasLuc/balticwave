<?php
require_once __DIR__ . '/functions.php';
$bwFooterMenu = menu_tree();
$bwFooterHalf = (int)ceil(count($bwFooterMenu) / 2);
$bwFooterCol1 = array_slice($bwFooterMenu, 0, $bwFooterHalf);
$bwFooterCol2 = array_slice($bwFooterMenu, $bwFooterHalf);
$bwHasSocial  = setting('youtube_url') !== '' || setting('facebook_url') !== '' || setting('contact_email') !== '';
?>
</main>

<footer class="bw-footer">
  <div class="bw-footer-inner">
    <div class="bw-footer-brand">
      <div class="bw-logo bw-footer-logo">
        <?= bw_logo_svg('bw-logo-mark') ?>
        <span class="bw-logo-text"><?= e(setting('site_name', 'Baltic Wave')) ?></span>
      </div>
      <p><?= e(setting('footer_text', 'A real-time global music event.')) ?></p>
    </div>

    <div class="bw-footer-nav">
      <span class="bw-footer-heading">Navigation</span>
      <div class="bw-footer-links-grid">
        <div class="bw-footer-links">
          <?php foreach ($bwFooterCol1 as $item): ?>
            <a href="<?= e(menu_item_url($item)) ?>"><?= e($item['label']) ?></a>
          <?php endforeach; ?>
        </div>
        <div class="bw-footer-links">
          <?php foreach ($bwFooterCol2 as $item): ?>
            <a href="<?= e(menu_item_url($item)) ?>"><?= e($item['label']) ?></a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <?php if ($bwHasSocial): ?>
    <div class="bw-footer-social">
      <span class="bw-footer-heading">Connect</span>
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
    <?php endif; ?>
  </div>
  <div class="bw-footer-bottom">
    &copy; <?= date('Y') ?> <?= e(setting('site_name', 'Baltic Wave')) ?>. All rights reserved.
  </div>
</footer>

<script src="<?= e(base_url()) ?>/assets/js/main.js?v=<?= e(BW_VERSION) ?>"></script>
</body>
</html>
