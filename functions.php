<?php

require_once __DIR__ . '/inc/config.php';


function sv2_page_has_shortcode( $tag ) {
    if ( ! is_singular() ) return false;
    $post = get_queried_object();
    if ( ! $post ) return false;
    if ( has_shortcode( $post->post_content, $tag ) ) return true;
    // Also check Elementor's stored data (JSON in postmeta)
    $el_data = get_post_meta( $post->ID, '_elementor_data', true );
    return $el_data && strpos( $el_data, $tag ) !== false;
}

function sv2_enqueue_styles() {
    $uri = get_stylesheet_directory_uri();
    $ver = wp_get_theme()->get( 'Version' );

    wp_enqueue_style( 'style',       get_template_directory_uri() . '/style.css', [], $ver );
    wp_enqueue_style( 'child-style', $uri . '/style.css', [ 'style' ], $ver );
    wp_enqueue_style( 'sv2-header',  $uri . '/assets/css/header.css', [ 'child-style' ], $ver );
    wp_enqueue_script( 'sv2-header', $uri . '/assets/js/header.js', [], $ver, true );
    wp_enqueue_script( 'saltlux-v2', $uri . '/assets/custom.js', [ 'jquery' ], $ver, true );

    $gallery_slides = [];
    if ( function_exists( 'get_field' ) ) {
        foreach ( (array) get_field( 'gallery_slides', 'option' ) as $row ) {
            $gallery_slides[] = [
                'url'   => $row['slide_url']   ?? '',
                'label' => $row['slide_label'] ?? '',
            ];
        }
    }
    wp_localize_script( 'saltlux-v2', 'pghConfig', [ 'slides' => $gallery_slides ] );

    if ( is_product() ) {
        wp_enqueue_style(  'sv2-modal',  $uri . '/assets/css/style.css', [], $ver );
        wp_enqueue_script( 'sv2-modal',  $uri . '/assets/js/script.js', [ 'jquery' ], $ver, true );
    }

    if ( sv2_page_has_shortcode( 'saltlux_dealer_map' ) ) {
        /* Google Maps is loaded async in wp_head with callback=sv2DealerMapInit */
        wp_enqueue_style(  'sv2-dealer-map', $uri . '/assets/css/dealer-map.css', [], $ver );
        wp_enqueue_script( 'sv2-dealer-map', $uri . '/assets/js/dealer-map.js', [], $ver, true );
        wp_localize_script( 'sv2-dealer-map', 'saltluxDealer', [
            'dealers' => function_exists( 'saltlux_build_dealers_array' ) ? saltlux_build_dealers_array() : [],
        ] );
    }

    if ( sv2_page_has_shortcode( 'saltlux_kol_grid' ) || sv2_page_has_shortcode( 'saltlux_kol_slider' ) ) {
        wp_enqueue_style( 'sv2-kol', $uri . '/assets/css/kol-page.css', [], $ver );
    }
}
add_action( 'wp_enqueue_scripts', 'sv2_enqueue_styles' );

/* Load Google Maps async with callback — must be in <head> before body renders */
add_action( 'wp_head', function () {
    if ( ! function_exists( 'sv2_page_has_shortcode' ) || ! sv2_page_has_shortcode( 'saltlux_dealer_map' ) ) return;
    $key = 'AIzaSyDwpkuWFDYO2uNOVgtgFoSx6TZXb-1UnVw';
    ?>
<script>
window.gm_authFailure = function () {
    var mapEl = document.getElementById('dl-leaflet-map');
    if ( !mapEl ) return;
    function removeOverlay() {
        mapEl.querySelectorAll( '[class*="gm-err"], .dismissButton' ).forEach(function(el){
            if ( el.parentNode ) el.parentNode.removeChild(el);
        });
        Array.prototype.slice.call( mapEl.children ).forEach(function(el){
            if ( el.textContent && el.textContent.indexOf('Google Maps') !== -1 &&
                 el.textContent.indexOf('tải') !== -1 ) {
                el.style.display = 'none';
            }
        });
    }
    removeOverlay();
    var obs = new MutationObserver(removeOverlay);
    obs.observe( mapEl, { childList: true, subtree: true } );
    setTimeout(function(){ obs.disconnect(); }, 5000);
};
/* sv2DealerMapInit is called by Maps when the API is ready.
   dealer-map.js registers __sv2DealerMapImpl; if Maps loads first we call it directly. */
window.sv2DealerMapInit = function () {
    if ( typeof window.__sv2DealerMapImpl === 'function' ) {
        window.__sv2DealerMapImpl();
    } else {
        window.__sv2MapsApiReady = true;
    }
};
</script>
<script async src="https://maps.googleapis.com/maps/api/js?key=<?php echo esc_attr( $key ); ?>&callback=sv2DealerMapInit&language=vi&region=VN&loading=async"></script>
    <?php
}, 1 );

