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
        var fancyGroup = $gallery.find('.saltlux-gallery-link').first().data('fancybox') || 'sv2-gallery';

        /* ── helpers ─────────────────────────────────────────────────────── */

        function esc(s) {
            return String(s || '').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        }

        function getDots() { return $('.sv2-gallery-dots'); }

        function rebuildDots(count) {
            var $dots = getDots();
            if (count < 2) { $dots.hide(); return; }
            var html = '';
            for (var i = 0; i < count; i++) {
                html += '<span class="sv2-gallery-dot' + (i === 0 ? ' is-active' : '') + '" data-index="' + i + '"></span>';
            }
            if ($dots.length) {
                $dots.html(html).show();
            } else {
                var $nd = $('<div class="sv2-gallery-dots">' + html + '</div>');
                $gallery.after($nd);
                $nd.on('click', '.sv2-gallery-dot', function () {
                    $gallery[0].scrollTo({ left: parseInt($(this).data('index'),10) * $gallery[0].offsetWidth, behavior: 'smooth' });
                });
            }
        }

        /**
         * Thêm class is-loaded khi ảnh trong mỗi item thực sự load xong.
         * Giống logic skeleton ở đầu file — dùng chung cho cả gallery gốc
         * lẫn gallery được xây dựng động bởi variant.
         */
        function markItemsLoaded($container) {
            $container.find('.saltlux-gallery-item').each(function () {
                var $item = $(this);
                if ($item.hasClass('is-loaded')) return; // đã xử lý rồi
                var img = $item.find('.saltlux-gallery-img')[0];
                if (!img) { $item.addClass('is-loaded'); return; }
                if (img.complete && img.naturalWidth > 0) {
                    $item.addClass('is-loaded');
                } else {
                    $(img).one('load error', function () { $item.addClass('is-loaded'); });
                }
            });
        }

        /**
         * Build HTML gallery items.
         * KHÔNG thêm is-loaded vào class — để markItemsLoaded xử lý sau
         * khi ảnh thực sự load xong (tránh skeleton bị xóa trước khi ảnh hiện).
         */
        function buildGalleryHTML(images) {
            var count = images.length, html = '';
            for (var i = 0; i < count; i++) {
                var img       = images[i];
                var isMain    = (i === 0);
                var isLastOdd = (count % 2 !== 0) && (i === count - 1);
                var cls       = 'saltlux-gallery-item';
                if (isMain)    cls += ' saltlux-gallery-item--main';
                if (isLastOdd) cls += ' saltlux-gallery-item--last-odd';

                html += '<div class="' + cls + '">';
                html += '<a href="' + esc(img.full_src || img.src) + '" data-fancybox="' + esc(fancyGroup) + '" class="saltlux-gallery-link">';
                html += '<img src="' + esc(img.src) + '"';
                if (img.srcset) html += ' srcset="' + esc(img.srcset) + '"';
                if (img.sizes)  html += ' sizes="'  + esc(img.sizes)  + '"';
                html += ' alt="' + esc(img.alt) + '" class="saltlux-gallery-img"';
                html += ' loading="' + (isMain ? 'eager' : 'lazy') + '">';
                html += '</a></div>';
            }
            return html;
        }

        function applyVariantGallery(imgs) {
            if (!imgs || !imgs.length) return;
            $gallery.html(buildGalleryHTML(imgs));
            $gallery.attr('data-count', imgs.length);
            $gallery[0].scrollLeft = 0;
            rebuildDots(imgs.length);
            markItemsLoaded($gallery); // skeleton đúng chuẩn cho ảnh mới
        }

        function restoreGallery() {
            $gallery.html(origHTML);
            $gallery.attr('data-count', origCount);
            $gallery[0].scrollLeft = 0;
            if (origCount > 1) rebuildDots(origCount);
            else getDots().hide();
            // Ảnh gốc đã được cache — thêm is-loaded ngay để tránh skeleton thừa
            $gallery.find('.saltlux-gallery-item').addClass('is-loaded');
        }

        /* ── detect color attribute ────────────────────────────────────────
         *
         * Dùng "consistency check" thay vì overwrite:
         * - Build valToSrcs[value] = SET of unique srcs across all variations.
         * - Attribute MÀU: mỗi value → đúng 1 src (consistent) VÀ ≥2 value
         *   có src khác nhau.
         *
         * Tại sao: overwrite (valToImg[v] = src) gây lỗi khi variations được
         * lưu xen kẽ (Trắng-O, Xanh-O, Trắng-L, Xanh-L ...). Size 'o' bị
         * ghi đè bởi Xanh Lá (green) còn size 'm' bị ghi đè bởi Trắng
         * (white) → SIZE có vẻ như có ≥2 ảnh khác nhau → bị nhầm là color.
         * Consistency check phát hiện size 'o' map đến cả white lẫn green
         * → loại SIZE khỏi candidates.
         */
        var _imageAttr; // undefined = chưa tính, null = không tìm thấy

        function getImageAttr(variations) {
            if (_imageAttr !== undefined) return _imageAttr;
            _imageAttr = null;

            var attrs = {};
            for (var i = 0; i < variations.length; i++) {
                for (var k in variations[i].attributes) {
                    if (variations[i].attributes.hasOwnProperty(k)) attrs[k] = true;
                }
            }

            for (var attr in attrs) {
                if (!attrs.hasOwnProperty(attr)) continue;

                var valToSrcs = {};
                for (var i = 0; i < variations.length; i++) {
                    var val = variations[i].attributes[attr];
                    var src = variations[i].image && variations[i].image.src;
                    if (!val || !src) continue; // '' = "any value" → skip
                    if (!valToSrcs[val]) valToSrcs[val] = {};
                    valToSrcs[val][src] = true;
                }

                var ok = true, globalSrcs = {};
                for (var v in valToSrcs) {
                    if (!valToSrcs.hasOwnProperty(v)) continue;
                    var srcs = Object.keys(valToSrcs[v]);
                    if (srcs.length !== 1) { ok = false; break; } // value này có nhiều ảnh → không phải color
                    globalSrcs[srcs[0]] = true;
                }
                if (ok && Object.keys(globalSrcs).length > 1) {
                    _imageAttr = attr; break;
                }
            }
            return _imageAttr;
        }

        /* ── collect images for a color value ─────────────────────────────── */

        /**
         * Thu thập ảnh main + gallery_images cho một màu sắc cụ thể.
         *
         * Phân biệt hai loại variation:
         *  - Exact match (val === colorValue): lấy cả main image lẫn gallery_images
         *  - "Any" match (val === ''): CHỈ lấy main image, KHÔNG lấy gallery_images
         *
         * Lý do: gallery_images của "any color" variation có thể là ảnh của MỘT màu
         * cụ thể (ví dụ Trắng O được admin đặt thành "any color, size O") và nếu lấy
         * sẽ xuất hiện cho tất cả màu → lẫn ảnh. Main image của "any" variation
         * thường vô hại (là ảnh đại diện chung) nên vẫn được phép.
         */
        function collectByColor(variations, imageAttr, colorValue) {
            var seen = {}, images = [];
            for (var i = 0; i < variations.length; i++) {
                var v       = variations[i];
                var val     = v.attributes[imageAttr];
                var isExact = (val === colorValue);
                var isAny   = (val === '');

                if (!isExact && !isAny) continue; // khác màu → bỏ qua

                // Main image: lấy từ cả exact lẫn "any"
                if (v.image && v.image.src) {
                    var k = v.image.full_src || v.image.src;
                    if (!seen[k]) { seen[k] = true; images.push(v.image); }
                }

                // Gallery images: CHỈ lấy từ exact-color variation
                if (!isExact) continue;

                var extras = v.gallery_images || [];
                for (var j = 0; j < extras.length; j++) {
                    if (!extras[j] || !extras[j].src) continue;
                    var ek = extras[j].full_src || extras[j].src;
                    if (!seen[ek]) { seen[ek] = true; images.push(extras[j]); }
                }
            }
            return images;
        }

        /* ── form binding ──────────────────────────────────────────────────── */

        function bindVariationForm($form) {
            _imageAttr = undefined;
            $form.off('found_variation.sv2 reset_data.sv2 change.sv2gallery');

            $form.on('change.sv2gallery', 'select[name^="attribute_"]', function () {
                var variations = $form.data('product_variations');
                if (!variations || !variations.length) return;

                var imageAttr = getImageAttr(variations);
                if (!imageAttr) return;
                if ($(this).attr('name') !== imageAttr) return; // size → bỏ qua

                var colorVal = $(this).val();
                if (!colorVal) { restoreGallery(); return; }

                var imgs = collectByColor(variations, imageAttr, colorVal);
                if (imgs.length) applyVariantGallery(imgs);
            });

            $form.on('found_variation.sv2', function (e, variation) {
                if (!variation) return;
                var variations = $form.data('product_variations');

                if (variations && variations.length) {
                    // Inline mode: gom theo màu
                    var imageAttr = getImageAttr(variations);
                    if (!imageAttr) return;
                    var colorVal = $form.find('select[name="' + imageAttr + '"]').val();
                    if (!colorVal) return;
                    var imgs = collectByColor(variations, imageAttr, colorVal);
                    if (imgs.length) applyVariantGallery(imgs);
                } else {
                    // AJAX mode (>30 variations): dùng data của variation hiện tại
                    var imgs = [], seen = {};
                    var add = function (img) {
                        if (!img || !img.src) return;
                        var k = img.full_src || img.src;
                        if (!seen[k]) { seen[k] = true; imgs.push(img); }
                    };
                    add(variation.image);
                    var extras = variation.gallery_images || [];
                    for (var j = 0; j < extras.length; j++) add(extras[j]);
                    if (imgs.length) applyVariantGallery(imgs);
                }
            });

            $form.on('reset_data.sv2', function () { restoreGallery(); });
        }

        var $form = $('form.variations_form');
        if ($form.length) bindVariationForm($form);
        $(document).on('wc_variation_form', function (e) { bindVariationForm($(e.target)); });
    }());

});
