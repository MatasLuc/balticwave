<?php
require_once __DIR__ . '/includes/boot.php';
require_admin();

$items = q_all('SELECT * FROM menu_items ORDER BY sort_order, id');
$pages = q_all('SELECT id, title, slug FROM pages ORDER BY title');

// Flat render order: parents first, each followed by its children.
$top      = array_values(array_filter($items, fn($i) => empty($i['parent_id'])));
$children = [];
foreach ($items as $i) {
    if (!empty($i['parent_id'])) {
        $children[$i['parent_id']][] = $i;
    }
}
$flat = [];
foreach ($top as $t) {
    $flat[] = $t;
    foreach ($children[$t['id']] ?? [] as $c) {
        $flat[] = $c;
    }
}

$adminTitle   = 'Meniu';
$adminSection = 'menu';
$adminActions = '<button class="btn btn-ghost" id="menu-add">+ Pridėti punktą</button>'
              . '<button class="btn btn-primary" id="menu-save">Išsaugoti meniu</button>';
require __DIR__ . '/includes/header.php';
?>

<div class="adm-card">
  <p style="color:var(--muted);margin:0 0 16px;font-size:.88rem">
    Tempkite punktus už ⠿ rankenėlės, kad pakeistumėte eiliškumą. Punktas tampa submeniu pasirinkus „Tėvinį punktą“.
    Nepamirškite paspausti <strong>„Išsaugoti meniu“</strong>.
  </p>
  <div id="menu-list"></div>
</div>

<template id="menu-row-tpl">
  <div class="menu-row" data-id="0">
    <span class="drag-handle" title="Tempti">⠿</span>
    <input type="text" class="mi-label" placeholder="Pavadinimas" maxlength="120">
    <select class="mi-link">
      <option value="">— Nuoroda —</option>
      <?php foreach ($pages as $pg): ?>
        <option value="p<?= (int)$pg['id'] ?>"><?= e($pg['title']) ?> (<?= e($pg['slug']) ?>)</option>
      <?php endforeach; ?>
      <option value="url">Kita nuoroda (URL)</option>
    </select>
    <input type="text" class="mi-url" placeholder="https://…" style="display:none;flex:1">
    <select class="mi-parent"><option value="0">— Viršutinis lygis —</option></select>
    <label class="adm-check" style="margin:0"><input type="checkbox" class="mi-visible" checked> rodyti</label>
    <button class="btn btn-danger btn-sm mi-del" type="button">✕</button>
  </div>
</template>

<script>
(function () {
  var DATA = <?= json_encode(array_map(fn($i) => [
        'id' => (int)$i['id'], 'label' => $i['label'], 'page_id' => (int)$i['page_id'],
        'url' => $i['url'], 'parent_id' => (int)$i['parent_id'], 'visible' => (int)$i['visible'],
      ], $flat), JSON_UNESCAPED_UNICODE) ?>;

  var list = document.getElementById('menu-list');
  var tpl  = document.getElementById('menu-row-tpl');

  function makeRow(d) {
    var row = tpl.content.firstElementChild.cloneNode(true);
    row.dataset.id = d.id || 0;
    row.querySelector('.mi-label').value = d.label || '';
    var link = row.querySelector('.mi-link');
    var url  = row.querySelector('.mi-url');
    if (d.page_id) { link.value = 'p' + d.page_id; }
    else if (d.url) { link.value = 'url'; url.value = d.url; url.style.display = ''; }
    row.querySelector('.mi-visible').checked = !!d.visible;
    row._parentId = d.parent_id || 0;

    link.addEventListener('change', function () {
      url.style.display = link.value === 'url' ? '' : 'none';
    });
    row.querySelector('.mi-del').addEventListener('click', function () {
      if (confirm('Pašalinti šį meniu punktą?')) { row.remove(); refreshParents(); }
    });
    row.querySelector('.mi-parent').addEventListener('change', function () {
      row._parentId = parseInt(this.value, 10) || 0;
      row.classList.toggle('child', row._parentId > 0);
    });
    row.querySelector('.mi-label').addEventListener('input', refreshParents);
    return row;
  }

  // Parent dropdowns list every saved (id>0) top-level row except the row itself.
  function refreshParents() {
    var rows = Array.prototype.slice.call(list.querySelectorAll('.menu-row'));
    rows.forEach(function (row) {
      var sel = row.querySelector('.mi-parent');
      var current = row._parentId || 0;
      sel.innerHTML = '<option value="0">— Viršutinis lygis —</option>';
      rows.forEach(function (other) {
        var oid = parseInt(other.dataset.id, 10);
        if (oid > 0 && other !== row && !(other._parentId > 0)) {
          var o = document.createElement('option');
          o.value = oid;
          o.textContent = '↳ ' + (other.querySelector('.mi-label').value || 'Be pavadinimo');
          if (oid === current) o.selected = true;
          sel.appendChild(o);
        }
      });
      if (current > 0 && parseInt(sel.value, 10) !== current) { row._parentId = 0; }
      row.classList.toggle('child', row._parentId > 0);
    });
  }

  DATA.forEach(function (d) { list.appendChild(makeRow(d)); });
  refreshParents();
  bwSortable(list, '.menu-row', refreshParents);

  document.getElementById('menu-add').addEventListener('click', function () {
    var row = makeRow({ id: 0, visible: 1 });
    list.appendChild(row);
    row.draggable = true;
    refreshParents();
    row.querySelector('.mi-label').focus();
  });

  document.getElementById('menu-save').addEventListener('click', function () {
    var items = [];
    list.querySelectorAll('.menu-row').forEach(function (row) {
      var link = row.querySelector('.mi-link').value;
      items.push({
        id: parseInt(row.dataset.id, 10) || 0,
        label: row.querySelector('.mi-label').value.trim(),
        page_id: link.charAt(0) === 'p' ? parseInt(link.slice(1), 10) : 0,
        url: link === 'url' ? row.querySelector('.mi-url').value.trim() : '',
        parent_id: row._parentId || 0,
        visible: row.querySelector('.mi-visible').checked ? 1 : 0
      });
    });
    bwApi('menu_save', { items: JSON.stringify(items) })
      .then(function () { bwToast('Meniu išsaugotas.'); setTimeout(function () { location.reload(); }, 600); })
      .catch(function (e) { bwToast(e.message, true); });
  });
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
