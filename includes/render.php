<?php
/**
 * Block renderer — turns a page's JSON layout into HTML.
 *
 * A layout is:
 *   { "height": 900, "mobileHeight": 1200, "blocks": [
 *       { id, type, visibility, props, desktop: {x,y,w,z}, mobile: {x,y,w,z} }, …
 *   ] }
 *   x, w — percent of the canvas width;  y — pixels from the top;  z — stacking.
 *
 * Desktop and mobile each have their own fully independent free-form layout
 * (own positions, own canvas height) — not a responsive reflow of the same
 * one. We render TWO complete canvases and let CSS show only the one that
 * matches the viewport; this is exactly what the builder's Desktop/Mobile
 * tabs edit.
 */
require_once __DIR__ . '/functions.php';

function render_page_canvas(?string $layoutJson): string
{
    $layout       = json_decode($layoutJson ?: '{}', true) ?: [];
    $blocks       = $layout['blocks'] ?? [];
    $height       = max(200, (int)($layout['height'] ?? 600));
    $mobileHeight = max(200, (int)($layout['mobileHeight'] ?? $height));

    $sortBy = function (array $blocks, string $device): array {
        usort($blocks, fn($a, $b) =>
            [$a[$device]['y'] ?? 0, $a[$device]['x'] ?? 0] <=> [$b[$device]['y'] ?? 0, $b[$device]['x'] ?? 0]);
        return $blocks;
    };

    $desktopBlocks = $sortBy(array_filter($blocks, fn($b) => ($b['visibility'] ?? 'all') !== 'mobile'), 'desktop');
    $mobileBlocks  = $sortBy(array_filter($blocks, fn($b) => ($b['visibility'] ?? 'all') !== 'desktop'), 'mobile');

    $html = '<div class="bw-canvas bw-canvas-desktop" style="--h:' . $height . 'px">';
    foreach ($desktopBlocks as $b) {
        $html .= render_block($b, 'desktop');
    }
    $html .= '</div>';

    $html .= '<div class="bw-canvas bw-canvas-mobile" style="--h:' . $mobileHeight . 'px">';
    foreach ($mobileBlocks as $b) {
        $html .= render_block($b, 'mobile');
    }
    $html .= '</div>';

    return $html;
}

function render_block(array $b, string $device = 'desktop'): string
{
    $type  = $b['type'] ?? 'text';
    $props = $b['props'] ?? [];
    // Falls back to the pre-migration flat x/y/w/z if a block somehow
    // never went through the setupdb.php migration.
    $pos   = $b[$device] ?? $b['desktop']
           ?? ['x' => $b['x'] ?? 10, 'y' => $b['y'] ?? 0, 'w' => $b['w'] ?? 80, 'z' => $b['z'] ?? 1];
    $x = max(0, min(100, (float)($pos['x'] ?? 0)));
    $w = max(2, min(100, (float)($pos['w'] ?? 100)));
    $y = max(0, (float)($pos['y'] ?? 0));
    $z = (int)($pos['z'] ?? 1);

    $style = sprintf('--x:%s%%;--y:%spx;--w:%s%%;--z:%d', $x, $y, $w, $z);
    $inner = render_block_inner($type, $props);
    return '<div class="bw-block bw-' . e($type) . '" style="' . $style . '">' . $inner . '</div>';
}

function render_block_inner(string $type, array $p): string
{
    switch ($type) {
        case 'heading': {
            $level = in_array((int)($p['level'] ?? 2), [1, 2, 3], true) ? (int)$p['level'] : 2;
            $align = e($p['align'] ?? 'left');
            return "<h$level class=\"bw-h bw-h$level\" style=\"text-align:$align\">" . e($p['text'] ?? '') . "</h$level>";
        }
        case 'text': {
            $align = e($p['align'] ?? 'left');
            // Admin-authored HTML is intentionally allowed here.
            return '<div class="bw-richtext" style="text-align:' . $align . '">' . ($p['html'] ?? '') . '</div>';
        }
        case 'image': {
            $src = $p['src'] ?? '';
            if ($src === '') {
                return '';
            }
            if (!preg_match('~^https?://~', $src)) {
                $src = base_url() . '/' . ltrim($src, '/');
            }
            $r   = (int)($p['radius'] ?? 16);
            $img = '<img src="' . e($src) . '" alt="' . e($p['alt'] ?? '') . '" loading="lazy" style="border-radius:' . $r . 'px">';
            return !empty($p['caption'])
                ? '<figure>' . $img . '<figcaption>' . e($p['caption']) . '</figcaption></figure>'
                : $img;
        }
        case 'button': {
            $url    = e($p['url'] ?? '#');
            $cls    = ($p['variant'] ?? 'solid') === 'outline' ? 'bw-btn bw-btn-outline' : 'bw-btn';
            $align  = e($p['align'] ?? 'left');
            $colorA = (!empty($p['color']) && preg_match('/^#[0-9a-fA-F]{6}$/', $p['color']))
                    ? ' style="color:' . e($p['color']) . '"' : '';
            return '<div style="text-align:' . $align . '"><a class="' . $cls . '" href="' . $url . '"' . $colorA . '>'
                 . e($p['label'] ?? 'Button') . '<span class="bw-btn-arrow">&rarr;</span></a></div>';
        }
        case 'youtube': {
            $id = youtube_id($p['url'] ?? '');
            if ($id === '') {
                return '';
            }
            return '<div class="bw-video-frame"><iframe src="https://www.youtube-nocookie.com/embed/' . e($id) . '" '
                 . 'title="YouTube video" loading="lazy" allowfullscreen '
                 . 'allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe></div>';
        }
        case 'quote': {
            $out = '<blockquote class="bw-quote"><p>' . e($p['text'] ?? '') . '</p>';
            if (!empty($p['author'])) {
                $out .= '<cite>' . e($p['author']) . '</cite>';
            }
            return $out . '</blockquote>';
        }
        case 'divider':
            return '<hr class="bw-divider">';
        case 'countdown': {
            $date = e($p['date'] ?? '');
            return '<div class="bw-countdown" data-date="' . $date . '">'
                 . '<div class="bw-countdown-label">' . e($p['label'] ?? '') . '</div>'
                 . '<div class="bw-countdown-units"></div></div>';
        }
        case 'html':
            return '<div class="bw-html">' . ($p['code'] ?? '') . '</div>';
        case 'galleryGrid':
            return render_gallery_grid((int)($p['album_id'] ?? 0), (int)($p['columns'] ?? 3));
        case 'videoList':
            return render_video_list((int)($p['columns'] ?? 2));
        case 'contactForm':
            return render_contact_form($p);
        default:
            return '';
    }
}

