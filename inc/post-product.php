<?php
/**
 * Post featured product — ACF field, block renderer, content filter, asset enqueue.
 */

// ---------------------------------------------------------------------------
// ACF field group: one product picker on each post
// ---------------------------------------------------------------------------
add_action( 'acf/include_fields', function() {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) {
        return;
    }
    acf_add_local_field_group( array(
        'key'    => 'group_post_featured_product',
        'title'  => 'Sản phẩm trong bài viết',
        'fields' => array(
            array(
                'key'           => 'field_post_featured_product',
                'label'         => 'Sản phẩm',
                'name'          => 'post_featured_product',
                'type'          => 'post_object',
                'instructions'  => 'Chọn sản phẩm rồi dùng shortcode [post_product id="ID_SẢN_PHẨM"] để chèn card sản phẩm vào bất kỳ vị trí nào trong nội dung bài viết.',
                'required'      => 0,
                'post_type'     => array( 'product' ),
                'taxonomy'      => '',
                'allow_null'    => 1,
                'multiple'      => 0,
                'return_format' => 'object',
                'ui'            => 1,
            ),
        ),
        'location' => array(
            array(
                array(
                    'param'    => 'post_type',
                    'operator' => '==',
                    'value'    => 'post',
                ),
            ),
        ),
        'menu_order'      => 5,
        'position'        => 'side',
        'style'           => 'default',
        'label_placement' => 'top',
    ) );
} );

// ---------------------------------------------------------------------------
// Helper — find the page using page-dealers.php template
// ---------------------------------------------------------------------------
function sv2_get_dealer_page_url() {
//     $pages = get_posts( array(
//         'post_type'      => 'page',
//         'post_status'    => 'publish',
//         'posts_per_page' => 1,
//         'meta_key'       => '_wp_page_template',
//         'meta_value'     => 'page-dealers.php',
//         'no_found_rows'  => true,
//         'fields'         => 'ids',
//     ) );
//     if ( $pages ) {
//         return get_permalink( $pages[0] );
//     }
    return home_url( '/' . SV2_PAGE_SLUG_DEALERS . '/' );
}

// ---------------------------------------------------------------------------
// Render the product block
// ---------------------------------------------------------------------------
function sv2_render_post_product_block( $product_post ) {
    if ( ! function_exists( 'wc_get_product' ) ) {
        return '';
    }
    $wc = wc_get_product( $product_post->ID );
    if ( ! $wc ) {
        return '';
    }

    $name        = $wc->get_name();
    $product_url = get_permalink( $product_post->ID );
    $dealer_url  = add_query_arg( 'locate', '1', sv2_get_dealer_page_url() );
    $price_html  = $wc->get_price_html();

    $thumb_id   = $wc->get_image_id();
    $thumb_html = $thumb_id
        ? wp_get_attachment_image( $thumb_id, 'woocommerce_thumbnail', false, array(
            'alt'     => esc_attr( $name ),
            'loading' => 'lazy',
        ) )
        : '';

    $cats     = wc_get_product_category_list( $product_post->ID );
    $cat_html = $cats ? '<div class="sv2-pp-cat">' . wp_kses_post( $cats ) . '</div>' : '';

    ob_start();
    ?>
    <div class="sv2-post-product" itemscope itemtype="https://schema.org/Product">
        <?php if ( $thumb_html ) : ?>
        <a class="sv2-pp-image" href="<?php echo esc_url( $product_url ); ?>" tabindex="-1" aria-hidden="true">
            <?php echo $thumb_html; ?>
        </a>
        <?php endif; ?>
        <div class="sv2-pp-body">
            <?php echo $cat_html; ?>
            <h3 class="sv2-pp-name" itemprop="name">
                <a href="<?php echo esc_url( $product_url ); ?>"><?php echo esc_html( $name ); ?></a>
            </h3>
            <?php if ( $price_html ) : ?>
            <div class="sv2-pp-price" itemprop="offers" itemscope itemtype="https://schema.org/Offer">
                <meta itemprop="priceCurrency" content="VND">
                <?php echo $price_html; ?>
            </div>
            <?php endif; ?>
            <div class="sv2-pp-actions">
                <a class="sv2-pp-btn sv2-pp-btn--primary" href="<?php echo esc_url( $product_url ); ?>">
                    Xem sản phẩm
                </a>
                <a class="sv2-pp-btn sv2-pp-btn--locate" href="<?php echo esc_url( $dealer_url ); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    Tìm gần tôi
                </a>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// ---------------------------------------------------------------------------
// Shortcode: [post_product id="123"]
// ---------------------------------------------------------------------------
add_shortcode( 'post_product', function( $atts ) {
    $atts = shortcode_atts( array( 'id' => 0 ), $atts, 'post_product' );
    $id   = absint( $atts['id'] );
    if ( ! $id ) {
        return '';
    }
    $product_post = get_post( $id );
    if ( ! $product_post || $product_post->post_type !== 'product' ) {
        return '';
    }
    sv2_post_product_enqueue_assets();
    return sv2_render_post_product_block( $product_post );
} );

// ---------------------------------------------------------------------------
// Enqueue CSS on single posts
// ---------------------------------------------------------------------------
function sv2_post_product_enqueue_assets() {
    static $enqueued = false;
    if ( $enqueued ) {
        return;
    }
    $uri = get_stylesheet_directory_uri();
    $ver = wp_get_theme()->get( 'Version' );
    // No dep on 'child-style' — keep this standalone so an asset optimizer / cache
    // combine dropping the base handle can't take this stylesheet down with it.
    wp_enqueue_style( 'sv2-post-product', $uri . '/assets/css/post-product.css', array(), $ver );
    $enqueued = true;
}

add_action( 'wp_enqueue_scripts', function() {
    if ( ! is_singular( 'post' ) ) {
        return;
    }
    sv2_post_product_enqueue_assets();
} );
