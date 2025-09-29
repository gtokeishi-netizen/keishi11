# Google Sheets同期システム - セットアップガイド

## 🎯 概要
このドキュメントでは、WordPress と Google Sheets の間でシンプルで確実な双方向同期を設定する方法を説明します。

## ✨ 新システムの特徴

### 🔧 シンプル設計
- **複雑な設定を削除** - デバッグ機能や余計な設定を整理
- **わかりやすい管理画面** - WordPress管理画面から簡単に設定
- **視覚的なヘッダー設定** - カラム（ヘッダー行）を直感的に設定可能

### ⚡ 確実な動作
- **JWT認証方式** - Googleの推奨認証方法を採用
- **エラーハンドリング** - 問題が発生した際の明確なエラー表示
- **接続テスト機能** - ボタン一つで接続状態を確認

## 📋 セットアップ手順

### 1. Google Cloud Console での設定

#### 1-1. プロジェクト作成
1. [Google Cloud Console](https://console.cloud.google.com/) にアクセス
2. 新しいプロジェクトを作成（例：`grant-sheets-sync`）

#### 1-2. Google Sheets API 有効化
1. 「ライブラリ」から「Google Sheets API」を検索
2. 「有効にする」をクリック

#### 1-3. サービスアカウント作成
1. 「認証情報」→「認証情報を作成」→「サービスアカウント」
2. サービスアカウント名：`sheets-sync-service`
3. 「キーを作成」→「JSON」を選択してダウンロード

### 2. Google Sheets の準備

#### 2-1. スプレッドシート作成
1. [Google Sheets](https://sheets.google.com) で新しいスプレッドシートを作成
2. シート名を `grant_import` に変更（または任意の名前）

#### 2-2. 共有設定
1. 右上の「共有」ボタンをクリック
2. サービスアカウントのメールアドレス（`***@***.iam.gserviceaccount.com`）を追加
3. 権限を「編集者」に設定

#### 2-3. スプレッドシートID取得
- URLから取得：`https://docs.google.com/spreadsheets/d/【ここがスプレッドシートID】/edit`

### 3. WordPress での設定

#### 3-1. 管理画面での設定
1. WordPress管理画面 → 「助成金」→ 「Sheets同期」
2. 以下の情報を入力：

**スプレッドシートID**
```
1kGc1Eb4AYvURkSfdzMwipNjfe8xC6iGCM2q1sUgIfWg
```

**シート名**
```
grant_import
```

**サービスアカウントJSON**
```json
{
  "type": "service_account",
  "project_id": "your-project-id",
  "private_key_id": "...",
  "private_key": "-----BEGIN PRIVATE KEY-----\n...",
  "client_email": "sheets-sync-service@your-project.iam.gserviceaccount.com",
  "client_id": "...",
  "auth_uri": "https://accounts.google.com/o/oauth2/auth",
  "token_uri": "https://oauth2.googleapis.com/token",
  "auth_provider_x509_cert_url": "https://www.googleapis.com/oauth2/v1/certs",
  "client_x509_cert_url": "...",
  "universe_domain": "googleapis.com"
}
```

**ヘッダー行（カラム名）**
```
ID, タイトル, 内容, カテゴリ, 都道府県, 市町村, タグ, 募集開始日, 募集終了日, 公開状況, 最終更新
```

#### 3-2. 接続テスト
1. 「接続テスト」ボタンをクリック
2. 「Google Sheetsへの接続に成功しました」が表示されることを確認

### 4. Google Apps Script の設定

#### 4-1. GASプロジェクト作成
1. [Google Apps Script](https://script.google.com) にアクセス
2. 新しいプロジェクトを作成

#### 4-2. コード設定
1. `SimpleSheetSync.gs` の内容をコピー
2. 以下の設定を更新：

```javascript
const CONFIG = {
  wordpressUrl: 'https://your-wordpress-site.com',  // あなたのWordPressサイト
  webhookSecret: 'your-secret-key-123',              // セキュリティ用秘密鍵
  sheetName: 'grant_import'                          // シート名
};
```

#### 4-3. Webアプリとして公開
1. 「デプロイ」→「新しいデプロイ」
2. 種類：「ウェブアプリ」
3. 実行ユーザー：「自分」
4. アクセス権限：「全員」
5. デプロイ後、URLをコピー

### 5. WordPress - GAS 連携設定

#### 5-1. Webhook URL設定
WordPressの管理画面で、GASのWebアプリURLを設定

#### 5-2. セキュリティキー統一
WordPress と GAS で同じ `webhookSecret` を使用

## 🧪 動作テスト

### テスト1: WordPress → Sheets
1. WordPress管理画面で新しい助成金投稿を作成
2. Google Sheetsで新しい行が追加されることを確認

### テスト2: 手動同期
1. 「手動同期実行」ボタンをクリック
2. 全ての投稿がSheetsに反映されることを確認

### テスト3: ヘッダー設定
1. ヘッダー行の設定を変更
2. 「手動同期実行」を実行
3. Sheetsのヘッダー行が更新されることを確認

## 🔧 カスタマイズ

### ヘッダー（カラム）のカスタマイズ
管理画面で以下のようにカラムを追加・変更できます：

```
ID, タイトル, 内容, カテゴリ, 都道府県, 市町村, タグ, 
募集開始日, 募集終了日, 公開状況, 最終更新, 備考, 担当者
```

### 市町村データの追加
GASの`CONFIG.prefectures`で都道府県と市町村の関係を設定：

```javascript
prefectures: {
  '京都府': ['京都市', '宇治市', '亀岡市', '城陽市', '向日市'],
  '滋賀県': ['大津市', '彦根市', '長浜市', '近江八幡市', '草津市']
}
```

## 🚨 トラブルシューティング

### よくある問題

#### 1. 「接続に失敗しました」エラー
- **原因**: サービスアカウントJSONが間違っている
- **解決**: Google Cloud Consoleで再度JSONキーをダウンロード

#### 2. 「権限がありません」エラー  
- **原因**: Sheetsの共有設定が間違っている
- **解決**: サービスアカウントのメールアドレスを「編集者」として追加

#### 3. データが同期されない
- **原因**: Webhook URLが設定されていない
- **解決**: GASのWebアプリURLを正しく設定

#### 4. ヘッダーが更新されない
- **原因**: シート名が間違っている
- **解決**: WordPressとGASで同じシート名を使用

### エラーログの確認方法
```bash
# WordPressのエラーログ
tail -f /path/to/wordpress/wp-content/debug.log

# GASでのログ確認
console.log() の内容をGAS実行ログで確認
```

## 📞 サポート

問題が解決しない場合は、以下の情報を提供してください：

1. エラーメッセージの完全な内容
2. WordPress、GASの設定内容
3. 実行時のログ
4. ブラウザの開発者ツールのエラー

---

**🎉 設定完了後は、WordPress と Google Sheets が自動的に同期され、効率的な助成金管理が可能になります！**