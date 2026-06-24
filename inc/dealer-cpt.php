<?php

// ── Register CPT ──────────────────────────────────────────────────────────────
add_action('init', function () {
    register_post_type('saltlux_dealer', [
        'labels' => [
            'name'          => 'Đại lý',
            'singular_name' => 'Đại lý',
            'add_new'       => 'Thêm mới',
            'add_new_item'  => 'Thêm đại lý',
            'edit_item'     => 'Sửa đại lý',
            'search_items'  => 'Tìm đại lý',
            'not_found'     => 'Không tìm thấy đại lý nào.',
        ],
        'public'        => false,
        'show_ui'       => true,
        'show_in_menu'  => true,
        'menu_icon'     => 'dashicons-location-alt',
        'menu_position' => 5,
        'supports'      => ['title'],
        'show_in_rest'  => false,
    ]);
});

// ── Register taxonomy ─────────────────────────────────────────────────────────
add_action('init', function () {
    register_taxonomy('saltlux_province', 'saltlux_dealer', [
        'labels' => [
            'name'              => 'Tỉnh / Thành phố',
            'singular_name'     => 'Tỉnh / Thành phố',
            'add_new_item'      => 'Thêm tỉnh/thành',
            'edit_item'         => 'Sửa tỉnh/thành',
            'parent_item'       => 'Vùng / Miền',
            'parent_item_colon' => 'Vùng / Miền:',
        ],
        'hierarchical' => true,
        'show_ui'      => true,
        'show_in_rest' => false,
        'rewrite'      => ['slug' => 'province'],
    ]);
});

// ── Enqueue assets on dealer edit screen ─────────────────────────────────────
add_action('admin_enqueue_scripts', function ($hook) {
    if (!in_array($hook, ['post.php', 'post-new.php'], true)) return;
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== 'saltlux_dealer') return;

    wp_enqueue_script('google-maps-admin', 'https://maps.googleapis.com/maps/api/js?key=AIzaSyDwpkuWFDYO2uNOVgtgFoSx6TZXb-1UnVw&language=vi&region=VN', [], null, true);
    wp_enqueue_media();
});

// ── Meta box registration ─────────────────────────────────────────────────────
add_action('add_meta_boxes', function () {
    add_meta_box(
        'saltlux_dealer_info',
        'Thông tin đại lý',
        'saltlux_dealer_meta_box_html',
        'saltlux_dealer',
        'normal',
        'high'
    );
});

