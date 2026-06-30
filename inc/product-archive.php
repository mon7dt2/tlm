<?php
/**
 * Product archive / category listing — JP-style layout.
 */

defined( 'ABSPATH' ) || exit;

function sv2_is_shop_listing() {
    return is_shop() || is_product_taxonomy();
}

function sv2_shop_archive_setup() {
    if ( ! sv2_is_shop_listing() ) {
        return;
    }

    remove_action( 'woocommerce_before_shop_loop_item', 'woocommerce_template_loop_product_link_open', 10 );
    remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_product_link_close', 5 );
    remove_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_show_product_loop_sale_flash', 10 );
    remove_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail', 10 );
    remove_action( 'woocommerce_shop_loop_item_title', 'woocommerce_template_loop_product_title', 10 );
    remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_rating', 5 );
    remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10 );
    remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );

    remove_action( 'woocommerce_shop_loop_header', 'woocommerce_product_taxonomy_archive_header', 10 );
    remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );

    // Remove WC defaults + any priority Astra Addon may have re-added them at (13, 14, 17, 18).
    foreach ( array( 5, 10, 13, 14, 15, 17, 18, 20, 25, 30 ) as $p ) {
        remove_action( 'woocommerce_before_shop_loop', 'woocommerce_result_count', $p );
        remove_action( 'woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', $p );
    }

    // Remove Astra Addon's toolbar wrapper divs (prevent broken/unclosed div output).
    // We scan the registered callbacks directly to avoid relying on get_instance().
    global $wp_filter;
    if ( isset( $wp_filter['woocommerce_before_shop_loop'] ) ) {
        $astra_methods = array(
            'before_shop_loop_starts_wrapper',
            'shop_toolbar_aside_starts_wrapper',
            'shop_toolbar_elements_ends_wrapper',
            'add_views_layout_support',
            'off_canvas_button',
            'off_canvas_applied_filters',
            'shop_filter_markup',
        );
        foreach ( $wp_filter['woocommerce_before_shop_loop']->callbacks as $priority => $callbacks ) {
            foreach ( $callbacks as $callback ) {
                if ( ! is_array( $callback['function'] ) ) {
                    continue;
                }
                $method = $callback['function'][1] ?? '';
                if ( in_array( $method, $astra_methods, true ) ) {
                    remove_action( 'woocommerce_before_shop_loop', $callback['function'], $priority );
                }
            }
        }
    }
}
// Run at 999 so we execute after Astra Addon's wp@71 which re-adds the toolbar elements.
add_action( 'wp', 'sv2_shop_archive_setup', 999 );

add_filter( 'astra_apply_hero_header_banner', function( $apply ) {
    return sv2_is_shop_listing() ? false : $apply;
} );

add_filter( 'astra_page_layout', function( $layout ) {
    if ( sv2_is_shop_listing() ) {
        return 'no-sidebar';
    }
    return $layout;
}, 20 );

add_filter( 'astra_get_store_sidebar_layout', function( $layout ) {
    if ( sv2_is_shop_listing() ) {
        return 'no-sidebar';
    }
    return $layout;
}, 20 );

add_filter( 'astra_get_store_content_layout', function( $layout ) {
    if ( sv2_is_shop_listing() ) {
        return 'full-width-container';
    }
    return $layout;
}, 20 );

add_filter( 'body_class', function( $classes ) {
    if ( sv2_is_shop_listing() ) {
        $classes = array_values(
            array_filter(
                $classes,
                function( $class ) {
                    return ! in_array( $class, array( 'ast-left-sidebar', 'ast-right-sidebar' ), true );
                }
            )
        );

        $classes[] = 'sv2-shop-archive';
        $classes[] = 'sv2-shop-fullwidth';
        $classes[] = 'ast-no-sidebar';
    }
    return $classes;
} );

