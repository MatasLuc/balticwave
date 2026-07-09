/* ============================================================
   Baltic Wave CMS — visual drag & drop page builder
   Every block carries TWO independent positions — b.desktop and
   b.mobile (each {x,y,w,z}, x/w in % of canvas width, y in px) —
   edited on two separate canvases via the Desktop/Mobile toolbar
   tabs. Content/props are shared between both. Saved as JSON via
   admin/api.php.
   ============================================================ */
(function () {
  'use strict';

  var layout = window.BW_LAYOUT || { height: 600, mobileHeight: 600, blocks: [] };
  layout.blocks = layout.blocks || [];
  if (layout.mobileHeight == null) layout.mobileHeight = layout.height || 600;

  var canvas = document.getElementById('bld-canvas');
  var canvasMobile = document.getElementById('bld-canvas-mobile');
  var outer = document.getElementById('bld-canvas-outer');
  var propsBody = document.getElementById('bld-props-body');
  var statusEl = document.getElementById('bld-status');
  var heightInput = document.getElementById('bld-height');
  var snapInput = document.getElementById('bld-snap');

  var selectedId = null;
  var dirty = false;
  var device = 'desktop';

  // ---------------------------------------------------------------
  // Helpers
  // ---------------------------------------------------------------
  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }
  function ytId(url) {
    var m = /(?:youtu\.be\/|v=|embed\/|shorts\/|live\/)([A-Za-z0-9_-]{6,20})/.exec(url || '');
    if (m) return m[1];
    return /^[A-Za-z0-9_-]{6,20}$/.test((url || '').trim()) ? url.trim() : '';
  }
  function uid() { return 'b' + Date.now().toString(36) + Math.random().toString(36).slice(2, 6); }
  function findBlock(id) {
    for (var i = 0; i < layout.blocks.length; i++) {
      if (layout.blocks[i].id === id) return layout.blocks[i];
    }
    return null;
  }
  function markDirty() {
    dirty = true;
    statusEl.textContent = 'Neišsaugoti pakeitimai';
    statusEl.className = 'bld-status dirty';
  }
  function placeholder(title, note) {
    return '<div class="bld-placeholder"><b>' + esc(title) + '</b>' + esc(note || '') + '</div>';
  }
  function activeCanvas() { return device === 'mobile' ? canvasMobile : canvas; }
  function heightKey() { return device === 'mobile' ? 'mobileHeight' : 'height'; }

  // ---------------------------------------------------------------
  // Block definitions: label, icon, defaults, editable fields, preview
  // ---------------------------------------------------------------
  var ALBUM_OPTS = [{ v: 0, t: 'Visi albumai' }].concat(
    (window.BW_ALBUMS || []).map(function (a) { return { v: a.id, t: a.title }; })
  );
  var ALIGN_OPTS = [{ v: 'left', t: 'Kairėje' }, { v: 'center', t: 'Centre' }, { v: 'right', t: 'Dešinėje' }];

  var DEFS = {
    heading: {
      label: 'Antraštė', icon: 'H', w: 80,
      props: { text: 'Nauja antraštė', level: 2, align: 'left' },
      fields: [
        { k: 'text', t: 'text', label: 'Tekstas' },
        { k: 'level', t: 'select', label: 'Dydis', opts: [{ v: 1, t: 'H1 — didžiausia' }, { v: 2, t: 'H2' }, { v: 3, t: 'H3 — sekcijos' }] },
        { k: 'align', t: 'select', label: 'Lygiavimas', opts: ALIGN_OPTS }
      ],
      render: function (p) {
        var l = [1, 2, 3].indexOf(+p.level) >= 0 ? +p.level : 2;
        return '<h' + l + ' class="bw-h bw-h' + l + '" style="text-align:' + esc(p.align || 'left') + '">' + esc(p.text) + '</h' + l + '>';
      }
    },
    text: {
      label: 'Tekstas', icon: '¶', w: 60,
      props: { html: '<p>Naujas tekstas. Galite naudoti HTML žymes.</p>', align: 'left' },
      fields: [
        { k: 'html', t: 'textarea', label: 'Turinys (HTML: <p>, <strong>, <a>, <ul>…)' },
        { k: 'align', t: 'select', label: 'Lygiavimas', opts: ALIGN_OPTS }
      ],
      render: function (p) {
        return '<div class="bw-richtext" style="text-align:' + esc(p.align || 'left') + '">' + (p.html || '') + '</div>';
      }
    },
    image: {
      label: 'Paveikslėlis', icon: '▣', w: 50,
      props: { src: '', alt: '', caption: '', radius: 16 },
      fields: [
        { k: 'src', t: 'image', label: 'Paveikslėlis' },
        { k: 'alt', t: 'text', label: 'Alt tekstas (SEO)' },
        { k: 'caption', t: 'text', label: 'Parašas po nuotrauka' },
        { k: 'radius', t: 'number', label: 'Kampų užapvalinimas (px)', min: 0, max: 60 }
      ],
      render: function (p) {
        if (!p.src) return placeholder('Paveikslėlis', 'Pasirinkite failą savybių skydelyje →');
        var img = '<img src="' + esc(p.src) + '" alt="' + esc(p.alt) + '" style="border-radius:' + (parseInt(p.radius, 10) || 0) + 'px">';
        return p.caption ? '<figure>' + img + '<figcaption>' + esc(p.caption) + '</figcaption></figure>' : img;
      }
    },
    button: {
      label: 'Mygtukas', icon: '⬲', w: 30,
      props: { label: 'Mygtukas', url: '#', variant: 'solid', align: 'left', color: '' },
      fields: [
        { k: 'label', t: 'text', label: 'Užrašas' },
        { k: 'url', t: 'text', label: 'Nuoroda (URL arba puslapis.php)' },
        { k: 'variant', t: 'select', label: 'Stilius', opts: [{ v: 'solid', t: 'Ryškus' }, { v: 'outline', t: 'Kontūrinis' }] },
        { k: 'align', t: 'select', label: 'Lygiavimas', opts: ALIGN_OPTS },
        { k: 'color', t: 'color', label: 'Teksto spalva', fallback: function (props) {
            return props.variant === 'outline' ? '#16203c' : '#ffffff';
          } }
      ],
      render: function (p) {
        var cls = p.variant === 'outline' ? 'bw-btn bw-btn-outline' : 'bw-btn';
        var style = /^#[0-9a-f]{6}$/i.test(p.color || '') ? ' style="color:' + esc(p.color) + '"' : '';
        return '<div style="text-align:' + esc(p.align || 'left') + '"><a class="' + cls + '" href="#"' + style + '>' + esc(p.label) + '<span class="bw-btn-arrow">→</span></a></div>';
      }
    },
    youtube: {
      label: 'YouTube video', icon: '▶', w: 70,
      props: { url: '' },
      fields: [{ k: 'url', t: 'text', label: 'YouTube nuoroda' }],
      render: function (p) {
        var id = ytId(p.url);
        if (!id) return placeholder('YouTube video', 'Įveskite nuorodą savybių skydelyje →');
        return '<div class="bw-video-frame"><iframe src="https://www.youtube-nocookie.com/embed/' + esc(id) + '" allowfullscreen></iframe></div>';
      }
    },
    quote: {
      label: 'Citata', icon: '❝', w: 60,
      props: { text: 'Citatos tekstas', author: '' },
      fields: [
        { k: 'text', t: 'textarea', label: 'Citata' },
        { k: 'author', t: 'text', label: 'Autorius / šaltinis' }
      ],
      render: function (p) {
        return '<blockquote class="bw-quote"><p>' + esc(p.text) + '</p>' + (p.author ? '<cite>' + esc(p.author) + '</cite>' : '') + '</blockquote>';
      }
    },
    divider: {
      label: 'Skirtukas', icon: '—', w: 30,
      props: {},
      fields: [],
      render: function () { return '<hr class="bw-divider">'; }
    },
    countdown: {
      label: 'Atgalinis laikmatis', icon: '⏱', w: 60,
      props: { date: '', label: 'Iki renginio liko' },
      fields: [
        { k: 'label', t: 'text', label: 'Užrašas' },
        { k: 'date', t: 'datetime', label: 'Data ir laikas (tuščia = „Stay tuned“)' }
      ],
      render: function (p) {
        var units = p.date
          ? '<div class="bw-countdown-unit"><b>07</b><span>days</span></div><div class="bw-countdown-unit"><b>12</b><span>hours</span></div><div class="bw-countdown-unit"><b>45</b><span>min</span></div>'
          : '<div class="bw-countdown-tuned">Stay tuned…</div>';
        return '<div class="bw-countdown"><div class="bw-countdown-label">' + esc(p.label) + '</div><div class="bw-countdown-units">' + units + '</div></div>';
      }
    },
    html: {
      label: 'HTML blokas', icon: '</>', w: 60,
      props: { code: '<div class="bw-card"><h3>Kortelė</h3><p>Laisvas HTML turinys.</p></div>' },
      fields: [{ k: 'code', t: 'textarea', label: 'HTML kodas' }],
      render: function (p) { return '<div class="bw-html">' + (p.code || '') + '</div>'; }
    },
    galleryGrid: {
      label: 'Galerija', icon: '⊞', w: 84,
      props: { album_id: 0, columns: 3 },
      fields: [
        { k: 'album_id', t: 'select', label: 'Albumas', opts: ALBUM_OPTS },
        { k: 'columns', t: 'number', label: 'Stulpeliai (1–5)', min: 1, max: 5 }
      ],
      render: function (p) {
        var a = null;
        (window.BW_ALBUMS || []).forEach(function (x) { if (x.id === +p.album_id) a = x; });
        return placeholder('Galerija: ' + (a ? a.title : 'visi albumai'),
          'Nuotraukos rodomos publikuotame puslapyje (' + (p.columns || 3) + ' stulp.)');
      }
    },
    videoList: {
      label: 'Video sąrašas', icon: '≣', w: 84,
      props: { columns: 2 },
      fields: [{ k: 'columns', t: 'number', label: 'Stulpeliai (1–3)', min: 1, max: 3 }],
      render: function (p) {
        return placeholder('Video sąrašas', 'Visi įrašai iš skilties „Video“ (' + (p.columns || 2) + ' stulp.)');
      }
    },
    contactForm: {
      label: 'Kontaktų forma', icon: '✉', w: 50,
      props: { title: 'Get in touch' },
      fields: [{ k: 'title', t: 'text', label: 'Formos antraštė' }],
      render: function (p) {
        return placeholder('Kontaktų forma: „' + (p.title || '') + '“', 'Žinutės keliauja į skiltį „Žinutės“');
      }
    }
  };

  // ---------------------------------------------------------------
  // Canvas rendering — each canvas (desktop/mobile) is a fully
  // independent set of DOM nodes bound to b.desktop / b.mobile.
  // ---------------------------------------------------------------
  function applyGeometry(el, pos) {
    el.style.setProperty('--x', pos.x + '%');
    el.style.setProperty('--y', pos.y + 'px');
    el.style.setProperty('--w', pos.w + '%');
    el.style.setProperty('--z', pos.z || 1);
  }

  function blockClassName(b) {
    var visCls = b.visibility === 'desktop' ? ' bld-only-desktop' : (b.visibility === 'mobile' ? ' bld-only-mobile' : '');
    return 'bw-block bw-' + b.type + visCls + (b.id === selectedId ? ' selected' : '');
  }

  function blockEl(b, deviceKey) {
    var el = document.createElement('div');
    el.className = blockClassName(b);
    el.dataset.id = b.id;
    applyGeometry(el, b[deviceKey]);
    el.innerHTML = (DEFS[b.type] ? DEFS[b.type].render(b.props || {}) : '') +
      '<div class="bld-resize" title="Keisti plotį"></div>';
    return el;
  }

  function renderCanvasFor(cv, deviceKey) {
    var h = layout[deviceKey === 'mobile' ? 'mobileHeight' : 'height'];
    cv.style.setProperty('--h', h + 'px');
    cv.style.minHeight = h + 'px';
    cv.innerHTML = '';
    layout.blocks.forEach(function (b) { cv.appendChild(blockEl(b, deviceKey)); });
  }

  function renderCanvas() {
    renderCanvasFor(canvas, 'desktop');
    renderCanvasFor(canvasMobile, 'mobile');
  }

  /** Content/visibility changed (shared) — refresh this block in BOTH canvases, keeping each one's own geometry. */
  function refreshBlockContent(b) {
    [[canvas, 'desktop'], [canvasMobile, 'mobile']].forEach(function (pair) {
      var el = pair[0].querySelector('[data-id="' + b.id + '"]');
      if (el) {
        el.className = blockClassName(b);
        applyGeometry(el, b[pair[1]]);
        el.innerHTML = DEFS[b.type].render(b.props || {}) + '<div class="bld-resize" title="Keisti plotį"></div>';
      }
    });
  }

  /** Geometry changed for the currently active device only — reposition without a full re-render. */
  function refreshGeometryActive(b) {
    var el = activeCanvas().querySelector('[data-id="' + b.id + '"]');
    if (el) applyGeometry(el, b[device]);
  }

  function growCanvasIfNeeded() {
    var cv = activeCanvas();
    var key = heightKey();
    var bottom = 0;
    cv.querySelectorAll('.bw-block').forEach(function (el) {
      bottom = Math.max(bottom, el.offsetTop + el.offsetHeight);
    });
    if (bottom + 40 > layout[key]) {
      layout[key] = Math.ceil((bottom + 80) / 10) * 10;
      heightInput.value = layout[key];
      cv.style.minHeight = layout[key] + 'px';
      cv.style.setProperty('--h', layout[key] + 'px');
    }
  }

  // ---------------------------------------------------------------
  // Palette
  // ---------------------------------------------------------------
  var paletteBox = document.getElementById('bld-palette');
  Object.keys(DEFS).forEach(function (type) {
    var d = DEFS[type];
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'bld-pal-item';
    btn.innerHTML = '<span class="ico">' + esc(d.icon) + '</span>' + esc(d.label);
    btn.addEventListener('click', function () { addBlock(type); });
    paletteBox.appendChild(btn);
  });

  function addBlock(type) {
    var d = DEFS[type];
    var pos = { x: Math.round((100 - d.w) / 2), y: Math.max(20, Math.round(outer.scrollTop + 60)), w: d.w, z: 1 };
    var b = {
      id: uid(), type: type, visibility: 'all',
      props: JSON.parse(JSON.stringify(d.props)),
      desktop: { x: pos.x, y: pos.y, w: pos.w, z: pos.z },
      mobile: { x: pos.x, y: pos.y, w: pos.w, z: pos.z }
    };
    layout.blocks.push(b);
    canvas.appendChild(blockEl(b, 'desktop'));
    canvasMobile.appendChild(blockEl(b, 'mobile'));
    select(b.id);
    markDirty();
    growCanvasIfNeeded();
  }

  // ---------------------------------------------------------------
  // Selection & properties panel
  // ---------------------------------------------------------------
  function select(id) {
    selectedId = id;
    [canvas, canvasMobile].forEach(function (cv) {
      cv.querySelectorAll('.bw-block').forEach(function (el) {
        el.classList.toggle('selected', el.dataset.id === id);
      });
    });
    if (id != null) setPropsCollapsed(false);
    buildProps();
  }

  function propInput(field, value, onChange, blockProps) {
    var wrap = document.createElement('div');
    wrap.className = 'bld-prop';
    var lab = document.createElement('label');
    lab.textContent = field.label;
    wrap.appendChild(lab);
    var input;

    if (field.t === 'color') {
      var row = document.createElement('div');
      row.className = 'bld-prop-color';
      var fallback = field.fallback ? field.fallback(blockProps || {}) : '#000000';
      input = document.createElement('input');
      input.type = 'color';
      input.value = /^#[0-9a-f]{6}$/i.test(value || '') ? value : fallback;
      var resetBtn = document.createElement('button');
      resetBtn.type = 'button';
      resetBtn.className = 'btn btn-ghost btn-sm';
      resetBtn.textContent = value ? '✕ Numatyta' : 'Numatyta spalva';
      resetBtn.addEventListener('click', function () { onChange(''); buildProps(); });
      row.appendChild(input);
      row.appendChild(resetBtn);
      wrap.appendChild(row);
      input.addEventListener('input', function () { onChange(input.value); });
      return wrap;
    } else if (field.t === 'textarea') {
      input = document.createElement('textarea');
      input.value = value == null ? '' : value;
    } else if (field.t === 'select') {
      input = document.createElement('select');
      field.opts.forEach(function (o) {
        var opt = document.createElement('option');
        opt.value = o.v;
        opt.textContent = o.t;
        if (String(o.v) === String(value)) opt.selected = true;
        input.appendChild(opt);
      });
    } else if (field.t === 'number') {
      input = document.createElement('input');
      input.type = 'number';
      if (field.min != null) input.min = field.min;
      if (field.max != null) input.max = field.max;
      input.value = value == null ? '' : value;
    } else if (field.t === 'datetime') {
      input = document.createElement('input');
      input.type = 'datetime-local';
      input.value = value == null ? '' : String(value).slice(0, 16);
    } else if (field.t === 'image') {
      input = document.createElement('input');
      input.type = 'text';
      input.placeholder = 'uploads/failas.jpg arba https://…';
      input.value = value == null ? '' : value;
      var pick = document.createElement('button');
      pick.type = 'button';
      pick.className = 'btn btn-ghost btn-sm';
      pick.style.marginTop = '6px';
      pick.textContent = '🖼 Pasirinkti / įkelti…';
      pick.addEventListener('click', function () {
        openPicker(function (url) { input.value = url; onChange(url); });
      });
      wrap.appendChild(input);
      wrap.appendChild(pick);
      input.addEventListener('input', function () { onChange(input.value); });
      return wrap;
    } else {
      input = document.createElement('input');
      input.type = 'text';
      input.value = value == null ? '' : value;
    }

    input.addEventListener('input', function () {
      var v = input.value;
      if (field.t === 'number' || (field.t === 'select' && /^\d+$/.test(v))) {
        v = v === '' ? '' : parseInt(v, 10);
      }
      onChange(v);
    });
    wrap.appendChild(input);
    return wrap;
  }

  function geoInput(label, value, min, max, step, onChange) {
    var wrap = document.createElement('div');
    wrap.className = 'bld-prop';
    wrap.innerHTML = '<label>' + esc(label) + '</label>';
    var input = document.createElement('input');
    input.type = 'number';
    input.min = min; input.max = max; input.step = step;
    input.value = value;
    input.addEventListener('input', function () {
      var v = parseFloat(input.value);
      if (!isNaN(v)) onChange(Math.max(min, Math.min(max, v)));
    });
    wrap.appendChild(input);
    return wrap;
  }

  var VISIBILITY_OPTS = [
    { v: 'all', t: 'Visur' },
    { v: 'desktop', t: 'Tik Desktop' },
    { v: 'mobile', t: 'Tik Mobile' }
  ];

  function buildProps() {
    var b = findBlock(selectedId);
    propsBody.innerHTML = '';
    if (!b) {
      propsBody.innerHTML = '<div class="empty">Pasirinkite bloką drobėje arba pridėkite naują iš kairės.</div>';
      return;
    }
    var d = DEFS[b.type];
    var title = document.createElement('div');
    title.style.cssText = 'font-family:var(--font-display);font-weight:700;margin-bottom:14px';
    title.textContent = d.icon + ' ' + d.label;
    propsBody.appendChild(title);

    // Device visibility — applies to every block type.
    propsBody.appendChild(propInput({ k: 'visibility', t: 'select', label: 'Matomumas', opts: VISIBILITY_OPTS },
      b.visibility || 'all', function (v) {
        b.visibility = v;
        refreshBlockContent(b);
        markDirty();
      }));

    // Type-specific fields (shared between devices).
    d.fields.forEach(function (f) {
      propsBody.appendChild(propInput(f, b.props[f.k], function (v) {
        b.props[f.k] = v;
        refreshBlockContent(b);
        markDirty();
      }, b.props));
    });

    // Geometry — independent per device; edits only the active canvas.
    var geoTitle = document.createElement('h3');
    geoTitle.textContent = 'Padėtis ir dydis (' + (device === 'mobile' ? 'Mobile' : 'Desktop') + ')';
    geoTitle.style.marginTop = '18px';
    propsBody.appendChild(geoTitle);
    var pos = b[device];
    var row1 = document.createElement('div');
    row1.className = 'bld-prop-row';
    row1.appendChild(geoInput('X (%)', pos.x, 0, 100, 0.5, function (v) { pos.x = v; refreshGeometryActive(b); markDirty(); }));
    row1.appendChild(geoInput('Y (px)', pos.y, 0, 30000, 5, function (v) { pos.y = v; refreshGeometryActive(b); markDirty(); growCanvasIfNeeded(); }));
    propsBody.appendChild(row1);
    var row2 = document.createElement('div');
    row2.className = 'bld-prop-row';
    row2.appendChild(geoInput('Plotis (%)', pos.w, 2, 100, 0.5, function (v) { pos.w = v; refreshGeometryActive(b); markDirty(); }));
    row2.appendChild(geoInput('Sluoksnis (z)', pos.z || 1, 0, 99, 1, function (v) { pos.z = v; refreshGeometryActive(b); markDirty(); }));
    propsBody.appendChild(row2);

    // Actions.
    var actions = document.createElement('div');
    actions.className = 'bld-prop-actions';
    var dup = document.createElement('button');
    dup.className = 'btn btn-ghost btn-sm';
    dup.textContent = '⧉ Dubliuoti';
    dup.addEventListener('click', function () {
      var copy = JSON.parse(JSON.stringify(b));
      copy.id = uid();
      copy.desktop.y += 40;
      copy.mobile.y += 40;
      layout.blocks.push(copy);
      canvas.appendChild(blockEl(copy, 'desktop'));
      canvasMobile.appendChild(blockEl(copy, 'mobile'));
      select(copy.id);
      markDirty();
    });
    var del = document.createElement('button');
    del.className = 'btn btn-danger btn-sm';
    del.textContent = '✕ Pašalinti';
    del.addEventListener('click', function () { removeBlock(b.id); });
    actions.appendChild(dup);
    actions.appendChild(del);
    propsBody.appendChild(actions);
  }

  function removeBlock(id) {
    layout.blocks = layout.blocks.filter(function (b) { return b.id !== id; });
    [canvas, canvasMobile].forEach(function (cv) {
      var el = cv.querySelector('[data-id="' + id + '"]');
      if (el) el.remove();
    });
    if (selectedId === id) select(null);
    markDirty();
  }

  // ---------------------------------------------------------------
  // Dragging & resizing (pointer events) — bound to whichever canvas
  // is currently active; operates on b[device] only.
  // ---------------------------------------------------------------
  var drag = null;

  function onCanvasPointerDown(e) {
    var el = e.target.closest('.bw-block');
    if (!el) { select(null); return; }
    var b = findBlock(el.dataset.id);
    if (!b) return;
    select(b.id);
    e.preventDefault();

    var pos = b[device];
    var rect = e.currentTarget.getBoundingClientRect();
    drag = {
      b: b, el: el, pos: pos,
      mode: e.target.classList.contains('bld-resize') ? 'resize' : 'move',
      startX: e.clientX, startY: e.clientY,
      origX: pos.x, origY: pos.y, origW: pos.w,
      cw: rect.width
    };
  }
  canvas.addEventListener('pointerdown', onCanvasPointerDown);
  canvasMobile.addEventListener('pointerdown', onCanvasPointerDown);

  document.addEventListener('pointermove', function (e) {
    if (!drag) return;
    var dxPct = (e.clientX - drag.startX) / drag.cw * 100;
    var snap = snapInput.checked;

    if (drag.mode === 'move') {
      var nx = drag.origX + dxPct;
      var ny = drag.origY + (e.clientY - drag.startY);
      if (snap) { nx = Math.round(nx); ny = Math.round(ny / 10) * 10; }
      drag.pos.x = Math.max(0, Math.min(100 - drag.pos.w, Math.round(nx * 10) / 10));
      drag.pos.y = Math.max(0, Math.round(ny));
    } else {
      var nw = drag.origW + dxPct;
      if (snap) nw = Math.round(nw);
      drag.pos.w = Math.max(4, Math.min(100 - drag.pos.x, Math.round(nw * 10) / 10));
    }
    applyGeometry(drag.el, drag.pos);
    markDirty();
  });

  document.addEventListener('pointerup', function () {
    if (!drag) return;
    growCanvasIfNeeded();
    buildProps(); // sync X/Y/W inputs
    drag = null;
  });

  // Keyboard nudging.
  document.addEventListener('keydown', function (e) {
    var tag = (document.activeElement && document.activeElement.tagName) || '';
    if (/INPUT|TEXTAREA|SELECT/.test(tag)) return;
    var b = findBlock(selectedId);
    if (!b) return;
    var pos = b[device];
    var step = e.shiftKey ? 10 : 1;
    var handled = true;
    if (e.key === 'ArrowLeft') pos.x = Math.max(0, pos.x - step * 0.5);
    else if (e.key === 'ArrowRight') pos.x = Math.min(100 - pos.w, pos.x + step * 0.5);
    else if (e.key === 'ArrowUp') pos.y = Math.max(0, pos.y - step);
    else if (e.key === 'ArrowDown') pos.y = pos.y + step;
    else if (e.key === 'Delete' || e.key === 'Backspace') { removeBlock(b.id); return; }
    else handled = false;
    if (handled) {
      e.preventDefault();
      refreshGeometryActive(b);
      markDirty();
    }
  });

  // ---------------------------------------------------------------
  // Height & snap controls — apply to whichever device is active.
  // ---------------------------------------------------------------
  heightInput.addEventListener('input', function () {
    var v = parseInt(heightInput.value, 10);
    if (!isNaN(v) && v >= 200) {
      var key = heightKey();
      var cv = activeCanvas();
      layout[key] = v;
      cv.style.minHeight = v + 'px';
      cv.style.setProperty('--h', v + 'px');
      markDirty();
    }
  });
  snapInput.addEventListener('change', function () {
    canvas.classList.toggle('grid-on', snapInput.checked);
    canvasMobile.classList.toggle('grid-on', snapInput.checked);
  });

  // ---------------------------------------------------------------
  // Collapsible properties panel
  // ---------------------------------------------------------------
  var propsPanelEl = document.getElementById('bld-props');
  var propsToggle = document.getElementById('bld-props-toggle');
  var propsTab = document.getElementById('bld-props-tab');

  function setPropsCollapsed(collapsed) {
    propsPanelEl.classList.toggle('collapsed', collapsed);
    propsTab.hidden = !collapsed;
  }
  propsToggle.addEventListener('click', function () { setPropsCollapsed(true); });
  propsTab.addEventListener('click', function () { setPropsCollapsed(false); });

  // ---------------------------------------------------------------
  // Desktop / Mobile — each is a real, independently editable canvas;
  // switching just shows/hides one and retargets height/grid/geometry
  // controls at it. Nothing is regenerated or lost either way.
  // ---------------------------------------------------------------
  var deviceButtons = document.querySelectorAll('.bld-device-btn');
  var phoneFrame = document.getElementById('bld-phone-frame');

  function setDevice(next) {
    device = next;
    deviceButtons.forEach(function (btn) { btn.classList.toggle('active', btn.dataset.device === device); });
    canvas.hidden = device !== 'desktop';
    phoneFrame.hidden = device !== 'mobile';
    heightInput.value = layout[heightKey()];
    buildProps(); // geometry fields must reflect the newly active device
  }
  deviceButtons.forEach(function (btn) {
    btn.addEventListener('click', function () { setDevice(btn.dataset.device); });
  });

  // ---------------------------------------------------------------
  // Save
  // ---------------------------------------------------------------
  function save() {
    growCanvasIfNeeded();
    statusEl.textContent = 'Saugoma…';
    bwApi('save_layout', {
      page_id: window.BW_PAGE.id,
      layout: JSON.stringify(layout)
    }).then(function () {
      dirty = false;
      statusEl.textContent = 'Išsaugota ✓';
      statusEl.className = 'bld-status saved';
    }).catch(function (e) {
      statusEl.textContent = 'Klaida!';
      statusEl.className = 'bld-status dirty';
      bwToast(e.message, true);
    });
  }
  document.getElementById('bld-save').addEventListener('click', save);
  document.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
      e.preventDefault();
      save();
    }
  });
  window.addEventListener('beforeunload', function (e) {
    if (dirty) { e.preventDefault(); e.returnValue = ''; }
  });

  // ---------------------------------------------------------------
  // Image picker modal
  // ---------------------------------------------------------------
  var picker = document.getElementById('bld-picker');
  var pickGrid = document.getElementById('bld-pick-grid');
  var pickCb = null;

  function openPicker(cb) {
    pickCb = cb;
    picker.classList.add('open');
    pickGrid.innerHTML = '<p style="color:var(--muted)">Kraunama…</p>';
    bwApi('list_uploads', {}).then(function (j) {
      pickGrid.innerHTML = j.files.length ? '' : '<p style="color:var(--muted)">Įkeltų failų nėra — įkelkite naują.</p>';
      j.files.forEach(function (f) {
        var img = document.createElement('img');
        img.src = '../' + f.url;
        img.title = f.name;
        img.addEventListener('click', function () {
          picker.classList.remove('open');
          if (pickCb) pickCb(f.url);
          buildProps();
        });
        pickGrid.appendChild(img);
      });
    }).catch(function (e) { pickGrid.innerHTML = '<p style="color:var(--red)">' + esc(e.message) + '</p>'; });
  }

  document.getElementById('bld-picker-close').addEventListener('click', function () {
    picker.classList.remove('open');
  });
  picker.addEventListener('click', function (e) {
    if (e.target === picker) picker.classList.remove('open');
  });
  document.getElementById('bld-upload-btn').addEventListener('click', function () {
    document.getElementById('bld-upload-input').click();
  });
  document.getElementById('bld-upload-input').addEventListener('change', function () {
    if (!this.files.length) return;
    bwApi('upload', { file: this.files[0] }).then(function (j) {
      picker.classList.remove('open');
      if (pickCb) pickCb(j.url);
      buildProps();
      bwToast('Įkelta.');
    }).catch(function (e) { bwToast(e.message, true); });
    this.value = '';
  });

  // ---------------------------------------------------------------
  // Boot
  // ---------------------------------------------------------------
  renderCanvas();
})();
