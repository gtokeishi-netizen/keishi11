<?php
/**
 * Grant Insight Perfect - Core Setup File
 *
 * テーマの基本設定、投稿タイプ、タクソノミーを管理
 * 
 * @package Grant_Insight_Perfect
 * @version 8.0.0
 */

// セキュリティチェック
if (!defined('ABSPATH')) {
    exit;
}

/**
 * =============================================================================
 * 1. テーマ基本設定
 * =============================================================================
 */

/**
 * テーマ基本設定
 */
function gi_setup() {
    // 基本的なテーマサポート
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', array(
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption'
    ));
    add_theme_support('custom-logo', array(
        'height'      => 100,
        'width'       => 300,
        'flex-width'  => true,
        'flex-height' => true,
    ));
    add_theme_support('automatic-feed-links');
    
    // 助成金関連の画像サイズ
    add_image_size('grant-thumbnail', 400, 300, true);
    add_image_size('grant-featured', 800, 450, true);
    
    // 言語ファイル
    load_theme_textdomain('grant-insight', get_template_directory() . '/languages');
    
    // HTTPS強制化 - Mixed Content対策
    if (is_ssl()) {
        add_filter('upload_dir', 'gi_force_https_uploads');
        add_filter('wp_get_attachment_url', 'gi_force_https_url');
        add_filter('get_site_icon_url', 'gi_force_https_url');
        add_filter('site_icon_url', 'gi_force_https_url');
    }
    
    // メニュー登録
    register_nav_menus(array(
        'primary' => 'メインメニュー',
        'footer' => 'フッターメニュー'
    ));
}
add_action('after_setup_theme', 'gi_setup');

/**
 * コンテンツ幅設定
 */
function gi_content_width() {
    $GLOBALS['content_width'] = 1200;
}
add_action('after_setup_theme', 'gi_content_width', 0);

/**
 * スクリプト・スタイルの読み込み
 */
function gi_enqueue_scripts() {
    // メインスタイルシート
    wp_enqueue_style('gi-style', get_stylesheet_uri(), array(), GI_THEME_VERSION);
    
    // 統合されたメインCSS
    wp_enqueue_style('gi-main-css', get_template_directory_uri() . '/assets/css/main.css', array(), GI_THEME_VERSION);
    
    // Google Fonts（日本語フォント）
    wp_enqueue_style('google-fonts-noto', 'https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;700&display=swap', array(), null);
    
    // メインJavaScript
    wp_enqueue_script('gi-main', get_template_directory_uri() . '/assets/js/main.js', array('jquery'), GI_THEME_VERSION, true);
    
    // Enhanced AI Generator (管理画面でのみ)
    if (is_admin()) {
        wp_enqueue_script('gi-enhanced-ai', get_template_directory_uri() . '/assets/js/enhanced-ai-generator.js', array('jquery'), GI_THEME_VERSION, true);
        
        // AI用の追加AJAX設定
        wp_localize_script('gi-enhanced-ai', 'gi_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gi_ai_nonce')
        ));
    }
    
    // AJAX設定
    wp_localize_script('gi-main', 'gi_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('gi_ajax_nonce')
    ));
}
add_action('wp_enqueue_scripts', 'gi_enqueue_scripts');

/**
 * ウィジェットエリア登録
 */
