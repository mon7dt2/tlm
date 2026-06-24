<?php

/**
 * Product page enhancements
 *
 * DOM order (after_summary hooks): summary → gallery(@1) → dots(JS) → pairs(@15) → reviews(@25)
 *                                   → wc-tabs(@27) → related(@28) → services(@30)
 * Mobile: correct visual order follows DOM naturally — no CSS reordering needed.
 * Desktop: CSS Grid places gallery col-1 row-1, summary col-2 row-1 regardless of DOM order.
 */

// ---------------------------------------------------------------------------
// Helper — is this product in the "Quần áo" category (ID 101) or any child?
// ---------------------------------------------------------------------------
function sv2_is_clothing_product( $product_id = null ) {
    if ( ! $product_id ) {
        global $product;
        if ( ! $product ) return false;
        $product_id = $product->get_id();
    }
    $root = get_term_by( 'slug', 'quan-ao', 'product_cat' );
    if ( ! $root ) return false;
    $children = get_term_children( $root->term_id, 'product_cat' );
    $terms    = array_merge( array( $root->term_id ), is_array( $children ) ? $children : array() );
    return has_term( $terms, 'product_cat', $product_id );
}

// ---------------------------------------------------------------------------
// Enqueue
// ---------------------------------------------------------------------------
add_action( 'wp_enqueue_scripts', 'sv2_enqueue_product_page_script' );
function sv2_enqueue_product_page_script() {
    if ( is_product() ) {
        wp_enqueue_script(
            'saltlux-product-page',
            get_stylesheet_directory_uri() . '/assets/product-page.js',
            array( 'jquery' ),
            wp_get_theme()->get( 'Version' ),
            true
        );
    }
}

// WooCommerce 9.x dùng wp.hooks + wp.template bên trong add-to-cart-variation.min.js
// nhưng không khai báo đúng dependency → TypeError khi script load trước wp-util/wp-hooks.
// Tìm handle bằng URL pattern (robust hơn đoán tên), patch deps, và inject polyfill
// trực tiếp trước script tag để đảm bảo window.wp.template luôn tồn tại khi cần.
add_action( 'wp_enqueue_scripts', 'sv2_fix_wc_variation_wp_deps', 999 );
function sv2_fix_wc_variation_wp_deps() {
    if ( ! is_product() ) return;

    wp_enqueue_script( 'wp-hooks' );
    wp_enqueue_script( 'wp-util' );

    // Polyfill tối giản: đảm bảo wp.template không undefined.
    // wp-util (nếu load đúng thứ tự) sẽ overwrite bằng implementation thật.
    $polyfill = 'window.wp=window.wp||{};'
              . 'window.wp.template=window.wp.template||function(id){'
              .   'return function(data){'
              .     'try{'
              .       'var el=document.getElementById("tmpl-"+id);'
              .       'if(el&&typeof _!=="undefined")'
              .         'return _.template(el.innerHTML,{variable:"data",evaluate:/<#([\\s\\S]+?)#>/g,interpolate:/\\{\\{\\{([\\s\\S]+?)\\}\\}\\}/g,escape:/\\{\\{([^}]+)\\}\\}/g})(data);'
              .     '}catch(e){}'
              .     'return "";'
              .   '};'
              . '};';

    global $wp_scripts;

    // Tìm script handle bằng URL pattern — không đoán tên handle
    $var_handle = null;
    foreach ( $wp_scripts->registered as $h => $s ) {
        if ( strpos( $s->src, 'add-to-cart-variation' ) !== false ) {
            $var_handle = $h;
            break;
        }
    }

    if ( $var_handle ) {
        // Patch deps để WordPress output wp-hooks + wp-util TRƯỚC script này
        foreach ( array( 'wp-hooks', 'wp-util' ) as $dep ) {
            if ( ! in_array( $dep, $wp_scripts->registered[ $var_handle ]->deps, true ) ) {
                $wp_scripts->registered[ $var_handle ]->deps[] = $dep;
            }
        }
        // Inject polyfill ngay trước script tag — belt-and-suspenders
        wp_add_inline_script( $var_handle, $polyfill, 'before' );
    } else {
        // Handle không tìm thấy → inject sớm sau jQuery
        wp_add_inline_script( 'jquery-core', $polyfill, 'after' );
    }
}