function sv2_prepare_theme( $themes ) {
    unset( $themes['astra'] );
    return $themes;
}
add_filter( 'wp_prepare_themes_for_js', 'sv2_prepare_theme' );

// Cart fragment — keeps .sl-cart-count in sync after add-to-cart
add_filter( 'woocommerce_add_to_cart_fragments', function ( $fragments ) {
    $count = function_exists( 'WC' ) && WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
    $fragments['.sl-cart-count'] = '<span class="sl-cart-count">' . $count . '</span>';
    return $fragments;
} );

// Remove Astra's own header output — our header.php replaces it entirely
add_filter( 'astra_header_markup_after', '__return_false' );
remove_action( 'astra_header', 'astra_header_markup' );

function sv2_register_dealers_template( $templates ) {
    $templates['page-dealers.php'] = 'Hệ thống đại lý';
    $templates['page-kol.php']     = 'Tour Players / KOL';
    $templates['page-custom-fitting.php'] = 'Custom Fitting';
    return $templates;
}
add_filter( 'theme_page_templates', 'sv2_register_dealers_template' );

function sv2_load_dealers_template( $template ) {
    if ( ! is_page() ) {
        return $template;
    }

    $page_id           = get_queried_object_id();
    $selected_template = get_page_template_slug( $page_id );

    $dealer_template = get_stylesheet_directory() . '/page-dealers.php';
    if ( file_exists( $dealer_template ) && $selected_template === 'page-dealers.php' ) {
        return $dealer_template;
    }

    $kol_template = get_stylesheet_directory() . '/page-kol.php';
    if ( file_exists( $kol_template ) && $selected_template === 'page-kol.php' ) {
        return $kol_template;
    }

    $fitting_template = get_stylesheet_directory() . '/page-custom-fitting.php';
    if ( file_exists( $fitting_template ) && ( $selected_template === 'page-custom-fitting.php' || is_page( SV2_PAGE_SLUG_CUSTOM_FITTING ) ) ) {
        return $fitting_template;
    }

    return $template;
}
add_filter( 'template_include', 'sv2_load_dealers_template', 99 );

add_action( 'astra_breadcrumb_trail', 'sv2_breadcrumb_trail' );
function sv2_breadcrumb_trail() {
    if ( function_exists( 'sv2_is_shop_listing' ) && sv2_is_shop_listing() ) {
        return;
    }

    if ( ! function_exists( 'get_field' ) ) return;

    $queried_object = get_queried_object();
    if ( isset( $queried_object->term_id ) ) {
        $banner = get_field( 'woo_category_banner', 'product_cat_' . $queried_object->term_id );
    }

    if ( ! empty( $banner ) ) { ?>
        <div id="banner-prod-cat" class="banner-prod-cat"><img src="<?php echo esc_url( $banner ); ?>" alt="Banner" /></div>
    <?php }
}

function sv2_override_page_title() {
    return false;
}
add_filter( 'woocommerce_show_page_title', 'sv2_override_page_title' );

function sv2_category_title( $title ) {
    if ( is_tax( 'product_cat' ) ) {
        $title = single_cat_title( '', false );
    }
    return $title;
}
add_filter( 'get_the_archive_title', 'sv2_category_title' );

function sv2_make_billing_email_optional( $fields ) {
    if ( isset( $fields['billing']['billing_email'] ) ) {
        $fields['billing']['billing_email']['required'] = false;
    }
    return $fields;
}
add_filter( 'woocommerce_checkout_fields', 'sv2_make_billing_email_optional' );

function sv2_replace_checkout_text( $checkout_text ) {
    if ( 'en_US' === get_locale() ) {
        $checkout_text = str_replace( 'Đặt hàng', 'Checkout', $checkout_text );
    }
    return $checkout_text;
}
add_filter( 'woocommerce_order_button_html', 'sv2_replace_checkout_text', 99 );

