<?php
/**
 * Admin JSON API — used by the page builder, menu editor and media managers.
 * Every request must be POST with a valid X-CSRF header (or csrf field).
 */
require_once __DIR__ . '/includes/boot.php';

header('Content-Type: application/json; charset=utf-8');

function api_out(array $data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!is_logged_in()) {
    api_out(['ok' => false, 'error' => 'Neprisijungta.'], 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_out(['ok' => false, 'error' => 'Tik POST.'], 405);
}
$sent = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF'] ?? '');
if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)$sent)) {
    api_out(['ok' => false, 'error' => 'Negaliojanti CSRF sesija.'], 419);
}

const BW_BLOCK_TYPES = ['heading', 'text', 'image', 'button', 'youtube', 'quote',
    'divider', 'countdown', 'html', 'galleryGrid', 'videoList', 'contactForm'];

$action = (string)($_GET['action'] ?? $_POST['action'] ?? '');

switch ($action) {

    // -- Page builder: persist a layout ------------------------------------
    case 'save_layout': {
        $pageId = (int)($_POST['page_id'] ?? 0);
        $page   = q_row('SELECT id FROM pages WHERE id = ?', [$pageId]);
        if (!$page) {
            api_out(['ok' => false, 'error' => 'Puslapis nerastas.'], 404);
        }
        $layout = json_decode((string)($_POST['layout'] ?? ''), true);
        if (!is_array($layout) || !isset($layout['blocks']) || !is_array($layout['blocks'])) {
            api_out(['ok' => false, 'error' => 'Blogas maketo formatas.'], 400);
        }
        $clean = [
            'height'       => max(200, min(30000, (int)($layout['height'] ?? 600))),
            'mobileHeight' => max(200, min(30000, (int)($layout['mobileHeight'] ?? 600))),
            'blocks'       => [],
        ];
        $cleanPos = function ($pos): array {
            $pos = is_array($pos) ? $pos : [];
            return [
                'x' => max(0, min(100, (float)($pos['x'] ?? 0))),
                'y' => max(0, min(30000, (float)($pos['y'] ?? 0))),
                'w' => max(2, min(100, (float)($pos['w'] ?? 100))),
                'z' => max(0, min(99, (int)($pos['z'] ?? 1))),
            ];
        };
        foreach ($layout['blocks'] as $b) {
            if (!is_array($b) || !in_array($b['type'] ?? '', BW_BLOCK_TYPES, true)) {
                continue;
            }
            $props = is_array($b['props'] ?? null) ? $b['props'] : [];
            array_walk_recursive($props, function (&$v) {
                if (is_string($v)) { $v = mb_substr($v, 0, 20000); }
            });
            $visibility = in_array($b['visibility'] ?? 'all', ['all', 'desktop', 'mobile'], true)
                        ? $b['visibility'] : 'all';
            $clean['blocks'][] = [
                'id'         => preg_replace('/[^A-Za-z0-9_-]/', '', (string)($b['id'] ?? uniqid('b'))),
                'type'       => $b['type'],
                'visibility' => $visibility,
                'desktop'    => $cleanPos($b['desktop'] ?? null),
                'mobile'     => $cleanPos($b['mobile'] ?? null),
                'props'      => $props,
            ];
        }
        if (count($clean['blocks']) > 200) {
            api_out(['ok' => false, 'error' => 'Per daug blokų (maks. 200).'], 400);
        }
        q('UPDATE pages SET content = ? WHERE id = ?',
          [json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $pageId]);
        api_out(['ok' => true, 'blocks' => count($clean['blocks'])]);
    }

    // -- Upload an image (optionally straight into a gallery album) ---------
    case 'upload': {
        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            api_out(['ok' => false, 'error' => 'Failas neįkeltas.'], 400);
        }
        $f = $_FILES['file'];
        if ($f['size'] > 12 * 1024 * 1024) {
            api_out(['ok' => false, 'error' => 'Failas per didelis (maks. 12 MB).'], 400);
        }
        $mime = (string)(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
        $extByMime = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
                      'image/gif' => 'gif', 'image/avif' => 'avif'];
        if (!isset($extByMime[$mime])) {
            api_out(['ok' => false, 'error' => 'Leidžiami tik paveikslėliai (JPG, PNG, WEBP, GIF, AVIF).'], 400);
        }
        if (!is_dir(UPLOADS_DIR)) {
            @mkdir(UPLOADS_DIR, 0755, true);
        }
        $base = slugify(pathinfo($f['name'], PATHINFO_FILENAME)) ?: 'img';
        $name = substr($base, 0, 60) . '-' . bin2hex(random_bytes(5)) . '.' . $extByMime[$mime];
        if (!move_uploaded_file($f['tmp_name'], UPLOADS_DIR . '/' . $name)) {
            api_out(['ok' => false, 'error' => 'Nepavyko išsaugoti failo. Patikrinkite /uploads teises.'], 500);
        }
        $albumId = (int)($_POST['album_id'] ?? 0);
        $imageId = null;
        if ($albumId > 0 && q_row('SELECT id FROM gallery_albums WHERE id = ?', [$albumId])) {
            $order = (int)q_val('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM gallery_images WHERE album_id = ?', [$albumId]);
            q('INSERT INTO gallery_images (album_id, filename, caption, sort_order) VALUES (?, ?, ?, ?)',
              [$albumId, $name, '', $order]);
            $imageId = (int)db()->lastInsertId();
        }
        api_out(['ok' => true, 'filename' => $name, 'url' => UPLOADS_URL . '/' . $name, 'image_id' => $imageId]);
    }

    // -- List files already in /uploads (for the builder image picker) ------
    case 'list_uploads': {
        $files = [];
        if (is_dir(UPLOADS_DIR)) {
            foreach (scandir(UPLOADS_DIR) as $f) {
                if (preg_match('/\.(jpe?g|png|webp|gif|avif)$/i', $f)) {
                    $files[] = ['name' => $f, 'url' => UPLOADS_URL . '/' . $f,
                                'time' => filemtime(UPLOADS_DIR . '/' . $f)];
                }
            }
        }
        usort($files, fn($a, $b) => $b['time'] <=> $a['time']);
        api_out(['ok' => true, 'files' => array_slice($files, 0, 300)]);
    }

    // -- Menu editor: save the whole menu in one shot ------------------------
    case 'menu_save': {
        $items = json_decode((string)($_POST['items'] ?? ''), true);
        if (!is_array($items)) {
            api_out(['ok' => false, 'error' => 'Blogi meniu duomenys.'], 400);
        }
        $ids = array_map(fn($i) => (int)($i['id'] ?? 0), $items);
        db()->beginTransaction();
        try {
            // Remove items deleted in the editor.
            $existing = array_column(q_all('SELECT id FROM menu_items'), 'id');
            foreach (array_diff($existing, $ids) as $gone) {
                q('DELETE FROM menu_items WHERE id = ?', [$gone]);
            }
            $order = 0;
            foreach ($items as $it) {
                $id       = (int)($it['id'] ?? 0);
                $label    = mb_substr(trim((string)($it['label'] ?? '')), 0, 120) ?: 'Meniu punktas';
                $pageId   = (int)($it['page_id'] ?? 0) ?: null;
                $url      = $pageId ? '' : mb_substr(trim((string)($it['url'] ?? '')), 0, 500);
                $parentId = (int)($it['parent_id'] ?? 0) ?: null;
                $visible  = !empty($it['visible']) ? 1 : 0;
                if ($id > 0) {
                    q('UPDATE menu_items SET label=?, page_id=?, url=?, parent_id=?, sort_order=?, visible=? WHERE id=?',
                      [$label, $pageId, $url, $parentId, $order, $visible, $id]);
                } else {
                    q('INSERT INTO menu_items (label, page_id, url, parent_id, sort_order, visible) VALUES (?,?,?,?,?,?)',
                      [$label, $pageId, $url, $parentId, $order, $visible]);
                }
                $order++;
            }
            db()->commit();
        } catch (Throwable $e) {
            db()->rollBack();
            api_out(['ok' => false, 'error' => 'Nepavyko išsaugoti: ' . $e->getMessage()], 500);
        }
        api_out(['ok' => true]);
    }

    // -- Gallery: reorder images inside an album -----------------------------
    case 'images_reorder': {
        $ids = json_decode((string)($_POST['ids'] ?? ''), true);
        if (!is_array($ids)) {
            api_out(['ok' => false, 'error' => 'Blogi duomenys.'], 400);
        }
        foreach (array_values($ids) as $i => $id) {
            q('UPDATE gallery_images SET sort_order = ? WHERE id = ?', [$i, (int)$id]);
        }
        api_out(['ok' => true]);
    }

    // -- Videos: reorder -------------------------------------------------------
    case 'videos_reorder': {
        $ids = json_decode((string)($_POST['ids'] ?? ''), true);
        if (!is_array($ids)) {
            api_out(['ok' => false, 'error' => 'Blogi duomenys.'], 400);
        }
        foreach (array_values($ids) as $i => $id) {
            q('UPDATE videos SET sort_order = ? WHERE id = ?', [$i, (int)$id]);
        }
        api_out(['ok' => true]);
    }

    // -- Update one image caption ----------------------------------------------
    case 'image_caption': {
        q('UPDATE gallery_images SET caption = ? WHERE id = ?',
          [mb_substr(trim((string)($_POST['caption'] ?? '')), 0, 300), (int)($_POST['id'] ?? 0)]);
        api_out(['ok' => true]);
    }

    default:
        api_out(['ok' => false, 'error' => 'Nežinomas veiksmas.'], 400);
}
