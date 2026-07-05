<?php
/**
 * Baltic Wave CMS — configuration
 *
 * Edit these values to match your server before opening setupdb.php.
 */

// --- Database ---
define('DB_HOST', getenv('BW_DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('BW_DB_NAME') ?: 'balticwave');
define('DB_USER', getenv('BW_DB_USER') ?: 'root');
define('DB_PASS', getenv('BW_DB_PASS') !== false ? getenv('BW_DB_PASS') : '');

// --- Paths / URLs ---
// Leave BASE_URL empty ('') to auto-detect from the request.
define('BASE_URL', getenv('BW_BASE_URL') ?: '');

// Directory (inside the project) where uploaded media is stored.
define('UPLOADS_DIR', __DIR__ . '/uploads');
define('UPLOADS_URL', 'uploads');

// --- Misc ---
define('BW_VERSION', '1.1.0');
define('BW_DEBUG', (bool)(getenv('BW_DEBUG') ?: false));

if (BW_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}