function gi_widgets_init() {
    // サイドバー
    register_sidebar(array(
        'name'          => 'サイドバー',
        'id'            => 'sidebar-1',
        'description'   => 'サイドバーウィジェットエリア',
        'before_widget' => '<div id="%1$s" class="widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ));
    
    // フッター
    register_sidebar(array(
        'name'          => 'フッター',
        'id'            => 'footer-1',
        'description'   => 'フッターウィジェットエリア',
        'before_widget' => '<div id="%1$s" class="widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h4 class="widget-title">',
        'after_title'   => '</h4>',
    ));
}
add_action('widgets_init', 'gi_widgets_init');

/**
 * カスタマイザー設定
 */
function gi_customize_register($wp_customize) {
    // 助成金表示設定セクション
    $wp_customize->add_section('gi_grant_display', array(
        'title' => '助成金表示設定',
        'priority' => 30,
    ));
    
    // 1ページあたりの表示件数
    $wp_customize->add_setting('gi_grants_per_page', array(
        'default' => 12,
        'sanitize_callback' => 'absint',
    ));
    
    $wp_customize->add_control('gi_grants_per_page', array(
        'label' => '1ページあたりの表示件数',
        'section' => 'gi_grant_display',
        'type' => 'number',
        'input_attrs' => array(
            'min' => 6,
            'max' => 30,
            'step' => 3,
        ),
    ));
    
    // グリッド表示の列数
    $wp_customize->add_setting('gi_grid_columns', array(
        'default' => 3,
        'sanitize_callback' => 'absint',
    ));
    
    $wp_customize->add_control('gi_grid_columns', array(
        'label' => 'グリッド表示の列数',
        'section' => 'gi_grant_display',
        'type' => 'select',
        'choices' => array(
            2 => '2列',
            3 => '3列',
            4 => '4列',
        ),
    ));
}
add_action('customize_register', 'gi_customize_register');

/**
 * 助成金検索機能の強化
 */
function gi_enhance_grant_search($query) {
    if (!is_admin() && $query->is_main_query()) {
        // 助成金アーカイブページの表示件数
        if (is_post_type_archive('grant') || is_tax('grant_category') || is_tax('grant_prefecture')) {
            $per_page = get_theme_mod('gi_grants_per_page', 12);
            $query->set('posts_per_page', $per_page);
            $query->set('orderby', 'date');
            $query->set('order', 'DESC');
        }
        
        // 検索結果に助成金を含める
        if ($query->is_search()) {
            $post_types = $query->get('post_type');
            if (empty($post_types)) {
                $query->set('post_type', array('post', 'grant'));
            }
        }
    }
}
add_action('pre_get_posts', 'gi_enhance_grant_search');

/**
 * セキュリティ強化
 */
function gi_security_headers() {
    if (!is_admin()) {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
    }
}
add_action('send_headers', 'gi_security_headers');

// 不要なヘッダー情報を削除
remove_action('wp_head', 'wp_generator');
remove_action('wp_head', 'rsd_link');
remove_action('wp_head', 'wlwmanifest_link');
remove_action('wp_head', 'wp_shortlink_wp_head');

/**
 * =============================================================================
 * 2. カスタム投稿タイプ・タクソノミー
 * =============================================================================
 */

/**
 * カスタム投稿タイプ登録
 */
function gi_register_post_types() {
    // 助成金投稿タイプ
    register_post_type('grant', array(
        'labels' => array(
            'name' => '助成金・補助金',
            'singular_name' => '助成金・補助金',
            'add_new' => '新規追加',
            'add_new_item' => '新しい助成金・補助金を追加',
            'edit_item' => '助成金・補助金を編集',
            'new_item' => '新しい助成金・補助金',
            'view_item' => '助成金・補助金を表示',
            'search_items' => '助成金・補助金を検索',
            'not_found' => '助成金・補助金が見つかりませんでした',
            'not_found_in_trash' => 'ゴミ箱に助成金・補助金はありません',
            'all_items' => 'すべての助成金・補助金',
            'menu_name' => '助成金・補助金'
        ),
        'description' => '助成金・補助金情報を管理します',
        'public' => true,
        'publicly_queryable' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'show_in_admin_bar' => true,
        'query_var' => true,
        'rewrite' => array(
            'slug' => 'grants',
            'with_front' => false
        ),
        'capability_type' => 'post',
        'has_archive' => true,
        'hierarchical' => false,
        'menu_position' => 5,
        'menu_icon' => 'dashicons-money-alt',
        'supports' => array('title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'revisions'),
        'show_in_rest' => true
    ));
}
add_action('init', 'gi_register_post_types');

// パーマリンクフラッシュ（投稿タイプが確実に認識されるように）
add_action('after_switch_theme', 'flush_rewrite_rules');
add_action('wp_loaded', function() {
    static $flushed = false;
    if (!$flushed && !get_option('gi_permalinks_flushed_v2')) {
        flush_rewrite_rules();
        update_option('gi_permalinks_flushed_v2', true);
        $flushed = true;
    }
});

/**
 * カスタムタクソノミー登録
 */
function gi_register_taxonomies() {
    // 助成金カテゴリー
    register_taxonomy('grant_category', 'grant', array(
        'labels' => array(
            'name' => '助成金カテゴリー',
            'singular_name' => '助成金カテゴリー',
            'search_items' => 'カテゴリーを検索',
            'all_items' => 'すべてのカテゴリー',
            'parent_item' => '親カテゴリー',
            'parent_item_colon' => '親カテゴリー:',
            'edit_item' => 'カテゴリーを編集',
            'update_item' => 'カテゴリーを更新',
            'add_new_item' => '新しいカテゴリーを追加',
            'new_item_name' => '新しいカテゴリー名'
        ),
        'description' => '助成金・補助金をカテゴリー別に分類します',
        'public' => true,
        'publicly_queryable' => true,
        'hierarchical' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'show_in_nav_menus' => true,
        'show_in_rest' => true,
        'show_tagcloud' => true,
        'show_admin_column' => true,
        'query_var' => true,
        'rewrite' => array(
            'slug' => 'grant-category',
            'with_front' => false,
            'hierarchical' => true
        )
    ));
    
    // 都道府県タクソノミー
    register_taxonomy('grant_prefecture', 'grant', array(
        'labels' => array(
            'name' => '対象都道府県',
            'singular_name' => '都道府県',
            'search_items' => '都道府県を検索',
            'all_items' => 'すべての都道府県',
            'edit_item' => '都道府県を編集',
            'update_item' => '都道府県を更新',
            'add_new_item' => '新しい都道府県を追加',
            'new_item_name' => '新しい都道府県名'
        ),
        'description' => '助成金・補助金の対象都道府県を管理します',
        'public' => true,
        'publicly_queryable' => true,
        'hierarchical' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'show_in_nav_menus' => true,
        'show_in_rest' => true,
        'show_tagcloud' => true,
        'show_admin_column' => true,
        'query_var' => true,
        'rewrite' => array(
            'slug' => 'prefecture',
            'with_front' => false
        )
    ));
    
    // 助成金タグ
    register_taxonomy('grant_tag', 'grant', array(
        'labels' => array(
            'name' => '助成金タグ',
            'singular_name' => '助成金タグ',
            'search_items' => 'タグを検索',
            'all_items' => 'すべてのタグ',
            'edit_item' => 'タグを編集',
            'update_item' => 'タグを更新',
            'add_new_item' => '新しいタグを追加',
            'new_item_name' => '新しいタグ名'
        ),
        'description' => '助成金・補助金をタグで分類します',
        'public' => true,
        'publicly_queryable' => true,
        'hierarchical' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'show_in_nav_menus' => true,
        'show_in_rest' => true,
        'show_tagcloud' => true,
        'show_admin_column' => true,
        'query_var' => true,
        'rewrite' => array(
            'slug' => 'grant-tag',
            'with_front' => false
        )
    ));
    
    // 市町村タクソノミー
    register_taxonomy('grant_municipality', 'grant', array(
        'labels' => array(
            'name' => '対象市町村',
            'singular_name' => '市町村',
            'search_items' => '市町村を検索',
            'all_items' => 'すべての市町村',
            'edit_item' => '市町村を編集',
            'update_item' => '市町村を更新',
            'add_new_item' => '新しい市町村を追加',
            'new_item_name' => '新しい市町村名'
        ),
        'description' => '助成金・補助金の対象市町村を管理します',
        'public' => true,
        'publicly_queryable' => true,
        'hierarchical' => true, // 都道府県 > 市町村の階層構造対応
        'show_ui' => true,
        'show_in_menu' => true,
        'show_in_nav_menus' => true,
        'show_in_rest' => true,
        'show_tagcloud' => true,
        'show_admin_column' => true,
        'query_var' => true,
        'rewrite' => array(
            'slug' => 'grant-municipality',
            'with_front' => false,
            'hierarchical' => true
        )
    ));
}
add_action('init', 'gi_register_taxonomies');

/**
 * =============================================================================
 * 3. 都道府県データ初期化
 * =============================================================================
 */

/**
 * 47都道府県の初期データを登録
 */
function gi_init_prefecture_terms() {
    // タクソノミーが存在するか確認
    if (!taxonomy_exists('grant_prefecture')) {
        return;
    }
    
    $prefectures = array(
        // 北海道・東北
        array('name' => '北海道', 'slug' => 'hokkaido'),
        array('name' => '青森県', 'slug' => 'aomori'),
        array('name' => '岩手県', 'slug' => 'iwate'),
        array('name' => '宮城県', 'slug' => 'miyagi'),
        array('name' => '秋田県', 'slug' => 'akita'),
        array('name' => '山形県', 'slug' => 'yamagata'),
        array('name' => '福島県', 'slug' => 'fukushima'),
        // 関東
        array('name' => '茨城県', 'slug' => 'ibaraki'),
        array('name' => '栃木県', 'slug' => 'tochigi'),
        array('name' => '群馬県', 'slug' => 'gunma'),
        array('name' => '埼玉県', 'slug' => 'saitama'),
        array('name' => '千葉県', 'slug' => 'chiba'),
        array('name' => '東京都', 'slug' => 'tokyo'),
        array('name' => '神奈川県', 'slug' => 'kanagawa'),
        // 中部
        array('name' => '新潟県', 'slug' => 'niigata'),
        array('name' => '富山県', 'slug' => 'toyama'),
        array('name' => '石川県', 'slug' => 'ishikawa'),
        array('name' => '福井県', 'slug' => 'fukui'),
        array('name' => '山梨県', 'slug' => 'yamanashi'),
        array('name' => '長野県', 'slug' => 'nagano'),
        array('name' => '岐阜県', 'slug' => 'gifu'),
        array('name' => '静岡県', 'slug' => 'shizuoka'),
        array('name' => '愛知県', 'slug' => 'aichi'),
        // 近畿
        array('name' => '三重県', 'slug' => 'mie'),
        array('name' => '滋賀県', 'slug' => 'shiga'),
        array('name' => '京都府', 'slug' => 'kyoto'),
        array('name' => '大阪府', 'slug' => 'osaka'),
        array('name' => '兵庫県', 'slug' => 'hyogo'),
        array('name' => '奈良県', 'slug' => 'nara'),
        array('name' => '和歌山県', 'slug' => 'wakayama'),
        // 中国
        array('name' => '鳥取県', 'slug' => 'tottori'),
        array('name' => '島根県', 'slug' => 'shimane'),
        array('name' => '岡山県', 'slug' => 'okayama'),
        array('name' => '広島県', 'slug' => 'hiroshima'),
        array('name' => '山口県', 'slug' => 'yamaguchi'),
        // 四国
        array('name' => '徳島県', 'slug' => 'tokushima'),
        array('name' => '香川県', 'slug' => 'kagawa'),
        array('name' => '愛媛県', 'slug' => 'ehime'),
        array('name' => '高知県', 'slug' => 'kochi'),
        // 九州・沖縄
        array('name' => '福岡県', 'slug' => 'fukuoka'),
        array('name' => '佐賀県', 'slug' => 'saga'),
        array('name' => '長崎県', 'slug' => 'nagasaki'),
        array('name' => '熊本県', 'slug' => 'kumamoto'),
        array('name' => '大分県', 'slug' => 'oita'),
        array('name' => '宮崎県', 'slug' => 'miyazaki'),
        array('name' => '鹿児島県', 'slug' => 'kagoshima'),
        array('name' => '沖縄県', 'slug' => 'okinawa')
    );
    
    // 各都道府県を登録
    foreach ($prefectures as $prefecture) {
        if (!term_exists($prefecture['slug'], 'grant_prefecture')) {
            wp_insert_term(
                $prefecture['name'],
                'grant_prefecture',
                array('slug' => $prefecture['slug'])
            );
        }
    }
}
add_action('after_setup_theme', 'gi_init_prefecture_terms');

/**
 * 都道府県データを取得するヘルパー関数
 */
function gi_get_all_prefectures() {
    return array(
        // 北海道・東北
        array('name' => '北海道', 'slug' => 'hokkaido', 'region' => 'hokkaido'),
        array('name' => '青森県', 'slug' => 'aomori', 'region' => 'tohoku'),
        array('name' => '岩手県', 'slug' => 'iwate', 'region' => 'tohoku'),
        array('name' => '宮城県', 'slug' => 'miyagi', 'region' => 'tohoku'),
        array('name' => '秋田県', 'slug' => 'akita', 'region' => 'tohoku'),
        array('name' => '山形県', 'slug' => 'yamagata', 'region' => 'tohoku'),
        array('name' => '福島県', 'slug' => 'fukushima', 'region' => 'tohoku'),
        // 関東
        array('name' => '茨城県', 'slug' => 'ibaraki', 'region' => 'kanto'),
        array('name' => '栃木県', 'slug' => 'tochigi', 'region' => 'kanto'),
        array('name' => '群馬県', 'slug' => 'gunma', 'region' => 'kanto'),
        array('name' => '埼玉県', 'slug' => 'saitama', 'region' => 'kanto'),
        array('name' => '千葉県', 'slug' => 'chiba', 'region' => 'kanto'),
        array('name' => '東京都', 'slug' => 'tokyo', 'region' => 'kanto'),
        array('name' => '神奈川県', 'slug' => 'kanagawa', 'region' => 'kanto'),
        // 中部
        array('name' => '新潟県', 'slug' => 'niigata', 'region' => 'chubu'),
        array('name' => '富山県', 'slug' => 'toyama', 'region' => 'chubu'),
        array('name' => '石川県', 'slug' => 'ishikawa', 'region' => 'chubu'),
        array('name' => '福井県', 'slug' => 'fukui', 'region' => 'chubu'),
        array('name' => '山梨県', 'slug' => 'yamanashi', 'region' => 'chubu'),
        array('name' => '長野県', 'slug' => 'nagano', 'region' => 'chubu'),
        array('name' => '岐阜県', 'slug' => 'gifu', 'region' => 'chubu'),
        array('name' => '静岡県', 'slug' => 'shizuoka', 'region' => 'chubu'),
        array('name' => '愛知県', 'slug' => 'aichi', 'region' => 'chubu'),
        // 近畿
        array('name' => '三重県', 'slug' => 'mie', 'region' => 'kinki'),
        array('name' => '滋賀県', 'slug' => 'shiga', 'region' => 'kinki'),
        array('name' => '京都府', 'slug' => 'kyoto', 'region' => 'kinki'),
        array('name' => '大阪府', 'slug' => 'osaka', 'region' => 'kinki'),
        array('name' => '兵庫県', 'slug' => 'hyogo', 'region' => 'kinki'),
        array('name' => '奈良県', 'slug' => 'nara', 'region' => 'kinki'),
        array('name' => '和歌山県', 'slug' => 'wakayama', 'region' => 'kinki'),
        // 中国
        array('name' => '鳥取県', 'slug' => 'tottori', 'region' => 'chugoku'),
        array('name' => '島根県', 'slug' => 'shimane', 'region' => 'chugoku'),
        array('name' => '岡山県', 'slug' => 'okayama', 'region' => 'chugoku'),
        array('name' => '広島県', 'slug' => 'hiroshima', 'region' => 'chugoku'),
        array('name' => '山口県', 'slug' => 'yamaguchi', 'region' => 'chugoku'),
        // 四国
        array('name' => '徳島県', 'slug' => 'tokushima', 'region' => 'shikoku'),
        array('name' => '香川県', 'slug' => 'kagawa', 'region' => 'shikoku'),
        array('name' => '愛媛県', 'slug' => 'ehime', 'region' => 'shikoku'),
        array('name' => '高知県', 'slug' => 'kochi', 'region' => 'shikoku'),
        // 九州・沖縄
        array('name' => '福岡県', 'slug' => 'fukuoka', 'region' => 'kyushu'),
        array('name' => '佐賀県', 'slug' => 'saga', 'region' => 'kyushu'),
        array('name' => '長崎県', 'slug' => 'nagasaki', 'region' => 'kyushu'),
        array('name' => '熊本県', 'slug' => 'kumamoto', 'region' => 'kyushu'),
        array('name' => '大分県', 'slug' => 'oita', 'region' => 'kyushu'),
        array('name' => '宮崎県', 'slug' => 'miyazaki', 'region' => 'kyushu'),
        array('name' => '鹿児島県', 'slug' => 'kagoshima', 'region' => 'kyushu'),
        array('name' => '沖縄県', 'slug' => 'okinawa', 'region' => 'kyushu')
    );
}

/**
 * =============================================================================
 * 4. ユーティリティ関数
 * =============================================================================
 */

/**
 * パンくずリスト生成関数
 */
function gi_breadcrumbs() {
    if (is_front_page()) return;
    
    echo '<nav class="breadcrumbs">';
    echo '<a href="' . home_url() . '">ホーム</a>';
    
    if (is_post_type_archive('grant')) {
        echo ' > 助成金・補助金一覧';
    } elseif (is_tax('grant_category')) {
        echo ' > <a href="' . get_post_type_archive_link('grant') . '">助成金・補助金一覧</a>';
        echo ' > ' . single_term_title('', false);
    } elseif (is_tax('grant_prefecture')) {
        echo ' > <a href="' . get_post_type_archive_link('grant') . '">助成金・補助金一覧</a>';
        echo ' > ' . single_term_title('', false);
    } elseif (is_singular('grant')) {
        echo ' > <a href="' . get_post_type_archive_link('grant') . '">助成金・補助金一覧</a>';
        echo ' > ' . get_the_title();
    } elseif (is_page()) {
        echo ' > ' . get_the_title();
    } elseif (is_single()) {
        $categories = get_the_category();
        if ($categories) {
            echo ' > <a href="' . get_category_link($categories[0]->term_id) . '">' . $categories[0]->name . '</a>';
        }
        echo ' > ' . get_the_title();
    }
    
    echo '</nav>';
}

/**
 * ページネーション関数
 */
function gi_pagination($pages = '') {
    global $paged;
    
    if (empty($paged)) $paged = 1;
    
    if ($pages == '') {
        global $wp_query;
        $pages = $wp_query->max_num_pages;
        if (!$pages) $pages = 1;
    }
    
    if ($pages != 1) {
        echo '<div class="pagination">';
        
        if ($paged > 1) {
            echo '<a href="' . get_pagenum_link($paged - 1) . '" class="prev">前へ</a>';
        }
        
        for ($i = 1; $i <= $pages; $i++) {
            if ($paged == $i) {
                echo '<span class="current">' . $i . '</span>';
            } else {
                echo '<a href="' . get_pagenum_link($i) . '">' . $i . '</a>';
            }
        }
        
        if ($paged < $pages) {
            echo '<a href="' . get_pagenum_link($paged + 1) . '" class="next">次へ</a>';
        }
        
        echo '</div>';
    }
}

/**
 * 管理画面用のスタイル
 */
function gi_admin_styles() {
    echo '<style>
        #adminmenu .menu-icon-grant div.wp-menu-image:before {
            content: "\f155";
        }
        .grant-status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        .grant-status-active { background: #7ad03a; color: white; }
        .grant-status-soon { background: #ffba00; color: white; }
        .grant-status-expired { background: #dd3333; color: white; }
        
        /* Enhanced AI Generator Styles */
        .gi-ai-button-container {
            margin-top: 8px;
        }
        
        .gi-ai-button {
            margin-right: 5px;
            margin-bottom: 5px;
        }
        
        .gi-ai-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .gi-ai-panel {
            animation: slideInRight 0.3s ease;
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .gi-ai-notification {
            animation: slideInDown 0.3s ease;
        }
        
        @keyframes slideInDown {
            from {
                transform: translateY(-100%);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
    </style>';
}
add_action('admin_head', 'gi_admin_styles');

/**
 * =============================================================================
 * HTTPS 強制化・Mixed Content対策
 * =============================================================================
 */

/**
 * アップロードディレクトリをHTTPSに強制
 */
function gi_force_https_uploads($upload_dir) {
    if (is_ssl()) {
        $upload_dir['url'] = str_replace('http://', 'https://', $upload_dir['url']);
        $upload_dir['baseurl'] = str_replace('http://', 'https://', $upload_dir['baseurl']);
    }
    return $upload_dir;
}

/**
 * 添付ファイルURLをHTTPSに強制
 */
function gi_force_https_url($url) {
    if (is_ssl() && !empty($url)) {
        $url = str_replace('http://', 'https://', $url);
    }
    return $url;
}

/**
 * すべてのコンテンツURLをHTTPSに変換
 */
function gi_force_https_content($content) {
    if (is_ssl()) {
        $site_url = get_site_url();
        $http_site_url = str_replace('https://', 'http://', $site_url);
        $content = str_replace($http_site_url, $site_url, $content);
    }
    return $content;
}
add_filter('the_content', 'gi_force_https_content');
add_filter('widget_text', 'gi_force_https_content');

/**
 * カスタマイザーでのMixed Content警告抑制
 */
function gi_customize_https_fix() {
    if (is_customize_preview() && is_ssl()) {
        add_filter('wp_get_attachment_url', function($url) {
            return str_replace('http://', 'https://', $url);
        });
        add_filter('wp_get_attachment_image_src', function($image) {
            if (is_array($image) && isset($image[0])) {
                $image[0] = str_replace('http://', 'https://', $image[0]);
            }
            return $image;
        });
    }
}
add_action('init', 'gi_customize_https_fix');

/**
 * =============================================================================
 * 4. 市町村データ初期化（都道府県連動）
 * =============================================================================
 */

/**
 * 都道府県別市町村データの初期化
 */
function gi_init_municipality_terms() {
    // タクソノミーが存在するか確認
    if (!taxonomy_exists('grant_municipality') || !taxonomy_exists('grant_prefecture')) {
        return;
    }

    // 主要都市のサンプルデータ（実際の運用では全市町村データが必要）
    $municipalities_data = array(
        // 北海道
        '北海道' => array(
            '札幌市', '旭川市', '函館市', '釧路市', '苫小牧市', '帯広市', '小樽市', 
            '北見市', '江別市', '千歳市', '室蘭市', '岩見沢市', '恵庭市', '北広島市'
        ),
        // 青森県  
        '青森県' => array(
            '青森市', '弘前市', '八戸市', '黒石市', '五所川原市', 'つがる市', '平川市'
        ),
        // 岩手県
        '岩手県' => array(
            '盛岡市', '宮古市', '大船渡市', '花巻市', '北上市', '久慈市', '遠野市', '一関市'
        ),
        // 宮城県
        '宮城県' => array(
            '仙台市', '石巻市', '塩竈市', '気仙沼市', '白石市', '名取市', '多賀城市', '岩沼市'
        ),
        // 秋田県
        '秋田県' => array(
            '秋田市', '能代市', '横手市', '大館市', '男鹿市', '湯沢市', '鹿角市', '由利本荘市'
        ),
        // 山形県
        '山形県' => array(
            '山形市', '米沢市', '鶴岡市', '酒田市', '新庄市', '寒河江市', '上山市', '村山市'
        ),
        // 福島県
        '福島県' => array(
            '福島市', '会津若松市', '郡山市', 'いわき市', '白河市', '須賀川市', '喜多方市'
        ),
        // 茨城県
        '茨城県' => array(
            '水戸市', '日立市', '土浦市', '古河市', '石岡市', '結城市', '龍ケ崎市', '下妻市'
        ),
        // 栃木県
        '栃木県' => array(
            '宇都宮市', '足利市', '栃木市', '佐野市', '鹿沼市', '日光市', '小山市', '真岡市'
        ),
        // 群馬県
        '群馬県' => array(
            '前橋市', '高崎市', '桐生市', '伊勢崎市', '太田市', '沼田市', '館林市', '渋川市'
        ),
        // 埼玉県
        '埼玉県' => array(
            'さいたま市', '川越市', '熊谷市', '川口市', '行田市', '秩父市', '所沢市', '飯能市'
        ),
        // 千葉県
        '千葉県' => array(
            '千葉市', '銚子市', '市川市', '船橋市', '館山市', '木更津市', '松戸市', '野田市'
        ),
        // 東京都
        '東京都' => array(
            '千代田区', '中央区', '港区', '新宿区', '文京区', '台東区', '墨田区', '江東区',
            '品川区', '目黒区', '大田区', '世田谷区', '渋谷区', '中野区', '杉並区', '豊島区',
            '北区', '荒川区', '板橋区', '練馬区', '足立区', '葛飾区', '江戸川区',
            '八王子市', '立川市', '武蔵野市', '三鷹市', '青梅市', '府中市', '昭島市'
        ),
        // 神奈川県
        '神奈川県' => array(
            '横浜市', '川崎市', '相模原市', '横須賀市', '平塚市', '鎌倉市', '藤沢市', '小田原市'
        ),
        // 新潟県
        '新潟県' => array(
            '新潟市', '長岡市', '三条市', '柏崎市', '新発田市', '小千谷市', '加茂市', '十日町市'
        ),
        // 富山県
        '富山県' => array(
            '富山市', '高岡市', '魚津市', '氷見市', '滑川市', '黒部市', '砺波市', '小矢部市'
        ),
        // 石川県
        '石川県' => array(
            '金沢市', '七尾市', '小松市', '輪島市', '珠洲市', '加賀市', '羽咋市', 'かほく市'
        ),
        // 福井県
        '福井県' => array(
            '福井市', '敦賀市', '小浜市', '大野市', '勝山市', '鯖江市', 'あわら市', '越前市'
        ),
        // 山梨県
        '山梨県' => array(
            '甲府市', '富士吉田市', '都留市', '山梨市', '大月市', '韮崎市', '南アルプス市'
        ),
        // 長野県
        '長野県' => array(
            '長野市', '松本市', '上田市', '岡谷市', '飯田市', '諏訪市', '須坂市', '小諸市'
        ),
        // 岐阜県
        '岐阜県' => array(
            '岐阜市', '大垣市', '高山市', '多治見市', '関市', '中津川市', '美濃市', '瑞浪市'
        ),
        // 静岡県
        '静岡県' => array(
            '静岡市', '浜松市', '沼津市', '熱海市', '三島市', '富士宮市', '伊東市', '島田市'
        ),
        // 愛知県
        '愛知県' => array(
            '名古屋市', '豊橋市', '岡崎市', '一宮市', '瀬戸市', '半田市', '春日井市', '豊川市'
        ),
        // 三重県
        '三重県' => array(
            '津市', '四日市市', '伊勢市', '松阪市', '桑名市', '鈴鹿市', '名張市', '尾鷲市'
        ),
        // 滋賀県
        '滋賀県' => array(
            '大津市', '彦根市', '長浜市', '近江八幡市', '草津市', '守山市', '栗東市', '甲賀市'
        ),
        // 京都府
        '京都府' => array(
            '京都市', '福知山市', '舞鶴市', '綾部市', '宇治市', '宮津市', '亀岡市', '城陽市'
        ),
        // 大阪府
        '大阪府' => array(
            '大阪市', '堺市', '岸和田市', '豊中市', '池田市', '吹田市', '泉大津市', '高槻市'
        ),
        // 兵庫県
        '兵庫県' => array(
            '神戸市', '姫路市', '尼崎市', '明石市', '西宮市', '洲本市', '芦屋市', '伊丹市'
        ),
        // 奈良県
        '奈良県' => array(
            '奈良市', '大和高田市', '大和郡山市', '天理市', '橿原市', '桜井市', '五條市'
        ),
        // 和歌山県
        '和歌山県' => array(
            '和歌山市', '海南市', '橋本市', '有田市', '御坊市', '田辺市', '新宮市', '紀の川市'
        ),
        // 鳥取県
        '鳥取県' => array(
            '鳥取市', '米子市', '倉吉市', '境港市'
        ),
        // 島根県
        '島根県' => array(
            '松江市', '浜田市', '出雲市', '益田市', '大田市', '安来市', '江津市', '雲南市'
        ),
        // 岡山県
        '岡山県' => array(
            '岡山市', '倉敷市', '津山市', '玉野市', '笠岡市', '井原市', '総社市', '高梁市'
        ),
        // 広島県
        '広島県' => array(
            '広島市', '呉市', '竹原市', '三原市', '尾道市', '福山市', '府中市', '三次市'
        ),
        // 山口県
        '山口県' => array(
            '下関市', '宇部市', '山口市', '萩市', '防府市', '下松市', '岩国市', '光市'
        ),
        // 徳島県
        '徳島県' => array(
            '徳島市', '鳴門市', '小松島市', '阿南市', '吉野川市', '阿波市', '美馬市', '三好市'
        ),
        // 香川県
        '香川県' => array(
            '高松市', '丸亀市', '坂出市', '善通寺市', '観音寺市', 'さぬき市', '東かがわ市'
        ),
        // 愛媛県
        '愛媛県' => array(
            '松山市', '今治市', '宇和島市', '八幡浜市', '新居浜市', '西条市', '大洲市'
        ),
        // 高知県
        '高知県' => array(
            '高知市', '室戸市', '安芸市', '南国市', '土佐市', '須崎市', '宿毛市', '土佐清水市'
        ),
        // 福岡県
        '福岡県' => array(
            '北九州市', '福岡市', '大牟田市', '久留米市', '直方市', '飯塚市', '田川市', '柳川市'
        ),
        // 佐賀県
        '佐賀県' => array(
            '佐賀市', '唐津市', '鳥栖市', '多久市', '伊万里市', '武雄市', '鹿島市', '小城市'
        ),
        // 長崎県
        '長崎県' => array(
            '長崎市', '佐世保市', '島原市', '諫早市', '大村市', '平戸市', '松浦市', '対馬市'
        ),
        // 熊本県
        '熊本県' => array(
            '熊本市', '八代市', '人吉市', '荒尾市', '水俣市', '玉名市', '山鹿市', '菊池市'
        ),
        // 大分県
        '大分県' => array(
            '大分市', '別府市', '中津市', '日田市', '佐伯市', '臼杵市', '津久見市', '竹田市'
        ),
        // 宮崎県
        '宮崎県' => array(
            '宮崎市', '都城市', '延岡市', '日南市', '小林市', '日向市', '串間市', '西都市'
        ),
        // 鹿児島県
        '鹿児島県' => array(
            '鹿児島市', '鹿屋市', '枕崎市', '阿久根市', '出水市', '指宿市', '西之表市', '垂水市'
        ),
        // 沖縄県
        '沖縄県' => array(
            '那覇市', '宜野湾市', '石垣市', '浦添市', '名護市', '糸満市', '沖縄市', '豊見城市'
        )
    );

    // 各都道府県の市町村を登録
    foreach ($municipalities_data as $prefecture_name => $municipalities) {
        // 都道府県タームを取得
        $prefecture_term = get_term_by('name', $prefecture_name, 'grant_prefecture');
        
        if ($prefecture_term) {
            foreach ($municipalities as $municipality_name) {
                // 市町村がすでに存在するかチェック
                $existing_term = get_term_by('name', $municipality_name, 'grant_municipality');
                
                if (!$existing_term) {
                    $result = wp_insert_term(
                        $municipality_name, 
                        'grant_municipality',
                        array(
                            'description' => $prefecture_name . 'の' . $municipality_name,
                            'slug' => sanitize_title($prefecture_name . '-' . $municipality_name)
                        )
                    );
                    
                    if (!is_wp_error($result)) {
                        // カスタムメタで都道府県との関連付けを保存
                        add_term_meta($result['term_id'], 'prefecture_id', $prefecture_term->term_id);
                        add_term_meta($result['term_id'], 'prefecture_name', $prefecture_name);
                    }
                }
            }
        }
    }
}

/**
 * 都道府県に基づく市町村の取得
 */
function gi_get_municipalities_by_prefecture($prefecture_term_id) {
    $municipalities = get_terms(array(
        'taxonomy' => 'grant_municipality',
        'hide_empty' => false,
        'meta_query' => array(
            array(
                'key' => 'prefecture_id',
                'value' => $prefecture_term_id,
                'compare' => '='
            )
        )
    ));
    
    return $municipalities;
}

/**
 * 管理画面での市町村選択用Ajax（複数都道府県対応）
 */
function gi_ajax_get_municipalities_by_prefectures() {
    check_ajax_referer('gi_admin_nonce', 'nonce');
    
    $prefecture_ids = isset($_POST['prefecture_ids']) ? array_map('intval', $_POST['prefecture_ids']) : array();
    
    if (empty($prefecture_ids)) {
        wp_send_json_success(array());
        return;
    }
    
    $municipalities = array();
    
    foreach ($prefecture_ids as $prefecture_id) {
        $prefecture_municipalities = gi_get_municipalities_by_prefecture($prefecture_id);
        
        foreach ($prefecture_municipalities as $municipality) {
            $prefecture_name = get_term_meta($municipality->term_id, 'prefecture_name', true);
            
            $municipalities[] = array(
                'id' => $municipality->term_id,
                'name' => $municipality->name,
                'prefecture_id' => $prefecture_id,
                'prefecture_name' => $prefecture_name ?: '不明'
            );
        }
    }
    
    // 市町村名でソート
    usort($municipalities, function($a, $b) {
        return strcmp($a['name'], $b['name']);
    });
    
    wp_send_json_success($municipalities);
}
add_action('wp_ajax_get_municipalities_by_prefectures', 'gi_ajax_get_municipalities_by_prefectures');

/**
 * 管理画面での市町村選択用Ajax（単一都道府県）
 */
function gi_ajax_get_municipalities() {
    check_ajax_referer('gi_admin_nonce', 'nonce');
    
    $prefecture_id = intval($_POST['prefecture_id']);
    
    if (!$prefecture_id) {
        wp_die('Invalid prefecture ID');
    }
    
    $municipalities = gi_get_municipalities_by_prefecture($prefecture_id);
    
    $response = array();
    foreach ($municipalities as $municipality) {
        $response[] = array(
            'id' => $municipality->term_id,
            'name' => $municipality->name
        );
    }
    
    wp_send_json_success($response);
}
add_action('wp_ajax_get_municipalities', 'gi_ajax_get_municipalities');

/**
 * 市町村データの初期化を実行
 */
add_action('init', 'gi_init_municipality_terms', 99);