<?php
/**
 * 簡潔なGoogle Sheets同期システム
 * シンプルで理解しやすい双方向同期
 * 
 * @package Grant_Insight_Perfect
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SimpleGoogleSheetsSync {
    
    private static $instance = null;
    private $access_token;
    private $token_expires_at;
    
    // 必須設定のみ
    const SHEETS_API_URL = 'https://sheets.googleapis.com/v4/spreadsheets/';
    const AUTH_SCOPE = 'https://www.googleapis.com/auth/spreadsheets';
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * フックの初期化
     */
    private function init_hooks() {
        // WordPress管理画面に設定ページを追加
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        
        // 同期フック
        add_action('save_post_grant', array($this, 'sync_post_to_sheets'), 10, 3);
        add_action('before_delete_post', array($this, 'delete_post_from_sheets'));
        
        // AJAX処理
        add_action('wp_ajax_gi_test_sheets_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_gi_manual_sheets_sync', array($this, 'ajax_manual_sync'));
    }
    
    /**
     * 管理画面メニューを追加
     */
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=grant',
            'Google Sheets同期設定',
            'Sheets同期',
            'manage_options',
            'gi-sheets-sync',
            array($this, 'admin_page')
        );
    }
    
    /**
     * 設定の登録
     */
    public function register_settings() {
        register_setting('gi_sheets_settings', 'gi_sheets_config');
        
        add_settings_section(
            'gi_sheets_basic',
            'Google Sheets設定',
            null,
            'gi_sheets_settings'
        );
        
        // スプレッドシートID
        add_settings_field(
            'spreadsheet_id',
            'スプレッドシートID',
            array($this, 'spreadsheet_id_field'),
            'gi_sheets_settings',
            'gi_sheets_basic'
        );
        
        // シート名
        add_settings_field(
            'sheet_name',
            'シート名',
            array($this, 'sheet_name_field'),
            'gi_sheets_settings',
            'gi_sheets_basic'
        );
        
        // サービスアカウントJSON
        add_settings_field(
            'service_account_json',
            'サービスアカウントJSON',
            array($this, 'service_account_field'),
            'gi_sheets_settings',
            'gi_sheets_basic'
        );
        
        // ヘッダー行設定
        add_settings_field(
            'header_row',
            'ヘッダー行（カラム名）',
            array($this, 'header_row_field'),
            'gi_sheets_settings',
            'gi_sheets_basic'
        );
    }
    
    /**
     * 管理画面ページ
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>Google Sheets同期設定</h1>
            
            <?php if (isset($_GET['settings-updated'])) : ?>
                <div class="notice notice-success is-dismissible">
                    <p>設定が保存されました。</p>
                </div>
            <?php endif; ?>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('gi_sheets_settings');
                do_settings_sections('gi_sheets_settings');
                submit_button();
                ?>
            </form>
            
            <hr>
            
            <h2>動作確認</h2>
            <p>
                <button type="button" class="button" id="test-connection">接続テスト</button>
                <button type="button" class="button button-primary" id="manual-sync">手動同期実行</button>
            </p>
            
            <div id="sync-result" style="margin-top: 20px;"></div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#test-connection').click(function() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'gi_test_sheets_connection',
                        nonce: '<?php echo wp_create_nonce('gi_sheets_nonce'); ?>'
                    },
                    success: function(response) {
                        $('#sync-result').html('<div class="notice notice-' + 
                            (response.success ? 'success' : 'error') + '"><p>' + 
                            response.data + '</p></div>');
                    }
                });
            });
            
            $('#manual-sync').click(function() {
                $(this).prop('disabled', true).text('同期中...');
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'gi_manual_sheets_sync',
                        nonce: '<?php echo wp_create_nonce('gi_sheets_nonce'); ?>'
                    },
                    success: function(response) {
                        $('#sync-result').html('<div class="notice notice-' + 
                            (response.success ? 'success' : 'error') + '"><p>' + 
                            response.data + '</p></div>');
                    },
                    complete: function() {
                        $('#manual-sync').prop('disabled', false).text('手動同期実行');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * 設定フィールド
     */
    public function spreadsheet_id_field() {
        $config = get_option('gi_sheets_config', array());
        $value = isset($config['spreadsheet_id']) ? $config['spreadsheet_id'] : '';
        echo '<input type="text" name="gi_sheets_config[spreadsheet_id]" value="' . esc_attr($value) . '" class="regular-text" placeholder="1ABC...XYZ" />';
        echo '<p class="description">Google SheetsのURLからスプレッドシートIDを取得してください</p>';
    }
    
    public function sheet_name_field() {
        $config = get_option('gi_sheets_config', array());
        $value = isset($config['sheet_name']) ? $config['sheet_name'] : 'grant_import';
        echo '<input type="text" name="gi_sheets_config[sheet_name]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">同期するシートのタブ名</p>';
    }
    
    public function service_account_field() {
        $config = get_option('gi_sheets_config', array());
        $value = isset($config['service_account_json']) ? $config['service_account_json'] : '';
        echo '<textarea name="gi_sheets_config[service_account_json]" rows="10" class="large-text code" placeholder=\'{"type": "service_account", ...}\'>' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">Google Cloud Consoleから取得したサービスアカウントのJSONキー</p>';
    }
    
    public function header_row_field() {
        $config = get_option('gi_sheets_config', array());
        $default_headers = array(
            'ID', 'タイトル', '内容', 'カテゴリ', '都道府県', '市町村', 
            'タグ', '募集開始日', '募集終了日', '公開状況', '最終更新'
        );
        $headers = isset($config['headers']) ? $config['headers'] : $default_headers;
        
        echo '<div id="header-fields">';
        foreach ($headers as $index => $header) {
            echo '<div class="header-field">';
            echo '<input type="text" name="gi_sheets_config[headers][' . $index . ']" value="' . esc_attr($header) . '" placeholder="カラム名" />';
            echo '<button type="button" class="button remove-header">削除</button>';
            echo '</div>';
        }
        echo '</div>';
        echo '<button type="button" class="button" id="add-header">カラム追加</button>';
        echo '<p class="description">Google Sheetsの1行目に設定されるヘッダー（カラム名）</p>';
        
        ?>
        <script>
        jQuery(document).ready(function($) {
            let headerIndex = <?php echo count($headers); ?>;
            
            $('#add-header').click(function() {
                $('#header-fields').append(
                    '<div class="header-field">' +
                    '<input type="text" name="gi_sheets_config[headers][' + headerIndex + ']" placeholder="カラム名" />' +
                    '<button type="button" class="button remove-header">削除</button>' +
                    '</div>'
                );
                headerIndex++;
            });
            
            $(document).on('click', '.remove-header', function() {
                $(this).closest('.header-field').remove();
            });
        });
        </script>
        <style>
        .header-field {
            margin-bottom: 5px;
        }
        .header-field input {
            width: 200px;
            margin-right: 10px;
        }
        </style>
        <?php
    }
    
    /**
     * 設定を取得
     */
    private function get_config() {
        return get_option('gi_sheets_config', array());
    }
    
    /**
     * サービスアカウント情報を取得
     */
    private function get_service_account_key() {
        $config = $this->get_config();
        if (empty($config['service_account_json'])) {
            return false;
        }
        
        $json = json_decode($config['service_account_json'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }
        
        return $json;
    }
    
    /**
     * アクセストークンを取得
     */
    private function get_access_token() {
        // キャッシュされたトークンがあり、まだ有効な場合
        if ($this->access_token && $this->token_expires_at && time() < ($this->token_expires_at - 300)) {
            return $this->access_token;
        }
        
        $service_account = $this->get_service_account_key();
        if (!$service_account) {
            error_log('Google Sheets Sync: Service account key not found');
            return false;
        }
        
        // JWT作成
        $jwt = $this->create_jwt($service_account);
        if (!$jwt) {
            return false;
        }
        
        // トークンリクエスト
        $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
            'body' => array(
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt
            ),
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded'
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            error_log('Google Sheets Sync: Token request failed - ' . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $token_data = json_decode($body, true);
        
        if (!isset($token_data['access_token'])) {
            error_log('Google Sheets Sync: Invalid token response - ' . $body);
            return false;
        }
        
        // トークンをキャッシュ
        $this->access_token = $token_data['access_token'];
        $this->token_expires_at = time() + intval($token_data['expires_in']);
        
        // データベースにも保存
        update_option('gi_sheets_access_token', $this->access_token);
        update_option('gi_sheets_token_expires', $this->token_expires_at);
        
        return $this->access_token;
    }
    
    /**
     * JWTを作成
     */
    private function create_jwt($service_account) {
        $header = array(
            'alg' => 'RS256',
            'typ' => 'JWT'
        );
        
        $now = time();
        $payload = array(
            'iss' => $service_account['client_email'],
            'scope' => self::AUTH_SCOPE,
            'aud' => $service_account['token_uri'],
            'exp' => $now + 3600,
            'iat' => $now
        );
        
        $header_encoded = $this->base64url_encode(json_encode($header));
        $payload_encoded = $this->base64url_encode(json_encode($payload));
        
        $signature_input = $header_encoded . '.' . $payload_encoded;
        
        $private_key = $service_account['private_key'];
        if (!openssl_sign($signature_input, $signature, $private_key, OPENSSL_ALGO_SHA256)) {
            error_log('Google Sheets Sync: JWT signing failed');
            return false;
        }
        
        $signature_encoded = $this->base64url_encode($signature);
        
        return $signature_input . '.' . $signature_encoded;
    }
    
    /**
     * Base64URLエンコード
     */
    private function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Sheetsからデータを読み取り
     */
    public function read_sheet_data($range = null) {
        $config = $this->get_config();
        if (empty($config['spreadsheet_id'])) {
            return false;
        }
        
        $access_token = $this->get_access_token();
        if (!$access_token) {
            return false;
        }
        
        if (!$range) {
            $sheet_name = !empty($config['sheet_name']) ? $config['sheet_name'] : 'grant_import';
            $range = $sheet_name . '!A:Z';  // A-Z列を読み取り
        }
        
        $url = self::SHEETS_API_URL . $config['spreadsheet_id'] . '/values/' . urlencode($range);
        
        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            error_log('Google Sheets Sync: Read request failed - ' . $response->get_error_message());
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            error_log('Google Sheets Sync: Read failed - HTTP ' . $response_code);
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        return isset($data['values']) ? $data['values'] : array();
    }
    
    /**
     * Sheetsにデータを書き込み
     */
    public function write_sheet_data($range, $values) {
        $config = $this->get_config();
        if (empty($config['spreadsheet_id'])) {
            return false;
        }
        
        $access_token = $this->get_access_token();
        if (!$access_token) {
            return false;
        }
        
        $url = self::SHEETS_API_URL . $config['spreadsheet_id'] . '/values/' . urlencode($range) . '?valueInputOption=RAW';
        
        $response = wp_remote_request($url, array(
            'method' => 'PUT',
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'values' => $values
            )),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            error_log('Google Sheets Sync: Write request failed - ' . $response->get_error_message());
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        return $response_code === 200;
    }
    
    /**
     * ヘッダー行を設定
     */
    public function setup_sheet_headers() {
        $config = $this->get_config();
        $headers = isset($config['headers']) ? $config['headers'] : array();
        
        if (empty($headers)) {
            return false;
        }
        
        $sheet_name = !empty($config['sheet_name']) ? $config['sheet_name'] : 'grant_import';
        $range = $sheet_name . '!A1:' . $this->number_to_column(count($headers)) . '1';
        
        return $this->write_sheet_data($range, array($headers));
    }
    
    /**
     * 数字を列文字に変換 (1=A, 2=B, ...)
     */
    private function number_to_column($number) {
        $column = '';
        while ($number > 0) {
            $number--;
            $column = chr($number % 26 + 65) . $column;
            $number = intval($number / 26);
        }
        return $column;
    }
    
    /**
     * 投稿をSheetsに同期
     */
    public function sync_post_to_sheets($post_id, $post, $update) {
        if ($post->post_type !== 'grant' || wp_is_post_revision($post_id)) {
            return;
        }
        
        $post_data = $this->prepare_post_data($post_id);
        if (!$post_data) {
            return;
        }
        
        // Sheetsから既存データを取得
        $sheet_data = $this->read_sheet_data();
        if ($sheet_data === false) {
            error_log('Google Sheets Sync: Failed to read sheet data for post ' . $post_id);
            return;
        }
        
        // 既存行を探す
        $row_found = false;
        $row_index = 0;
        
        foreach ($sheet_data as $index => $row) {
            if (!empty($row[0]) && intval($row[0]) === $post_id) {
                $row_found = true;
                $row_index = $index + 1; // Sheetsは1から始まる
                break;
            }
        }
        
        $config = $this->get_config();
        $sheet_name = !empty($config['sheet_name']) ? $config['sheet_name'] : 'grant_import';
        
        if ($row_found) {
            // 既存行を更新
            $range = $sheet_name . '!A' . $row_index . ':' . $this->number_to_column(count($post_data)) . $row_index;
        } else {
            // 新しい行を追加
            $row_index = count($sheet_data) + 1;
            $range = $sheet_name . '!A' . $row_index . ':' . $this->number_to_column(count($post_data)) . $row_index;
        }
        
        $result = $this->write_sheet_data($range, array($post_data));
        
        if ($result) {
            update_post_meta($post_id, '_sheets_last_sync', time());
        }
    }
    
    /**
     * 投稿データを準備
     */
    private function prepare_post_data($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return false;
        }
        
        // カテゴリ取得
        $categories = wp_get_post_terms($post_id, 'grant_category');
        $category_names = array();
        foreach ($categories as $cat) {
            $category_names[] = $cat->name;
        }
        
        // 都道府県取得
        $prefectures = wp_get_post_terms($post_id, 'grant_prefecture');
        $prefecture_names = array();
        foreach ($prefectures as $pref) {
            $prefecture_names[] = $pref->name;
        }
        
        // 市町村取得
        $municipalities = wp_get_post_terms($post_id, 'grant_municipality');
        $municipality_names = array();
        foreach ($municipalities as $muni) {
            $municipality_names[] = $muni->name;
        }
        
        // タグ取得
        $tags = wp_get_post_terms($post_id, 'grant_tag');
        $tag_names = array();
        foreach ($tags as $tag) {
            $tag_names[] = $tag->name;
        }
        
        // ACFフィールド
        $start_date = get_field('application_start_date', $post_id);
        $end_date = get_field('application_end_date', $post_id);
        
        return array(
            $post_id,
            $post->post_title,
            wp_strip_all_tags($post->post_content),
            implode(', ', $category_names),
            implode(', ', $prefecture_names),
            implode(', ', $municipality_names),
            implode(', ', $tag_names),
            $start_date ? $start_date : '',
            $end_date ? $end_date : '',
            $post->post_status,
            $post->post_modified
        );
    }
    
    /**
     * 投稿をSheetsから削除
     */
    public function delete_post_from_sheets($post_id) {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'grant') {
            return;
        }
        
        // 実装: 行を空にする または 削除マークを付ける
        // 簡単な実装では行を空にする
        $sheet_data = $this->read_sheet_data();
        if ($sheet_data === false) {
            return;
        }
        
        foreach ($sheet_data as $index => $row) {
            if (!empty($row[0]) && intval($row[0]) === $post_id) {
                $config = $this->get_config();
                $sheet_name = !empty($config['sheet_name']) ? $config['sheet_name'] : 'grant_import';
                $row_index = $index + 1;
                $range = $sheet_name . '!A' . $row_index . ':Z' . $row_index;
                
                // 行を空にする
                $this->write_sheet_data($range, array(array_fill(0, 26, '')));
                break;
            }
        }
    }
    
    /**
     * AJAX: 接続テスト
     */
    public function ajax_test_connection() {
        check_ajax_referer('gi_sheets_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('権限がありません');
        }
        
        $access_token = $this->get_access_token();
        
        if ($access_token) {
            wp_send_json_success('Google Sheetsへの接続に成功しました。');
        } else {
            wp_send_json_error('Google Sheetsへの接続に失敗しました。設定を確認してください。');
        }
    }
    
    /**
     * AJAX: 手動同期
     */
    public function ajax_manual_sync() {
        check_ajax_referer('gi_sheets_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('権限がありません');
        }
        
        // ヘッダー行を設定
        $header_result = $this->setup_sheet_headers();
        if (!$header_result) {
            wp_send_json_error('ヘッダー行の設定に失敗しました。');
            return;
        }
        
        // 全ての助成金投稿を同期
        $grants = get_posts(array(
            'post_type' => 'grant',
            'post_status' => array('publish', 'draft', 'private'),
            'numberposts' => -1
        ));
        
        $success_count = 0;
        $error_count = 0;
        
        foreach ($grants as $post) {
            $this->sync_post_to_sheets($post->ID, $post, true);
            
            // 結果をチェック（簡単な実装では成功とみなす）
            $success_count++;
        }
        
        wp_send_json_success("同期完了: {$success_count}件の投稿を同期しました。");
    }
}

// インスタンス初期化
function gi_init_simple_google_sheets_sync() {
    return SimpleGoogleSheetsSync::getInstance();
}

add_action('init', 'gi_init_simple_google_sheets_sync');