/* ── Elementor hosted-video fallback for non-admin guests ──────────── */
/* Elementor escapes <video> to &lt;video&gt; for guests. innerHTML       */
/* re-encodes text nodes back to &lt;video&gt; so we can find and fix it. */
(function () {
  function fixEscapedVideos() {
    document.querySelectorAll('.e-hosted-video').forEach(function (w) {
      var h = w.innerHTML;
      if (h.indexOf('&lt;video') === -1) return;
      w.innerHTML = h.replace(/&lt;(\/?)video\b([^]*?)&gt;/gi, '<$1video$2>');
    });
  }
  if (document.readyState !== 'loading') {
    fixEscapedVideos();
  } else {
    document.addEventListener('DOMContentLoaded', fixEscapedVideos);
  }
})();

const j$ = jQuery;

j$.noConflict();

'use strict';

j$( document ).ready( function() {

  function translateCustomText() {
    let currentLang = j$('html').attr('lang');
    if (currentLang === 'en-US') {
      j$( '.wpforms-submit' ).attr( 'data-submit-text', 'Sending...' );
      j$( '.wpforms-submit' ).attr( 'data-alt-text', 'Sending...' );
      j$( '[data-title="Tin nhắn"]' ).text('Messenger');
    }
  }
  translateCustomText();
  /* -------------------------------------------------------------- */
  function checkFilter() {
    // Hide parent elements that contain "N/A"
    j$( ".wcapf-filter-inner div:contains('N/A')" ).each( function() {
      j$( this ).closest( '.wcapf-filter' ).hide();
    } );
    // Show parent elements that do not contain "N/A"
    j$( '.wcapf-filter-inner div' ).filter( function() {
      return ! j$( this ).text().includes( 'N/A' );
    } ).closest( '.wcapf-filter' ).show();
  }
  checkFilter();
  j$( document ).ajaxStop( function() {
    checkFilter();
  } );

  /* -------------------------------------------------------------- */

  // Handle show contact button
  j$( '#wp-nt-aio-wrapper' ).addClass( 'nt-aio-active nt-aio-show-list' );

  /* -------------------------------------------------------------- */
  // Disable some input filed
  function disableInput() {
    j$( '.validate-blling-vat' ).find( 'input' ).prop( 'disabled', true );
    // clear input value
    j$( '.validate-blling-vat' ).find( 'input' ).val( '' );
  }

  // Re-enable some input filed
  function enableInput() {
    j$( '.validate-blling-vat' ).find( 'input' ).prop( 'disabled', false );
  }

  // Disable validate required input
  function disableValidate() {
    j$( '#billing_email_field' ).removeClass( 'validate-required' );
    // Remove * in input placeholder
    j$( '#billing_email' ).attr( 'placeholder', 'Email' );
  }

  // Check checkbox checked
  function checkCheckbox() {
    j$( '.woocommerce-input-wrapper input[name="billing_vat"]' ).click( function() {
      if ( j$( this ).is( ':checked' ) ) {
        j$( '.validate-blling-vat input' ).addClass( 'vat-checked' );
        enableInput();
      } else {
        j$( '.validate-blling-vat input' ).removeClass( 'vat-checked' );
        disableInput();
      }
    } );
  }

  // Check is checkout page via URL
  if ( window.location.href.indexOf( 'checkout' ) > -1 ) {
    disableInput();
    checkCheckbox();
  }
  /* -------------------------------------------------------------- */
  function switchLanguage( newLang ) {
    const url = window.location.href;
    const urlParts = url.split( '/' );

    // Assuming the language part is the third segment (like 'en' in 'https://example.com/en/path')
    // Adjust the index as per the actual URL structure
    const langIndex = 3;

    if ( urlParts[ langIndex ] === 'en' ) {
      urlParts[ langIndex ] = newLang; // Change 'en' to the new language
    } else {
      // Insert the new language if it's not 'en'
      urlParts.splice( langIndex, 0, newLang );
    }

    // Construct the new URL and redirect
    const newUrl = urlParts.join( '/' );
    window.location.href = newUrl;
  }
  j$( '.saltlux-language-item-vn' ).on( 'click', function() {
    switchLanguage( '' );
  } );

  j$( '.saltlux-language-item-en' ).on( 'click', function() {
    switchLanguage( 'en' );
  } );

  /* -------------------------------------------------------------- */


  // Toggle class for contact element
  function toggleContactElement() {
    j$( '.wll-launcher-button-container.cursor-pointer' ).on( 'click', function() {
      j$( '#wp-nt-aio-wrapper' ).toggleClass( 'active-award' );
    });
  }

  /* -------------------------------------------------------------- */
} );

/* ── Reveal animation (IntersectionObserver for [data-reveal]) ── */
(function () {
  if (!('IntersectionObserver' in window)) {
    document.querySelectorAll('[data-reveal]').forEach(function (el) {
      el.classList.add('is-revealed');
    });
    return;
  }
  var observer = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (entry.isIntersecting) {
        entry.target.classList.add('is-revealed');
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.12 });

  function observe() {
    document.querySelectorAll('[data-reveal]').forEach(function (el) {
      observer.observe(el);
    });
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', observe);
  } else {
    observe();
  }
})();

