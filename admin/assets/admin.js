/* Baltic Wave CMS — shared admin helpers (toast, API, drag-sort). */
(function () {
  'use strict';

  // ---------- Toast ----------
  var toastEl = null, toastTimer = null;
  window.bwToast = function (msg, isError) {
    if (!toastEl) {
      toastEl = document.createElement('div');
      toastEl.className = 'adm-toast';
      document.body.appendChild(toastEl);
    }
    toastEl.textContent = msg;
    toastEl.classList.toggle('error', !!isError);
    toastEl.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { toastEl.classList.remove('show'); }, 2600);
  };

  // ---------- API helper ----------
  window.bwApi = function (action, data) {
    var body = new FormData();
    Object.keys(data || {}).forEach(function (k) { body.append(k, data[k]); });
    return fetch('api.php?action=' + encodeURIComponent(action), {
      method: 'POST',
      headers: { 'X-CSRF': window.BW_CSRF },
      body: body
    }).then(function (r) { return r.json(); }).then(function (j) {
      if (!j.ok) { throw new Error(j.error || 'Klaida'); }
      return j;
    });
  };

  // ---------- HTML5 drag-to-reorder ----------
  // bwSortable(container, '.item-selector', function onDrop(){ … })
  window.bwSortable = function (container, selector, onDrop) {
    var dragging = null;
    container.querySelectorAll(selector).forEach(function (el) { el.draggable = true; });

    container.addEventListener('dragstart', function (e) {
      var item = e.target.closest(selector);
      if (!item) return;
      dragging = item;
      item.classList.add('dragging');
      e.dataTransfer.effectAllowed = 'move';
      try { e.dataTransfer.setData('text/plain', ''); } catch (err) {}
    });
    container.addEventListener('dragend', function () {
      if (dragging) { dragging.classList.remove('dragging'); dragging = null; if (onDrop) onDrop(); }
    });
    container.addEventListener('dragover', function (e) {
      if (!dragging) return;
      e.preventDefault();
      var after = null;
      var items = Array.prototype.slice.call(container.querySelectorAll(selector + ':not(.dragging)'));
      for (var i = 0; i < items.length; i++) {
        var box = items[i].getBoundingClientRect();
        var isGrid = box.height < container.getBoundingClientRect().height - 5 &&
                     getComputedStyle(container).display === 'grid';
        var before = isGrid
          ? (e.clientY < box.top + box.height / 2 || (e.clientY < box.bottom && e.clientX < box.left + box.width / 2))
          : e.clientY < box.top + box.height / 2;
        if (before) { after = items[i]; break; }
      }
      if (after) { container.insertBefore(dragging, after); }
      else { container.appendChild(dragging); }
    });
  };
})();
