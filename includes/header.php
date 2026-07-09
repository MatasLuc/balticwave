<?php
/**
 * Public site header. Expects (optionally): $pageTitle, $pageDescription, $activeSlug.
 */
require_once __DIR__ . '/auth.php';
bw_session_start();

$siteName  = setting('site_name', 'Baltic Wave');
$tagline   = setting('tagline', '');
$title     = isset($pageTitle) && $pageTitle !== '' ? $pageTitle . ' — ' . $siteName : $siteName;
$desc      = $pageDescription ?? setting('meta_description', '');
$menu      = menu_tree();
$active    = $activeSlug ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<?php if ($desc !== ''): ?><meta name="description" content="<?= e($desc) ?>"><?php endif; ?>
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:type" content="website">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(base_url()) ?>/assets/css/style.css?v=<?= e(BW_VERSION) ?>">
<link rel="icon" href="<?= bw_favicon_href() ?>">
</head>
<body class="bw-site">
<div class="bw-aurora" aria-hidden="true"><span></span><span></span><span></span></div>

<header class="bw-header">
  <div class="bw-header-inner">
    <a class="bw-logo" href="<?= e(page_url('home')) ?>">
      <?= bw_logo_svg('bw-logo-mark') ?>
      <span class="bw-logo-text"><?= e($siteName) ?></span>
    </a>

    <nav class="bw-nav" id="bw-nav" aria-label="Main navigation">
      <ul>
        <?php foreach ($menu as $item): ?>
          <?php
            $isActive = (!empty($item['page_slug']) && $item['page_slug'] === $active);
            foreach ($item['children'] as $c) {
                if (!empty($c['page_slug']) && $c['page_slug'] === $active) { $isActive = true; }
            }
          ?>
          <li class="<?= $item['children'] ? 'has-sub' : '' ?>">
            <a href="<?= e(menu_item_url($item)) ?>" class="<?= $isActive ? 'active' : '' ?>">
              <?= e($item['label']) ?><?php if ($item['children']): ?><span class="bw-caret" aria-hidden="true"></span><?php endif; ?>
            </a>
            <?php if ($item['children']): ?>
              <ul class="bw-sub">
                <?php foreach ($item['children'] as $child): ?>
                  <li><a href="<?= e(menu_item_url($child)) ?>" class="<?= (!empty($child['page_slug']) && $child['page_slug'] === $active) ? 'active' : '' ?>"><?= e($child['label']) ?></a></li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </nav>

    <div class="bw-header-actions">
      <?php if (is_logged_in()): ?>
        <a class="bw-admin-link" href="<?= e(base_url()) ?>/admin/">Admin</a>
      <?php endif; ?>
      <button class="bw-burger" id="bw-burger" aria-label="Menu" aria-expanded="false">
        <span></span><span></span><span></span>
      </button>
    </div>
  </div>
</header>

<main class="bw-main">
<?= flash_render() ?>
