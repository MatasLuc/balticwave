<?php
/**
 * Baltic Wave CMS — database installer & migrator.
 *
 * Open this file in the browser (or run `php setupdb.php`) after every code
 * update: it creates the database, applies any pending schema migrations and
 * seeds the initial content on a fresh install. Safe to run repeatedly.
 *
 * Adding a migration: append a new numbered entry to $BW_MIGRATIONS below —
 * already-applied versions are recorded in the `migrations` table and skipped.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$log = [];
$ok  = true;

function bw_log(array &$log, string $msg, string $type = 'ok'): void
{
    $log[] = ['msg' => $msg, 'type' => $type];
}

// ---------------------------------------------------------------------------
// Migrations — append new versions here after code updates.
// ---------------------------------------------------------------------------
$BW_MIGRATIONS = [

    1 => function (PDO $db): string {
        $db->exec("CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(60) NOT NULL UNIQUE,
            email VARCHAR(190) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS settings (
            skey VARCHAR(60) NOT NULL PRIMARY KEY,
            svalue TEXT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS pages (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(80) NOT NULL UNIQUE,
            title VARCHAR(190) NOT NULL,
            meta_description VARCHAR(300) NOT NULL DEFAULT '',
            content MEDIUMTEXT NULL,
            published TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS menu_items (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            label VARCHAR(120) NOT NULL,
            page_id INT UNSIGNED NULL,
            url VARCHAR(500) NOT NULL DEFAULT '',
            parent_id INT UNSIGNED NULL,
            sort_order INT NOT NULL DEFAULT 0,
            visible TINYINT(1) NOT NULL DEFAULT 1,
            CONSTRAINT fk_menu_page FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE,
            CONSTRAINT fk_menu_parent FOREIGN KEY (parent_id) REFERENCES menu_items(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS gallery_albums (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(190) NOT NULL,
            description VARCHAR(500) NOT NULL DEFAULT '',
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS gallery_images (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            album_id INT UNSIGNED NOT NULL,
            filename VARCHAR(255) NOT NULL,
            caption VARCHAR(300) NOT NULL DEFAULT '',
            sort_order INT NOT NULL DEFAULT 0,
            uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_img_album FOREIGN KEY (album_id) REFERENCES gallery_albums(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS videos (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(190) NOT NULL,
            youtube_url VARCHAR(300) NOT NULL,
            description VARCHAR(500) NOT NULL DEFAULT '',
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS messages (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            email VARCHAR(190) NOT NULL,
            message TEXT NOT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        return 'Sukurtos pagrindinės lentelės (users, settings, pages, menu_items, gallery, videos, messages).';
    },

    2 => function (PDO $db): string {
        // Desktop and mobile used to share one x/y/w/z per block (mobile was
        // just forced to stack via CSS). They're now independently editable
        // layouts, so every block needs its own "desktop" and "mobile"
        // position — split the old flat fields into both, cloning the old
        // position as the mobile starting point.
        $rows = $db->query('SELECT id, content FROM pages')->fetchAll(PDO::FETCH_ASSOC);
        $upd  = $db->prepare('UPDATE pages SET content = ? WHERE id = ?');
        $migrated = 0;
        foreach ($rows as $row) {
            $layout = json_decode($row['content'] ?: '{}', true);
            if (!is_array($layout) || empty($layout['blocks']) || !is_array($layout['blocks'])) {
                continue;
            }
            $changed = false;
            foreach ($layout['blocks'] as &$b) {
                if (!is_array($b) || isset($b['desktop'])) {
                    continue;
                }
                $b['desktop'] = [
                    'x' => $b['x'] ?? 10, 'y' => $b['y'] ?? 0, 'w' => $b['w'] ?? 80, 'z' => $b['z'] ?? 1,
                ];
                $b['mobile'] = $b['desktop'];
                unset($b['x'], $b['y'], $b['w'], $b['z']);
                $changed = true;
            }
            unset($b);
            if (!isset($layout['mobileHeight'])) {
                $layout['mobileHeight'] = $layout['height'] ?? 600;
                $changed = true;
            }
            if ($changed) {
                $upd->execute([json_encode($layout, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $row['id']]);
                $migrated++;
            }
        }
        return "Migruota $migrated puslapio maketų į atskirus Desktop/Mobile išdėstymus (pradinis Mobile — Desktop kopija, koreguokite redaktoriuje).";
    },

    // 3 => function (PDO $db): string { … next schema change goes here … },
];

// ---------------------------------------------------------------------------
// Layout seeding helpers
// ---------------------------------------------------------------------------

/** Rough rendered height of a block — used only to auto-stack seeded layouts. */
function bw_estimate_height(string $type, array $p, float $w, float $canvasWidth = 1160): float
{
    $px = $canvasWidth * $w / 100; // approximate rendered width in px
    $mobile = $canvasWidth < 800;
    switch ($type) {
        case 'heading':
            return $mobile
                ? ([1 => 110, 2 => 80, 3 => 56][(int)($p['level'] ?? 2)] ?? 70)
                : ([1 => 140, 2 => 96, 3 => 64][(int)($p['level'] ?? 2)] ?? 90);
        case 'text': {
            $html  = $p['html'] ?? '';
            $chars = mb_strlen(strip_tags($html));
            $cpl   = max(30, $px / 9.4);
            return max(1, ceil($chars / $cpl)) * 31
                 + substr_count($html, '<p') * 14
                 + substr_count($html, '<li') * 12 + 26;
        }
        case 'html': {
            $chars = mb_strlen(strip_tags($p['code'] ?? ''));
            return max(150, ceil($chars / max(30, $px / 9.4)) * 30 + 110);
        }
        case 'youtube':     return $px * 9 / 16 + 24;
        case 'image':       return 340;
        case 'button':      return 76;
        case 'quote':       return ceil(mb_strlen($p['text'] ?? '') / max(30, $px / 10)) * 34 + 100;
        case 'divider':     return 34;
        case 'countdown':   return 180;
        case 'galleryGrid': return 460;
        case 'videoList':   return 1450;
        case 'contactForm': return 620;
    }
    return 120;
}