// ---------------------------------------------------------------------------
// Sắp xếp size theo thứ tự chuẩn quần áo: XS S M L XL XXL O
// Hook 1 – woocommerce_get_product_terms: cover tất cả swatch plugin dùng wc_get_product_terms()
// Hook 2 – woocommerce_dropdown_variation_attribute_options_args: fallback cho select native
// ---------------------------------------------------------------------------

/** Map tên size → thứ tự sort (so sánh case-insensitive). */
function sv2_size_order() {
    return array(
        'XS'       => 0, 'S'  => 1, 'M'  => 2,
        'L'        => 3, 'XL' => 4, 'XXL' => 5,
        '2XL'      => 6, '3XL' => 7,
        'O'        => 8, 'OS' => 9, 'ONE SIZE' => 10,
    );
}

// Hook vào wc_get_product_terms() — được hầu hết swatch plugin gọi
add_filter( 'woocommerce_get_product_terms', 'sv2_sort_product_size_terms', 10, 4 );
function sv2_sort_product_size_terms( $terms, $product_id, $taxonomy, $args ) {
    if ( empty( $terms ) || ! is_array( $terms ) || ! ( $terms[0] instanceof WP_Term ) ) {
        return $terms;
    }
    $order = sv2_size_order();
    // Chỉ sort nếu ít nhất 1 term nhận diện được là size
    $has_size = false;
    foreach ( $terms as $term ) {
        if ( isset( $order[ strtoupper( trim( $term->name ) ) ] ) ) {
            $has_size = true;
            break;
        }
    }
    if ( ! $has_size ) return $terms;

    usort( $terms, function ( $a, $b ) use ( $order ) {
        $ao = $order[ strtoupper( trim( $a->name ) ) ] ?? 999;
        $bo = $order[ strtoupper( trim( $b->name ) ) ] ?? 999;
        return $ao - $bo;
    } );
    return $terms;
}

add_filter( 'woocommerce_dropdown_variation_attribute_options_args', 'sv2_sort_size_options', 20 );
function sv2_sort_size_options( $args ) {
    if ( empty( $args['options'] ) ) return $args;

    $order = sv2_size_order();

    // options có thể là: array of WP_Term | array of slugs (string) | array of names (string)
    // Chuẩn hoá về dạng [ index => label_for_sort ]
    $labels = array();
    foreach ( $args['options'] as $idx => $opt ) {
        if ( $opt instanceof WP_Term ) {
            $labels[ $idx ] = strtoupper( trim( $opt->name ) );
        } else {
            // Nếu là slug/name dạng string: thử dùng trực tiếp, hoặc resolve qua terms
            $labels[ $idx ] = strtoupper( trim( (string) $opt ) );
        }
    }

    // Nếu product + attribute có sẵn, resolve slug → term name cho chính xác hơn
    if ( ! empty( $args['product'] ) && ! empty( $args['attribute'] ) ) {
        $terms = wc_get_product_terms(
            $args['product']->get_id(),
            $args['attribute'],
            array( 'fields' => 'all' )
        );
        if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
            $slug_to_name = array();
            foreach ( $terms as $term ) {
                $slug_to_name[ $term->slug ] = strtoupper( trim( $term->name ) );
            }
            foreach ( $labels as $idx => $lbl ) {
                $opt = $args['options'][ $idx ];
                $slug = $opt instanceof WP_Term ? $opt->slug : (string) $opt;
                if ( isset( $slug_to_name[ $slug ] ) ) {
                    $labels[ $idx ] = $slug_to_name[ $slug ];
                }
            }
        }
    }

    // Chỉ sort nếu ít nhất 1 option nhận diện được là size (không ép sort màu sắc)
    $has_known = false;
    foreach ( $labels as $lbl ) {
        if ( isset( $order[ $lbl ] ) ) { $has_known = true; break; }
    }
    if ( ! $has_known ) return $args;

    // Sort theo order map; unknown sizes đẩy về cuối theo alpha
    $options = $args['options'];
    array_multisort(
        array_map( function( $lbl ) use ( $order ) {
            return isset( $order[ $lbl ] ) ? $order[ $lbl ] : 999;
        }, $labels ),
        SORT_NUMERIC,
        $labels,
        SORT_STRING,
        $options
    );
    $args['options'] = $options;

    return $args;
}