// ---------------------------------------------------------------------------

function render_gallery_grid(int $albumId, int $cols): string
{
    $cols = max(1, min(5, $cols));
    if ($albumId > 0) {
        $images = q_all('SELECT * FROM gallery_images WHERE album_id = ? ORDER BY sort_order, id', [$albumId]);
        $out = '<div class="bw-gallery" style="--cols:' . $cols . '">';
        foreach ($images as $img) {
            $src = base_url() . '/' . UPLOADS_URL . '/' . rawurlencode($img['filename']);
            $out .= '<a class="bw-gallery-item" href="' . e($src) . '" data-lightbox data-caption="' . e($img['caption']) . '">'
                  . '<img src="' . e($src) . '" alt="' . e($img['caption']) . '" loading="lazy"></a>';
        }
        return $out . '</div>';
    }

    // All albums → album cards with a lightbox-able grid per album.
    $albums = q_all('SELECT * FROM gallery_albums ORDER BY sort_order, id');
    $out = '';
    foreach ($albums as $al) {
        $images = q_all('SELECT * FROM gallery_images WHERE album_id = ? ORDER BY sort_order, id', [$al['id']]);
        $out .= '<div class="bw-album"><h3 class="bw-album-title">' . e($al['title']) . '</h3>';
        if ($al['description'] !== '') {
            $out .= '<p class="bw-album-desc">' . e($al['description']) . '</p>';
        }
        if (!$images) {
            $out .= '<p class="bw-album-empty">No photos yet.</p>';
        } else {
            $out .= '<div class="bw-gallery" style="--cols:' . $cols . '">';
            foreach ($images as $img) {
                $src = base_url() . '/' . UPLOADS_URL . '/' . rawurlencode($img['filename']);
                $out .= '<a class="bw-gallery-item" href="' . e($src) . '" data-lightbox data-caption="' . e($img['caption']) . '">'
                      . '<img src="' . e($src) . '" alt="' . e($img['caption']) . '" loading="lazy"></a>';
            }
            $out .= '</div>';
        }
        $out .= '</div>';
    }
    return $out ?: '<p class="bw-album-empty">No albums yet.</p>';
}

function render_video_list(int $cols): string
{
    $cols   = max(1, min(3, $cols));
    $videos = q_all('SELECT * FROM videos ORDER BY sort_order, id');
    if (!$videos) {
        return '<p class="bw-album-empty">No videos yet.</p>';
    }
    $out = '<div class="bw-videolist" style="--cols:' . $cols . '">';
    foreach ($videos as $v) {
        $id = youtube_id($v['youtube_url']);
        if ($id === '') {
            continue;
        }
        $out .= '<div class="bw-videocard"><div class="bw-video-frame">'
              . '<iframe src="https://www.youtube-nocookie.com/embed/' . e($id) . '" title="' . e($v['title']) . '" loading="lazy" allowfullscreen></iframe>'
              . '</div><div class="bw-videocard-body"><h3>' . e($v['title']) . '</h3>';
        if ($v['description'] !== '') {
            $out .= '<p>' . e($v['description']) . '</p>';
        }
        $out .= '</div></div>';
    }
    return $out . '</div>';
}

function render_contact_form(array $p): string
{
    $title = e($p['title'] ?? 'Get in touch');
    $sent  = !empty($_SESSION['bw_contact_sent']);
    unset($_SESSION['bw_contact_sent']);

    $out = '<div class="bw-contact"><h3>' . $title . '</h3>';
    if ($sent) {
        $out .= '<div class="flash flash-success">Thank you! Your message has been sent.</div>';
    }
    $out .= '<form method="post" class="bw-contact-form">'
          . csrf_field()
          . '<input type="hidden" name="bw_contact" value="1">'
          . '<div class="bw-field-row">'
          . '<label>Name<input type="text" name="name" required maxlength="120"></label>'
          . '<label>Email<input type="email" name="email" required maxlength="190"></label>'
          . '</div>'
          . '<label>Message<textarea name="message" rows="5" required maxlength="5000"></textarea></label>'
          . '<button type="submit" class="bw-btn">Send message<span class="bw-btn-arrow">&rarr;</span></button>'
          . '</form></div>';
    return $out;
}