/**
 * Stack block definitions top-to-bottom for one device's canvas.
 * Each def: [type, props, opts] where opts = x, w, gap, h, samerow.
 * Mobile ignores "samerow"/x (side-by-side pairs don't fit a narrow phone)
 * and always stacks full-width, single column.
 */
function bw_layout_stack(array $defs, bool $mobile): array
{
    $canvasWidth = $mobile ? 390 : 1160;
    $y = 70;
    $positions = [];
    $prevY = 70;
    foreach ($defs as $d) {
        [$type, $props] = $d;
        $o   = $d[2] ?? [];
        $x   = $mobile ? 5 : ($o['x'] ?? 10);
        $w   = $mobile ? 90 : ($o['w'] ?? 80);
        $gap = $o['gap'] ?? 30;
        $h   = $mobile
             ? bw_estimate_height($type, $props, $w, $canvasWidth)
             : ($o['h'] ?? bw_estimate_height($type, $props, (float)$w, $canvasWidth));
        $sameRow = !$mobile && !empty($o['samerow']);
        $yy = $sameRow ? $prevY : $y;

        $positions[] = ['x' => $x, 'y' => $yy, 'w' => $w, 'z' => 1];
        if ($sameRow) {
            $y = max($y, $yy + $h + $gap);
        } else {
            $prevY = $yy;
            $y = $yy + $h + $gap;
        }
    }
    return ['height' => (int)($y + 50), 'positions' => $positions];
}