// ---------------------------------------------------------------------------
// Helper: tìm gallery IDs của variation từ bất kỳ plugin nào
// ---------------------------------------------------------------------------
function sv2_get_variation_gallery_ids( $var_id, $variation ) {

    // 1. WC native (variation->get_gallery_image_ids đọc _product_image_gallery)
    $ids = $variation->get_gallery_image_ids();
    if ( ! empty( $ids ) ) return $ids;

    // 2. Meta key cụ thể của các plugin gallery phổ biến
    $known_keys = array(
        'woo_variation_gallery_images',        // WooCommerce Variation Gallery (ThemeComplete / ILLID)
        '_product_image_gallery',              // WC native nếu plugin ghi trực tiếp
        'variation_image_gallery',             // Additional Variation Images (nhiều plugin)
        '_pwwg_gallery_images',                // Pimwick
        '_wc_additional_variation_images',     // Iconic / generic
        'yith_wc_variation_gallery_images',    // YITH
        'wcvi_image_gallery',                  // WooCommerce Variation Images
        'codeixer_variation_gallery',          // CodeIxer swatch
        '_additional_variation_images',
        'variation_gallery_images',
        '_wgv_image_ids',                      // WooCommerce Product Variation Gallery
        'raq_product_image_ids',               // ThemeIsle variant
        '_raq_product_image_ids',
        'polycon_gallery_ids',                 // PolyGon Variation Image Gallery
        'wvg_gallery',                         // WooCommerce Variation Gallery Pro
        'mv_variation_images',                 // Meow Variation
        '_av_gallery_images',
    );

    foreach ( $known_keys as $key ) {
        $raw = get_post_meta( $var_id, $key, true );
        if ( empty( $raw ) ) continue;
        $ids = is_array( $raw )
            ? array_values( array_filter( array_map( 'intval', $raw ) ) )
            : array_values( array_filter( array_map( 'intval', explode( ',', (string) $raw ) ) ) );
        if ( ! empty( $ids ) && min( $ids ) > 0 ) return $ids;
    }

    // 3. Auto-detect toàn diện: quét TẤT CẢ meta key (không lọc theo keyword).
    //    Điều kiện xác nhận: giá trị là mảng / CSV của các positive integer VÀ
    //    phần tử đầu tiên phải là attachment ảnh hợp lệ (wp_attachment_is_image).
    //    Điều này loại bỏ false-positive như product_pairs_with (product IDs sẽ
    //    fail wp_attachment_is_image vì không phải attachment với MIME image).
    //
    //    Các meta key WC system được skip để tiết kiệm thời gian.
    //    Các meta key kết thúc bằng số ID của variation KHÁC (e.g. _gallery_20162
    //    trên variation #20166) → bỏ qua, đây là dữ liệu lưu chéo của plugin.
    $skip_keys = array(
        '_sku', '_price', '_regular_price', '_sale_price', '_stock',
        '_manage_stock', '_backorders', '_weight', '_length', '_width', '_height',
        '_virtual', '_downloadable', '_tax_class', '_thumbnail_id',
        '_variation_description', '_edit_lock', '_edit_last',
        '_wp_attachment_metadata', '_wp_attached_file',
    );

    foreach ( get_post_meta( $var_id ) as $meta_key => $meta_arr ) {
        if ( strpos( $meta_key, 'attribute_' ) === 0 ) continue;
        if ( in_array( $meta_key, $skip_keys, true ) ) continue;

        // Bỏ qua key có dạng *_<id> nếu <id> là ID của variation KHÁC
        // Ví dụ: _gallery_20162 trên variation #20166 → dữ liệu chéo của plugin
        if ( preg_match( '/[_\-](\d{4,})$/', $meta_key, $id_m )
             && (int) $id_m[1] !== $var_id ) {
            continue;
        }

        $raw = maybe_unserialize( $meta_arr[0] ?? '' );

        if ( is_array( $raw ) && count( $raw ) >= 1 ) {
            $candidate = array_values( array_filter( array_map( 'intval', $raw ) ) );
            // Mảng thuần integer dương, và tất cả phần tử đều convert được → không lẫn string
            if ( count( $candidate ) === count( $raw )
                && min( $candidate ) > 0
                && wp_attachment_is_image( $candidate[0] ) ) {
                return $candidate;
            }
        } elseif ( is_string( $raw )
            && preg_match( '/^\d+(?:,\s*\d+)+$/', trim( $raw ) ) ) {
            $candidate = array_values( array_filter( array_map( 'intval', explode( ',', $raw ) ) ) );
            if ( ! empty( $candidate ) && min( $candidate ) > 0
                && wp_attachment_is_image( $candidate[0] ) ) {
                return $candidate;
            }
        }
    }

    return array();
}