/* ── product-home-gallery: hover overlay + click navigation ── */
(function () {
  var slides = (window.pghConfig && Array.isArray(window.pghConfig.slides))
    ? window.pghConfig.slides : [];

  function esc(str) {
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function getSlideData(slide) {
    var idx = parseInt(
      slide.dataset.pghIdx !== undefined
        ? slide.dataset.pghIdx
        : slide.dataset.swiperSlideIndex,
      10
    );
    return (!isNaN(idx) && slides[idx]) ? slides[idx] : {};
  }

  function addOverlay(slide) {
    if (slide.querySelector('.pgh-overlay')) return;
    var data = getSlideData(slide);
    var ov = document.createElement('div');
    ov.className = 'pgh-overlay';
    ov.innerHTML =
      '<div class="pgh-title">' + esc(data.label || '') + '</div>' +
      '<div class="pgh-action">' +
      '<span class="pgh-cta">Khám phá ngay</span>' +
      '<span class="pgh-plus">+</span>' +
      '</div>';
    slide.appendChild(ov);
  }

  function initGallery() {
    var slider = document.getElementById('product-home-gallery');
    if (!slider) return;

    /* Tag original slides with 0-based index before adding overlays */
    slider.querySelectorAll('.swiper-slide:not(.swiper-slide-duplicate)').forEach(function (slide, i) {
      slide.dataset.pghIdx = i;
      addOverlay(slide);
    });

    /* Clones already present (Swiper may init before this runs) */
    slider.querySelectorAll('.swiper-slide-duplicate').forEach(addOverlay);

    /* Watch for clones added later */
    var wrapper = slider.querySelector('.swiper-wrapper');
    if (wrapper) {
      new MutationObserver(function (mutations) {
        mutations.forEach(function (m) {
          m.addedNodes.forEach(function (node) {
            if (node.nodeType === 1 && node.classList.contains('swiper-slide')) {
              addOverlay(node);
            }
          });
        });
      }).observe(wrapper, { childList: true });
    }

    slider.addEventListener('click', function (e) {
      var slide = e.target.closest('.swiper-slide');
      if (!slide) return;
      var data = getSlideData(slide);
      if (!data.url) return;
      e.preventDefault();
      window.location.href = data.url;
    });
  }

  var _inited = false;
  function tryInit() {
    if (_inited) return;
    var slider = document.getElementById('product-home-gallery');
    if (!slider) return;
    /* Kiểm tra Swiper đã tạo slide chưa */
    if (!slider.querySelector('.swiper-slide')) return;
    _inited = true;
    initGallery();
  }

  /* Thử nhiều thời điểm để cover mọi tình huống Elementor init */
  document.addEventListener('DOMContentLoaded', tryInit);
  window.addEventListener('load', tryInit);
  [100, 300, 600, 1200].forEach(function(ms) { setTimeout(tryInit, ms); });
})();

/* ── product-home-gallery: mobile peek 1.2 items ── */
(function ($) {
  if (window.innerWidth > 767) return;

  var patched = false;

  function applyPeek(swiperEl) {
    if (patched || !swiperEl) return;
    var sw = swiperEl.swiper || ($ && $(swiperEl).data('swiper'));
    if (!sw) return;
    patched = true;
    sw.params.slidesPerView = 1.2;
    sw.params.slidesPerGroup = 1;
    sw.update();
  }

  function findSwiperEl() {
    var outer = document.getElementById('product-home-gallery');
    if (!outer) return null;
    if (outer.classList.contains('swiper') || outer.classList.contains('swiper-container')) return outer;
    return outer.querySelector('.elementor-main-swiper, .elementor-carousel-swiper, .swiper, .swiper-container');
  }

  function registerElementorHook() {
    if (!window.elementorFrontend) return false;
    elementorFrontend.hooks.addAction('frontend/element_ready/global', function ($scope) {
      var isTarget = $scope.attr('id') === 'product-home-gallery' ||
        $scope.find('#product-home-gallery').length > 0;
      if (!isTarget) return;
      var swiperEl = $scope.find('.elementor-main-swiper, .elementor-carousel-swiper, .swiper, .swiper-container')[0];
      setTimeout(function () { applyPeek(swiperEl); }, 50);
    });
    return true;
  }

  if (!registerElementorHook()) {
    $(window).on('elementor/frontend/init', registerElementorHook);
  }

  [300, 600, 1000, 1500, 2000, 3000].forEach(function (ms) {
    setTimeout(function () { applyPeek(findSwiperEl()); }, ms);
  });

}(window.jQuery));

/* ── home-prod-carousel: mobile peek 1.2 items ── */
(function ($) {
  if (window.innerWidth > 767) return;

  var patched = false;

  function applyPeek(swiperEl) {
    if (patched || !swiperEl) return;
    var sw = swiperEl.swiper || ($ && $(swiperEl).data('swiper'));
    if (!sw) return;
    patched = true;
    sw.params.slidesPerView = 1.2;
    sw.params.slidesPerGroup = 1;
    sw.update();
  }

  function findSwiperEl() {
    var outer = document.getElementById('home-prod-carousel');
    if (!outer) return null;
    if (outer.classList.contains('swiper') || outer.classList.contains('swiper-container')) return outer;
    return outer.querySelector('.elementor-main-swiper, .elementor-carousel-swiper, .swiper, .swiper-container');
  }

  function registerElementorHook() {
    if (!window.elementorFrontend) return false;
    elementorFrontend.hooks.addAction('frontend/element_ready/global', function ($scope) {
      var isTarget = $scope.attr('id') === 'home-prod-carousel' ||
        $scope.find('#home-prod-carousel').length > 0;
      if (!isTarget) return;
      var swiperEl = $scope.find('.elementor-main-swiper, .elementor-carousel-swiper, .swiper, .swiper-container')[0];
      setTimeout(function () { applyPeek(swiperEl); }, 50);
    });
    return true;
  }

  if (!registerElementorHook()) {
    $(window).on('elementor/frontend/init', registerElementorHook);
  }

  [300, 600, 1000, 1500, 2000, 3000].forEach(function (ms) {
    setTimeout(function () { applyPeek(findSwiperEl()); }, ms);
  });

}(window.jQuery));