function sv2_search_by_sku( $search, $wp_query ) {
    global $wpdb;

    if ( ! is_admin() && $wp_query->is_search() && isset( $wp_query->query_vars['s'] ) ) {
        $search = $wp_query->query_vars['s'];
        $like   = '%' . $wpdb->esc_like( $search ) . '%';

        $search = $wpdb->prepare(
            "
            AND (
                ({$wpdb->posts}.post_title LIKE %s)
                OR ({$wpdb->posts}.post_content LIKE %s)
                OR EXISTS (
                    SELECT 1
                    FROM {$wpdb->postmeta}
                    WHERE {$wpdb->postmeta}.meta_key = '_sku'
                    AND {$wpdb->postmeta}.meta_value LIKE %s
                    AND {$wpdb->postmeta}.post_id = {$wpdb->posts}.ID
                )
                OR EXISTS (
                    SELECT 1
                    FROM {$wpdb->postmeta} AS pm
                    INNER JOIN {$wpdb->posts} AS parent
                    ON pm.meta_key = '_sku' AND pm.meta_value LIKE %s
                    AND pm.post_id = parent.ID
                    AND parent.post_type = 'product_variation'
                    AND parent.post_parent = {$wpdb->posts}.ID
                )
            )
            ",
            $like, $like, $like, $like
        );
    }

    return $search;
}
add_filter( 'posts_search', 'sv2_search_by_sku', 10, 2 );

// Guarantee core product summary elements are always rendered,
// regardless of how Astra's 'single-product-structure' option is set on the server.
add_filter( 'astra_woo_single_product_structure', function( $structure ) {
    $required = array( 'title', 'ratings', 'price', 'add_cart', 'meta' );
    if ( empty( $structure ) || ! is_array( $structure ) ) {
        return $required;
    }
    foreach ( $required as $item ) {
        if ( ! in_array( $item, $structure, true ) ) {
            $structure[] = $item;
        }
    }
    return $structure;
} );

// Add-to-cart safety net: detect whether WooCommerce actually rendered the form,
// then render it at priority 999 if nothing else did.
// woocommerce_before_add_to_cart_form fires inside every add-to-cart template
// (simple, variable, …) right before the <form> tag — reliable across WC versions.
$GLOBALS['sv2_cart_rendered'] = false;
add_action( 'woocommerce_before_add_to_cart_form', function() {
    $GLOBALS['sv2_cart_rendered'] = true;
} );
add_action( 'woocommerce_single_product_summary', function() {
    if ( $GLOBALS['sv2_cart_rendered'] ) return;
    global $product;
    if ( ! $product || ! is_product() ) return;
    woocommerce_template_single_add_to_cart();
}, 999 );