function sv2_get_shop_header_context() {
    if ( is_tax( 'product_cat' ) ) {
        $term = get_queried_object();
        if ( ! $term instanceof WP_Term ) {
            return null;
        }

        return array(
            'title'       => $term->name,
            'description' => term_description( $term->term_id, 'product_cat' ),
            'term'        => $term,
        );
    }

    if ( is_shop() ) {
        $shop_id = wc_get_page_id( 'shop' );
        return array(
            'title'       => woocommerce_page_title( false ),
            'description' => $shop_id > 0 ? apply_filters( 'the_content', get_post_field( 'post_content', $shop_id ) ) : '',
            'term'        => null,
        );
    }

    return null;
}

function sv2_get_shop_subnav_items() {
    $items = array();

    if ( is_tax( 'product_cat' ) ) {
        $current = get_queried_object();
        if ( ! $current instanceof WP_Term ) {
            return $items;
        }

        $parent_id = (int) $current->parent;
        $nav_root  = $parent_id ? get_term( $parent_id, 'product_cat' ) : $current;

        if ( ! $nav_root || is_wp_error( $nav_root ) ) {
            return $items;
        }

        $children = get_terms(
            array(
                'taxonomy'   => 'product_cat',
                'hide_empty' => true,
                'parent'     => (int) $nav_root->term_id,
                'orderby'    => 'menu_order',
                'order'      => 'ASC',
            )
        );

        if ( empty( $children ) || is_wp_error( $children ) ) {
            return $items;
        }

        $items[] = array(
            'label'  => 'TẤT CẢ',
            'url'    => get_term_link( $nav_root ),
            'active' => (int) $current->term_id === (int) $nav_root->term_id,
        );

        foreach ( $children as $child ) {
            $items[] = array(
                'label'  => $child->name,
                'url'    => get_term_link( $child ),
                'active' => (int) $current->term_id === (int) $child->term_id,
            );
        }

        return $items;
    }

    if ( is_shop() ) {
        $top_terms = get_terms(
            array(
                'taxonomy'   => 'product_cat',
                'hide_empty' => true,
                'parent'     => 0,
                'exclude'    => array( (int) get_option( 'default_product_cat' ) ),
                'orderby'    => 'menu_order',
                'order'      => 'ASC',
            )
        );

        if ( empty( $top_terms ) || is_wp_error( $top_terms ) ) {
            return $items;
        }

        foreach ( $top_terms as $term ) {
            $items[] = array(
                'label'  => $term->name,
                'url'    => get_term_link( $term ),
                'active' => false,
            );
        }
    }

    return $items;
}

function sv2_shop_archive_header() {
    if ( ! sv2_is_shop_listing() ) {
        return;
    }

    $context = sv2_get_shop_header_context();
    if ( ! $context ) {
        return;
    }

    $subnav = sv2_get_shop_subnav_items();
    ?>
    <div class="sv2-shop-header">
        <h1 class="sv2-shop-header__title"><?php echo esc_html( $context['title'] ); ?></h1>
        <?php if ( ! empty( trim( wp_strip_all_tags( $context['description'] ) ) ) ) : ?>
            <div class="sv2-shop-header__desc"><?php echo wp_kses_post( wpautop( $context['description'] ) ); ?></div>
        <?php endif; ?>

        <?php if ( $subnav ) : ?>
            <nav class="sv2-shop-subnav" aria-label="<?php esc_attr_e( 'Danh mục con', 'saltlux-v2' ); ?>">
                <?php foreach ( $subnav as $item ) : ?>
                    <a
                        class="sv2-shop-subnav__pill<?php echo $item['active'] ? ' is-active' : ''; ?>"
                        href="<?php echo esc_url( $item['url'] ); ?>"
                        <?php echo $item['active'] ? ' aria-current="page"' : ''; ?>
                    ><?php echo esc_html( $item['label'] ); ?></a>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>
    </div>
    <?php
}
function sv2_shop_archive_open_wrap() {
    if ( ! sv2_is_shop_listing() ) {
        return;
    }
    echo '<div class="sv2-shop-wrap">';
}
add_action( 'woocommerce_before_main_content', 'sv2_shop_archive_open_wrap', 11 );
add_action( 'woocommerce_before_main_content', 'sv2_shop_archive_header', 15 );

function sv2_shop_archive_close_wrap() {
    if ( ! sv2_is_shop_listing() ) {
        return;
    }
    echo '</div><!-- .sv2-shop-wrap -->';
}
add_action( 'woocommerce_after_main_content', 'sv2_shop_archive_close_wrap', 9 );