// ── Meta box HTML ─────────────────────────────────────────────────────────────
if ( ! function_exists( 'saltlux_dealer_meta_box_html' ) ) {
function saltlux_dealer_meta_box_html($post) {
    wp_nonce_field('saltlux_dealer_save', 'saltlux_dealer_nonce');

    $address     = get_post_meta($post->ID, 'dealer_address', true);
    $phone       = get_post_meta($post->ID, 'dealer_phone', true);
    $email       = get_post_meta($post->ID, 'dealer_email', true);
    $website     = get_post_meta($post->ID, 'dealer_website', true);
    $lat         = get_post_meta($post->ID, 'dealer_lat', true);
    $lng         = get_post_meta($post->ID, 'dealer_lng', true);
    $is_priority   = get_post_meta($post->ID, 'dealer_is_priority', true);
    $is_high_order = get_post_meta($post->ID, 'dealer_is_high_order', true);

    // Logo: stored as attachment ID (int). Supports both native and legacy ACF-stored IDs.
    $logo_id  = absint(get_post_meta($post->ID, 'dealer_logo', true));
    $logo_url = $logo_id ? wp_get_attachment_url($logo_id) : '';
    ?>
    <style>
    .sdm-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px 20px; margin-bottom: 4px; }
    .sdm-full { grid-column: 1 / -1; }
    .sdm-field label { display: block; font-weight: 600; font-size: 12px; text-transform: uppercase; color: #3c434a; margin-bottom: 5px; }
    .sdm-field input[type=text],
    .sdm-field input[type=email],
    .sdm-field input[type=url],
    .sdm-field textarea { width: 100%; }
    .sdm-divider { border: none; border-top: 1px solid #dcdcde; margin: 20px 0 16px; }
    .sdm-section-label { font-weight: 700; font-size: 11px; letter-spacing: .08em; text-transform: uppercase; color: #646970; margin: 0 0 14px; }
    .sdm-map-wrap { border: 1px solid #dcdcde; border-radius: 3px; overflow: hidden; }
    .sdm-map-toolbar { background: #f6f7f7; border-bottom: 1px solid #dcdcde; padding: 8px 12px; display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap; }
    .sdm-map-toolbar p { margin: 0; color: #50575e; font-size: 12px; }
    #sdm-leaflet-map { height: 360px; width: 100%; }
    .sdm-logo-preview { max-width: 140px; max-height: 90px; display: block; border: 1px solid #dcdcde; border-radius: 2px; margin-top: 8px; }
    .sdm-logo-actions { display: flex; gap: 8px; align-items: center; margin-top: 8px; }
    .sdm-priority-row { display: flex; align-items: center; gap: 8px; padding: 10px 0; }
    .sdm-priority-row input { margin: 0; }
    .sdm-priority-row span { font-size: 13px; color: #3c434a; }
    </style>

    <div class="sdm-grid" style="margin-top:12px;">

        <div class="sdm-field sdm-full">
            <label for="sdm_address">Địa chỉ</label>
            <textarea id="sdm_address" name="dealer_address" rows="3"><?php echo esc_textarea($address); ?></textarea>
        </div>

        <div class="sdm-field">
            <label for="sdm_phone">Số điện thoại</label>
            <input type="text" id="sdm_phone" name="dealer_phone" value="<?php echo esc_attr($phone); ?>">
        </div>
        <div class="sdm-field">
            <label for="sdm_email">Email</label>
            <input type="email" id="sdm_email" name="dealer_email" value="<?php echo esc_attr($email); ?>">
        </div>

        <div class="sdm-field sdm-full">
            <label for="sdm_website">Website</label>
            <input type="url" id="sdm_website" name="dealer_website" value="<?php echo esc_attr($website); ?>" placeholder="https://">
        </div>

        <div class="sdm-field sdm-full">
            <label>Logo</label>
            <input type="hidden" id="sdm_logo_id" name="dealer_logo" value="<?php echo esc_attr($logo_id ?: ''); ?>">
            <img id="sdm_logo_preview" src="<?php echo esc_url($logo_url); ?>" class="sdm-logo-preview"<?php echo $logo_url ? '' : ' style="display:none"'; ?>>
            <div class="sdm-logo-actions">
                <button type="button" class="button" id="sdm_logo_btn">Chọn ảnh</button>
                <button type="button" class="button-link-delete" id="sdm_logo_remove"<?php echo $logo_id ? '' : ' style="display:none"'; ?>>Xoá</button>
            </div>
        </div>

        <div class="sdm-field">
            <div class="sdm-priority-row">
                <input type="checkbox" id="sdm_priority" name="dealer_is_priority" value="1"<?php checked($is_priority, '1'); ?>>
                <label for="sdm_priority" style="text-transform:none;font-weight:400;font-size:13px;color:#3c434a;margin:0;">
                    Đại lý ưu tiên — hiện badge <strong>"Đại lý chính thức"</strong>
                </label>
            </div>
        </div>

        <div class="sdm-field">
            <div class="sdm-priority-row">
                <input type="checkbox" id="sdm_high_order" name="dealer_is_high_order" value="1"<?php checked($is_high_order, '1'); ?>>
                <label for="sdm_high_order" style="text-transform:none;font-weight:400;font-size:13px;color:#3c434a;margin:0;">
                    Hiện lên đầu danh sách
                </label>
            </div>
        </div>

    </div>

    <hr class="sdm-divider">
    <p class="sdm-section-label">Vị trí trên bản đồ</p>

    <div class="sdm-grid" style="margin-bottom:12px;">
        <div class="sdm-field">
            <label for="sdm_lat">Vĩ độ (Latitude)</label>
            <input type="text" id="sdm_lat" name="dealer_lat" value="<?php echo esc_attr($lat); ?>" placeholder="21.0285">
        </div>
        <div class="sdm-field">
            <label for="sdm_lng">Kinh độ (Longitude)</label>
            <input type="text" id="sdm_lng" name="dealer_lng" value="<?php echo esc_attr($lng); ?>" placeholder="105.8542">
        </div>
    </div>

    <div class="sdm-map-wrap">
        <div class="sdm-map-toolbar">
            <p>Click trực tiếp lên bản đồ để điền tọa độ. Hoặc nhập thủ công ở trên rồi nhấn Enter.</p>
            <button type="button" class="button" id="sdm_open_google">Mở Google Maps</button>
        </div>
        <div id="sdm-leaflet-map"></div>
    </div>
    <?php
}
}

// ── Map + uploader JS (admin_footer — runs after Leaflet is loaded) ────────────
add_action('admin_footer', function () {
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== 'saltlux_dealer') return;
    ?>
    <script>
    (function () {
        if (typeof google === 'undefined' || !document.getElementById('sdm-leaflet-map')) return;

        var latInput = document.getElementById('sdm_lat');
        var lngInput = document.getElementById('sdm_lng');
        var initLat  = parseFloat(latInput.value) || 16.047;
        var initLng  = parseFloat(lngInput.value) || 108.206;
        var initZoom = latInput.value ? 14 : 6;

        var map = new google.maps.Map(document.getElementById('sdm-leaflet-map'), {
            center           : { lat: initLat, lng: initLng },
            zoom             : initZoom,
            mapTypeControl   : false,
            streetViewControl: false,
            fullscreenControl: false,
        });
        var marker = null;

        if (latInput.value && lngInput.value) {
            marker = new google.maps.Marker({ position: { lat: initLat, lng: initLng }, map: map });
        }

        function setCoords(lat, lng) {
            latInput.value = Number(lat).toFixed(6);
            lngInput.value = Number(lng).toFixed(6);
            if (!marker) {
                marker = new google.maps.Marker({ position: { lat: lat, lng: lng }, map: map });
            } else {
                marker.setPosition({ lat: lat, lng: lng });
            }
        }

        map.addListener('click', function (e) {
            setCoords(e.latLng.lat(), e.latLng.lng());
        });

        function syncFromInputs() {
            var lat = parseFloat(latInput.value);
            var lng = parseFloat(lngInput.value);
            if (!isNaN(lat) && !isNaN(lng)) {
                map.setCenter({ lat: lat, lng: lng });
                map.setZoom(Math.max(map.getZoom(), 12));
                setCoords(lat, lng);
            }
        }
        latInput.addEventListener('change', syncFromInputs);
        lngInput.addEventListener('change', syncFromInputs);

        document.getElementById('sdm_open_google').addEventListener('click', function () {
            var addr = document.getElementById('sdm_address').value.trim();
            var q    = addr || (latInput.value && lngInput.value
                ? latInput.value + ',' + lngInput.value
                : 'Vietnam');
            window.open(
                'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(q),
                '_blank', 'noopener'
            );
        });

        // ── Logo media uploader ───────────────────────────────────────────────
        if (typeof wp === 'undefined' || !wp.media) return;

        var logoFrame  = null;
        var logoIdEl   = document.getElementById('sdm_logo_id');
        var logoPreview= document.getElementById('sdm_logo_preview');
        var logoRemove = document.getElementById('sdm_logo_remove');

        document.getElementById('sdm_logo_btn').addEventListener('click', function () {
            if (!logoFrame) {
                logoFrame = wp.media({
                    title    : 'Chọn logo đại lý',
                    button   : { text: 'Chọn ảnh' },
                    multiple : false
                });
                logoFrame.on('select', function () {
                    var att = logoFrame.state().get('selection').first().toJSON();
                    logoIdEl.value        = att.id;
                    logoPreview.src       = att.url;
                    logoPreview.style.display = 'block';
                    logoRemove.style.display  = '';
                });
            }
            logoFrame.open();
        });

        logoRemove.addEventListener('click', function () {
            logoIdEl.value            = '';
            logoPreview.src           = '';
            logoPreview.style.display = 'none';
            logoRemove.style.display  = 'none';
        });
    })();
    </script>
    <?php
});

// ── Save post meta ────────────────────────────────────────────────────────────
add_action('save_post_saltlux_dealer', function ($post_id) {
    if (!isset($_POST['saltlux_dealer_nonce'])) return;
    if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['saltlux_dealer_nonce'])), 'saltlux_dealer_save')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    // Textarea
    if (isset($_POST['dealer_address'])) {
        update_post_meta($post_id, 'dealer_address', sanitize_textarea_field(wp_unslash($_POST['dealer_address'])));
    }

    // Single-line text fields
    foreach (['dealer_phone', 'dealer_email', 'dealer_website', 'dealer_lat', 'dealer_lng'] as $key) {
        if (isset($_POST[$key])) {
            update_post_meta($post_id, $key, sanitize_text_field(wp_unslash($_POST[$key])));
        }
    }

    // Logo (attachment ID)
    $logo_id = isset($_POST['dealer_logo']) ? absint($_POST['dealer_logo']) : 0;
    update_post_meta($post_id, 'dealer_logo', $logo_id);

    // Priority checkboxes (absent when unchecked)
    update_post_meta($post_id, 'dealer_is_priority',   isset($_POST['dealer_is_priority'])   ? '1' : '0');
    update_post_meta($post_id, 'dealer_is_high_order', isset($_POST['dealer_is_high_order']) ? '1' : '0');
});

// ── Build dealers array (used by page-dealers.php and dealer-map.js) ──────────
if ( ! function_exists( 'saltlux_build_dealers_array' ) ) {
function saltlux_build_dealers_array() {
    static $cache = null;
    if ( $cache !== null ) return $cache;

    $posts = get_posts( [
        'post_type'      => 'saltlux_dealer',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'orderby'        => 'title',
        'order'          => 'ASC',
    ] );

    $dealers = [];
    foreach ( $posts as $post ) {
        $lat = get_post_meta( $post->ID, 'dealer_lat', true );
        $lng = get_post_meta( $post->ID, 'dealer_lng', true );
        if ( ! $lat || ! $lng ) continue;

        $logo_id  = absint( get_post_meta( $post->ID, 'dealer_logo', true ) );
        $logo_url = $logo_id ? wp_get_attachment_url( $logo_id ) : '';

        $terms         = wp_get_post_terms( $post->ID, 'saltlux_province' );
        $province_name = '';
        $province_slug = '';
        $region_name   = '';
        if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
            // Dealers are usually assigned BOTH the region (parent) and the
            // province (child). Must pick the CHILD term as the province —
            // relying on $terms[0] picks whichever name sorts first alphabetically,
            // which grabs the region for some names (e.g. "Miền Nam" < "TP. Hồ Chí
            // Minh") and breaks sidebar grouping for that whole region.
            $term = $terms[0];
            foreach ( $terms as $t ) {
                if ( $t->parent ) { $term = $t; break; }  // first child (province) wins
            }
            $province_name = $term->name;
            $province_slug = $term->slug;
            if ( $term->parent ) {
                $parent      = get_term( $term->parent, 'saltlux_province' );
                $region_name = ( ! is_wp_error( $parent ) && $parent ) ? $parent->name : '';
            }
        }

        $dealers[] = [
            'id'             => $post->ID,
            'name'           => $post->post_title,
            'address'        => get_post_meta( $post->ID, 'dealer_address', true ),
            'phone'          => get_post_meta( $post->ID, 'dealer_phone', true ),
            'email'          => get_post_meta( $post->ID, 'dealer_email', true ),
            'website'        => get_post_meta( $post->ID, 'dealer_website', true ),
            'lat'            => (float) $lat,
            'lng'            => (float) $lng,
            'logo'           => $logo_url,
            'is_priority'    => (bool) get_post_meta( $post->ID, 'dealer_is_priority', true ),
            'is_high_order'  => (bool) get_post_meta( $post->ID, 'dealer_is_high_order', true ),
            'province'       => $province_name,
            'province_slug'  => $province_slug,
            'region'         => $region_name,
        ];
    }

    usort( $dealers, function( $a, $b ) {
        return (int) $b['is_high_order'] - (int) $a['is_high_order'];
    } );

    $cache = $dealers;
    return $cache;
}
}
