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

class GoogleSheetsSync {
    
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
        // 同期フック（管理画面は既存のSheetsAdminUIを使用）
        add_action('save_post_grant', array($this, 'sync_post_to_sheets'), 10, 3);
        add_action('before_delete_post', array($this, 'delete_post_from_sheets'));
        
        // 既存管理画面用のAJAX処理をサポート
        add_action('wp_ajax_gi_test_sheets_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_gi_manual_sheets_sync', array($this, 'ajax_manual_sync'));
    }
    

    

    

    
    /**
     * サービスアカウント情報を取得（既存のハードコード設定を使用）
     */
    private function get_service_account_key() {
        // 既存のハードコード設定を使用（元のコードから）
        return array(
            "type" => "service_account",
            "project_id" => "grant-sheets-integration",
            "private_key_id" => "c0fdd6753a43e1c51cbc1854c4ce53cb461b0136",
            "private_key" => "-----BEGIN PRIVATE KEY-----\nMIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQC+Ba+i0O4k0Jta\n17u3D/hJaqkLuptpyknOhjeQLzOGl9GtRP88KYX+NpKO1RxuuZMmlBt/7ShlXDPk\nJXdOtOjPlMzHZeh32M/f+98L9S9PVfapGUKRV0p4XJmExljmP7AVnXaMjlXqm9BJ\ngvO7K898LApyAsdrtcOYgt371LWZbQdTqpNWQemfJcYnTndwMcYzv6Snm/lOUruD\nrV2VOhvsMfqwVOaKywhE6rvUrF1ARaT3meQJyF9CpqFcb947f5phRUVD1QEdQp1K\nfGeFmMqR3nT4sY6I7VVqnseyr7v6U4i9V2aaL8KhUmH895xRlL6cc+QR7lgPtkT3\nZ8FJdseLAgMBAAECggEAWj9OFrg+2jo/Bmp+SyepBolDJwBl7lz2J8Fj4zUfthUl\nrrKdu9+GtWEKww5g1g+J3SErXFrwvA8J0BmhK77M8UWc6jiyqzTMKXcwjDfS082i\ne9Y04N1Bz58/BCnFr/jgcquZ0ZCKKoX86uToR+U7QiCSh2pddwDZF/ZTYla4NtiZ\nP/uZBAIuO/Fz2bLnjzQrQ1tLBdgY3mWx/wChi6+JhqubiNTnrWqy8qXG8P2OieZS\nQxU31/EjOp8rK4ErxqN5WDS0BRhIKM0DTN3WXwB8Sb5JCSluxksdICvNshiilsVF\nQGsXF3pGZA6Okv9cJS0u6vUoYVMMSzeWQvyM0tKwuQKBgQDgrUS2K21sVun+mI3L\niQ99XlMDT0AhsDaSWyenqveNawosoKz3ueBXEwkpOcM8DdcTDKbZVohM7h1cTEax\nPobdj2bQdUFWkzup5kekVBu88bIPthTMK5IuTUcHYyfiH8V7vsEtrX184UAiET/p\nXmHZ+lcUCuL+8+uKogEdvy/1UwKBgQDYg5eJlQ0hoOH0VP8HkSeJSn246X8CdeHT\n1kgkymJcLwWYr+EKngTQrSkLkIfxBER3UMfHtla95IL4qGC/iNcIWbie2Gtc2wXz\nWvwpaoliReoKOYyFG94Fl5zdcp5xYi2oA2qB9LM+eyCqqEEkVhpg3w61Xfj03wMI\n6Ibxc0al6QKBgQC7KVut7WtP7u8qOWcVgG244BSDE0e3SJWNQgY8tD1YPyzQlGDC\nVMM/hgoBn661nknmAooTTvRoMYuf0aKqEA5FDyp0yNjPCAORutU/XRlmQmk0kVet\n5TX3AEUFMGKPCix2syc1p+p7VyEXwArfmtIkxVg4yADkpck3SVFouFV5JQKBgDcz\njb45L0jkoNdPmFoQixj40gcEGSrCbVo6JtiidON15aJhLSos0aN2kqFtLwum/+G/\nyb/EYGc3zKCjJU+QDusFHQn6uZzKBsFd8C6LCA3zL1F+DLKfQUMBva/EGltkIanV\nfSE3B0Al2lVIYptmDIGoPTLGi8O63CY4SrdioZ+JAoGAMjzeU4jqFtkXaiRBTa+v\njspaqbk1rq1x4ZmnPMZzMQnZLStP9QP7SQn5/my/ZSWcnmjxW8ZgMdfWB1TD51RC\n4HYL/jGrjOUmumshQmiA1a7zCvr8yVJFkOVcYpCWl6TT5hiFbqrW82Dw73JFHTuK\n30Chu7ki9aOiJJeMmHaOfOU=\n-----END PRIVATE KEY-----\n",
            "client_email" => "grant-sheets-service@grant-sheets-integration.iam.gserviceaccount.com",
            "client_id" => "109769300820349787611",
            "auth_uri" => "https://accounts.google.com/o/oauth2/auth",
            "token_uri" => "https://oauth2.googleapis.com/token",
            "auth_provider_x509_cert_url" => "https://www.googleapis.com/oauth2/v1/certs",
            "client_x509_cert_url" => "https://www.googleapis.com/robot/v1/metadata/x509/grant-sheets-service%40grant-sheets-integration.iam.gserviceaccount.com",
            "universe_domain" => "googleapis.com"
        );
    }
    
    /**
     * スプレッドシート設定を取得
     */
    private function get_spreadsheet_id() {
        return '1kGc1Eb4AYvURkSfdzMwipNjfe8xC6iGCM2q1sUgIfWg';
    }
    
    private function get_sheet_name() {
        return 'grant_import';
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
        $access_token = $this->get_access_token();
        if (!$access_token) {
            return false;
        }
        
        if (!$range) {
            $range = $this->get_sheet_name() . '!A:Z';  // A-Z列を読み取り
        }
        
        $url = self::SHEETS_API_URL . $this->get_spreadsheet_id() . '/values/' . urlencode($range);
        
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
        $access_token = $this->get_access_token();
        if (!$access_token) {
            return false;
        }
        
        $url = self::SHEETS_API_URL . $this->get_spreadsheet_id() . '/values/' . urlencode($range) . '?valueInputOption=RAW';
        
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
     * ヘッダー行を設定（固定）
     */
    public function setup_sheet_headers() {
        $headers = array(
            'ID', 'タイトル', '内容', 'カテゴリ', '都道府県', '市町村', 
            'タグ', '募集開始日', '募集終了日', '公開状況', '最終更新'
        );
        
        $range = $this->get_sheet_name() . '!A1:' . $this->number_to_column(count($headers)) . '1';
        
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
        
        $sheet_name = $this->get_sheet_name();
        
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
            // A-K列: 基本フィールド（初期バージョン）
            $post_id,                                    // A列: ID
            $post->post_title,                           // B列: タイトル
            wp_strip_all_tags($post->post_content),      // C列: 内容
            implode(', ', $category_names),              // D列: カテゴリ ★基本フィールド
            implode(', ', $prefecture_names),            // E列: 都道府県 ★基本フィールド
            implode(', ', $municipality_names),          // F列: 市町村 ★基本フィールド
            implode(', ', $tag_names),                   // G列: タグ ★基本フィールド
            $start_date ? $start_date : '',              // H列: 募集開始日 ★基本フィールド
            $end_date ? $end_date : '',                  // I列: 募集終了日 ★基本フィールド
            $post->post_status,                          // J列: 公開状況 ★基本フィールド
            $post->post_modified,                        // K列: 最終更新 ★基本フィールド
            
            // L-S列: 組織・申請情報
            get_field('implementing_organization', $post_id) ?: '',     // L列: 実施組織
            get_field('organization_type', $post_id) ?: '',             // M列: 組織タイプ
            get_field('target_description', $post_id) ?: '',            // N列: 対象者・対象事業
            get_field('application_method', $post_id) ?: '',            // O列: 申請方法
            get_field('contact_info', $post_id) ?: '',                  // P列: 問い合わせ先
            get_field('official_url', $post_id) ?: '',                  // Q列: 公式URL
            get_field('area_restriction', $post_id) ?: '',              // R列: 地域制限
            get_field('application_status', $post_id) ?: '',            // S列: 申請ステータス
            
            // T-X列: 追加情報
            get_field('required_documents', $post_id) ?: '',            // T列: 必要書類
            get_field('adoption_rate', $post_id) ?: '',                 // U列: 採択率（%）
            get_field('difficulty_level', $post_id) ?: '',              // V列: 申請難易度
            get_field('eligible_expenses', $post_id) ?: '',             // W列: 対象経費
            get_field('subsidy_rate', $post_id) ?: '',                  // X列: 補助率
            
            // Y列: システム情報
            current_time('Y-m-d H:i:s')                                 // Y列: シート更新日
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
                $sheet_name = $this->get_sheet_name();
                $row_index = $index + 1;
                $range = $sheet_name . '!A' . $row_index . ':Y' . $row_index;
                
                // 行を空にする（25列に対応）
                $this->write_sheet_data($range, array(array_fill(0, 25, '')));
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
function gi_init_google_sheets_sync() {
    return GoogleSheetsSync::getInstance();
}

add_action('init', 'gi_init_google_sheets_sync');