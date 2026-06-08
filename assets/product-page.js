jQuery(function ($) {

    // ─── Gallery: skeleton — fade in each item when image loads ─────────────
    $('.saltlux-gallery-item').each(function () {
        var $item = $(this);
        var img   = $item.find('.saltlux-gallery-img')[0];
        if (!img) { $item.addClass('is-loaded'); return; }
        function markLoaded() { $item.addClass('is-loaded'); }
        if (img.complete && img.naturalWidth > 0) {
            markLoaded();
        } else {
            $(img).one('load', markLoaded).one('error', markLoaded);
        }
    });

    // ─── Gallery: mobile dots indicator ─────────────────────────────────────
    (function () {
        var $gallery = $('.saltlux-product-gallery');
        if (!$gallery.length) return;
        var $items = $gallery.find('.saltlux-gallery-item');
        var count  = $items.length;
        if (count < 2) return;

        var $dots = $('<div class="sv2-gallery-dots"></div>');
        for (var i = 0; i < count; i++) {
            $dots.append('<span class="sv2-gallery-dot' + (i === 0 ? ' is-active' : '') + '" data-index="' + i + '"></span>');
        }
        $gallery.after($dots);

        var ticking = false;
        $gallery[0].addEventListener('scroll', function () {
            if (ticking) return;
            ticking = true;
            requestAnimationFrame(function () {
                var current = Math.round($gallery[0].scrollLeft / $gallery[0].offsetWidth);
                $dots.find('.sv2-gallery-dot').removeClass('is-active').eq(current).addClass('is-active');
                ticking = false;
            });
        }, { passive: true });

        $dots.on('click', '.sv2-gallery-dot', function () {
            var idx = parseInt($(this).data('index'), 10);
            $gallery[0].scrollTo({ left: idx * $gallery[0].offsetWidth, behavior: 'smooth' });
        });
    }());

    // ─── Gallery: Fancybox lightbox ──────────────────────────────────────────
    if (typeof $.fn.fancybox === 'function') {
        $().fancybox({
            selector : '.saltlux-gallery-link',
            backFocus: false,
            buttons  : ['zoom', 'close'],
        });
    }

    // --- Product detail accordion ---
    $(document).on('click', '.saltlux-accordion-toggle', function () {
        var $btn  = $(this);
        var $item = $btn.closest('.saltlux-accordion-item');
        var $body = $item.find('.saltlux-accordion-body');
        var open  = $btn.attr('aria-expanded') === 'true';

        // Close all siblings first
        $item.siblings('.saltlux-accordion-item').each(function () {
            $(this).removeClass('is-open')
                   .find('.saltlux-accordion-toggle').attr('aria-expanded', 'false');
            $(this).find('.saltlux-accordion-body').slideUp(180, function () {
                $(this).attr('hidden', true);
            });
        });

        if (open) {
            $item.removeClass('is-open');
            $btn.attr('aria-expanded', 'false');
            $body.slideUp(180, function () { $body.attr('hidden', true); });
        } else {
            $item.addClass('is-open');
            $btn.attr('aria-expanded', 'true');
            $body.removeAttr('hidden').hide().slideDown(200);
        }
    });

    // --- Dealer section toggle ---
    $(document).on('click', '.saltlux-dealer-toggle', function () {
        var $btn  = $(this);
        var $list = $btn.closest('.saltlux-dealer-section').find('.saltlux-dealer-list');
        var open  = $btn.attr('aria-expanded') === 'true';

        if (open) {
            $list.slideUp(200, function () { $list.attr('hidden', true); });
            $btn.attr('aria-expanded', 'false');
        } else {
            $list.removeAttr('hidden').hide().slideDown(200);
            $btn.attr('aria-expanded', 'true');
        }
    });

    // --- Color swatches ---
    // Click a swatch → sync hidden <select> → trigger WooCommerce variation JS
    $(document).on('click', '.sv2-swatch', function () {
        var $swatch    = $(this);
        var $container = $swatch.closest('.sv2-swatches');
        var attribute  = $container.data('attribute');
        var value      = $swatch.data('value');
        var name       = $swatch.data('name');

        // Toggle off if already active
        if ($swatch.hasClass('is-active')) {
            $swatch.removeClass('is-active').attr('aria-pressed', 'false');
            // Clear the hidden select
            var $sel = $container.siblings('.sv2-swatch-native').length
                ? $container.siblings('.sv2-swatch-native')
                : $container.closest('.value').find('.sv2-swatch-native');
            $sel.val('').trigger('change');
            sv2UpdateColorLabel($container, '');
            return;
        }

        // Activate this swatch
        $container.find('.sv2-swatch').removeClass('is-active').attr('aria-pressed', 'false');
        $swatch.addClass('is-active').attr('aria-pressed', 'true');

        // Sync hidden native <select>
        var $native = $container.siblings('.sv2-swatch-native').length
            ? $container.siblings('.sv2-swatch-native')
            : $container.closest('.value').find('.sv2-swatch-native');
        $native.val(value).trigger('change');

        // Update label
        sv2UpdateColorLabel($container, name);
    });

    // When WooCommerce resets variations ("Xoá lựa chọn"), clear active swatches
    $(document).on('click', '.reset_variations', function () {
        $(this).closest('form').find('.sv2-swatch').removeClass('is-active').attr('aria-pressed', 'false');
        $(this).closest('form').find('.sv2-color-chosen').text('');
    });

    // If page loads with a pre-selected variation (e.g. via ?attribute_pa_color=red URL)
    // sync the active swatch to match the select
    $('.sv2-swatches').each(function () {
        var $container = $(this);
        var $native    = $container.siblings('.sv2-swatch-native');
        var current    = $native.val();
        if (!current) return;
        var $match = $container.find('.sv2-swatch[data-value="' + current + '"]');
        if ($match.length) {
            $match.addClass('is-active').attr('aria-pressed', 'true');
            sv2UpdateColorLabel($container, $match.data('name'));
        }
    });

    function sv2UpdateColorLabel($swatchContainer, name) {
        // Find the <label> in the same table row and append / clear the chosen name
        var $row    = $swatchContainer.closest('tr');
        var $lbl    = $row.find('th.label label');
        var $chosen = $lbl.find('.sv2-color-chosen');
        if (!$chosen.length) {
            $chosen = $('<span class="sv2-color-chosen"></span>');
            $lbl.append($chosen);
        }
        $chosen.text(name ? ': ' + name : '');
    }

    // --- Smooth scroll to #reviews ---
    $(document).on('click', 'a[href="#reviews"], .sv2-rating-link', function (e) {
        var target = document.getElementById('reviews');
        if (!target) return;
        e.preventDefault();
        var offset = 80;
        var top = target.getBoundingClientRect().top + window.pageYOffset - offset;
        window.scrollTo({ top: top, behavior: 'smooth' });
    });

    // --- Variation image: replace gallery with all variant images ---
    (function () {
        var $gallery = $('.saltlux-product-gallery');
        if (!$gallery.length) return;

        var origHTML  = $gallery.html();
        var origCount = parseInt($gallery.attr('data-count'), 10) || 1;

        // fancybox group name comes from the first link's data-fancybox
        var fancyGroup = $gallery.find('.saltlux-gallery-link').first().data('fancybox') || ('saltlux-gallery-variant');

        function esc(str) {
            return String(str || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }

        function getDots() {
            return $('.sv2-gallery-dots');
        }

        function rebuildDots(count) {
            var $dots = getDots();
            if (count < 2) {
                $dots.hide();
                return;
            }
            var html = '';
            for (var i = 0; i < count; i++) {
                html += '<span class="sv2-gallery-dot' + (i === 0 ? ' is-active' : '') + '" data-index="' + i + '"></span>';
            }
            if ($dots.length) {
                $dots.html(html).show();
            } else {
                // dots container didn't exist (original had 1 image) — create it
                var $newDots = $('<div class="sv2-gallery-dots">' + html + '</div>');
                $gallery.after($newDots);
                $newDots.on('click', '.sv2-gallery-dot', function () {
                    var idx = parseInt($(this).data('index'), 10);
                    $gallery[0].scrollTo({ left: idx * $gallery[0].offsetWidth, behavior: 'smooth' });
                });
            }
        }

        function buildGalleryHTML(images) {
            var count = images.length;
            var html  = '';
            for (var i = 0; i < count; i++) {
                var img       = images[i];
                var isMain    = (i === 0);
                var isLastOdd = (count % 2 !== 0) && (i === count - 1);
                var cls       = 'saltlux-gallery-item is-loaded';
                if (isMain)    cls += ' saltlux-gallery-item--main';
                if (isLastOdd) cls += ' saltlux-gallery-item--last-odd';

                html += '<div class="' + cls + '">';
                html += '<a href="' + esc(img.full_src || img.src) + '" data-fancybox="' + esc(fancyGroup) + '" class="saltlux-gallery-link">';
                html += '<img src="' + esc(img.src) + '"';
                if (img.srcset) html += ' srcset="' + esc(img.srcset) + '"';
                if (img.sizes)  html += ' sizes="' + esc(img.sizes) + '"';
                html += ' alt="' + esc(img.alt) + '" class="saltlux-gallery-img" loading="' + (isMain ? 'eager' : 'lazy') + '">';
                html += '</a></div>';
            }
            return html;
        }

        function applyVariantGallery(variation) {
            var mainImg      = (variation && variation.image && variation.image.src) ? variation.image : null;
            var galleryImgs  = (variation && variation.gallery_images) ? variation.gallery_images : [];
            var allImages    = [];
            if (mainImg) allImages.push(mainImg);
            for (var i = 0; i < galleryImgs.length; i++) {
                if (galleryImgs[i] && galleryImgs[i].src) allImages.push(galleryImgs[i]);
            }
            if (!allImages.length) return;

            $gallery.html(buildGalleryHTML(allImages));
            $gallery.attr('data-count', allImages.length);
            $gallery[0].scrollLeft = 0;
            rebuildDots(allImages.length);

            if (typeof $.fn.fancybox === 'function') {
                $gallery.find('.saltlux-gallery-link').fancybox({ backFocus: false, buttons: ['zoom', 'close'] });
            }
        }

        function restoreGallery() {
            $gallery.html(origHTML);
            $gallery.attr('data-count', origCount);
            $gallery[0].scrollLeft = 0;

            var $dots = getDots();
            if (origCount > 1) {
                rebuildDots(origCount);
            } else {
                $dots.hide();
            }

            if (typeof $.fn.fancybox === 'function') {
                $gallery.find('.saltlux-gallery-link').fancybox({ backFocus: false, buttons: ['zoom', 'close'] });
            }
        }

        // Cache: tên attribute màu per-form (undefined = chưa tính, null = không có)
        var _imageAttr;

        /**
         * Tự động detect attribute nào drive ảnh khác nhau (= attribute màu).
         * Tiêu chí: có ≥2 giá trị khác nhau → ≥2 src ảnh khác nhau.
         * Kết quả được cache để không tính lại mỗi event.
         */
        function getImageAttr(variations) {
            if (_imageAttr !== undefined) return _imageAttr;

            var attrs = {};
            for (var i = 0; i < variations.length; i++) {
                for (var k in variations[i].attributes) {
                    if (variations[i].attributes.hasOwnProperty(k)) attrs[k] = true;
                }
            }

            _imageAttr = null; // fallback: không tìm thấy
            for (var attr in attrs) {
                if (!attrs.hasOwnProperty(attr)) continue;
                var valToImg = {};
                for (var i = 0; i < variations.length; i++) {
                    var val = variations[i].attributes[attr];
                    var src = variations[i].image && variations[i].image.src;
                    if (!val || !src) continue; // bỏ qua "any" ('') và variation không ảnh
                    valToImg[val] = src;
                }
                var uniqueImgs = {};
                for (var v in valToImg) {
                    if (valToImg.hasOwnProperty(v)) uniqueImgs[valToImg[v]] = true;
                }
                if (Object.keys(uniqueImgs).length > 1) {
                    _imageAttr = attr;
                    break;
                }
            }
            return _imageAttr;
        }

        /**
         * Gom tất cả ảnh (main + gallery_images) từ mọi variation cùng màu.
         * Dedup theo full_src để tránh trùng lặp.
         */
        function collectByColor(variations, imageAttr, colorValue) {
            var seen = {}, images = [];
            for (var i = 0; i < variations.length; i++) {
                var v   = variations[i];
                var val = v.attributes[imageAttr];
                if (val !== '' && val !== colorValue) continue; // '' = "any" → match hết

                // Ảnh chính của variation
                if (v.image && v.image.src) {
                    var key = v.image.full_src || v.image.src;
                    if (!seen[key]) { seen[key] = true; images.push(v.image); }
                }
                // Ảnh gallery phụ (từ plugin variation gallery)
                var extras = v.gallery_images || [];
                for (var j = 0; j < extras.length; j++) {
                    if (!extras[j] || !extras[j].src) continue;
                    var ekey = extras[j].full_src || extras[j].src;
                    if (!seen[ekey]) { seen[ekey] = true; images.push(extras[j]); }
                }
            }
            return images;
        }

        function renderColorGallery(images) {
            if (!images.length) return;
            applyVariantGallery({ image: images[0], gallery_images: images.slice(1) });
        }

        function bindVariationForm($form) {
            _imageAttr = undefined; // reset cache khi bind lại
            $form.off('found_variation.sv2 reset_data.sv2 change.sv2gallery');

            // ── change: chỉ react khi đúng attribute màu thay đổi ──
            $form.on('change.sv2gallery', 'select[name^="attribute_"]', function () {
                var variations = $form.data('product_variations');
                if (!variations || !variations.length) return; // AJAX mode → dùng found_variation

                var imageAttr = getImageAttr(variations);
                if (!imageAttr) return;
                if ($(this).attr('name') !== imageAttr) return; // size/attribute khác → bỏ qua

                var colorValue = $(this).val();
                if (!colorValue) { restoreGallery(); return; }

                renderColorGallery(collectByColor(variations, imageAttr, colorValue));
            });

            // ── found_variation: ưu tiên collect theo màu từ inline data ──
            // Nếu là AJAX mode (no inline data), fallback dùng images từ variation object
            $form.on('found_variation.sv2', function (e, variation) {
                if (!variation) return;
                var variations = $form.data('product_variations');

                if (variations && variations.length) {
                    // Inline mode: gom đủ ảnh cùng màu
                    var imageAttr = getImageAttr(variations);
                    if (!imageAttr) return;
                    var colorValue = $form.find('select[name="' + imageAttr + '"]').val();
                    if (!colorValue) return;
                    renderColorGallery(collectByColor(variations, imageAttr, colorValue));
                } else {
                    // AJAX mode (>30 variations): chỉ có data của variation hiện tại
                    var imgs = [];
                    var seen = {};
                    var addImg = function(img) {
                        if (!img || !img.src) return;
                        var k = img.full_src || img.src;
                        if (!seen[k]) { seen[k] = true; imgs.push(img); }
                    };
                    addImg(variation.image);
                    var extras = variation.gallery_images || [];
                    for (var j = 0; j < extras.length; j++) addImg(extras[j]);
                    if (imgs.length) renderColorGallery(imgs);
                }
            });

            $form.on('reset_data.sv2', function () {
                restoreGallery();
            });
        }

        var $form = $('form.variations_form');
        if ($form.length) bindVariationForm($form);

        $(document).on('wc_variation_form', function (e) {
            bindVariationForm($(e.target));
        });
    }());

});
