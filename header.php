<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ── 1. WooCommerce top-level product categories (like original code) ─────
$_mega_top = get_terms( [
    'taxonomy'   => 'product_cat',
    'parent'     => 0,
    'hide_empty' => true,
    'orderby'    => 'menu_order',
    'exclude'    => [ (int) get_option( 'default_product_cat' ) ],
] );
if ( is_wp_error( $_mega_top ) ) $_mega_top = [];

$_nav_data = [];
foreach ( $_mega_top as $_top ) {
    $_subs = get_terms( [
        'taxonomy'   => 'product_cat',
        'parent'     => $_top->term_id,
        'hide_empty' => true,
        'orderby'    => 'menu_order',
    ] );
    if ( is_wp_error( $_subs ) ) $_subs = [];

    // Subcategory display priority: names listed here float to the top (in order).
    // Edit this array to change which items appear first — all others keep WC order.
    static $_mega_priority = [
        'Thời Trang Golf Nam',
        'Thời Trang Golf Nữ',
    ];

    if ( count( $_subs ) > 1 ) {
        usort( $_subs, function ( $a, $b ) use ( $_mega_priority ) {
            $ia = array_search( $a->name, $_mega_priority, true );
            $ib = array_search( $b->name, $_mega_priority, true );
            $oa = $ia !== false ? $ia : PHP_INT_MAX;
            $ob = $ib !== false ? $ib : PHP_INT_MAX;
            return $oa - $ob;
        } );
    }

    $_nav_data[] = [
        'id'       => 'cat-' . $_top->term_id,
        'label'    => $_top->name,
        'url'      => get_term_link( $_top ),
        'wc_subs'  => $_subs,
        'children' => [],
    ];
}

// ── 2. Non-product items from Primary menu ────────────────────────────────
// Items with children in the menu are product-cat placeholders ("mục phụ") → skip them.
// Items without children (HỆ THỐNG ĐẠI LÝ, TOUR PLAYERS, TIN TỨC, …) → add as direct links.
$_primary_menu = wp_get_nav_menu_object( 'Primary' );
$_raw_items    = $_primary_menu ? wp_get_nav_menu_items( $_primary_menu->term_id ) : [];

$_children_map = [];
foreach ( $_raw_items as $_mi ) {
    $_children_map[ (int) $_mi->menu_item_parent ][] = $_mi;
}

