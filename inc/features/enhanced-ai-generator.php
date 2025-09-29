<?php
/**
 * Enhanced AI Content Generator
 * Advanced AI generation with context awareness and SEO optimization
 * 
 * @package Grant_Insight_Perfect
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GI_Enhanced_AI_Generator {
    
    private $api_key;
    private $model = 'gpt-3.5-turbo';
    
    public function __construct() {
        // Get API key from options or constants
        $this->api_key = defined('OPENAI_API_KEY') ? OPENAI_API_KEY : get_option('gi_openai_api_key', '');
        
        add_action('wp_ajax_gi_smart_generate', array($this, 'handle_smart_generation'));
        add_action('wp_ajax_gi_regenerate_content', array($this, 'handle_regeneration'));
        add_action('wp_ajax_gi_contextual_fill', array($this, 'handle_contextual_fill'));
    }
    
    /**
     * Smart content generation based on existing fields
     */
    public function handle_smart_generation() {
        check_ajax_referer('gi_ai_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die('Permission denied');
        }
        
        $existing_data = $this->sanitize_input($_POST['existing_data'] ?? []);
        $target_field = sanitize_text_field($_POST['target_field'] ?? '');
        $generation_mode = sanitize_text_field($_POST['mode'] ?? 'smart_fill');
        
        try {
            $generated_content = $this->generate_contextual_content($existing_data, $target_field, $generation_mode);
            
            wp_send_json_success([
                'content' => $generated_content,
                'field' => $target_field,
                'mode' => $generation_mode,
                'context_used' => !empty($existing_data)
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'fallback' => $this->get_fallback_content($target_field, $existing_data)
            ]);
        }
    }
    
    /**
     * Handle content regeneration
     */
    public function handle_regeneration() {
        check_ajax_referer('gi_ai_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die('Permission denied');
        }
        
        $existing_data = $this->sanitize_input($_POST['existing_data'] ?? []);
        $target_field = sanitize_text_field($_POST['target_field'] ?? '');
        $current_content = sanitize_textarea_field($_POST['current_content'] ?? '');
        $regeneration_type = sanitize_text_field($_POST['type'] ?? 'improve');
        
        try {
            $regenerated_content = $this->regenerate_content($existing_data, $target_field, $current_content, $regeneration_type);
            
            wp_send_json_success([
                'content' => $regenerated_content,
                'original' => $current_content,
                'type' => $regeneration_type
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'fallback' => $this->improve_content_simple($current_content, $target_field)
            ]);
        }
    }
    
    /**
     * Handle contextual filling of multiple fields
     */
    public function handle_contextual_fill() {
        check_ajax_referer('gi_ai_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die('Permission denied');
        }
        
        $existing_data = $this->sanitize_input($_POST['existing_data'] ?? []);
        $empty_fields = $_POST['empty_fields'] ?? [];
        
        try {
            $filled_content = $this->fill_empty_fields($existing_data, $empty_fields);
            
            wp_send_json_success([
                'filled_fields' => $filled_content,
                'context_data' => $existing_data
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'partial_fill' => $this->get_fallback_fills($empty_fields)
            ]);
        }
    }
    
    /**
     * Generate contextual content based on existing data
     */
    private function generate_contextual_content($existing_data, $target_field, $mode) {
        // Build context from existing data
        $context = $this->build_context_prompt($existing_data);
        
        // Field-specific generation prompts
        $field_prompts = $this->get_field_specific_prompts();
        $field_prompt = $field_prompts[$target_field] ?? $field_prompts['default'];
        
        // SEO optimization instructions
        $seo_instructions = $this->get_seo_instructions($target_field);
        
        // Build the complete prompt
        $prompt = $this->build_generation_prompt($context, $field_prompt, $seo_instructions, $mode);
        
        // Call AI API
        return $this->call_openai_api($prompt);
    }
    
    /**
     * Build comprehensive context prompt from all available data
     */
    private function build_context_prompt($data) {
        $context_parts = [];
        
        // 基本情報
        if (!empty($data['title'])) {
            $context_parts[] = "助成金名: {$data['title']}";
        }
        
        if (!empty($data['organization'])) {
            $context_parts[] = "実施機関: {$data['organization']}";
        }
        
        if (!empty($data['organization_type'])) {
            $context_parts[] = "組織タイプ: {$data['organization_type']}";
        }
        
        // 金額情報
        if (!empty($data['max_amount'])) {
            $context_parts[] = "最大金額: {$data['max_amount']}万円";
        }
        
        if (!empty($data['min_amount'])) {
            $context_parts[] = "最小金額: {$data['min_amount']}万円";
        }
        
        if (!empty($data['max_amount_yen'])) {
            $context_parts[] = "最大助成額: " . number_format($data['max_amount_yen']) . "円";
        }
        
        if (!empty($data['subsidy_rate'])) {
            $context_parts[] = "補助率: {$data['subsidy_rate']}%";
        }
        
        if (!empty($data['amount_note'])) {
            $context_parts[] = "金額備考: {$data['amount_note']}";
        }
        
        // 期間情報
        if (!empty($data['application_deadline'])) {
            $context_parts[] = "申請期限: {$data['application_deadline']}";
        }
        
        if (!empty($data['recruitment_start'])) {
            $context_parts[] = "募集開始日: {$data['recruitment_start']}";
        }
        
        if (!empty($data['deadline'])) {
            $context_parts[] = "締切日: {$data['deadline']}";
        }
        
        if (!empty($data['deadline_note'])) {
            $context_parts[] = "締切備考: {$data['deadline_note']}";
        }
        
        if (!empty($data['application_status'])) {
            $context_parts[] = "申請ステータス: {$data['application_status']}";
        }
        
        // 対象・カテゴリー情報
        if (!empty($data['prefectures'])) {
            $prefectures = is_array($data['prefectures']) ? implode('、', $data['prefectures']) : $data['prefectures'];
            $context_parts[] = "対象都道府県: {$prefectures}";
        }
        
        if (!empty($data['categories'])) {
            $categories = is_array($data['categories']) ? implode('、', $data['categories']) : $data['categories'];
            $context_parts[] = "カテゴリー: {$categories}";
        }
        
        if (!empty($data['tags'])) {
            $tags = is_array($data['tags']) ? implode('、', $data['tags']) : $data['tags'];
            $context_parts[] = "タグ: {$tags}";
        }
        
        if (!empty($data['grant_target'])) {
            $context_parts[] = "助成金対象: {$data['grant_target']}";
        }
        
        // 対象経費フィールドは削除されました
        
        // 難易度・成功率
        if (!empty($data['difficulty'])) {
            $context_parts[] = "難易度: {$data['difficulty']}";
        }
        
        if (!empty($data['success_rate'])) {
            $context_parts[] = "成功率: {$data['success_rate']}%";
        }
        
        // 詳細情報
        if (!empty($data['eligibility_criteria'])) {
            $criteria_excerpt = mb_substr(strip_tags($data['eligibility_criteria']), 0, 150);
            $context_parts[] = "対象者・応募要件: {$criteria_excerpt}...";
        }
        
        if (!empty($data['application_process'])) {
            $process_excerpt = mb_substr(strip_tags($data['application_process']), 0, 150);
            $context_parts[] = "申請手順: {$process_excerpt}...";
        }
        
        if (!empty($data['application_method'])) {
            $context_parts[] = "申請方法: {$data['application_method']}";
        }
        
        if (!empty($data['required_documents'])) {
            $documents_excerpt = mb_substr(strip_tags($data['required_documents']), 0, 100);
            $context_parts[] = "必要書類: {$documents_excerpt}...";
        }
        
        if (!empty($data['contact_info'])) {
            $context_parts[] = "連絡先: {$data['contact_info']}";
        }
        
        if (!empty($data['official_url'])) {
            $context_parts[] = "公式URL: {$data['official_url']}";
        }
        
        if (!empty($data['summary'])) {
            $summary_excerpt = mb_substr(strip_tags($data['summary']), 0, 200);
            $context_parts[] = "概要: {$summary_excerpt}...";
        }
        
        if (!empty($data['content'])) {
            $content_excerpt = mb_substr(strip_tags($data['content']), 0, 200);
            $context_parts[] = "既存本文: {$content_excerpt}...";
        }
        
        return implode("\n", $context_parts);
    }
    
    /**
     * Get field-specific generation prompts with enhanced HTML/CSS support
     */
    private function get_field_specific_prompts() {
        return [
            'post_title' => [
                'instruction' => '魅力的で検索されやすい助成金タイトルを生成してください',
                'requirements' => '30-60文字、キーワードを含む、具体的で分かりやすい、緊急性や魅力を表現',
                'examples' => '「【令和6年度】IT導入支援事業補助金（最大1000万円）」「中小企業デジタル化促進助成金【申請期限間近】」'
            ],
            'post_content' => [
                'instruction' => 'HTMLとCSSを使用したスタイリッシュで詳細な助成金本文を生成してください',
                'requirements' => '1000-2500文字、HTML構造化、CSS付き、白黒ベースのスタイリッシュなデザイン、黄色蛍光ペン効果使用',
                'structure' => '概要（アイコン付き）→金額詳細（表組み）→対象者（箇条書き）→申請手順（ステップ表示）→必要書類（チェックリスト）→注意事項（警告ボックス）→連絡先（ボックス表示）',
                'html_requirements' => 'div, h2, h3, table, ul, ol, span, strong要素を使用。CSS classを含める。',
                'css_style' => 'モノクロ（#000, #333, #666, #ccc, #f9f9f9）+ 黄色ハイライト（#ffeb3b, #fff59d）を使用',
                'design_theme' => '白黒ベースのスタイリッシュなビジネス文書風、重要部分に黄色蛍光ペン効果'
            ],
            'post_excerpt' => [
                'instruction' => '簡潔で魅力的な助成金概要を生成してください',
                'requirements' => '120-180文字、要点を簡潔に、検索結果で目立つ内容、金額と対象を明確に',
                'focus' => '対象者、最大金額、申請期限、メリットを明確に',
                'tone' => '専門的だが親しみやすく、行動を促す表現'
            ],
            'eligibility_criteria' => [
                'instruction' => '具体的で分かりやすい対象者・応募要件をHTML形式で生成してください',
                'requirements' => 'HTML箇条書き形式、具体的な条件、除外条件も含む、視覚的に分かりやすい',
                'html_format' => '<ul>タグと<li>タグを使用、重要な条件は<strong>で強調',
                'style' => '明確で読みやすい構造、条件の階層化'
            ],
            'application_process' => [
                'instruction' => 'ステップバイステップの申請手順をHTML形式で生成してください',
                'requirements' => 'HTML番号付きリスト、各ステップの詳細、期間、注意点を含む',
                'html_format' => '<ol>と<li>を使用、各ステップに説明とポイントを追加',
                'visual_elements' => 'ステップ番号を視覚的に強調、重要な期限や注意点をハイライト'
            ],
            'required_documents' => [
                'instruction' => '必要書類一覧をHTML形式で生成してください',
                'requirements' => '具体的な書類名、取得方法、注意点をチェックリスト形式で',
                'html_format' => '<ul>でチェックリスト風、書類カテゴリーごとに整理',
                'practical_info' => '取得先や準備時間の目安も含める'
            ],
            'summary' => [
                'instruction' => '助成金の魅力的な概要をHTML形式で生成してください',
                'requirements' => '200-300文字、HTML構造化、重要ポイントを強調',
                'html_format' => '<p>と<span>を使用、キーワードを<strong>で強調',
                'content_focus' => '金額、対象者、メリット、緊急性を含める'
            ],
            'amount_details' => [
                'instruction' => '助成金額の詳細情報をHTML表形式で生成してください',
                'requirements' => 'HTML table形式、明確で理解しやすい金額体系',
                'html_format' => '<table>タグで構造化、ヘッダーと明確な項目分け',
                'content_items' => '最大金額、最小金額、補助率、対象経費を整理'
            ],
            'contact_info' => [
                'instruction' => '連絡先情報を分かりやすいHTML形式で生成してください',
                'requirements' => 'HTML構造化、電話番号、メール、住所を見やすく配置',
                'html_format' => '<div>でボックス化、各連絡手段を明確に分離',
                'practical_focus' => '営業時間や対応可能な問い合わせ内容も含める'
            ],
            'default' => [
                'instruction' => 'この助成金に関する有用な情報をHTML形式で生成してください',
                'requirements' => '正確で実用的、SEO対策済み、HTML構造化',
                'tone' => '専門的だが分かりやすい',
                'html_format' => '適切なHTML要素を使用して構造化'
            ]
        ];
    }
    
    /**
     * Get SEO instructions for specific fields
     */
    private function get_seo_instructions($field) {
        $seo_keywords = ['助成金', '補助金', '支援', '申請', '中小企業', 'スタートアップ'];
        
        switch ($field) {
            case 'post_title':
                return "SEO要件: 主要キーワードを自然に含める。検索意図に合致。32文字以内推奨。";
            case 'post_content':
                return "SEO要件: 関連キーワードを適度に配置。見出し(H2,H3)を使用。内部リンク機会を作る。ユーザーの検索意図に応える。";
            case 'post_excerpt':
                return "SEO要件: メタディスクリプションとしても機能。クリック誘導する内容。主要キーワード含む。";
            default:
                return "SEO要件: 関連キーワードを自然に含める。ユーザーに価値ある情報を提供。";
        }
    }
    
    /**
     * Build complete generation prompt with enhanced HTML/CSS support
     */
    private function build_generation_prompt($context, $field_config, $seo_instructions, $mode) {
        $prompt = "あなたは助成金・補助金の専門家兼Webデザイナーです。以下の情報を参考に、高品質で視覚的に魅力的な内容を生成してください。\n\n";
        
        if (!empty($context)) {
            $prompt .= "【参考データ】\n{$context}\n\n";
        }
        
        $prompt .= "【生成要件】\n";
        $prompt .= "目的: {$field_config['instruction']}\n";
        $prompt .= "要件: {$field_config['requirements']}\n";
        
        // HTML/CSS要件の追加
        if (isset($field_config['html_requirements'])) {
            $prompt .= "HTML要件: {$field_config['html_requirements']}\n";
        }
        
        if (isset($field_config['css_style'])) {
            $prompt .= "CSS基準: {$field_config['css_style']}\n";
        }
        
        if (isset($field_config['design_theme'])) {
            $prompt .= "デザインテーマ: {$field_config['design_theme']}\n";
        }
        
        if (isset($field_config['html_format'])) {
            $prompt .= "HTML形式: {$field_config['html_format']}\n";
        }
        
        $prompt .= "{$seo_instructions}\n\n";
        
        if (isset($field_config['structure'])) {
            $prompt .= "【コンテンツ構成】\n{$field_config['structure']}\n\n";
        }
        
        // 本文生成の場合の特別なCSS・HTMLテンプレート指示
        if (strpos($field_config['instruction'], 'HTMLとCSS') !== false) {
            $prompt .= $this->get_html_css_template_instructions();
        }
        
        $prompt .= "\n【生成モード】\n";
        switch ($mode) {
            case 'creative':
                $prompt .= "クリエイティブで魅力的な表現を重視してください。視覚的インパクトも考慮。";
                break;
            case 'professional':
                $prompt .= "専門的で正確な表現を重視してください。ビジネス文書として完成度高く。";
                break;
            case 'seo_focused':
                $prompt .= "SEO効果を最大化する内容を重視してください。検索エンジンに評価される構造で。";
                break;
            default:
                $prompt .= "バランス良く実用的な内容を生成してください。読みやすさと情報の正確性を両立。";
        }
        
        $prompt .= "\n\n【出力形式】\n";
        $prompt .= "生成内容のみを出力してください（説明文や前置きは不要）。\n";
        $prompt .= "HTMLタグを使用する場合は、正しく閉じタグまで含めて出力してください。";
        
        return $prompt;
    }
    
    /**
     * Get HTML/CSS template instructions for content generation
     */
    private function get_html_css_template_instructions() {
        return "
【HTML/CSSテンプレート指示】
1. CSSスタイル定義:
   - 基本色: #000000(黒), #333333(濃いグレー), #666666(グレー), #cccccc(薄いグレー), #f9f9f9(背景)
   - ハイライト色: #ffeb3b(黄色), #fff59d(薄い黄色) - 重要部分用蛍光ペン効果
   - フォント: sans-serif系、読みやすさ重視
   
2. 必須HTML構造:
   <div class=\"grant-content\">
     <h2 class=\"grant-section\">セクションタイトル</h2>
     <div class=\"grant-highlight\">重要情報ボックス</div>
     <table class=\"grant-table\">詳細表</table>
     <ul class=\"grant-list\">リスト項目</ul>
   </div>

3. CSS クラス定義を含めること:
   <style>
   .grant-content { /* メインコンテナ */ }
   .grant-section { /* セクション見出し */ }
   .grant-highlight { /* 重要情報ハイライト */ }
   .grant-table { /* 表組み */ }
   .grant-list { /* リスト */ }
   .highlight-yellow { /* 黄色蛍光ペン効果 */ }
   </style>

4. デザイン要素:
   - 📋 📊 💰 📅 📞 ✅ などのアイコン使用
   - 表組みでの情報整理
   - 重要部分への黄色ハイライト
   - 白黒ベースのスタイリッシュなレイアウト

";
    }
    
    /**
     * Regenerate existing content with improvements
     */
    private function regenerate_content($existing_data, $field, $current_content, $type) {
        $context = $this->build_context_prompt($existing_data);
        
        $prompt = "以下の内容を{$type}してください。\n\n";
        $prompt .= "【現在の内容】\n{$current_content}\n\n";
        
        if (!empty($context)) {
            $prompt .= "【参考情報】\n{$context}\n\n";
        }
        
        switch ($type) {
            case 'improve':
                $prompt .= "【改善要件】\n- より分かりやすく\n- SEO効果を向上\n- 専門性を高める\n- 文章の流れを改善";
                break;
            case 'shorten':
                $prompt .= "【短縮要件】\n- 要点を保持\n- 50%程度に短縮\n- 重要情報は残す";
                break;
            case 'expand':
                $prompt .= "【拡張要件】\n- より詳細に\n- 具体例を追加\n- 関連情報を補完";
                break;
            case 'seo_optimize':
                $prompt .= "【SEO最適化要件】\n- キーワード密度を適正化\n- 見出し構造を改善\n- 検索意図に最適化";
                break;
        }
        
        $prompt .= "\n\n改善された内容のみを出力してください:";
        
        return $this->call_openai_api($prompt);
    }
    
    /**
     * Fill multiple empty fields based on context
     */
    private function fill_empty_fields($existing_data, $empty_fields) {
        $context = $this->build_context_prompt($existing_data);
        $filled_content = [];
        
        foreach ($empty_fields as $field) {
            try {
                $field_prompts = $this->get_field_specific_prompts();
                $field_config = $field_prompts[$field] ?? $field_prompts['default'];
                
                $prompt = "以下の情報を参考に、{$field}の内容を生成してください。\n\n";
                $prompt .= "【参考情報】\n{$context}\n\n";
                $prompt .= "【要件】\n{$field_config['instruction']}\n{$field_config['requirements']}\n\n";
                $prompt .= "生成内容のみを出力してください:";
                
                $filled_content[$field] = $this->call_openai_api($prompt);
                
                // Rate limiting
                sleep(1);
                
            } catch (Exception $e) {
                $filled_content[$field] = $this->get_fallback_content($field, $existing_data);
            }
        }
        
        return $filled_content;
    }
    
    /**
     * Call OpenAI API
     */
    private function call_openai_api($prompt) {
        if (empty($this->api_key)) {
            throw new Exception('OpenAI API key not configured');
        }
        
        $url = 'https://api.openai.com/v1/chat/completions';
        
        $data = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'あなたは助成金・補助金の専門家です。正確で実用的な情報を提供し、SEOも考慮した高品質な日本語コンテンツを生成してください。'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'max_tokens' => 2000,
            'temperature' => 0.7
        ];
        
        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($data),
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            throw new Exception('API request failed: ' . $response->get_error_message());
        }
        
        $response_body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($response_body['error'])) {
            throw new Exception('OpenAI API error: ' . $response_body['error']['message']);
        }
        
        if (!isset($response_body['choices'][0]['message']['content'])) {
            throw new Exception('Invalid API response format');
        }
        
        return trim($response_body['choices'][0]['message']['content']);
    }
    
    /**
     * Get fallback content when AI fails
     */
    private function get_fallback_content($field, $existing_data = []) {
        $fallbacks = [
            'post_title' => $this->generate_title_fallback($existing_data),
            'post_content' => $this->generate_content_fallback($existing_data),
            'post_excerpt' => $this->generate_excerpt_fallback($existing_data),
            'eligibility_criteria' => "・中小企業、個人事業主が対象\n・法人設立から3年以内\n・従業員数50名以下\n・過去に同様の助成金を受給していないこと",
            'application_process' => "1. 申請書類の準備\n2. オンライン申請システムでの登録\n3. 必要書類のアップロード\n4. 審査結果の通知待ち\n5. 採択後の手続き",
            'required_documents' => "・申請書（指定様式）\n・会社概要書\n・事業計画書\n・見積書\n・直近の決算書\n・履歴事項全部証明書"
        ];
        
        return $fallbacks[$field] ?? "こちらの項目について詳細な情報をご確認ください。";
    }
    
    /**
     * Generate fallback fills for multiple fields
     */
    private function get_fallback_fills($fields) {
        $fills = [];
        foreach ($fields as $field) {
            $fills[$field] = $this->get_fallback_content($field);
        }
        return $fills;
    }
    
    /**
     * Generate title fallback
     */
    private function generate_title_fallback($data) {
        $org = !empty($data['organization']) ? $data['organization'] : '各自治体';
        $category = !empty($data['categories'][0]) ? $data['categories'][0] : 'ビジネス支援';
        return "{$org} {$category}助成金・補助金制度";
    }
    
    /**
     * Generate enhanced HTML content fallback with CSS styling
     */
    private function generate_content_fallback($data) {
        $title = !empty($data['title']) ? $data['title'] : '助成金制度';
        $org = !empty($data['organization']) ? $data['organization'] : '実施機関';
        $max_amount = !empty($data['max_amount']) ? $data['max_amount'] . '万円' : '規定額';
        $deadline = !empty($data['deadline']) ? $data['deadline'] : '随時受付';
        $categories = !empty($data['categories']) ? (is_array($data['categories']) ? implode('、', $data['categories']) : $data['categories']) : '事業支援';
        
        return '<style>
.grant-content { font-family: "Helvetica Neue", Arial, sans-serif; line-height: 1.6; color: #333; max-width: 800px; margin: 0 auto; }
.grant-section { color: #000; border-bottom: 2px solid #000; padding-bottom: 8px; margin: 24px 0 16px 0; font-weight: bold; }
.grant-highlight { background: #f9f9f9; border-left: 4px solid #000; padding: 16px; margin: 16px 0; }
.grant-table { width: 100%; border-collapse: collapse; margin: 16px 0; }
.grant-table th, .grant-table td { border: 1px solid #ccc; padding: 12px; text-align: left; }
.grant-table th { background: #000; color: white; font-weight: bold; }
.grant-list { margin: 16px 0; padding-left: 24px; }
.grant-list li { margin: 8px 0; }
.highlight-yellow { background: #ffeb3b; padding: 2px 4px; font-weight: bold; }
.contact-box { background: #f9f9f9; border: 1px solid #ccc; padding: 16px; margin: 16px 0; }
.step-number { background: #000; color: white; border-radius: 50%; padding: 4px 8px; margin-right: 8px; font-weight: bold; }
</style>

<div class="grant-content">
    <div class="grant-highlight">
        <h2>📋 ' . esc_html($title) . '</h2>
        <p><strong>実施機関:</strong> ' . esc_html($org) . '</p>
        <p><span class="highlight-yellow">最大助成額: ' . esc_html($max_amount) . '</span></p>
    </div>

    <h2 class="grant-section">💰 助成金概要</h2>
    <p>' . esc_html($title) . 'は、' . esc_html($org) . 'が実施する<span class="highlight-yellow">' . esc_html($categories) . '</span>を対象とした事業者支援制度です。事業の発展と成長を支援し、競争力強化を図ることを目的としています。</p>

    <h2 class="grant-section">📊 助成金詳細</h2>
    <table class="grant-table">
        <tr>
            <th>項目</th>
            <th>内容</th>
        </tr>
        <tr>
            <td>最大助成額</td>
            <td><span class="highlight-yellow">' . esc_html($max_amount) . '</span></td>
        </tr>
        <tr>
            <td>申請期限</td>
            <td>' . esc_html($deadline) . '</td>
        </tr>
        <tr>
            <td>対象分野</td>
            <td>' . esc_html($categories) . '</td>
        </tr>
        <tr>
            <td>実施機関</td>
            <td>' . esc_html($org) . '</td>
        </tr>
    </table>

    <h2 class="grant-section">✅ 対象者・応募要件</h2>
    <ul class="grant-list">
        <li>中小企業基本法に定める中小企業・小規模事業者</li>
        <li>個人事業主（開業届を提出している方）</li>
        <li>法人設立または開業から1年以上経過している事業者</li>
        <li>過去に同様の助成金を受給していない事業者</li>
        <li><span class="highlight-yellow">事業計画書の提出が可能な事業者</span></li>
    </ul>

    <h2 class="grant-section">📅 申請手順</h2>
    <ol class="grant-list">
        <li><span class="step-number">1</span>申請要件の確認と事前準備</li>
        <li><span class="step-number">2</span>必要書類の準備・収集</li>
        <li><span class="step-number">3</span>事業計画書の作成</li>
        <li><span class="step-number">4</span>申請書類の提出</li>
        <li><span class="step-number">5</span>審査結果の通知待ち</li>
        <li><span class="step-number">6</span>採択後の手続き・事業実施</li>
    </ol>

    <h2 class="grant-section">📞 お問い合わせ</h2>
    <div class="contact-box">
        <p><strong>実施機関:</strong> ' . esc_html($org) . '</p>
        <p><strong>受付時間:</strong> 平日 9:00～17:00（土日祝日を除く）</p>
        <p>詳細な申請方法や最新情報については、実施機関の公式サイトをご確認いただくか、直接お問い合わせください。</p>
    </div>

    <div class="grant-highlight">
        <p><strong>⚠️ 重要:</strong> 申請期限や条件は変更される場合があります。必ず最新の公式情報をご確認の上、お申し込みください。</p>
    </div>
</div>';
    }
    
    /**
     * Generate excerpt fallback
     */
    private function generate_excerpt_fallback($data) {
        $org = !empty($data['organization']) ? $data['organization'] : '実施機関';
        $amount = !empty($data['max_amount']) ? $data['max_amount'] : '規定の金額';
        
        return "{$org}による事業者向け助成金制度。最大{$amount}の支援を受けることができます。申請条件や手続き方法について詳しくご紹介します。";
    }
    
    /**
     * Simple content improvement (non-AI)
     */
    private function improve_content_simple($content, $field) {
        // Simple text improvements without AI
        $content = trim($content);
        
        // Add structure if missing
        if ($field === 'post_content' && strpos($content, '##') === false) {
            return "## 概要\n{$content}\n\n## 詳細情報\n申請や条件について、詳細は実施機関にお問い合わせください。";
        }
        
        return $content;
    }
    
    /**
     * Sanitize input data
     */
    private function sanitize_input($data) {
        if (!is_array($data)) {
            return [];
        }
        
        $sanitized = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = array_map('sanitize_text_field', $value);
            } else {
                $sanitized[$key] = sanitize_textarea_field($value);
            }
        }
        
        return $sanitized;
    }
}

// Initialize the enhanced AI generator
new GI_Enhanced_AI_Generator();