// ---------------------------------------------------------------------------
// Thêm gallery_images vào dữ liệu variation (WC inline JS + AJAX fallback)
// ---------------------------------------------------------------------------
add_filter( 'woocommerce_available_variation', function( $data, $product, $variation ) {

    // ── 1. Override ảnh chính sang size 'large' (WC mặc định dùng thumbnail nhỏ) ──
    $main_id = $variation->get_image_id();
    if ( $main_id ) {
        $large = wp_get_attachment_image_src( $main_id, 'large' );
        $full  = wp_get_attachment_image_src( $main_id, 'full' );
        if ( $large ) {
            $data['image']['src']    = $large[0];
            $data['image']['srcset'] = (string) ( wp_get_attachment_image_srcset( $main_id, 'large' ) ?: '' );
            $data['image']['sizes']  = (string) ( wp_get_attachment_image_sizes( $main_id, 'large' ) ?: '' );
        }
        if ( $full ) {
            $data['image']['full_src'] = $full[0];
        }
    }

    // ── 2. Gallery ảnh phụ: dùng helper auto-detect ──
    $var_id = $variation->get_id();
    $ids    = sv2_get_variation_gallery_ids( $var_id, $variation );

    $gallery = array();
    foreach ( $ids as $id ) {
        $id = (int) $id;
        if ( ! $id ) continue;
        $large = wp_get_attachment_image_src( $id, 'large' );
        $full  = wp_get_attachment_image_src( $id, 'full' );
        if ( ! $large ) continue;
        $gallery[] = array(
            'src'      => $large[0],
            'srcset'   => (string) ( wp_get_attachment_image_srcset( $id, 'large' ) ?: '' ),
            'sizes'    => (string) ( wp_get_attachment_image_sizes( $id, 'large' ) ?: '' ),
            'alt'      => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
            'full_src' => $full ? $full[0] : $large[0],
        );
    }
    $data['gallery_images'] = $gallery;

    // DEBUG (xoá sau khi xác định meta key): liệt kê tất cả meta key của variation
    // Xem trong DevTools → Network → ?wc-ajax=get_variation hoặc trong page source
    // tìm "product_variations" rồi xem trường _sv2_meta_keys của từng variation.
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        $all_keys = array_keys( get_post_meta( $var_id ) );
        sort( $all_keys );
        $data['_sv2_meta_keys'] = $all_keys;
    }

    return $data;
}, 10, 3 );

// ---------------------------------------------------------------------------
// 1. Gallery grid — after_summary @1 so it appears below summary in DOM
//    (mobile: natural flex order; desktop: CSS grid places it column 1)
//    Fancybox lightbox still works (plugin scripts remain enqueued).
// ---------------------------------------------------------------------------
add_filter( 'wpgs_default_gallery_hook', '__return_false' );

// Move WC default tabs from @10 to @27 so they appear after reviews in DOM
remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_product_data_tabs', 10 );
add_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_product_data_tabs', 27 );

// Mobile breadcrumb clone — sits before gallery in DOM (@0), visible only on mobile.
// Original breadcrumb inside .summary.entry-summary is hidden via CSS on mobile.
add_action( 'woocommerce_after_single_product_summary', function () {
    echo '<div class="sv2-breadcrumb-mobile">';
    woocommerce_breadcrumb();
    echo '</div>';
}, 0 );