function sv2_shop_archive_toolbar() {
    if ( ! sv2_is_shop_listing() ) {
        return;
    }
    ?>
    <div class="sv2-shop-toolbar">
        <div class="sv2-shop-toolbar__left">
            <?php woocommerce_result_count(); ?>
        </div>
        <div class="sv2-shop-toolbar__right">
            <?php do_action( 'sv2_shop_filter_button' ); ?>
        </div>
    </div>
    <?php
}
add_action( 'woocommerce_before_shop_loop', 'sv2_shop_archive_toolbar', 15 );

add_action( 'sv2_shop_filter_button', function() {
    if ( function_exists( 'woocommerce_catalog_ordering' ) ) {
        woocommerce_catalog_ordering();
    }
}, 20 );

function sv2_shop_archive_footer_content() {
    if ( ! sv2_is_shop_listing() ) {
        return;
    }

    $content = '';
    if ( is_tax( 'product_cat' ) ) {
        $term = get_queried_object();
        if ( $term instanceof WP_Term && function_exists( 'get_field' ) ) {
            $content = get_field( 'category_footer_content', 'product_cat_' . $term->term_id );
        }
    } elseif ( is_shop() ) {
        $shop_id = wc_get_page_id( 'shop' );
        if ( $shop_id > 0 && function_exists( 'get_field' ) ) {
            $content = get_field( 'shop_footer_content', $shop_id );
        }
    }

    if ( empty( trim( wp_strip_all_tags( $content ) ) ) ) {
        return;
    }
    ?>
    <section class="sv2-shop-seo" data-sv2-readmore>
        <div class="sv2-shop-seo__inner is-collapsed">
            <div class="sv2-shop-seo__content">
                <?php echo wp_kses_post( wpautop( $content ) ); ?>
            </div>
        </div>
        <button type="button" class="sv2-shop-seo__toggle" aria-expanded="false">
            <span class="sv2-shop-seo__toggle-more"><?php esc_html_e( 'Xem thêm', 'saltlux-v2' ); ?></span>
            <span class="sv2-shop-seo__toggle-less"><?php esc_html_e( 'Thu gọn', 'saltlux-v2' ); ?></span>
        </button>
    </section>
    <?php
}
add_action( 'woocommerce_after_main_content', 'sv2_shop_archive_footer_content', 8 );

function sv2_get_product_hover_image_url( WC_Product $product ) {
    if ( function_exists( 'get_field' ) ) {
        $hover = get_field( 'product_hover_image', $product->get_id() );
        if ( is_array( $hover ) && ! empty( $hover['url'] ) ) {
            return $hover['url'];
        }
        if ( is_numeric( $hover ) ) {
            $url = wp_get_attachment_image_url( (int) $hover, 'woocommerce_single' );
            if ( $url ) {
                return $url;
            }
        }
    }

    $gallery = $product->get_gallery_image_ids();
    if ( ! empty( $gallery[0] ) ) {
        $url = wp_get_attachment_image_url( $gallery[0], 'woocommerce_single' );
        if ( $url ) {
            return $url;
        }
    }

    return '';
}

function sv2_get_product_card_swatches( WC_Product $product, $limit = 5 ) {
    $swatches = array();
    $seen_ids = array();

    if ( $product->is_type( 'variable' ) ) {
        $variations = $product->get_available_variations();
        foreach ( $variations as $variation ) {
            $image_id = (int) ( $variation['image_id'] ?? 0 );
            if ( ! $image_id || in_array( $image_id, $seen_ids, true ) ) {
                continue;
            }
            $thumb = wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' );
            if ( ! $thumb ) {
                continue;
            }
            $seen_ids[] = $image_id;
            $full = wp_get_attachment_image_url( $image_id, 'woocommerce_single' );
            $swatches[] = array(
                'src'  => $thumb,
                'full' => $full ?: $thumb,
                'alt'  => ! empty( $variation['image']['alt'] ) ? $variation['image']['alt'] : $product->get_name(),
            );
            if ( count( $swatches ) >= $limit ) {
                break;
            }
        }
    } else {
        foreach ( $product->get_gallery_image_ids() as $attachment_id ) {
            $attachment_id = (int) $attachment_id;
            if ( in_array( $attachment_id, $seen_ids, true ) ) {
                continue;
            }
            $thumb = wp_get_attachment_image_url( $attachment_id, 'woocommerce_thumbnail' );
            if ( ! $thumb ) {
                continue;
            }
            $seen_ids[] = $attachment_id;
            $full = wp_get_attachment_image_url( $attachment_id, 'woocommerce_single' );
            $swatches[] = array(
                'src'  => $thumb,
                'full' => $full ?: $thumb,
                'alt'  => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ?: $product->get_name(),
            );
            if ( count( $swatches ) >= $limit ) {
                break;
            }
        }
    }

    return $swatches;
}

