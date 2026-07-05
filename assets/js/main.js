/* Baltic Wave — public site interactions: nav, lightbox, countdowns. */
(function () {
  'use strict';

  // ---------- Mobile navigation ----------
  var burger = document.getElementById('bw-burger');
  var nav = document.getElementById('bw-nav');
  if (burger && nav) {
    burger.addEventListener('click', function () {
      var open = nav.classList.toggle('open');
      burger.setAttribute('aria-expanded', open ? 'true' : 'false');
      document.body.style.overflow = open ? 'hidden' : '';
    });
  }

  // Touch devices: first tap on a parent item opens its submenu.
  document.querySelectorAll('.bw-nav li.has-sub > a').forEach(function (a) {
    a.addEventListener('click', function (e) {
      var li = a.parentElement;
      var isDesktop = window.matchMedia('(min-width: 981px)').matches;
      var hasHover = window.matchMedia('(hover: hover)').matches;
      if (isDesktop && !hasHover && !li.classList.contains('open')) {
        e.preventDefault();
        document.querySelectorAll('.bw-nav li.open').forEach(function (o) { o.classList.remove('open'); });
        li.classList.add('open');
      }
    });
  });

  // ---------- Lightbox ----------
  var items = Array.prototype.slice.call(document.querySelectorAll('[data-lightbox]'));
  if (items.length) {
    var lb = document.createElement('div');
    lb.className = 'bw-lightbox';
    lb.innerHTML =
      '<button class="bw-lightbox-close" aria-label="Close">&times;</button>' +
      '<button class="bw-lightbox-nav bw-lightbox-prev" aria-label="Previous">&larr;</button>' +
      '<img alt="">' +
      '<div class="bw-lightbox-caption"></div>' +
      '<button class="bw-lightbox-nav bw-lightbox-next" aria-label="Next">&rarr;</button>';
    document.body.appendChild(lb);

    var img = lb.querySelector('img');
    var caption = lb.querySelector('.bw-lightbox-caption');
    var current = 0;

    function show(i) {
      current = (i + items.length) % items.length;
      img.src = items[current].getAttribute('href');
      caption.textContent = items[current].getAttribute('data-caption') || '';
    }
    function open(i) { show(i); lb.classList.add('open'); document.body.style.overflow = 'hidden'; }
    function close() { lb.classList.remove('open'); document.body.style.overflow = ''; }

    items.forEach(function (a, i) {
      a.addEventListener('click', function (e) { e.preventDefault(); open(i); });
    });
    lb.querySelector('.bw-lightbox-close').addEventListener('click', close);
    lb.querySelector('.bw-lightbox-prev').addEventListener('click', function () { show(current - 1); });
    lb.querySelector('.bw-lightbox-next').addEventListener('click', function () { show(current + 1); });
    lb.addEventListener('click', function (e) { if (e.target === lb) close(); });
    document.addEventListener('keydown', function (e) {
      if (!lb.classList.contains('open')) return;
      if (e.key === 'Escape') close();
      if (e.key === 'ArrowLeft') show(current - 1);
      if (e.key === 'ArrowRight') show(current + 1);
    });
  }

  // ---------- Countdown blocks ----------
  document.querySelectorAll('.bw-countdown').forEach(function (el) {
    var units = el.querySelector('.bw-countdown-units');
    var dateStr = el.getAttribute('data-date');
    var target = dateStr ? new Date(dateStr).getTime() : NaN;

    if (!dateStr || isNaN(target)) {
      units.innerHTML = '<div class="bw-countdown-tuned">Stay tuned…</div>';
      return;
    }
    function unit(v, label) {
      return '<div class="bw-countdown-unit"><b>' + String(v).padStart(2, '0') + '</b><span>' + label + '</span></div>';
    }
    function tick() {
      var d = target - Date.now();
      if (d <= 0) {
        units.innerHTML = '<div class="bw-countdown-tuned">It is happening now!</div>';
        return;
      }
      var days = Math.floor(d / 864e5);
      var hrs = Math.floor(d % 864e5 / 36e5);
      var min = Math.floor(d % 36e5 / 6e4);
      var sec = Math.floor(d % 6e4 / 1e3);
      units.innerHTML = unit(days, 'days') + unit(hrs, 'hours') + unit(min, 'min') + unit(sec, 'sec');
      setTimeout(tick, 1000);
    }
    tick();
  });
})();