add_action( 'woocommerce_after_single_product_summary', 'sv2_gallery_grid', 1 );
function sv2_gallery_grid() {
    global $product;
    if ( ! $product ) return;

    $main_id     = $product->get_image_id();
    $gallery_ids = $product->get_gallery_image_ids();

    $all_ids = array();
    if ( $main_id ) $all_ids[] = (int) $main_id;
    foreach ( $gallery_ids as $id ) $all_ids[] = (int) $id;

    if ( empty( $all_ids ) ) {
        echo '<div class="saltlux-product-gallery saltlux-product-gallery--empty">'
            . wc_placeholder_img( 'woocommerce_single' )
            . '</div>';
        return;
    }

    $count = count( $all_ids );
    $group = 'saltlux-gallery-' . $product->get_id();
    ?>
    <div class="saltlux-product-gallery" data-count="<?php echo $count; ?>">
        <?php foreach ( $all_ids as $i => $attachment_id ) :
            $full_url    = wp_get_attachment_image_url( $attachment_id, 'full' );
            $alt         = trim( wp_strip_all_tags( get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ) );
            $is_main     = ( $i === 0 );
            $is_last_odd = ( $count % 2 !== 0 ) && ( $i === $count - 1 );
            $classes     = 'saltlux-gallery-item';
            if ( $is_main )     $classes .= ' saltlux-gallery-item--main';
            if ( $is_last_odd ) $classes .= ' saltlux-gallery-item--last-odd';
        ?>
        <div class="<?php echo esc_attr( $classes ); ?>">
            <a href="<?php echo esc_url( $full_url ); ?>"
               data-fancybox="<?php echo esc_attr( $group ); ?>"
               class="saltlux-gallery-link">
                <?php echo wp_get_attachment_image( $attachment_id, 'large', false, array(
                    'alt'           => $alt,
                    'class'         => 'saltlux-gallery-img',
                    'loading'       => $is_main ? 'eager' : 'lazy',
                    'fetchpriority' => $is_main ? 'high' : false,
                    'decoding'      => 'async',
                ) ); ?>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php
}

// Move SKU (meta) above product title in Astra's single product structure.
add_filter( 'astra_woo_single_product_structure', function( $structure ) {
    $structure = array_values( array_diff( $structure, array( 'meta' ) ) );
    $pos       = array_search( 'title', $structure );
    array_splice( $structure, false !== $pos ? $pos : 0, 0, array( 'meta' ) );
    return $structure;
} );

// ---------------------------------------------------------------------------
// 1. Accordions — Mô tả · Chi tiết · Vải & CN · Chăm sóc · Mua tại đại lý
// ---------------------------------------------------------------------------
remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20 );
remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40 );

