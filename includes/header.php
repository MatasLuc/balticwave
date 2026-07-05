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
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🌊</text></svg>">
</head>
<body class="bw-site">
<div class="bw-aurora" aria-hidden="true"><span></span><span></span><span></span></div>

<header class="bw-header">
  <div class="bw-header-inner">
    <a class="bw-logo" href="<?= e(page_url('home')) ?>">
      <svg class="bw-logo-mark" viewBox="0 0 40 40" fill="none" aria-hidden="true">
        <path d="M4 26c5-8 9-8 13 0s9 8 13 0 7-6 6-12" stroke="url(#lg)" stroke-width="3.4" stroke-linecap="round"/>
        <path d="M4 33c5-6 9-6 13 0s9 6 13 0" stroke="url(#lg)" stroke-width="2.2" stroke-linecap="round" opacity=".55"/>
        <defs><linearGradient id="lg" x1="0" y1="0" x2="40" y2="40"><stop stop-color="#4ff0d2"/><stop offset="1" stop-color="#7c6bff"/></linearGradient></defs>
      </svg>
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
