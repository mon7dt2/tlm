(function () {
  'use strict';

  /* ── Promo bar slider ─────────────────────────────────── */
  (function () {
    var bar    = document.getElementById('sl-promo-bar');
    if (!bar) return;

    var slides  = Array.prototype.slice.call(bar.querySelectorAll('.sl-promo-slide'));
    if (slides.length < 2) return;

    var btnPrev = bar.querySelector('.sl-promo-prev');
    var btnNext = bar.querySelector('.sl-promo-next');
    var current = 0;
    var timer;
    var DELAY   = 5000;

    function goTo(idx) {
      slides[current].classList.remove('is-active');
      slides[current].setAttribute('aria-hidden', 'true');
      current = (idx + slides.length) % slides.length;
      slides[current].classList.add('is-active');
      slides[current].setAttribute('aria-hidden', 'false');
    }

    function next() { goTo(current + 1); }
    function prev() { goTo(current - 1); }

    function startAuto() { timer = setInterval(next, DELAY); }
    function stopAuto()  { clearInterval(timer); }

    if (btnNext) btnNext.addEventListener('click', function () { stopAuto(); next(); startAuto(); });
    if (btnPrev) btnPrev.addEventListener('click', function () { stopAuto(); prev(); startAuto(); });

    bar.addEventListener('mouseenter', stopAuto);
    bar.addEventListener('mouseleave', startAuto);

    startAuto();
  })();

  /* ── Mega menu ────────────────────────────────────────── */
  var header   = document.getElementById('sl-header');
  var hamburger = document.getElementById('sl-hamburger');
  var mega     = document.getElementById('sl-mega');
  var backdrop = document.getElementById('sl-mega-backdrop');
  if (!header || !hamburger || !mega) return;

  var tabs     = Array.prototype.slice.call(document.querySelectorAll('.sl-mega-tab[data-panel]'));
  var panels   = Array.prototype.slice.call(document.querySelectorAll('.sl-mega-panel'));

  /* ── Open / Close ─────────────────────────────────────── */
  function openMega() {
    header.classList.add('sl-mega-open');
    document.body.classList.add('sl-mega-body-open');
    mega.setAttribute('aria-hidden', 'false');
    hamburger.setAttribute('aria-expanded', 'true');
    document.body.style.overflow = 'hidden';
    if (tabs.length && !tabs.some(function(t){ return t.classList.contains('is-active'); })) {
      activateTab(tabs[0]);
    }
  }

  function closeMega() {
    header.classList.remove('sl-mega-open');
    document.body.classList.remove('sl-mega-body-open');
    mega.setAttribute('aria-hidden', 'true');
    hamburger.setAttribute('aria-expanded', 'false');
    document.body.style.overflow = '';
  }

  /* ── Panel switching ──────────────────────────────────── */
  function activateTab(tab) {
    tabs.forEach(function(t) { t.classList.remove('is-active'); });
    panels.forEach(function(p) { p.classList.remove('is-active'); });
    tab.classList.add('is-active');
    var target = document.getElementById(tab.dataset.panel);
    if (target) target.classList.add('is-active');
  }

  /* ── Events ───────────────────────────────────────────── */
  hamburger.addEventListener('click', function () {
    header.classList.contains('sl-mega-open') ? closeMega() : openMega();
  });

  if (backdrop) backdrop.addEventListener('click', closeMega);

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && header.classList.contains('sl-mega-open')) closeMega();
  });

  tabs.forEach(function (tab) {
    tab.addEventListener('mouseenter', function () {
      if (header.classList.contains('sl-mega-open')) activateTab(tab);
    });
    tab.addEventListener('click', function () {
      activateTab(tab);
    });
  });

  /* ── WooCommerce cart fragment ────────────────────────── */
  document.body.addEventListener('wc_fragments_refreshed', syncCart);
  document.body.addEventListener('wc_fragments_loaded',    syncCart);
  document.body.addEventListener('added_to_cart',          syncCart);

  function syncCart() {
    try {
      var stored = sessionStorage.getItem('wc_fragments');
      if (!stored) return;
      var data = JSON.parse(stored);
      var key = Object.keys(data).find(function(k){ return k.indexOf('sl-cart-count') !== -1; });
      if (!key) return;
      var tmp = document.createElement('div');
      tmp.innerHTML = data[key];
      var newCount = tmp.querySelector('.sl-cart-count');
      if (!newCount) return;
      document.querySelectorAll('.sl-cart-count').forEach(function(el) {
        el.textContent = newCount.textContent;
      });
    } catch(e) {}
  }

})();