add_action( 'woocommerce_single_product_summary', 'sv2_product_accordions', 40 );
function sv2_product_accordions() {
    global $product;
    if ( ! $product ) return;

    $pid         = $product->get_id();
    $is_clothing = sv2_is_clothing_product( $pid );

    // Text sections — each entry renders as one accordion panel
    $sections = array();
    $dealers  = array();

    $acf = function( $key ) use ( $pid ) {
        return function_exists( 'get_field' )
            ? get_field( $key, $pid )
            : get_post_meta( $pid, $key, true );
    };

    foreach ( array(
        'product_details'     => __( 'Chi tiết', 'saltlux' ),
        'product_fabric_tech' => __( 'Vải & công nghệ', 'saltlux' ),
        'product_care'        => __( 'Thông tin chăm sóc / giặt', 'saltlux' ),
    ) as $field => $label ) {
        $value = $acf( $field );
        if ( $value ) {
            $sections[] = array( 'title' => $label, 'content' => $value );
        }
    }

    // Dealer rows — stored as ACF repeater postmeta
    $dealer_count = (int) get_post_meta( $pid, 'product_dealers', true );
    for ( $i = 0; $i < $dealer_count; $i++ ) {
        $name = get_post_meta( $pid, "product_dealers_{$i}_dealer_name", true );
        if ( ! $name ) continue;
        $dealers[] = array(
            'name'  => $name,
            'phone' => get_post_meta( $pid, "product_dealers_{$i}_dealer_phone", true ),
            'link'  => get_post_meta( $pid, "product_dealers_{$i}_dealer_link",  true ),
        );
    }

    // "Kết hợp tốt với" — all products
    if ( function_exists( 'get_field' ) ) {
        $raw   = get_field( 'product_pairs_with', $pid );
        $pairs = ! empty( $raw )
            ? array_map( function( $p ) { return is_object( $p ) ? $p : get_post( $p ); }, (array) $raw )
            : array();
    } else {
        $raw   = get_post_meta( $pid, 'product_pairs_with', true );
        $pairs = array();
        foreach ( (array) $raw as $id ) {
            $post = get_post( (int) $id );
            if ( $post ) $pairs[] = $post;
        }
    }
    $pairs_html = '';
    if ( ! empty( $pairs ) ) {
        ob_start();
        echo '<ul class="products columns-4 sv2-pairs-list">';
        foreach ( $pairs as $pair ) {
            $GLOBALS['post'] = $pair;
            setup_postdata( $GLOBALS['post'] );
            wc_get_template_part( 'content', 'product' );
        }
        wp_reset_postdata();
        echo '</ul>';
        $pairs_html = ob_get_clean();
    }

    if ( empty( $sections ) && empty( $dealers ) && ! $pairs_html ) return;

    $chevron = '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
         fill="none" stroke="currentColor" stroke-width="2.5"
         stroke-linecap="round" stroke-linejoin="round"
         class="saltlux-accordion-icon" aria-hidden="true">
        <polyline points="6 9 12 15 18 9"></polyline>
    </svg>';
    ?>
    <div class="saltlux-product-accordions">

        <?php foreach ( $sections as $idx => $section ) :
            $open = ( $idx === 0 );
        ?>
        <div class="saltlux-accordion-item<?php echo $open ? ' is-open' : ''; ?>">
            <button class="saltlux-accordion-toggle" aria-expanded="<?php echo $open ? 'true' : 'false'; ?>">
                <span><?php echo esc_html( $section['title'] ); ?></span>
                <?php echo $chevron; ?>
            </button>
            <div class="saltlux-accordion-body"<?php echo $open ? '' : ' hidden'; ?>>
                <div class="saltlux-accordion-content">
                    <?php echo wp_kses_post( $section['content'] ); ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if ( $dealers ) : ?>
        <div class="saltlux-accordion-item">
            <button class="saltlux-accordion-toggle" aria-expanded="false">
                <span><?php esc_html_e( 'Mua tại đại lý', 'saltlux' ); ?></span>
                <?php echo $chevron; ?>
            </button>
            <div class="saltlux-accordion-body" hidden>
                <div class="saltlux-accordion-content saltlux-dealer-accordion">
                    <ul class="saltlux-dealer-others">
                        <?php foreach ( $dealers as $d ) : ?>
                        <li class="saltlux-dealer-others-item">
                            <div class="saltlux-dealer-others-info">
                                <strong><?php echo esc_html( $d['name'] ); ?></strong>
                                <?php if ( $d['phone'] ) : ?>
                                    <a href="tel:<?php echo esc_attr( preg_replace( '/\D/', '', $d['phone'] ) ); ?>">
                                        <?php echo esc_html( $d['phone'] ); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                            <?php if ( $d['link'] ) : ?>
                                <a href="<?php echo esc_url( $d['link'] ); ?>"
                                   target="_blank" rel="noopener"
                                   class="saltlux-dealer-others-link">
                                    <?php esc_html_e( 'Mua tại đây', 'saltlux' ); ?> &rarr;
                                </a>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ( $pairs_html ) : ?>
        <div class="saltlux-accordion-item">
            <button class="saltlux-accordion-toggle" aria-expanded="false">
                <span><?php esc_html_e( 'Kết hợp tốt với…', 'saltlux' ); ?></span>
                <?php echo $chevron; ?>
            </button>
            <div class="saltlux-accordion-body" hidden>
                <div class="saltlux-accordion-content sv2-pairs-accordion-content">
                    <?php echo $pairs_html; // phpcs:ignore WordPress.Security.EscapeOutput ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>
    <?php
}