// Output critical layout CSS directly into <head> — bypasses file cache,
// handle dependency issues, and any CDN that ignores query-string versioning.
add_action( 'wp_head', function() {
    echo '<style id="sv2-critical">
.single-product div.product .woocommerce-product-gallery{display:none!important}
@media screen and (min-width:922px){
  .sv2-breadcrumb-mobile{display:none!important}
  .single-product div.product{display:grid!important;grid-template-columns:54% 1fr!important;column-gap:16px!important;align-items:start!important;background:#f5f5f5!important;padding:60px!important}
  .single-product div.product .saltlux-product-gallery{grid-column:1!important;grid-row:1!important;background:#f5f5f5!important}
  .single-product div.product .summary.entry-summary{grid-column:2!important;grid-row:1!important;width:auto!important;float:none!important;clear:none!important;background:#fff!important;padding:36px 40px!important}
  .single-product div.product .saltlux-services-strip,
  .single-product div.product .saltlux-pairs-section,
  .single-product div.product .woocommerce-tabs,
  .single-product div.product .wc-tabs-wrapper,
  .single-product div.product .up-sells,
  .single-product div.product .saltlux-related-products,
  .single-product div.product .related.products{grid-column:1/-1!important}
}
@media screen and (max-width:921px){
  .single-product div.product{display:flex!important;flex-direction:column!important}
  .single-product div.product>*{width:100%!important;float:none!important;grid-column:unset!important;grid-row:unset!important}
  .single-product div.product .summary.entry-summary{float:none!important;width:100%!important;clear:both!important;padding:24px 16px!important}
  .single-product div.product .sv2-breadcrumb-mobile{display:block!important;order:-3!important;padding-left:16px!important;padding-right:16px!important}
  .single-product div.product .saltlux-product-gallery{order:-2!important}
  .single-product div.product .sv2-gallery-dots{order:-1!important}
  .single-product div.product .summary.entry-summary .woocommerce-breadcrumb{display:none!important}
}
.sl-hamburger:hover,.sl-hamburger:focus,.sl-hamburger:focus-visible,.sl-hamburger:active{background:none!important;color:var(--sl-hdr-text)!important;outline:none!important;box-shadow:none!important;border-color:transparent!important}
</style>';
}, 99 );

// Fix Elementor hosted-video widget for non-admin users.
// Elementor calls esc_html() inside the video widget's render() when the user
// lacks unfiltered_html, encoding <video> to visible text.
// We decode it back at the widget level and also at the_content level as fallback
// (Elementor canvas templates bypass the_content, so both hooks are needed).
function sv2_decode_escaped_video( $content ) {
    if ( strpos( $content, '&lt;video' ) === false ) return $content;
    return preg_replace_callback(
        '/&lt;video\b[^&]*(?:&(?!gt;)[^&]*)*&gt;\s*&lt;\/video&gt;/i',
        function( $m ) {
            return html_entity_decode( $m[0], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        },
        $content
    );
}
add_filter( 'elementor/widget/render_content', 'sv2_decode_escaped_video', 10, 2 );
add_filter( 'the_content', 'sv2_decode_escaped_video', 99999 );

// Client-side fallback: inline script cached WITH the HTML by SpeedyCache.
// TranslatePress may re-encode <video> via htmlentities() even after the PHP fix above;
// this script runs after the DOM is ready and decodes any remaining escaped video tags.
add_action( 'wp_footer', function() {
    ?>
<script>
(function(){var els=document.querySelectorAll('.e-hosted-video');for(var i=0;i<els.length;i++){var h=els[i].innerHTML;if(h.indexOf('&lt;video')===-1)continue;els[i].innerHTML=h.replace(/&lt;(\/?)video\b([^]*?)&gt;/gi,'<$1video$2>');}})();
</script>
    <?php
}, 99 );

// Allow <video> tag through KSES for non-admin users (Elementor video widget)
add_filter( 'wp_kses_allowed_html', function( $tags, $context ) {
    if ( 'post' === $context ) {
        $tags['video'] = array(
            'src'          => true,
            'autoplay'     => true,
            'loop'         => true,
            'controls'     => true,
            'muted'        => true,
            'controlslist' => true,
            'class'        => true,
            'style'        => true,
            'width'        => true,
            'height'       => true,
            'poster'       => true,
            'preload'      => true,
            'playsinline'  => true,
        );
    }
    return $tags;
}, 10, 2 );

// Fitting module lives in inc/fitting-module.php (loaded via inc/functions.php).
add_action( 'wp_enqueue_scripts', function() {
    if ( ! is_page( SV2_PAGE_SLUG_CUSTOM_FITTING ) && ! is_page_template( 'page-custom-fitting.php' ) && ! sv2_page_has_shortcode( 'tm_fitting_hero' ) ) {
        return;
    }

    $uri = get_stylesheet_directory_uri();
    $ver = wp_get_theme()->get( 'Version' );
    $base = $uri . '/assets/tm';

    wp_enqueue_style( 'tm-tokens', $base . '/tm-tokens.css', array(), $ver );
    wp_enqueue_style( 'tm-base', $base . '/taylormade.css', array( 'tm-tokens' ), $ver );
    wp_enqueue_script( 'google-maps', 'https://maps.googleapis.com/maps/api/js?key=AIzaSyDwpkuWFDYO2uNOVgtgFoSx6TZXb-1UnVw&language=vi&region=VN', array(), null, true );
    wp_enqueue_script( 'tm-header', $base . '/tm-header.js', array(), $ver, true );
    wp_enqueue_script( 'tm-reveal', $base . '/tm-reveal.js', array(), $ver, true );
    wp_enqueue_script( 'tm-process', $base . '/tm-process.js', array(), $ver, true );
    wp_enqueue_script( 'tm-tech-parallax', $base . '/tm-tech-parallax.js', array(), $ver, true );
    wp_enqueue_script( 'tm-faq', $base . '/tm-faq.js', array(), $ver, true );
    wp_enqueue_script( 'tm-locator', $base . '/tm-locator.js', array( 'google-maps' ), $ver, true );

    $payload = function_exists( 'tm_build_locations_payload' ) ? tm_build_locations_payload() : array();
    wp_localize_script(
        'tm-locator',
        'TM_LOCATIONS',
        array(
            'items'       => $payload,
            'defaultLat'  => (float) ( function_exists( 'get_field' ) ? ( get_field( 'default_lat', 'option' ) ?: 10.8231 ) : 10.8231 ),
            'defaultLng'  => (float) ( function_exists( 'get_field' ) ? ( get_field( 'default_lng', 'option' ) ?: 106.6297 ) : 106.6297 ),
            'defaultZoom' => (int) ( function_exists( 'get_field' ) ? ( get_field( 'default_zoom', 'option' ) ?: 6 ) : 6 ),
        )
    );
}, 40 );

// Force full-width, no-sidebar on single product pages.
add_filter( 'astra_page_layout', function( $layout ) {
    if ( is_product() ) {
        return 'no-sidebar';
    }
    return $layout;
} );

// Force full-width container (removes Astra's max-width cap on product pages).
add_filter( 'astra_get_store_content_layout', function( $layout ) {
    if ( is_product() ) {
        return 'full-width-container';
    }
    return $layout;
} );

add_filter( 'astra_get_content_layout', function( $layout ) {
    if ( is_product() ) {
        return 'full-width-container';
    }
    return $layout;
}, 20 );

// Disable Astra Addon's accordion tabs on single product — we use our own WooCommerce tab template.
add_filter( 'astra_get_option_single-product-tabs-layout', function( $value ) {
    if ( is_product() ) {
        return 'tabs';
    }
    return $value;
} );
add_filter( 'astra_get_option_accordion-inside-woo-summary', function( $value ) {
    if ( is_product() ) {
        return false;
    }
    return $value;
} );

add_filter( 'body_class', function( $classes ) {
    if ( is_product() ) {
        $classes[] = 'sv2-product-fullwidth';
    }
    return $classes;
} );

require_once __DIR__ . '/inc/functions.php';

// Seed demo CPT data on theme activation (zip-only deploy, no saltlux-core plugin).
add_action(
    'after_switch_theme',
    function () {
        if ( 'saltlux-v2' !== get_stylesheet() ) {
            return;
        }
        update_option( 'sv2_seed_pending', SV2_SEED_VERSION );
        flush_rewrite_rules();
    }
);

add_action(
    'init',
    function () {
        $pending = get_option( 'sv2_seed_pending' );
        if ( ! $pending || ! function_exists( 'sv2_run_seed' ) ) {
            return;
        }
        sv2_run_seed( $pending );
        delete_option( 'sv2_seed_pending' );
    },
    100
);

// ── Product permalink: force slug = 'san-pham' ─────────────────────────────
// Priority PHP_INT_MAX đảm bảo chạy SAU mọi plugin (Rank Math, WPML, v.v.)
add_filter( 'pre_option_woocommerce_permalinks', function () {
    return [
        'product_base'           => 'san-pham',
        'category_base'          => '',
        'tag_base'               => '',
        'attribute_base'         => '',
        'use_verbose_page_rules' => '',
    ];
}, PHP_INT_MAX );

// Belt-and-suspenders: ghi thẳng vào $wp_post_types sau khi WC đăng ký (priority 5).
add_action( 'init', function () {
    global $wp_post_types;
    if ( ! isset( $wp_post_types['product'] ) ) return;
    $wp_post_types['product']->rewrite = [
        'slug'       => 'san-pham',
        'with_front' => false,
        'feeds'      => true,
        'ep_mask'    => EP_PERMALINK,
    ];
}, 99 );

// Flush rewrite rules mỗi khi version thay đổi.
add_action( 'init', function () {
    $flag = 'sv2_pf_v3';
    if ( get_transient( $flag ) ) return;
    delete_transient( 'sv2_pf_v1' );
    delete_transient( 'sv2_pf_v2' );
    flush_rewrite_rules( true );
    set_transient( $flag, 1, MONTH_IN_SECONDS );
}, 100 );
