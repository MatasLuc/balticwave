/* ============================================================
   Baltic Wave CMS — visual drag & drop page builder
   Blocks live on a free canvas: x/w in % of canvas width,
   y in px from the top. Saved as JSON via admin/api.php.
   ============================================================ */
(function () {
  'use strict';

  var layout = window.BW_LAYOUT || { height: 600, blocks: [] };
  layout.blocks = layout.blocks || [];

  var canvas = document.getElementById('bld-canvas');
  var outer = document.getElementById('bld-canvas-outer');
  var propsPanel = document.getElementById('bld-props');
  var statusEl = document.getElementById('bld-status');
  var heightInput = document.getElementById('bld-height');
  var snapInput = document.getElementById('bld-snap');

  var selectedId = null;
  var dirty = false;

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
      props: { label: 'Mygtukas', url: '#', variant: 'solid', align: 'left' },
      fields: [
        { k: 'label', t: 'text', label: 'Užrašas' },
        { k: 'url', t: 'text', label: 'Nuoroda (URL arba puslapis.php)' },
        { k: 'variant', t: 'select', label: 'Stilius', opts: [{ v: 'solid', t: 'Ryškus' }, { v: 'outline', t: 'Kontūrinis' }] },
        { k: 'align', t: 'select', label: 'Lygiavimas', opts: ALIGN_OPTS }
      ],
      render: function (p) {
        var cls = p.variant === 'outline' ? 'bw-btn bw-btn-outline' : 'bw-btn';
        return '<div style="text-align:' + esc(p.align || 'left') + '"><a class="' + cls + '" href="#">' + esc(p.label) + '<span class="bw-btn-arrow">→</span></a></div>';
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
  // Canvas rendering
  // ---------------------------------------------------------------
  function applyGeometry(el, b) {
    el.style.setProperty('--x', b.x + '%');
    el.style.setProperty('--y', b.y + 'px');
    el.style.setProperty('--w', b.w + '%');
    el.style.setProperty('--z', b.z || 1);
  }

  function blockEl(b) {
    var el = document.createElement('div');
    el.className = 'bw-block bw-' + b.type + (b.id === selectedId ? ' selected' : '');
    el.dataset.id = b.id;
    applyGeometry(el, b);
    el.innerHTML = (DEFS[b.type] ? DEFS[b.type].render(b.props || {}) : '') +
      '<div class="bld-resize" title="Keisti plotį"></div>';
    return el;
  }

  function renderCanvas() {
    canvas.style.setProperty('--h', layout.height + 'px');
    canvas.style.minHeight = layout.height + 'px';
    canvas.innerHTML = '';
    layout.blocks.forEach(function (b) { canvas.appendChild(blockEl(b)); });
  }

  function refreshBlock(b) {
    var el = canvas.querySelector('[data-id="' + b.id + '"]');
    if (el) {
      applyGeometry(el, b);
      el.innerHTML = DEFS[b.type].render(b.props || {}) + '<div class="bld-resize" title="Keisti plotį"></div>';
    }
  }

  function growCanvasIfNeeded() {
    var bottom = 0;
    canvas.querySelectorAll('.bw-block').forEach(function (el) {
      bottom = Math.max(bottom, el.offsetTop + el.offsetHeight);
    });
    if (bottom + 40 > layout.height) {
      layout.height = Math.ceil((bottom + 80) / 10) * 10;
      heightInput.value = layout.height;
      canvas.style.minHeight = layout.height + 'px';
      canvas.style.setProperty('--h', layout.height + 'px');
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
    var b = {
      id: uid(), type: type,
      x: Math.round((100 - d.w) / 2), y: Math.max(20, Math.round(outer.scrollTop + 60)),
      w: d.w, z: 1,
      props: JSON.parse(JSON.stringify(d.props))
    };
    layout.blocks.push(b);
    canvas.appendChild(blockEl(b));
    select(b.id);
    markDirty();
    growCanvasIfNeeded();
  }

  // ---------------------------------------------------------------
  // Selection & properties panel
  // ---------------------------------------------------------------
  function select(id) {
    selectedId = id;
    canvas.querySelectorAll('.bw-block').forEach(function (el) {
      el.classList.toggle('selected', el.dataset.id === id);
    });
    buildProps();
  }

  function propInput(field, value, onChange) {
    var wrap = document.createElement('div');
    wrap.className = 'bld-prop';
    var lab = document.createElement('label');
    lab.textContent = field.label;
    wrap.appendChild(lab);
    var input;

    if (field.t === 'textarea') {
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

  function buildProps() {
    var b = findBlock(selectedId);
    propsPanel.innerHTML = '<h3>Savybės</h3>';
    if (!b) {
      propsPanel.innerHTML += '<div class="empty">Pasirinkite bloką drobėje arba pridėkite naują iš kairės.</div>';
      return;
    }
    var d = DEFS[b.type];
    var title = document.createElement('div');
    title.style.cssText = 'font-family:var(--font-display);font-weight:700;margin-bottom:14px';
    title.textContent = d.icon + ' ' + d.label;
    propsPanel.appendChild(title);

    // Type-specific fields.
    d.fields.forEach(function (f) {
      propsPanel.appendChild(propInput(f, b.props[f.k], function (v) {
        b.props[f.k] = v;
        refreshBlock(b);
        markDirty();
      }));
    });

    // Geometry.
    var geoTitle = document.createElement('h3');
    geoTitle.textContent = 'Padėtis ir dydis';
    geoTitle.style.marginTop = '18px';
    propsPanel.appendChild(geoTitle);
    var row1 = document.createElement('div');
    row1.className = 'bld-prop-row';
    row1.appendChild(geoInput('X (%)', b.x, 0, 100, 0.5, function (v) { b.x = v; refreshBlock(b); markDirty(); }));
    row1.appendChild(geoInput('Y (px)', b.y, 0, 30000, 5, function (v) { b.y = v; refreshBlock(b); markDirty(); growCanvasIfNeeded(); }));
    propsPanel.appendChild(row1);
    var row2 = document.createElement('div');
    row2.className = 'bld-prop-row';
    row2.appendChild(geoInput('Plotis (%)', b.w, 2, 100, 0.5, function (v) { b.w = v; refreshBlock(b); markDirty(); }));
    row2.appendChild(geoInput('Sluoksnis (z)', b.z || 1, 0, 99, 1, function (v) { b.z = v; refreshBlock(b); markDirty(); }));
    propsPanel.appendChild(row2);

    // Actions.
    var actions = document.createElement('div');
    actions.className = 'bld-prop-actions';
    var dup = document.createElement('button');
    dup.className = 'btn btn-ghost btn-sm';
    dup.textContent = '⧉ Dubliuoti';
    dup.addEventListener('click', function () {
      var copy = JSON.parse(JSON.stringify(b));
      copy.id = uid();
      copy.y += 40;
      layout.blocks.push(copy);
      canvas.appendChild(blockEl(copy));
      select(copy.id);
      markDirty();
    });
    var del = document.createElement('button');
    del.className = 'btn btn-danger btn-sm';
    del.textContent = '✕ Pašalinti';
    del.addEventListener('click', function () { removeBlock(b.id); });
    actions.appendChild(dup);
    actions.appendChild(del);
    propsPanel.appendChild(actions);
  }

  function removeBlock(id) {
    layout.blocks = layout.blocks.filter(function (b) { return b.id !== id; });
    var el = canvas.querySelector('[data-id="' + id + '"]');
    if (el) el.remove();
    if (selectedId === id) select(null);
    markDirty();
  }

  // ---------------------------------------------------------------
  // Dragging & resizing (pointer events)
  // ---------------------------------------------------------------
  var drag = null;

  canvas.addEventListener('pointerdown', function (e) {
    var el = e.target.closest('.bw-block');
    if (!el) { select(null); return; }
    var b = findBlock(el.dataset.id);
    if (!b) return;
    select(b.id);
    e.preventDefault();

    var rect = canvas.getBoundingClientRect();
    drag = {
      b: b, el: el,
      mode: e.target.classList.contains('bld-resize') ? 'resize' : 'move',
      startX: e.clientX, startY: e.clientY,
      origX: b.x, origY: b.y, origW: b.w,
      cw: rect.width
    };
  });

  document.addEventListener('pointermove', function (e) {
    if (!drag) return;
    var dxPct = (e.clientX - drag.startX) / drag.cw * 100;
    var snap = snapInput.checked;

    if (drag.mode === 'move') {
      var nx = drag.origX + dxPct;
      var ny = drag.origY + (e.clientY - drag.startY);
      if (snap) { nx = Math.round(nx); ny = Math.round(ny / 10) * 10; }
      drag.b.x = Math.max(0, Math.min(100 - drag.b.w, Math.round(nx * 10) / 10));
      drag.b.y = Math.max(0, Math.round(ny));
    } else {
      var nw = drag.origW + dxPct;
      if (snap) nw = Math.round(nw);
      drag.b.w = Math.max(4, Math.min(100 - drag.b.x, Math.round(nw * 10) / 10));
    }
    applyGeometry(drag.el, drag.b);
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
    var step = e.shiftKey ? 10 : 1;
    var handled = true;
    if (e.key === 'ArrowLeft') b.x = Math.max(0, b.x - step * 0.5);
    else if (e.key === 'ArrowRight') b.x = Math.min(100 - b.w, b.x + step * 0.5);
    else if (e.key === 'ArrowUp') b.y = Math.max(0, b.y - step);
    else if (e.key === 'ArrowDown') b.y = b.y + step;
    else if (e.key === 'Delete' || e.key === 'Backspace') { removeBlock(b.id); return; }
    else handled = false;
    if (handled) {
      e.preventDefault();
      refreshBlock(b);
      markDirty();
    }
  });

  // ---------------------------------------------------------------
  // Height & snap controls
  // ---------------------------------------------------------------
  heightInput.addEventListener('input', function () {
    var v = parseInt(heightInput.value, 10);
    if (!isNaN(v) && v >= 200) {
      layout.height = v;
      canvas.style.minHeight = v + 'px';
      canvas.style.setProperty('--h', v + 'px');
      markDirty();
    }
  });
  snapInput.addEventListener('change', function () {
    canvas.classList.toggle('grid-on', snapInput.checked);
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