// ---------------------------------------------------------------------------
// 1b. TryMyLook — button block rendered after accordions
// ---------------------------------------------------------------------------
add_action( 'woocommerce_single_product_summary', 'sv2_trymylook_section', 45 );
function sv2_trymylook_section() {
    global $product;
    if ( ! $product ) return;
    if ( ! sv2_is_clothing_product( $product->get_id() ) ) return;

    $pid = $product->get_id();
    $url = function_exists( 'get_field' )
        ? get_field( 'product_trymylook_url', $pid )
        : get_post_meta( $pid, 'product_trymylook_url', true );

    if ( ! $url ) return;
    ?>
    <div class="saltlux-trymylook">
        <div class="saltlux-trymylook-inner">
            <div class="saltlux-trymylook-text">
                <strong><?php esc_html_e( 'Thử đồ ảo', 'saltlux' ); ?></strong>
                <span><?php esc_html_e( 'Xem sản phẩm trông như thế nào trước khi mua.', 'saltlux' ); ?></span>
            </div>
            <a class="saltlux-trymylook-btn"
               href="<?php echo esc_url( $url ); ?>"
               target="_blank" rel="noopener noreferrer">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                    <circle cx="12" cy="13" r="4"/>
                </svg>
                Try My Look
            </a>
        </div>
    </div>
    <?php
}

// ---------------------------------------------------------------------------
// 2. "Kết hợp tốt với" — moved into summary accordion (sv2_product_accordions)
// ---------------------------------------------------------------------------

// ---------------------------------------------------------------------------
// 3a. Browse strip — category pills, above services strip
// ---------------------------------------------------------------------------
add_action( 'woocommerce_after_single_product_summary', 'sv2_browse_strip', 29 );
function sv2_browse_strip() {
    $cats = get_terms( array(
        'taxonomy'   => 'product_cat',
        'hide_empty' => true,
        'parent'     => 0,
        'orderby'    => 'name',
        'order'      => 'ASC',
    ) );
    if ( is_wp_error( $cats ) || empty( $cats ) ) return;
    ?>
    <div class="sv2-browse-strip">
        <span class="sv2-browse-label">BROWSE
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2.5"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="5" y1="12" x2="19" y2="12"></line>
                <polyline points="12 5 19 12 12 19"></polyline>
            </svg>
        </span>
        <div class="sv2-browse-pills">
            <?php foreach ( $cats as $cat ) : ?>
            <a class="sv2-browse-pill"
               href="<?php echo esc_url( get_term_link( $cat ) ); ?>">
                <?php echo esc_html( $cat->name ); ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}

// ---------------------------------------------------------------------------
// 3. Services strip — woocommerce_after_single_product_summary @5
//    Fallback: Sự kiện / Fitting / Coaching
//    Data source: Admin → Cài đặt đại lý → Section dịch vụ
//    Also available as shortcode: [saltlux_services]
// ---------------------------------------------------------------------------
add_action( 'woocommerce_after_single_product_summary', function() {
    echo sv2_services_render();
}, 30 );
add_shortcode( 'saltlux_services', 'sv2_services_render' );