// ---------------------------------------------------------------------------
// "New" flag — admin checkbox on the product + helper for the card
// ---------------------------------------------------------------------------

/**
 * Whether a product is flagged as new (shows the red "New" label on the card).
 */
function sv2_product_is_new( WC_Product $product ) {
    return 'yes' === $product->get_meta( '_sv2_is_new' );
}

function sv2_update_product_new_flag( $post_id, $is_new ) {
    update_post_meta( (int) $post_id, '_sv2_is_new', $is_new ? 'yes' : 'no' );
}

function sv2_sync_product_new_from_elementor_settings( $post_id, $settings ) {
    if ( ! is_array( $settings ) || ! array_key_exists( 'sv2_is_new', $settings ) ) {
        return;
    }

    sv2_update_product_new_flag( $post_id, 'yes' === $settings['sv2_is_new'] );
}

add_action( 'init', function() {
    register_post_meta( 'product', '_sv2_is_new', array(
        'type'              => 'string',
        'single'            => true,
        'default'           => 'no',
        'show_in_rest'      => true,
        'sanitize_callback' => function( $value ) {
            return 'yes' === $value ? 'yes' : 'no';
        },
        'auth_callback'     => function() {
            return current_user_can( 'edit_products' );
        },
    ) );
} );

// Admin: render the checkbox on the product data > General tab.
add_action( 'woocommerce_product_options_general_product_data', function() {
    woocommerce_wp_checkbox( array(
        'id'          => '_sv2_is_new',
        'label'       => __( 'Sản phẩm mới (New)', 'saltlux-v2' ),
        'description' => __( 'Hiển thị nhãn "New" màu đỏ trên thẻ sản phẩm ở trang danh mục. Bỏ tích khi sản phẩm không còn mới.', 'saltlux-v2' ),
        'desc_tip'    => true,
    ) );
} );

// Admin: persist the checkbox value (WooCommerce saves the object after this hook).
add_action( 'woocommerce_admin_process_product_object', function( $product ) {
    $product->update_meta_data( '_sv2_is_new', isset( $_POST['_sv2_is_new'] ) ? 'yes' : 'no' );
} );

add_action( 'add_meta_boxes_product', function() {
    add_meta_box(
        'sv2_product_badges',
        __( 'Nhãn sản phẩm', 'saltlux-v2' ),
        'sv2_render_product_badges_metabox',
        'product',
        'side',
        'high'
    );
} );

function sv2_render_product_badges_metabox( $post ) {
    $is_new = 'yes' === get_post_meta( $post->ID, '_sv2_is_new', true );

    wp_nonce_field( 'sv2_save_product_badges', 'sv2_product_badges_nonce' );
    ?>
    <p>
        <label for="sv2_is_new_metabox">
            <input
                type="checkbox"
                id="sv2_is_new_metabox"
                name="_sv2_is_new"
                value="yes"
                <?php checked( $is_new ); ?>
            />
            <?php esc_html_e( 'Hiển thị chữ New ở danh mục sản phẩm', 'saltlux-v2' ); ?>
        </label>
    </p>
    <p class="description"><?php esc_html_e( 'Bỏ tick khi sản phẩm không còn mới.', 'saltlux-v2' ); ?></p>
    <script>
    (function(){
        document.addEventListener('change', function(event) {
            if (!event.target.matches('input[name="_sv2_is_new"]')) return;
            document.querySelectorAll('input[name="_sv2_is_new"]').forEach(function(input) {
                input.checked = event.target.checked;
            });
        });
    })();
    </script>
    <?php
}