/** Build a layout JSON with independent desktop and mobile stacks. */
function bw_layout(array $defs): string
{
    $desktop = bw_layout_stack($defs, false);
    $mobile  = bw_layout_stack($defs, true);

    $blocks = [];
    $n = 0;
    foreach ($defs as $i => $d) {
        [$type, $props] = $d;
        $blocks[] = [
            'id' => 'b' . (++$n), 'type' => $type,
            'visibility' => 'all',
            'desktop' => $desktop['positions'][$i],
            'mobile'  => $mobile['positions'][$i],
            'props' => $props,
        ];
    }
    return json_encode([
        'height' => $desktop['height'],
        'mobileHeight' => $mobile['height'],
        'blocks' => $blocks,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function bw_seed_content(PDO $db, array &$log): void
{
    if ((int)q_val('SELECT COUNT(*) FROM pages') > 0) {
        bw_log($log, 'Turinys jau egzistuoja — pradinis užpildymas praleistas.', 'info');
        return;
    }

    $pages = bw_seed_pages_definition();
    $ids   = [];
    foreach ($pages as $slug => $p) {
        q('INSERT INTO pages (slug, title, meta_description, content, published) VALUES (?, ?, ?, ?, 1)',
          [$slug, $p['title'], $p['meta'] ?? '', bw_layout($p['blocks'])]);
        $ids[$slug] = (int)db()->lastInsertId();
    }
    bw_log($log, 'Sukurta pradinių puslapių: ' . count($pages) . '.');

    // Menu ------------------------------------------------------------------
    $menu = [
        ['Home', 'home'], ['About', 'about'],
        ['Before', 'before', [
            ['Baltic Song Around the Globe', 'before-baltic-song'],
            ['Baltic Wave II', 'before-baltic-wave-ii'],
            ['Baltic Wave III. Black Ribbon', 'before-black-ribbon'],
            ['Baltic Wave IV. Canada', 'before-canada'],
        ]],
        ['Upcoming', 'upcoming'], ['Gallery', 'gallery'], ['Videos', 'videos'],
        ['In Media', 'media'], ['Contacts', 'contacts'],
    ];
    $order = 0;
    foreach ($menu as $m) {
        q('INSERT INTO menu_items (label, page_id, sort_order, visible) VALUES (?, ?, ?, 1)',
          [$m[0], $ids[$m[1]], $order++]);
        $parentId = (int)db()->lastInsertId();
        foreach ($m[2] ?? [] as $c) {
            q('INSERT INTO menu_items (label, page_id, parent_id, sort_order, visible) VALUES (?, ?, ?, ?, 1)',
              [$c[0], $ids[$c[1]], $parentId, $order++]);
        }
    }
    bw_log($log, 'Sukurtas pradinis meniu.');

    // Videos ----------------------------------------------------------------
    $videos = [
        ['Baltic Wave — the project', 'https://youtu.be/Kaub0jSOgBc', 'Musicians from around the world performing simultaneously as one orchestra and choir.'],
        ['BALTIC WAVE IV. CANADA (2018)', 'https://www.youtube.com/watch?v=F7RTXL3GbFA', 'Joint concert connecting Vilnius, Melbourne and Toronto for the Baltic States centenary.'],
        ['BALTIC WAVE III. BLACK RIBBON (2017)', 'https://www.youtube.com/watch?v=RTPFvBT4TOg', 'Commemorating the European Day of Remembrance for Victims of Stalinism and Nazism.'],
        ['BALTIC WAVE II (2017)', 'https://www.youtube.com/watch?v=zkEnpDcV6-g', 'The second stage of the project, performed on 12 March 2017 in Vilnius.'],
        ['BALTIC WAVE. Baltic Song Around the Globe (2016)', 'https://www.youtube.com/watch?v=-oxavD4ERkA', 'The first showing of this musical juncture, commemorating the Black Ribbon day.'],
    ];
    $i = 0;
    foreach ($videos as $v) {
        q('INSERT INTO videos (title, youtube_url, description, sort_order) VALUES (?, ?, ?, ?)',
          [$v[0], $v[1], $v[2], $i++]);
    }
    bw_log($log, 'Įkelti ' . count($videos) . ' vaizdo įrašai.');

    // Gallery ---------------------------------------------------------------
    q('INSERT INTO gallery_albums (title, description, sort_order) VALUES (?, ?, 0)',
      ['Baltic Wave', 'Photos from our performances around the globe.']);
    bw_log($log, 'Sukurtas pirmas galerijos albumas.');

    // Settings --------------------------------------------------------------
    $settings = [
        'site_name'        => 'Baltic Wave',
        'tagline'          => 'One world. One orchestra. One wave.',
        'meta_description' => 'Baltic Wave is a unique real time event, uniting musicians from around the World to perform simultaneously as one integral orchestra and choir.',
        'footer_text'      => 'A unique real time event, uniting musicians from around the World to perform simultaneously as one integral orchestra and choir.',
        'youtube_url'      => '',
        'facebook_url'     => '',
        'contact_email'    => '',
    ];
    foreach ($settings as $k => $v) {
        set_setting($k, $v);
    }
    bw_log($log, 'Įrašyti numatytieji nustatymai.');
}

/** All seed pages with their block layouts. */
function bw_seed_pages_definition(): array
{
    $p = [];

    // -- Home ----------------------------------------------------------------
    $p['home'] = [
        'title' => 'Home',
        'meta'  => 'Baltic Wave — a unique real time event uniting musicians from around the World to perform simultaneously as one integral orchestra and choir.',
        'blocks' => [
            ['text', ['html' => '<p class="bw-kicker">Real-time global concert</p>', 'align' => 'center'], ['x' => 10, 'w' => 80, 'gap' => 6, 'h' => 40]],
            ['heading', ['text' => 'BALTIC WAVE', 'level' => 1, 'align' => 'center'], ['x' => 5, 'w' => 90, 'gap' => 14]],
            ['text', ['html' => '<p>Baltic Wave is a unique real time event, uniting musicians from around the World to perform simultaneously as one integral orchestra and choir.</p>', 'align' => 'center'], ['x' => 20, 'w' => 60, 'gap' => 36]],
            ['button', ['label' => 'Discover the project', 'url' => 'about.php', 'variant' => 'solid', 'align' => 'right'], ['x' => 10, 'w' => 39, 'gap' => 44]],
            ['button', ['label' => 'Watch performances', 'url' => 'videos.php', 'variant' => 'outline', 'align' => 'left'], ['x' => 51, 'w' => 39, 'samerow' => true]],
            ['youtube', ['url' => 'https://youtu.be/Kaub0jSOgBc'], ['x' => 15, 'w' => 70, 'gap' => 56]],
            ['divider', [], ['x' => 35, 'w' => 30, 'gap' => 40]],
            ['quote', ['text' => 'Dedicated technology, modern internet connections and Atomic, Coordinated Universal Time unite performers for simultaneous performance without any time lags.', 'author' => 'The idea behind Baltic Wave'], ['x' => 18, 'w' => 64]],
        ],
    ];

    // -- About ----------------------------------------------------------------
    $p['about'] = [
        'title' => 'About',
        'meta'  => 'What is Baltic Wave? Learn about the real-time event uniting musicians from around the world.',
        'blocks' => [
            ['heading', ['text' => 'What is Baltic Wave?', 'level' => 1, 'align' => 'center'], ['x' => 10, 'w' => 80, 'gap' => 34]],
            ['text', ['html' => '<p>Baltic Wave is a unique real time event, uniting musicians from around the World to perform simultaneously as one integral orchestra and choir. With the help of innovative approaches and the latest technology it connects global audience with local participants.</p><p>Dedicated technology, modern internet connections and AT, UTC (Atomic, Coordinated Universal Time) unites performers for simultaneous performance without any time lags.</p>', 'align' => 'left'], ['x' => 20, 'w' => 60, 'gap' => 44]],
            ['divider', [], ['x' => 35, 'w' => 30, 'gap' => 40]],
            ['heading', ['text' => 'Project initiator and leader', 'level' => 3, 'align' => 'center'], ['x' => 20, 'w' => 60, 'gap' => 8]],
            ['text', ['html' => '<p><strong>Gediminas Zujus</strong></p>', 'align' => 'center'], ['x' => 20, 'w' => 60, 'gap' => 40]],
            ['button', ['label' => 'See past events', 'url' => 'before.php', 'variant' => 'solid', 'align' => 'center'], ['x' => 20, 'w' => 60]],
        ],
    ];

    // -- Before (overview) -----------------------------------------------------
    $card = static fn(string $tag, string $title, string $text, string $href): array =>
        ['html', ['code' => '<div class="bw-card"><span class="bw-card-tag">' . $tag . '</span><h3>' . $title . '</h3><p>' . $text . '</p><a class="bw-card-link" href="' . $href . '">Read more &rarr;</a></div>'], []];

    $p['before'] = [
        'title' => 'Before',
        'meta'  => 'The road to Baltic Wave — past events across three continents.',
        'blocks' => [
            ['heading', ['text' => 'Before Baltic Wave', 'level' => 1, 'align' => 'center'], ['x' => 10, 'w' => 80, 'gap' => 14]],
            ['text', ['html' => '<p>Four events, three continents, one wave. Explore the concerts that led to the grand finale.</p>', 'align' => 'center'], ['x' => 20, 'w' => 60, 'gap' => 46]],
            array_replace($card('2016 · Black Ribbon Day', 'Baltic Song Around the Globe', 'The first showing of this musical juncture — Los Angeles, Chicago, Washington, Melbourne and Vilnius joined for the Black Ribbon day.', 'before-baltic-song.php'), [2 => ['x' => 8, 'w' => 41, 'h' => 300]]),
            array_replace($card('2017 · Vilnius', 'Baltic Wave II', 'A complex rehearsal before the grand finale, held at the Residence of the Grand Dukes of Lithuania with performers from three continents.', 'before-baltic-wave-ii.php'), [2 => ['x' => 51, 'w' => 41, 'h' => 300, 'samerow' => true]]),
            array_replace($card('2017 · Seimas of Lithuania', 'Baltic Wave III. Black Ribbon', 'An exclusive joint concert commemorating the European Day of Remembrance for Victims of Stalinism and Nazism, and Day of the Baltic Way.', 'before-black-ribbon.php'), [2 => ['x' => 8, 'w' => 41, 'h' => 300]]),
            array_replace($card('2018 · Toronto, Canada', 'Baltic Wave IV. Canada', 'Celebrating the 100th Anniversary of the restored Baltic States with composers and performers from three continents.', 'before-canada.php'), [2 => ['x' => 51, 'w' => 41, 'h' => 300, 'samerow' => true]]),
        ],
    ];

    // -- Before: IV Canada ------------------------------------------------------
    $p['before-canada'] = [
        'title' => 'Baltic Wave IV. Canada',
        'meta'  => 'BALTIC WAVE IV. CANADA — joint concert for the 100th Anniversary of the restored Baltic States.',
        'blocks' => [
            ['heading', ['text' => 'BALTIC WAVE IV. CANADA', 'level' => 1, 'align' => 'center'], ['x' => 5, 'w' => 90, 'gap' => 30]],
            ['youtube', ['url' => 'https://www.youtube.com/watch?v=F7RTXL3GbFA'], ['x' => 15, 'w' => 70, 'gap' => 44]],
            ['text', ['html' => '<p>In 2018, restored Baltic States are celebrating its 100th Anniversary. We celebrated this event in a joint concert BALTIC WAVE on 22nd March 2018, which connected composers and performers from three continents (Vilnius, Melbourne and Toronto cities), streamed in Arraymusic concert space in Toronto, Canada and live on Youtube baltic100 and ArrayTV. You can hear Lithuanian composers as well as a premiere by Canada&rsquo;s first nations composer Barbara Croall&rsquo;s work &ldquo;Outside&rdquo;, composed specifically for this event.</p>'], ['x' => 15, 'w' => 70, 'gap' => 44]],
            ['heading', ['text' => 'Composers', 'level' => 3], ['x' => 15, 'w' => 70, 'gap' => 10]],
            ['text', ['html' => '<p>Arūnas Navakas &middot; Loreta Narvilaitė &middot; Barbara Croall &middot; Teisutis Makačinas &middot; Vytautas Germanavičius &middot; Bronius Kutavičius &middot; Gediminas Zujus</p>'], ['x' => 15, 'w' => 70, 'gap' => 36]],
            ['heading', ['text' => 'Performers', 'level' => 3], ['x' => 15, 'w' => 70, 'gap' => 10]],
            ['text', ['html' => '<p><strong>CANADA</strong> — Gould String Quartet: Atis Bankas, violin; Vera Sherwood, violin; Anna Antropova, viola; Jonathan Tortolano, cello. Soloists: Atis Bankas, violin; Peter Stoll, birbynė; Victoria Kogan, piano.</p><p><strong>LITHUANIA</strong> — Kaunas string quartet: Karolina Beinarytė-Palekauskienė, 1st violin; Aistė Mikutytė, 2nd violin; Laimis Krunglevičius, viola; Saulius Bartulis, cello; Aistė Bružaitė, kanklės; Motiejus Bazaras, piano; Egidijus Ališauskas, conductor; and LMTA (Lithuanian Academy of Music and Theatre) windwoods group; Petras Vyšniauskas, saxophone; Sigitas Gailius, vibraphone; Veronika Povilionienė, vocals; Nora Petročenko, vocals.</p><p><strong>AUSTRALIA</strong> — Brigita Lastauskaitė, vocals; Mindaugas Simankevičius, live electronics.</p>'], ['x' => 15, 'w' => 70, 'gap' => 36]],
            ['heading', ['text' => 'Project initiator and leader', 'level' => 3], ['x' => 15, 'w' => 70, 'gap' => 10]],
            ['text', ['html' => '<p>Gediminas Zujus</p>'], ['x' => 15, 'w' => 70, 'gap' => 36]],
            ['heading', ['text' => 'Sponsors', 'level' => 3], ['x' => 15, 'w' => 70, 'gap' => 10]],
            ['text', ['html' => '<p>Arraymusic &middot; RMIT University &middot; Concert hall &ldquo;Organum&rdquo; &middot; Association &ldquo;LATGA&rdquo; &middot; Vilnius College of Technologies and Design</p>'], ['x' => 15, 'w' => 70, 'gap' => 40]],
            ['button', ['label' => 'All past events', 'url' => 'before.php', 'variant' => 'outline', 'align' => 'center'], ['x' => 15, 'w' => 70]],
        ],
    ];

    // -- Before: II --------------------------------------------------------------
    $p['before-baltic-wave-ii'] = [
        'title' => 'Baltic Wave II',
        'meta'  => 'BALTIC WAVE II — the second stage of the project, performed on 12 March 2017.',
        'blocks' => [
            ['heading', ['text' => 'BALTIC WAVE II', 'level' => 1, 'align' => 'center'], ['x' => 5, 'w' => 90, 'gap' => 30]],
            ['youtube', ['url' => 'https://www.youtube.com/watch?v=zkEnpDcV6-g'], ['x' => 15, 'w' => 70, 'gap' => 44]],
            ['text', ['html' => '<p>The second stage of this project, called &ldquo;Baltic Wave&rdquo; took place on 12 March 2017. We performed various pieces composed by Veronica Krausas (USA), Kęstutis Daugirdas (USA), Brigita Lastauskaitė (Australia) and other Baltic composers.</p><p>This was a complex performance, as a rehearsal, before the grand finale which took take place in 2018 — 100th Anniversary of the Restoration of Independence in Lithuania, Latvia and Estonia.</p>'], ['x' => 15, 'w' => 70, 'gap' => 40]],
            ['heading', ['text' => 'Participants in Lithuania', 'level' => 3], ['x' => 15, 'w' => 70, 'gap' => 10]],
            ['text', ['html' => '<p>United Lithuanian, Latvian and Estonian military band, conducted by mjr. Egidijus Ališauskas, plk. ltn. Peeter Saan, plk. ltn. Dainis Vuškans &middot; String quartet &ldquo;Art Vio&rdquo; &middot; Soloists Veronika Povilionienė, Petras Vyšniauskas and Aistė Bružaitė.</p>'], ['x' => 15, 'w' => 70, 'gap' => 36]],
            ['heading', ['text' => 'Participants from abroad', 'level' => 3], ['x' => 15, 'w' => 70, 'gap' => 10]],
            ['text', ['html' => '<p>Atis Bankas and Gould String Quartet from the Toronto Symphony (Canada) &middot; Nida Grigalavičiūtė and Lisa Kristina from the Chicago Symphony (USA) &middot; Veronika Krausas from University South California (USA) &middot; Kęstutis Daugirdas and his &ldquo;Los Angelai&rdquo; choir (USA) &middot; Opera vocal quartet from Los Angeles (USA) &middot; Isaac Harrison and Brigita Lastauskaitė from RMIT University (Australia).</p>'], ['x' => 15, 'w' => 70, 'gap' => 36]],
            ['heading', ['text' => 'Location', 'level' => 3], ['x' => 15, 'w' => 70, 'gap' => 10]],
            ['text', ['html' => '<p>Residence of the Grand Dukes of Lithuania (Vilnius)</p>'], ['x' => 15, 'w' => 70, 'gap' => 40]],
            ['button', ['label' => 'All past events', 'url' => 'before.php', 'variant' => 'outline', 'align' => 'center'], ['x' => 15, 'w' => 70]],
        ],
    ];

    // -- Before: III Black Ribbon --------------------------------------------------
    $p['before-black-ribbon'] = [
        'title' => 'Baltic Wave III. Black Ribbon',
        'meta'  => 'BALTIC WAVE III. BLACK RIBBON — exclusive joint concert in the Lithuanian Parliament, 23 August 2017.',
        'blocks' => [
            ['heading', ['text' => 'BALTIC WAVE III. BLACK RIBBON', 'level' => 1, 'align' => 'center'], ['x' => 5, 'w' => 90, 'gap' => 30]],
            ['youtube', ['url' => 'https://www.youtube.com/watch?v=RTPFvBT4TOg'], ['x' => 15, 'w' => 70, 'gap' => 44]],
            ['text', ['html' => '<p>Prior to the Centenary of the restoration of Lithuania on 16 February 2018, we seized the moment to commemorate the significance of the European Day of Remembrance for Victims of Stalinism and Nazism, and Day of the Baltic Way. For this purpose, an exclusive joint concert with live online presence of most prominent composers and musicians from around the world was held in Vilnius on 23 August 2017. The event was based in the historical Hall of the Act of 11 March of the Lithuanian Parliament and was streamed live via the YouTube channel baltic100.</p>'], ['x' => 15, 'w' => 70, 'gap' => 40]],
            ['heading', ['text' => 'Programme', 'level' => 3], ['x' => 15, 'w' => 70, 'gap' => 10]],
            ['text', ['html' => '<p>The concert featured pieces by the outstanding Lithuanian, Latvian, Estonian and related composers, performed by well-known soloists, choirs and orchestras from three different continents. In addition, special stage design and video installations allowed broadcasting the views and sounds of the church bells from Lithuania&rsquo;s biggest carillon in Vilnius.</p>'], ['x' => 15, 'w' => 70, 'gap' => 36]],
            ['heading', ['text' => 'Conductors', 'level' => 3], ['x' => 15, 'w' => 70, 'gap' => 10]],
            ['text', ['html' => '<p>Modestas Pitrėnas &middot; Māris Kupčs &middot; maj. Egidijus Ališauskas &middot; Romas Gražinis</p>'], ['x' => 15, 'w' => 70, 'gap' => 36]],
            ['heading', ['text' => 'Composers', 'level' => 3], ['x' => 15, 'w' => 70, 'gap' => 10]],
            ['text', ['html' => '<p>Michael Gordon &middot; Pēteris Vasks &middot; Arvo Pärt &middot; Bronius Kutavičius</p>'], ['x' => 15, 'w' => 70, 'gap' => 36]],
            ['heading', ['text' => 'Performers', 'level' => 3], ['x' => 15, 'w' => 70, 'gap' => 10]],
            ['text', ['html' => '<p>Baltic Orchestra, Lithuanian Chamber Orchestra, Gould String Quartet and soloist Atis Bankas, Brigita Lastauskaitė from Melbourne RMIT University, Aidija choir, and United Baltic Military Band (Lithuania, Latvia, Estonia), soloists Justina Auškelytė, Veronika Povilionienė, Milda Arčikauskaitė and Agota Zdanavičiūtė.</p>'], ['x' => 15, 'w' => 70, 'gap' => 36]],
            ['heading', ['text' => 'Director | Scenographer | Camera man | IT', 'level' => 3], ['x' => 15, 'w' => 70, 'gap' => 10]],
            ['text', ['html' => '<p>Vesta Grapštaitė | Jonas Arčikauskas | Algimantas Mikutėnas | Kęstutis Dapkevičius</p>'], ['x' => 15, 'w' => 70, 'gap' => 36]],
            ['text', ['html' => '<p><strong>Project Initiator and Leader:</strong> Gediminas Zujus<br>Organised under the auspices of the Board of the Seimas of the Republic of Lithuania. Partially financed by the Office of the Government of the Republic of Lithuania.</p>'], ['x' => 15, 'w' => 70, 'gap' => 40]],
            ['button', ['label' => 'All past events', 'url' => 'before.php', 'variant' => 'outline', 'align' => 'center'], ['x' => 15, 'w' => 70]],
        ],
    ];

    // -- Before: Baltic Song Around the Globe ---------------------------------------
    $p['before-baltic-song'] = [
        'title' => 'Baltic Song Around the Globe',
        'meta'  => 'BALTIC WAVE | BALTIC SONG AROUND THE GLOBE — the first showing of this musical juncture, August 2016.',
        'blocks' => [
            ['heading', ['text' => 'BALTIC SONG AROUND THE GLOBE', 'level' => 1, 'align' => 'center'], ['x' => 5, 'w' => 90, 'gap' => 30]],
            ['youtube', ['url' => 'https://www.youtube.com/watch?v=-oxavD4ERkA'], ['x' => 15, 'w' => 70, 'gap' => 44]],
            ['text', ['html' => '<p>The first showing of this musical juncture took place on August 22, 2016, to commemorate The Black Ribbon day (&ldquo;Baltic Way&rdquo;). This was an extention of that fenomenal event and will serve as a great opportunity to prepare for the grand finale in 2018 — 100th Anniversary of the Restoration of independence of Lithuania, Latvia and Estonia.</p><p><strong>Date:</strong> August 22–23rd, 2016 — &ldquo;Black Ribbon&rdquo; day.</p>'], ['x' => 15, 'w' => 70, 'gap' => 40]],
            ['heading', ['text' => 'Participating cities', 'level' => 3], ['x' => 15, 'w' => 70, 'gap' => 10]],
            ['text', ['html' => '<p>Los Angeles &middot; Chicago &middot; Washington &middot; Melbourne &middot; Vilnius</p>'], ['x' => 15, 'w' => 70, 'gap' => 36]],
            ['heading', ['text' => 'Project leader', 'level' => 3], ['x' => 15, 'w' => 70, 'gap' => 10]],
            ['text', ['html' => '<p>Gediminas Zujus</p>'], ['x' => 15, 'w' => 70, 'gap' => 40]],
            ['button', ['label' => 'All past events', 'url' => 'before.php', 'variant' => 'outline', 'align' => 'center'], ['x' => 15, 'w' => 70]],
        ],
    ];

    // -- Upcoming ------------------------------------------------------------------
    $p['upcoming'] = [
        'title' => 'Upcoming',
        'meta'  => 'Upcoming Baltic Wave events.',
        'blocks' => [
            ['heading', ['text' => 'Upcoming', 'level' => 1, 'align' => 'center'], ['x' => 10, 'w' => 80, 'gap' => 24]],
            ['text', ['html' => '<p>Information will be updated soon, stay tuned!</p>', 'align' => 'center'], ['x' => 20, 'w' => 60, 'gap' => 40]],
            ['countdown', ['date' => '', 'label' => 'Next wave is being prepared'], ['x' => 20, 'w' => 60, 'gap' => 44]],
            ['button', ['label' => 'Contact us', 'url' => 'contacts.php', 'variant' => 'solid', 'align' => 'center'], ['x' => 20, 'w' => 60]],
        ],
    ];

    // -- In Media --------------------------------------------------------------------
    $p['media'] = [
        'title' => 'Baltic Wave in Media',
        'meta'  => 'Press and media coverage of the Baltic Wave project.',
        'blocks' => [
            ['heading', ['text' => 'Baltic Wave in Media', 'level' => 1, 'align' => 'center'], ['x' => 10, 'w' => 80, 'gap' => 40]],
            ['text', ['html' =>
                '<ul class="bw-medialist">'
                . '<li><a href="https://www.lrs.lt/sip/portal.show?p_r=119&amp;p_k=2&amp;p_t=176143" target="_blank" rel="noopener">Seimas hosted a unique concert dedicated to the European Day of Remembrance for Victims of Stalinism and Nazism, and Day of the Baltic Way</a><span>lrs.lt</span></li>'
                . '<li><a href="https://www.delfi.lt/veidai/kultura/isskirtinis-g-zujaus-projektas-lietuviska-sutartine-apskries-pasauli.d?id=72079588" target="_blank" rel="noopener">Išskirtinis G. Zujaus projektas: lietuviška sutartinė apskries pasaulį</a><span>delfi.lt</span></li>'
                . '<li><a href="https://www.draugas.org/news/baltic-song-around-the-globe/" target="_blank" rel="noopener">Baltic song around the globe</a><span>draugas.org</span></li>'
                . '<li><a href="http://kauno.diena.lt/naujienos/laisvalaikis-ir-kultura/kultura/juodojo-kaspino-diena-koncertavo-atlikejai-triju-zemynu-825937" target="_blank" rel="noopener">Juodojo kaspino dieną koncertavo atlikėjai iš trijų žemynų</a><span>kauno.diena.lt</span></li>'
                . '<li><a href="https://www.15min.lt/vardai/naujiena/lietuva/isskirtinis-gedimino-zujaus-projektas-lietuviska-sutartine-apskries-pasauli-1050-670983" target="_blank" rel="noopener">Išskirtinis Gedimino Zujaus projektas: lietuviška sutartinė apskries pasaulį</a><span>15min.lt</span></li>'
                . '</ul>'], ['x' => 15, 'w' => 70, 'h' => 560]],
        ],
    ];

    // -- Gallery -----------------------------------------------------------------------
    $p['gallery'] = [
        'title' => 'Gallery',
        'meta'  => 'Baltic Wave photo gallery.',
        'blocks' => [
            ['heading', ['text' => 'Gallery', 'level' => 1, 'align' => 'center'], ['x' => 10, 'w' => 80, 'gap' => 14]],
            ['text', ['html' => '<p>Moments from performances across three continents.</p>', 'align' => 'center'], ['x' => 20, 'w' => 60, 'gap' => 40]],
            ['galleryGrid', ['album_id' => 0, 'columns' => 3], ['x' => 8, 'w' => 84]],
        ],
    ];

    // -- Videos -------------------------------------------------------------------------
    $p['videos'] = [
        'title' => 'Videos',
        'meta'  => 'Baltic Wave video archive.',
        'blocks' => [
            ['heading', ['text' => 'Videos', 'level' => 1, 'align' => 'center'], ['x' => 10, 'w' => 80, 'gap' => 14]],
            ['text', ['html' => '<p>Full concerts and highlights from every Baltic Wave event.</p>', 'align' => 'center'], ['x' => 20, 'w' => 60, 'gap' => 40]],
            ['videoList', ['columns' => 2], ['x' => 8, 'w' => 84]],
        ],
    ];

    // -- Contacts -------------------------------------------------------------------------
    $p['contacts'] = [
        'title' => 'Contacts',
        'meta'  => 'Contact the Baltic Wave team.',
        'blocks' => [
            ['heading', ['text' => 'Contacts', 'level' => 1, 'align' => 'center'], ['x' => 10, 'w' => 80, 'gap' => 14]],
            ['text', ['html' => '<p>Project initiator and leader — <strong>Gediminas Zujus</strong>.<br>For inquiries, collaborations and media requests, please use the form below.</p>', 'align' => 'center'], ['x' => 20, 'w' => 60, 'gap' => 44]],
            ['contactForm', ['title' => 'Get in touch'], ['x' => 25, 'w' => 50]],
        ],
    ];

    return $p;
}

// ---------------------------------------------------------------------------
// Runner
// ---------------------------------------------------------------------------
try {
    // 1. Make sure the database itself exists.
    $root = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $root->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', DB_NAME)
              . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    bw_log($log, 'Duomenų bazė „' . DB_NAME . '“ paruošta.');
    unset($root);

    $db = db();

    // 2. Migration bookkeeping table.
    $db->exec('CREATE TABLE IF NOT EXISTS migrations (
        version INT UNSIGNED NOT NULL PRIMARY KEY,
        applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $applied = array_map('intval', array_column(q_all('SELECT version FROM migrations'), 'version'));

    // 3. Apply pending migrations in order.
    ksort($BW_MIGRATIONS);
    $ran = 0;
    foreach ($BW_MIGRATIONS as $version => $fn) {
        if (in_array($version, $applied, true)) {
            continue;
        }
        $note = $fn($db);
        q('INSERT INTO migrations (version) VALUES (?)', [$version]);
        bw_log($log, "Migracija #$version: $note");
        $ran++;
    }
    if ($ran === 0) {
        bw_log($log, 'Naujų migracijų nėra — schema jau naujausia.', 'info');
    }

    // 4. Seed initial content on a fresh install.
    bw_seed_content($db, $log);

    // 5. Make sure every page has its physical .php stub file.
    $made = 0;
    foreach (q_all('SELECT slug FROM pages') as $row) {
        if ($row['slug'] !== 'home' && !is_file(page_stub_path($row['slug']))) {
            $made += ensure_page_stub($row['slug']) ? 1 : 0;
        }
    }
    if ($made > 0) {
        bw_log($log, "Sugeneruoti $made puslapių .php failai.");
    }
    if (!is_writable(dirname(__DIR__ . '/index.php'))) {
        bw_log($log, 'Dėmesio: šakninis katalogas nėra įrašomas — naujų puslapių .php failai nebus kuriami automatiškai.', 'warn');
    }
    if (!is_dir(UPLOADS_DIR)) {
        @mkdir(UPLOADS_DIR, 0755, true);
    }
    if (!is_writable(UPLOADS_DIR)) {
        bw_log($log, 'Dėmesio: katalogas /uploads nėra įrašomas — nuotraukų įkėlimas neveiks. Suteikite rašymo teises.', 'warn');
    }

    $userCount = (int)q_val('SELECT COUNT(*) FROM users');
} catch (Throwable $e) {
    $ok = false;
    bw_log($log, 'Klaida: ' . $e->getMessage(), 'error');
    $userCount = 0;
}

// ---------------------------------------------------------------------------
// Output (works both in browser and CLI)
// ---------------------------------------------------------------------------
if (PHP_SAPI === 'cli') {
    foreach ($log as $l) {
        echo '[' . strtoupper($l['type']) . '] ' . $l['msg'] . "\n";
    }
    exit($ok ? 0 : 1);
}
?>
<!DOCTYPE html>
<html lang="lt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Baltic Wave CMS — duomenų bazės diegimas</title>
<style>
  body{margin:0;font-family:system-ui,sans-serif;background:#f2f5fa;color:#1a2440;display:grid;place-items:center;min-height:100vh}
  .card{background:#fff;border:1px solid rgba(20,30,60,.1);border-radius:16px;padding:36px 40px;max-width:640px;width:92%;box-shadow:0 20px 60px rgba(23,32,58,.14)}
  h1{margin:0 0 6px;font-size:1.5rem}
  h1 span{background:linear-gradient(90deg,#1c2541,#4a6fa5 60%,#8ab6d6);-webkit-background-clip:text;background-clip:text;color:transparent}
  p.sub{margin:0 0 22px;color:#61708f}
  ul{list-style:none;margin:0;padding:0}
  li{padding:9px 12px;border-radius:8px;margin-bottom:6px;font-size:.92rem;background:#f7f9fd;border:1px solid rgba(20,30,60,.07)}
  li.ok::before{content:"✓ ";color:#4a6fa5}
  li.info::before{content:"ℹ ";color:#4a6fa5}
  li.warn{background:#fdf6e5}li.warn::before{content:"⚠ ";color:#b97f0f}
  li.error{background:#fdeef1}li.error::before{content:"✕ ";color:#d6455d}
  .actions{margin-top:24px;display:flex;gap:12px;flex-wrap:wrap}
  a.btn{display:inline-block;padding:11px 20px;border-radius:10px;text-decoration:none;font-weight:600;font-size:.92rem}
  a.primary{background:linear-gradient(90deg,#1c2541,#8ab6d6);color:#fff}
  a.ghost{border:1px solid rgba(20,30,60,.2);color:#1a2440}
</style>
</head>
<body>
<div class="card">
  <h1><span>Baltic Wave CMS</span> — diegimas</h1>
  <p class="sub">Duomenų bazės schema ir pradinis turinys</p>
  <ul>
    <?php foreach ($log as $l): ?>
      <li class="<?= e($l['type']) ?>"><?= e($l['msg']) ?></li>
    <?php endforeach; ?>
  </ul>
  <?php if ($ok): ?>
  <div class="actions">
    <?php if ($userCount === 0): ?>
      <a class="btn primary" href="admin/register.php">Sukurti administratoriaus paskyrą →</a>
    <?php else: ?>
      <a class="btn primary" href="admin/">Į administravimo pultą →</a>
    <?php endif; ?>
    <a class="btn ghost" href="./">Peržiūrėti svetainę</a>
  </div>
  <?php endif; ?>
</div>
</body>
</html>