function sv2_services_render( $atts = array() ) {
    $services = array();

    if ( class_exists( 'ACF' ) && have_rows( 'product_services', 'option' ) ) {
        while ( have_rows( 'product_services', 'option' ) ) {
            the_row();
            $services[] = array(
                'title' => get_sub_field( 'title' ),
                'url'   => get_sub_field( 'url' ),
                'image' => get_sub_field( 'image' ),
            );
        }
    }

    if ( empty( $services ) ) {
        $services = array(
            array( 'title' => 'Sự kiện',  'url' => home_url( '/su-kien/' ),  'image' => null ),
            array( 'title' => 'Fitting',  'url' => home_url( '/fitting/' ),  'image' => null ),
            array( 'title' => 'Coaching', 'url' => home_url( '/coaching/' ), 'image' => null ),
        );
    }

    $svc_count = count( $services );
    ob_start();
    ?>
    <div class="saltlux-services-strip" data-count="<?php echo $svc_count; ?>">
        <?php foreach ( $services as $svc ) :
            $title   = esc_html( $svc['title'] ?? '' );
            $url     = esc_url( $svc['url'] ?? '#' );
            $img     = $svc['image'] ?? null;
            $has_img = ! empty( $img['url'] );
        ?>
        <a class="saltlux-service-card<?php echo $has_img ? '' : ' saltlux-service-card--no-img'; ?>"
           href="<?php echo $url; ?>">
            <figure class="saltlux-service-img">
                <?php if ( $has_img ) : ?>
                    <img src="<?php echo esc_url( $img['url'] ); ?>"
                         alt="<?php echo esc_attr( $img['alt'] ?: $title ); ?>"
                         loading="lazy">
                <?php endif; ?>
            </figure>
            <div class="saltlux-service-body">
                <span class="saltlux-service-label"><?php esc_html_e( 'Khám phá', 'saltlux' ); ?></span>
                <span class="saltlux-service-title"><?php echo $title; ?></span>
                <span class="saltlux-service-arrow">
                    <?php esc_html_e( 'Xem thêm', 'saltlux' ); ?>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                        <polyline points="12 5 19 12 12 19"></polyline>
                    </svg>
                </span>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}

// ---------------------------------------------------------------------------
// 3b. Reviews section — standalone below services strip, above related
//     Removed from product tabs to avoid duplication.
// ---------------------------------------------------------------------------
add_filter( 'woocommerce_product_tabs', function( $tabs ) {
    unset( $tabs['reviews'] );
    return $tabs;
}, 20 );

add_action( 'woocommerce_after_single_product_summary', 'sv2_reviews_section', 25 );
function sv2_reviews_section() {
    global $product;
    if ( ! $product || ! function_exists( 'get_field' ) ) return;

    $rating = get_field( 'product_fake_rating' );
    if ( ! $rating ) return;

    $rating = (float) $rating;
    $count  = (int) get_field( 'product_fake_count' );
    $full   = (int) floor( $rating );
    $half   = ( $rating - $full ) >= 0.5 ? 1 : 0;
    $empty  = 5 - $full - $half;
    ?>
    <section class="sv2-reviews-section" id="reviews">
        <div class="sv2-reviews-inner">
            <div class="sv2-fake-rating">
                <div class="sv2-fake-stars">
                    <?php echo str_repeat( '<span class="sv2-star sv2-star--full">★</span>', $full ); ?>
                    <?php if ( $half ) : ?><span class="sv2-star sv2-star--half">⯨</span><?php endif; ?>
                    <?php echo str_repeat( '<span class="sv2-star sv2-star--empty">☆</span>', $empty ); ?>
                </div>
                <?php if ( $count ) : ?>
                <p class="sv2-fake-count"><?php echo esc_html( $count ); ?> đánh giá</p>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <?php
}

// ---------------------------------------------------------------------------
// 4. Related products by category — replaces WooCommerce default
// ---------------------------------------------------------------------------
remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20 );
add_action( 'woocommerce_after_single_product_summary', 'sv2_related_by_category', 28 );

function sv2_related_by_category() {
    global $product;
    if ( ! $product ) return;

    $terms = get_the_terms( $product->get_id(), 'product_cat' );
    if ( ! $terms || is_wp_error( $terms ) ) return;

    $related = new WP_Query( array(
        'post_type'      => 'product',
        'posts_per_page' => 4,
        'post__not_in'   => array( $product->get_id() ),
        'tax_query'      => array( array(
            'taxonomy' => 'product_cat',
            'field'    => 'term_id',
            'terms'    => wp_list_pluck( $terms, 'term_id' ),
        ) ),
        'orderby'        => 'rand',
        'post_status'    => 'publish',
    ) );

    if ( ! $related->have_posts() ) return;
    ?>
    <section class="saltlux-related-products">
        <h2 class="saltlux-related-heading"><?php esc_html_e( 'Sản phẩm tương tự', 'saltlux' ); ?></h2>
        <ul class="products columns-4">
            <?php
            while ( $related->have_posts() ) {
                $related->the_post();
                wc_get_template_part( 'content', 'product' );
            }
            wp_reset_postdata();
            ?>
        </ul>
    </section>
    <?php
}
