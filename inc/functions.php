<?php
/**
 * Saltlux V2 theme — module bootstrap.
 * Self-contained when saltlux-core plugin is inactive (production default).
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/safe-load.php';

/**
 * Whether the legacy saltlux-core plugin is active (must stay OFF on prod).
 */
function sv2_is_core_plugin_active() {
    if ( ! function_exists( 'is_plugin_active' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    return is_plugin_active( 'saltlux-core/core.php' );
}

// CPT / REST / seed — theme owns these when plugin is disabled.
if ( ! sv2_is_core_plugin_active() ) {
    $sv2_core_modules = array(
        'dealer-cpt.php',
        'kol-cpt.php',
        'modal.php',
        'woo-cart.php',
        'tour-player.php',
        'fitting-module.php',
        'news-category.php',
        'rest-api.php',
        'seed-data.php',
    );
    foreach ( $sv2_core_modules as $module ) {
        sv2_require_theme_inc( $module );
    }
}

// ACF + presentation always from theme.
sv2_require_theme_inc( 'acf.php' );
sv2_require_theme_inc( 'color-swatches.php' );
sv2_require_theme_inc( 'acf-gallery.php' );
sv2_require_theme_inc( 'shortcode/languages.php' );
sv2_require_theme_inc( 'shortcode/dealer-map.php' );
sv2_require_theme_inc( 'shortcode/kol-grid.php' );
sv2_require_theme_inc( 'shortcode/kol-slider.php' );
sv2_require_theme_inc( 'woo.php' );
sv2_require_theme_inc( 'product-archive.php' );
sv2_require_theme_inc( 'product-page.php' );
sv2_require_theme_inc( 'post-product.php' );
sv2_require_theme_inc( 'single-post.php' );
sv2_require_theme_inc( 'shortcode/news-archive.php' );
sv2_require_theme_inc( 'legacy-urls.php' );

if ( sv2_is_core_plugin_active() && is_admin() ) {
    add_action(
        'admin_notices',
        function () {
            echo '<div class="notice notice-warning"><p><strong>Saltlux V2:</strong> Plugin <code>saltlux-core</code> đang active — trên production hãy <strong>deactivate</strong> plugin để tránh trùng CPT/hàm. Theme v2.10+ đã gom đủ module.</p></div>';
        }
    );
}