foreach ( $_children_map[0] ?? [] as $_mi ) {
    if ( ! empty( $_children_map[ $_mi->ID ] ) ) continue; // has children → product cat placeholder, skip
    $_nav_data[] = [
        'id'       => 'mi-' . $_mi->ID,
        'label'    => $_mi->title,
        'url'      => $_mi->url,
        'wc_subs'  => [],
        'children' => [],
    ];
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="profile" href="https://gmpg.org/xfn/11">
<?php wp_head(); ?>
</head>

<body <?php body_class(); ?> data-sv2="2.2">
<?php wp_body_open(); ?>

<a class="skip-link screen-reader-text" href="#content">Bỏ qua nội dung</a>

<!-- ═══════════════ PROMO BAR ═══════════════ -->
<?php
$_promo_slides = function_exists( 'get_field' ) ? get_field( 'promo_slides', 'option' ) : [];
if ( ! empty( $_promo_slides ) ) :
?>
<div class="sl-promo-bar" id="sl-promo-bar" aria-label="Thông báo">

    <div class="sl-promo-track" aria-live="polite">
        <?php foreach ( $_promo_slides as $_si => $_slide ) :
            $title     = ! empty( $_slide['slide_title'] )     ? $_slide['slide_title']     : '';
            $link_text = ! empty( $_slide['slide_link_text'] ) ? $_slide['slide_link_text'] : '';
            $link_url  = ! empty( $_slide['slide_link_url'] )  ? $_slide['slide_link_url']  : '';
        ?>
        <div class="sl-promo-slide<?php echo $_si === 0 ? ' is-active' : ''; ?>" aria-hidden="<?php echo $_si === 0 ? 'false' : 'true'; ?>">
            <?php if ( $title ) : ?>
            <span class="sl-promo-title"><?php echo esc_html( $title ); ?></span>
            <?php endif; ?>
            <?php if ( $link_text && $link_url ) : ?>
            <a class="sl-promo-link" href="<?php echo esc_url( $link_url ); ?>"><?php echo esc_html( $link_text ); ?></a>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ( count( $_promo_slides ) > 1 ) : ?>
    <button class="sl-promo-btn sl-promo-prev" aria-label="Slide trước">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
    </button>
    <button class="sl-promo-btn sl-promo-next" aria-label="Slide tiếp">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
    </button>
    <?php endif; ?>

</div>
<?php endif; ?>

<!-- ═══════════════ HEADER ═══════════════ -->
<header class="sl-header" id="sl-header">

    <!-- ─── Main bar ─── -->
    <div class="sl-header-bar">

        <!-- Left: hamburger + logo -->
        <div class="sl-header-left">
            <button class="sl-hamburger" id="sl-hamburger"
                    aria-expanded="false" aria-controls="sl-mega" aria-label="Mở menu">
                <span></span><span></span><span></span>
                <span class="sl-hamburger-label">MENU</span>
            </button>

            <a class="sl-logo-link" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php bloginfo( 'name' ); ?>">
                <?php
                if ( has_custom_logo() ) {
                    the_custom_logo();
                } else {
                    echo '<span class="sl-logo-text">' . esc_html( get_bloginfo( 'name' ) ) . '</span>';
                }
                ?>
            </a>
        </div>

        <!-- Center: search (desktop) -->
        <div class="sl-header-center">
            <form class="sl-search-form" role="search" method="get"
                  action="<?php echo esc_url( home_url( '/' ) ); ?>">
                <svg class="sl-search-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                     viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input class="sl-search-input" type="search" name="s"
                       placeholder="Tìm kiếm sản phẩm, mã sản phẩm…"
                       value="<?php echo esc_attr( get_search_query() ); ?>"
                       autocomplete="off">
                <input type="hidden" name="post_type" value="product">
            </form>
        </div>

        <!-- Right: account + cart -->
        <div class="sl-header-right">
            <?php if ( function_exists( 'wc_get_account_endpoint_url' ) ) : ?>
            <a class="sl-header-account" href="<?php echo esc_url( wc_get_account_endpoint_url( 'dashboard' ) ); ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="1.8"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
                <span class="sl-header-account-label">
                    <?php if ( is_user_logged_in() ) :
                        echo esc_html( wp_get_current_user()->display_name );
                    else : ?>
                        Đăng nhập
                    <?php endif; ?>
                </span>
            </a>
            <?php endif; ?>

            <?php if ( function_exists( 'WC' ) ) : ?>
            <a class="sl-header-cart" href="<?php echo esc_url( wc_get_cart_url() ); ?>"
               aria-label="Giỏ hàng">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="1.8"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                    <line x1="3" y1="6" x2="21" y2="6"/>
                    <path d="M16 10a4 4 0 0 1-8 0"/>
                </svg>
                <span class="sl-cart-count"><?php echo WC()->cart ? WC()->cart->get_cart_contents_count() : 0; ?></span>
            </a>
            <?php endif; ?>
        </div>

    </div><!-- /.sl-header-bar -->

    <!-- ─── Mobile search row ─── -->
    <div class="sl-search-row">
        <form class="sl-search-form sl-search-form--mobile" role="search" method="get"
              action="<?php echo esc_url( home_url( '/' ) ); ?>">
            <svg class="sl-search-icon" xmlns="http://www.w3.org/2000/svg" width="15" height="15"
                 viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            <input class="sl-search-input" type="search" name="s"
                   placeholder="Tìm kiếm sản phẩm…"
                   value="<?php echo esc_attr( get_search_query() ); ?>"
                   autocomplete="off">
            <input type="hidden" name="post_type" value="product">
        </form>
    </div>

    <!-- ─── Mega menu ─── -->
    <nav class="sl-mega" id="sl-mega" aria-hidden="true" aria-label="Danh mục sản phẩm">
        <div class="sl-mega-inner">

            <!-- Left: category tabs (driven by Primary menu) -->
            <ul class="sl-mega-nav" role="list">
                <?php foreach ( $_nav_data as $i => $entry ) :
                    $has_panel = ! empty( $entry['wc_subs'] ) || ! empty( $entry['children'] );
                ?>
                <li>
                    <?php if ( $has_panel ) : ?>
                    <button class="sl-mega-tab<?php echo $i === 0 ? ' is-active' : ''; ?>"
                            data-panel="sl-panel-<?php echo esc_attr( $entry['id'] ); ?>">
                        <?php echo esc_html( $entry['label'] ); ?>
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2.5"
                             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <polyline points="9 18 15 12 9 6"/>
                        </svg>
                    </button>
                    <?php else : ?>
                    <a class="sl-mega-tab sl-mega-tab--direct"
                       href="<?php echo esc_url( $entry['url'] ); ?>">
                        <?php echo esc_html( $entry['label'] ); ?>
                    </a>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>

            <!-- Right: subcategory panels (driven by Primary menu) -->
            <div class="sl-mega-panels">
                <?php foreach ( $_nav_data as $i => $entry ) :
                    if ( empty( $entry['wc_subs'] ) && empty( $entry['children'] ) ) continue;
                ?>
                <div class="sl-mega-panel<?php echo $i === 0 ? ' is-active' : ''; ?>"
                     id="sl-panel-<?php echo esc_attr( $entry['id'] ); ?>">

                    <div class="sl-mega-panel-inner">
                        <div class="sl-mega-subs">
                            <a class="sl-mega-sub sl-mega-sub--all"
                               href="<?php echo esc_url( $entry['url'] ); ?>">
                                Tất cả <?php echo esc_html( $entry['label'] ); ?>
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24"
                                     fill="none" stroke="currentColor" stroke-width="2.5"
                                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <line x1="5" y1="12" x2="19" y2="12"/>
                                    <polyline points="12 5 19 12 12 19"/>
                                </svg>
                            </a>

                            <?php if ( ! empty( $entry['wc_subs'] ) ) :
                                // Product category: show WC subcategories with thumbnails
                                foreach ( $entry['wc_subs'] as $_sub ) :
                                    $_sub_thumb_id = get_term_meta( $_sub->term_id, 'thumbnail_id', true );
                                    $_sub_img      = $_sub_thumb_id ? wp_get_attachment_image( $_sub_thumb_id, [120, 120], false, ['alt' => esc_attr( $_sub->name ), 'loading' => 'lazy'] ) : '';
                            ?>
                            <a class="sl-mega-sub<?php echo $_sub_img ? ' sl-mega-sub--has-img' : ''; ?>"
                               href="<?php echo esc_url( get_term_link( $_sub ) ); ?>">
                                <?php if ( $_sub_img ) : ?>
                                <div class="sl-mega-sub-img"><?php echo $_sub_img; ?></div>
                                <?php endif; ?>
                                <span class="sl-mega-sub-name"><?php echo esc_html( $_sub->name ); ?></span>
                            </a>
                            <?php endforeach;
                            else :
                                // Non-category item: use explicit menu children as links
                                foreach ( $entry['children'] as $_child ) :
                            ?>
                            <a class="sl-mega-sub"
                               href="<?php echo esc_url( $_child->url ); ?>">
                                <span class="sl-mega-sub-name"><?php echo esc_html( $_child->title ); ?></span>
                            </a>
                            <?php endforeach; endif; ?>
                        </div>

                    </div>

                </div>
                <?php endforeach; ?>
            </div>

        </div><!-- /.sl-mega-inner -->
    </nav><!-- /.sl-mega -->

</header><!-- /.sl-header -->

<div class="sl-mega-backdrop" id="sl-mega-backdrop" aria-hidden="true"></div>

<!-- ═══════════════ CONTENT ═══════════════ -->
<div id="page" class="hfeed site">
<?php astra_content_before(); ?>
<div id="content" class="site-content">
    <div class="ast-container">
    <?php astra_content_top(); ?>
