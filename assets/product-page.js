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

        function bindVariationForm($form) {
            $form.off('found_variation.sv2 reset_data.sv2 change.sv2gallery');

            $form.on('change.sv2gallery', 'select[name^="attribute_"]', function () {
                var variations = $form.data('product_variations');
                if (!variations || !variations.length) return;

                var chosen = {}, anySelected = false;
                $form.find('select[name^="attribute_"]').each(function () {
                    chosen[this.name] = this.value;
                    if (this.value) anySelected = true;
                });
                if (!anySelected) { restoreGallery(); return; }

                // Collect images from ALL variations that match chosen attributes.
                // A variation attribute of '' means "any value" — still a match.
                // This ensures selecting only color (without size) gathers every
                // size variant's images for that color into one gallery.
                var seenSrc = {}, allImages = [];
                for (var i = 0; i < variations.length; i++) {
                    var v = variations[i];
                    var ok = true;
                    for (var key in chosen) {
                        if (!chosen.hasOwnProperty(key) || !chosen[key]) continue;
                        if (v.attributes[key] !== '' && v.attributes[key] !== chosen[key]) {
                            ok = false; break;
                        }
                    }
                    if (!ok) continue;

                    // Main variation image
                    if (v.image && v.image.src && !seenSrc[v.image.src]) {
                        seenSrc[v.image.src] = true;
                        allImages.push(v.image);
                    }
                    // Extra gallery images
                    var extras = v.gallery_images || [];
                    for (var j = 0; j < extras.length; j++) {
                        if (extras[j] && extras[j].src && !seenSrc[extras[j].src]) {
                            seenSrc[extras[j].src] = true;
                            allImages.push(extras[j]);
                        }
                    }
                }

                if (allImages.length) {
                    applyVariantGallery({ image: allImages[0], gallery_images: allImages.slice(1) });
                }
            });

            $form.on('found_variation.sv2', function (e, variation) {
                if (!variation) return;
                applyVariantGallery(variation);
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
