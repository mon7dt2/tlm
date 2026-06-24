<?php
/**
 * Shortcode: [saltlux_dealer_map]
 * Renders the full dealer locator — search panel + province sidebar + Leaflet map.
 */

// Add body class early (before <body> is printed).
add_filter( 'body_class', function ( $classes ) {
    if ( ! is_singular() ) return $classes;
    $post = get_queried_object();
    if ( ! $post ) return $classes;
    $in_content  = has_shortcode( $post->post_content, 'saltlux_dealer_map' );
    $el_data     = get_post_meta( $post->ID, '_elementor_data', true );
    $in_elementor = $el_data && strpos( $el_data, 'saltlux_dealer_map' ) !== false;
    if ( $in_content || $in_elementor ) {
        $classes[] = 'dl-fullwidth-page';
    }
    return array_unique( $classes );
} );

add_shortcode( 'saltlux_dealer_map', 'sv2_shortcode_dealer_map' );
function sv2_shortcode_dealer_map( $atts = [] ) {
    $atts   = shortcode_atts( [ 'title' => '__auto__', 'layout' => '' ], $atts, 'saltlux_dealer_map' );
	$layout = $atts['layout'];
	if ( ! $layout && ( is_front_page() || is_home() ) ) {
		$layout = 'slider';
	}

    if ( ! function_exists( 'saltlux_build_dealers_array' ) ) {
        return '<p>saltlux_build_dealers_array() không tồn tại.</p>';
    }

    $all_dealers = saltlux_build_dealers_array();

    $province_counts = [];
    foreach ( $all_dealers as $d ) {
        if ( $d['province'] ) {
            $province_counts[ $d['province'] ] = ( $province_counts[ $d['province'] ] ?? 0 ) + 1;
        }
    }

    $regions     = get_terms( [ 'taxonomy' => 'saltlux_province', 'parent' => 0, 'hide_empty' => false, 'orderby' => 'term_order' ] );
    $region_tree = [];
    foreach ( (array) $regions as $region ) {
        $children      = get_terms( [ 'taxonomy' => 'saltlux_province', 'parent' => $region->term_id, 'hide_empty' => false, 'orderby' => 'name' ] );
        $region_tree[] = [ 'region' => $region, 'provinces' => is_wp_error( $children ) ? [] : $children ];
    }

    ob_start();

    // Schema.org JSON-LD — valid in <body> per spec
    if ( ! empty( $all_dealers ) ) {
        $items = [];
        foreach ( $all_dealers as $i => $d ) {
            $item = [ '@type' => 'LocalBusiness', 'name' => $d['name'] ];
            if ( $d['address'] ) $item['address']   = $d['address'];
            if ( $d['phone'] )   $item['telephone'] = $d['phone'];
            if ( $d['website'] ) $item['url']       = $d['website'];
            $items[] = [ '@type' => 'ListItem', 'position' => $i + 1, 'item' => $item ];
        }
        echo '<script type="application/ld+json">' . wp_json_encode( [
            '@context'        => 'https://schema.org',
            '@type'           => 'ItemList',
            'name'            => 'Hệ thống đại lý TaylorMade Việt Nam',
            'numberOfItems'   => count( $items ),
            'itemListElement' => $items,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
    }
    ?>
    <div class="dl-page">

        <?php
        $heading = ( $atts['title'] === '__auto__' ) ? get_the_title() : $atts['title'];
        if ( $heading ) : ?>
        <h1 class="dl-page-title"><?php echo esc_html( $heading ); ?></h1>
        <?php endif; ?>

        <div class="dl-search-panel">
            <div class="dl-search-col">
                <label class="dl-search-label" for="dl-keyword">Tìm theo địa chỉ / tên đại lý</label>
                <div class="dl-input-wrap">
                    <input type="text" id="dl-keyword" class="dl-input"
                           placeholder="Địa chỉ, tên đại lý, tỉnh/thành…" autocomplete="off">
                    <button type="button" class="dl-btn-search" id="dl-btn-keyword">Tìm</button>
                </div>
            </div>
            <div class="dl-search-col dl-search-col--locate">
                <label class="dl-search-label">Vị trí hiện tại</label>
                <button type="button" class="dl-btn-locate" id="dl-btn-locate">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2.5"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="3"/><path d="M12 1v4M12 19v4M1 12h4M19 12h4"/>
                    </svg>
                    Tìm gần tôi
                </button>
            </div>
        </div>

        <div class="dl-layout">

            <aside class="dl-sidebar" aria-label="Danh sách tỉnh/thành phố">
                <div class="dl-sidebar-header">
                    <span class="dl-sidebar-count">
                        <span id="dl-visible-count"><?php echo count( $all_dealers ); ?></span>
                        / <?php echo count( $all_dealers ); ?> đại lý
                    </span>
                    <button type="button" class="dl-show-all" id="dl-show-all">Tất cả</button>
                </div>

                <?php if ( ! empty( $region_tree ) ) : ?>
                    <nav class="dl-region-list">
                        <?php foreach ( $region_tree as $g ) :
                            $has_dealers = false;
                            foreach ( $g['provinces'] as $p ) {
                                if ( ( $province_counts[ $p->name ] ?? 0 ) > 0 ) { $has_dealers = true; break; }
                            }
                            if ( ! $has_dealers ) continue;
                        ?>
                        <div class="dl-region-group">
                            <h3 class="dl-region-label"><?php echo esc_html( $g['region']->name ); ?></h3>
                            <?php foreach ( $g['provinces'] as $p ) :
                                $count = $province_counts[ $p->name ] ?? 0;
                                if ( $count === 0 ) continue;
                            ?>
                            <button type="button"
                                    class="dl-province-btn"
                                    data-province="<?php echo esc_attr( $p->name ); ?>">
                                <span class="dl-province-name"><?php echo esc_html( $p->name ); ?></span>
                                <span class="dl-province-count"><?php echo $count; ?></span>
                            </button>
                            <?php endforeach; ?>
                        </div>
                        <?php endforeach; ?>
                    </nav>
                <?php endif; ?>
            </aside>

            <div class="dl-map-wrap">
                <div id="dl-leaflet-map" aria-label="Bản đồ đại lý TaylorMade"></div>
            </div>

        </div><!-- /.dl-layout -->

        <div id="dl-dealer-list" class="dl-dealer-list"<?php echo $layout ? ' data-layout="' . esc_attr( $layout ) . '"'
   : ''; ?> aria-label="Danh sách đại lý"></div>

    </div><!-- /.dl-page -->
    <?php
    return ob_get_clean();
}