add_action( 'save_post_product', function( $post_id ) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    if ( wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    if (
        ! isset( $_POST['sv2_product_badges_nonce'] )
        || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sv2_product_badges_nonce'] ) ), 'sv2_save_product_badges' )
    ) {
        return;
    }

    sv2_update_product_new_flag( $post_id, isset( $_POST['_sv2_is_new'] ) );
} );

add_action( 'elementor/documents/register_controls', function( $document ) {
    if ( ! class_exists( '\Elementor\Controls_Manager' ) || ! is_object( $document ) || ! method_exists( $document, 'get_main_id' ) ) {
        return;
    }

    $post_id = (int) $document->get_main_id();
    if ( ! $post_id || 'product' !== get_post_type( $post_id ) ) {
        return;
    }

    $document->start_controls_section(
        'sv2_product_badges_section',
        array(
            'label' => __( 'Nhãn sản phẩm', 'saltlux-v2' ),
            'tab'   => \Elementor\Controls_Manager::TAB_SETTINGS,
        )
    );

    $document->add_control(
        'sv2_is_new',
        array(
            'label'        => __( 'Hiển thị New', 'saltlux-v2' ),
            'type'         => \Elementor\Controls_Manager::SWITCHER,
            'label_on'     => __( 'Có', 'saltlux-v2' ),
            'label_off'    => __( 'Không', 'saltlux-v2' ),
            'return_value' => 'yes',
            'default'      => 'yes' === get_post_meta( $post_id, '_sv2_is_new', true ) ? 'yes' : '',
        )
    );

    $document->end_controls_section();
} );

