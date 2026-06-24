<?php
/**
 * Single post — TaylorMade editorial layout.
 * Hero · Header (category + title + meta) · Body · Tags · Related articles
 */

defined( 'ABSPATH' ) || exit;

// ── Enqueue ───────────────────────────────────────────────────────────────
add_action( 'wp_enqueue_scripts', function () {
    if ( ! is_singular( 'post' ) ) return;
    $uri = get_stylesheet_directory_uri();
    $ver = wp_get_theme()->get( 'Version' );
    // No dep on 'child-style' — standalone so a dropped base handle can't cascade-remove these.
    wp_enqueue_style( 'sv2-single-post', $uri . '/assets/css/single-post.css', array(), $ver );
    wp_enqueue_style( 'sv2-news-archive', $uri . '/assets/css/news-archive.css', array(), $ver );
} );

// ── Body class ────────────────────────────────────────────────────────────
add_filter( 'body_class', function ( $classes ) {
    if ( is_singular( 'post' ) ) {
        $classes[] = 'sv2-single-post';
    }
    return $classes;
} );

// ── Estimated read time (200 wpm) ─────────────────────────────────────────
function sv2_estimate_read_time( $post_id = null ) {
    $content = get_post_field( 'post_content', $post_id ?? get_the_ID() );
    $words   = str_word_count( strip_tags( $content ) );
    return max( 1, (int) ceil( $words / 200 ) );
}

// ── Related posts ─────────────────────────────────────────────────────────
function sv2_render_single_related( $post_id, $cats ) {
    $args = array(
        'post_type'      => 'post',
        'posts_per_page' => 3,
        'post__not_in'   => array( $post_id ),
        'post_status'    => 'publish',
        'orderby'        => 'date',
        'order'          => 'DESC',
        'no_found_rows'  => true,
    );

    if ( $cats && ! is_wp_error( $cats ) ) {
        $args['tax_query'] = array( array(
            'taxonomy' => 'news_category',
            'field'    => 'term_id',
            'terms'    => wp_list_pluck( $cats, 'term_id' ),
        ) );
    }

    $related = new WP_Query( $args );

    // Fallback: recent posts from any category
    if ( ! $related->have_posts() ) {
        unset( $args['tax_query'] );
        $related = new WP_Query( $args );
    }

    if ( ! $related->have_posts() ) return;
    ?>
    <section class="sp-related">
        <div class="sp-related__inner">
            <h2 class="sp-related__heading"><?php esc_html_e( 'Bài viết liên quan', 'saltlux-v2' ); ?></h2>
            <div class="sp-related__grid">
                <?php
                while ( $related->have_posts() ) {
                    $related->the_post();
                    sv2_render_news_card( get_post() );
                }
                wp_reset_postdata();
                ?>
            </div>
        </div>
    </section>
    <?php
}

// ── Main render ───────────────────────────────────────────────────────────
function sv2_render_single_post() {
    $post_id   = get_the_ID();
    $title     = get_the_title();
    $hero_url  = get_the_post_thumbnail_url( $post_id, 'full' );
    $author    = get_the_author_meta( 'display_name' );
    $date_fmt  = get_the_date( 'd/m/Y' );
    $date_iso  = get_the_date( 'Y-m-d' );
    $read_time = sv2_estimate_read_time( $post_id );
    $cats      = get_the_terms( $post_id, 'news_category' );
    $tags      = get_the_tags( $post_id );
    $news_url  = function_exists( 'sv2_get_news_archive_url' )
        ? sv2_get_news_archive_url()
        : home_url( '/news/' );

    $arrow_left = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
        fill="none" stroke="currentColor" stroke-width="2.2"
        stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
    </svg>';
    ?>
    <div class="sp-wrap" itemscope itemtype="https://schema.org/Article">

        <!-- ── Hero ── -->
        <div class="sp-hero<?php echo $hero_url ? '' : ' sp-hero--no-img'; ?>">
            <?php if ( $hero_url ) : ?>
                <img class="sp-hero__img"
                     src="<?php echo esc_url( $hero_url ); ?>"
                     alt="<?php echo esc_attr( $title ); ?>"
                     loading="eager" fetchpriority="high"
                     itemprop="image">
                <div class="sp-hero__overlay" aria-hidden="true"></div>
            <?php endif; ?>
        </div>

        <!-- ── Category nav bar ── -->
        <?php
        $active_term_id = ( $cats && ! is_wp_error( $cats ) ) ? (int) $cats[0]->term_id : 0;
        if ( function_exists( 'sv2_get_news_category_nav_html' ) ) :
        ?>
        <div class="sp-cat-bar">
            <div class="sp-cat-bar__inner">
                <?php echo sv2_get_news_category_nav_html( $active_term_id ); // phpcs:ignore ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- ── Article header ── -->
        <header class="sp-header">
            <div class="sp-container">

                <nav class="sp-breadcrumb" aria-label="Breadcrumb">
                    <a class="sp-breadcrumb__back" href="<?php echo esc_url( $news_url ); ?>">
                        <?php echo $arrow_left; // phpcs:ignore ?>
                        <?php esc_html_e( 'Tin tức', 'saltlux-v2' ); ?>
                    </a>
                </nav>

                <?php if ( $cats && ! is_wp_error( $cats ) ) : ?>
                <div class="sp-cats">
                    <?php foreach ( $cats as $cat ) :
                        $color = function_exists( 'get_field' ) ? get_field( 'category_color', $cat ) : '';
                        $style = $color ? ' style="background:' . esc_attr( $color ) . '"' : '';
                    ?>
                    <a class="sp-cat-badge"
                       href="<?php echo esc_url( get_term_link( $cat ) ); ?>"
                       <?php echo $style; ?>>
                        <?php echo esc_html( $cat->name ); ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <h1 class="sp-title" itemprop="headline"><?php echo esc_html( $title ); ?></h1>

                <div class="sp-meta">
                    <span class="sp-meta__author" itemprop="author"><?php echo esc_html( $author ); ?></span>
                    <span class="sp-meta__dot" aria-hidden="true">·</span>
                    <time class="sp-meta__date" datetime="<?php echo esc_attr( $date_iso ); ?>" itemprop="datePublished">
                        <?php echo esc_html( $date_fmt ); ?>
                    </time>
                    <span class="sp-meta__dot" aria-hidden="true">·</span>
                    <span class="sp-meta__read-time">
                        <?php echo esc_html( $read_time ); ?> <?php esc_html_e( 'phút đọc', 'saltlux-v2' ); ?>
                    </span>
                </div>

            </div><!-- /.sp-container -->
        </header>

        <!-- ── Article body ── -->
        <div class="sp-body" itemprop="articleBody">
            <div class="sp-container entry-content">
	                <?php the_content(); ?>
            </div>
        </div>

        <!-- ── Tags ── -->
        <?php if ( $tags ) : ?>
        <div class="sp-tags-wrap">
            <div class="sp-container">
                <div class="sp-tags">
                    <?php foreach ( $tags as $tag ) : ?>
                    <a class="sp-tag" href="<?php echo esc_url( get_tag_link( $tag->term_id ) ); ?>">
                        #<?php echo esc_html( $tag->name ); ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ── Related posts ── -->
        <?php sv2_render_single_related( $post_id, $cats ); ?>

    </div><!-- /.sp-wrap -->
    <?php
}
