<?php
/**
 * 古いGoogle Sheets同期システム - 無効化済み
 * 
 * このファイルは新しいシンプルなシステムに置き換えられました。
 * google-sheets-sync-simple.php を使用してください。
 * 
 * @deprecated Use google-sheets-sync-simple.php instead
 * @package Grant_Insight_Perfect
 */

// セキュリティチェック
if (!defined('ABSPATH')) {
    exit;
}

// 管理者に通知
add_action('admin_notices', function() {
    if (current_user_can('manage_options')) {
        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p><strong>Google Sheets同期システム:</strong> 新しいシンプルなシステムに移行されました。';
        echo '設定は「助成金」→「Sheets同期」から行ってください。</p>';
        echo '</div>';
    }
});

// 古いクラスが呼び出された場合の互換性クラス（エラー防止）
if (!class_exists('GoogleSheetsSync')) {
    class GoogleSheetsSync {
        private static $instance = null;
        
        public static function getInstance() {
            if (self::$instance === null) {
                self::$instance = new self();
            }
            return self::$instance;
        }
        
        public function __construct() {
            // 何もしない - 無効化されたクラス
        }
        
        // 旧メソッドのスタブ（エラー防止）
        public function sync_post_to_sheets($post_id, $post, $update) {
            // 新しいシステムにリダイレクト
            if (class_exists('SimpleGoogleSheetsSync')) {
                $simple_sync = SimpleGoogleSheetsSync::getInstance();
                return $simple_sync->sync_post_to_sheets($post_id, $post, $update);
            }
        }
        
        public function delete_post_from_sheets($post_id) {
            if (class_exists('SimpleGoogleSheetsSync')) {
                $simple_sync = SimpleGoogleSheetsSync::getInstance();
                return $simple_sync->delete_post_from_sheets($post_id);
            }
        }
        
        // その他の古いメソッド呼び出しを防ぐ
        public function __call($method, $args) {
            error_log("Deprecated GoogleSheetsSync method called: {$method}. Use SimpleGoogleSheetsSync instead.");
            return null;
        }
    }
}