add_action( 'elementor/document/after_save', function( $document, $data = null ) {
    if ( ! is_object( $document ) || ! method_exists( $document, 'get_main_id' ) ) {
        return;
    }

    $post_id = (int) $document->get_main_id();
    if ( ! $post_id || 'product' !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    $settings = array();
    if ( is_array( $data ) && isset( $data['settings'] ) && is_array( $data['settings'] ) ) {
        $settings = $data['settings'];
    } elseif ( method_exists( $document, 'get_settings' ) ) {
        $document_settings = $document->get_settings();
        if ( is_array( $document_settings ) ) {
            $settings = $document_settings;
        }
    }

    if ( ! array_key_exists( 'sv2_is_new', $settings ) ) {
        return;
    }

    sv2_sync_product_new_from_elementor_settings( $post_id, $settings );
}, 10, 2 );

add_action( 'added_post_meta', 'sv2_sync_product_new_from_elementor_page_settings_meta', 10, 4 );
add_action( 'updated_post_meta', 'sv2_sync_product_new_from_elementor_page_settings_meta', 10, 4 );

function sv2_sync_product_new_from_elementor_page_settings_meta( $meta_id, $post_id, $meta_key, $meta_value ) {
    if ( '_elementor_page_settings' !== $meta_key || 'product' !== get_post_type( $post_id ) ) {
        return;
    }

    sv2_sync_product_new_from_elementor_settings( $post_id, $meta_value );
}

function sv2_render_product_card_rating( WC_Product $product ) {
    $rating = (float) $product->get_average_rating();
    $count  = (int) $product->get_review_count();

    if ( $rating <= 0 && $count <= 0 ) {
        return;
    }

    echo '<div class="sv2-card__rating">';
    echo wc_get_rating_html( $rating, $count ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    if ( $count > 0 ) {
        echo '<span class="sv2-card__review-count">(' . esc_html( number_format_i18n( $count ) ) . ')</span>';
    }
    echo '</div>';
}

function sv2_render_product_card( WC_Product $product ) {
    $permalink   = get_permalink( $product->get_id() );
    $primary_id  = $product->get_image_id();
    $primary_src = $primary_id ? wp_get_attachment_image_url( $primary_id, 'woocommerce_single' ) : wc_placeholder_img_src( 'woocommerce_single' );
    $hover_src   = sv2_get_product_hover_image_url( $product );
    $swatches    = sv2_get_product_card_swatches( $product );
    $is_new      = sv2_product_is_new( $product );
    ?>
    <div class="sv2-product-card__inner">
        <div class="sv2-card__media">
            <a class="sv2-card__link" href="<?php echo esc_url( $permalink ); ?>" aria-label="<?php echo esc_attr( $product->get_name() ); ?>">
                <?php if ( $product->is_on_sale() ) : ?>
                    <?php woocommerce_show_product_loop_sale_flash(); ?>
                <?php endif; ?>
                <span class="sv2-card__image-wrap<?php echo $hover_src ? ' has-hover' : ''; ?>">
                    <img
                        class="sv2-card__img sv2-card__img--primary"
                        src="<?php echo esc_url( $primary_src ); ?>"
                        alt="<?php echo esc_attr( $product->get_name() ); ?>"
                        loading="lazy"
                        decoding="async"
                    />
                    <?php if ( $hover_src ) : ?>
                        <img
                            class="sv2-card__img sv2-card__img--hover"
                            src="<?php echo esc_url( $hover_src ); ?>"
                            alt=""
                            loading="lazy"
                            decoding="async"
                        />
                    <?php endif; ?>
                </span>
            </a>

            <?php if ( $swatches ) : ?>
                <div class="sv2-card__swatches" role="list" aria-label="<?php esc_attr_e( 'Màu sắc / biến thể', 'saltlux-v2' ); ?>">
                    <?php foreach ( $swatches as $swatch ) : ?>
                        <span class="sv2-card__swatch"
                              role="listitem"
                              data-img="<?php echo esc_url( $swatch['full'] ); ?>"
                              title="<?php echo esc_attr( $swatch['alt'] ); ?>">
                            <img src="<?php echo esc_url( $swatch['src'] ); ?>"
                                 alt="<?php echo esc_attr( $swatch['alt'] ); ?>"
                                 loading="lazy" decoding="async" />
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="sv2-card__body">
            <h2 class="woocommerce-loop-product__title sv2-card__title">
                <a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $product->get_name() ); ?></a>
            </h2>
            <?php if ( $price_html = $product->get_price_html() ) : ?>
                <span class="price sv2-card__price"><?php echo wp_kses_post( $price_html ); ?></span>
            <?php endif; ?>
            <?php sv2_render_product_card_rating( $product ); ?>
            <?php if ( $is_new ) : ?>
                <p class="sv2-card__tagline sv2-card__new"><?php esc_html_e( 'New', 'saltlux-v2' ); ?></p>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

function sv2_render_shop_loop_product_card() {
    if ( ! sv2_is_shop_listing() ) {
        return;
    }

    global $product;
    if ( ! $product instanceof WC_Product ) {
        return;
    }

    sv2_render_product_card( $product );
}
add_action( 'woocommerce_before_shop_loop_item', 'sv2_render_shop_loop_product_card', 10 );

function sv2_shop_archive_assets() {
    if ( ! sv2_is_shop_listing() ) {
        return;
    }

    $uri = get_stylesheet_directory_uri();
    $ver = wp_get_theme()->get( 'Version' );

    wp_enqueue_script( 'sv2-shop-archive', $uri . '/assets/js/shop-archive.js', array( 'jquery' ), $ver, true );
}
add_action( 'wp_enqueue_scripts', 'sv2_shop_archive_assets', 30 );

add_action( 'wp_head', function() {
    if ( ! sv2_is_shop_listing() ) {
        return;
    }
    echo '<style id="sv2-shop-critical">
.sv2-shop-fullwidth #secondary,.sv2-shop-fullwidth .widget-area{display:none!important}
.sv2-shop-fullwidth #primary,.sv2-shop-fullwidth #content,.sv2-shop-fullwidth .site-content{width:100%!important;max-width:100%!important;float:none!important}
.sv2-shop-fullwidth .site-content>.ast-container,.sv2-shop-fullwidth #primary>.ast-container,.sv2-shop-fullwidth .ast-woocommerce-container{max-width:100%!important;width:100%!important}
.sv2-shop-wrap{width:100%;max-width:100%;box-sizing:border-box}
</style>';
}, 99 );
