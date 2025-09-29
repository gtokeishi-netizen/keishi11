/**
 * シンプルなGoogle Apps Script - WordPress連携
 * 理解しやすく、保守しやすいコード
 * 
 * @version 2.0.0
 * @author Grant Insight Perfect
 */

// ===== 設定 =====
const CONFIG = {
  // WordPress設定
  wordpressUrl: 'https://あなたのサイト.com', // WordPressサイトのURL
  webhookSecret: 'your-secret-key', // セキュリティ用の秘密鍵
  
  // スプレッドシート設定
  sheetName: 'grant_import', // 使用するシート名
  headerRow: 1, // ヘッダー行（通常は1行目）
  
  // 都道府県データ（簡潔版）
  prefectures: {
    '北海道': ['札幌市', '函館市', '旭川市', '釧路市', '帯広市'],
    '東京都': ['千代田区', '中央区', '港区', '新宿区', '文京区', '台東区', '墨田区', '江東区', '品川区', '目黒区', 
              '大田区', '世田谷区', '渋谷区', '中野区', '杉並区', '豊島区', '北区', '荒川区', '板橋区', '練馬区', '足立区', '葛飾区', '江戸川区',
              '八王子市', '立川市', '武蔵野市', '三鷹市', '青梅市', '府中市', '昭島市', '調布市', '町田市', '小金井市', '小平市', '日野市', '東村山市', '国分寺市', '国立市'],
    '大阪府': ['大阪市', '堺市', '岸和田市', '豊中市', '池田市', '吹田市', '泉大津市', '高槻市', '貝塚市', '守口市', '枚方市', '茨木市', '八尾市', '泉佐野市', '富田林市'],
    '神奈川県': ['横浜市', '川崎市', '相模原市', '横須賀市', '平塚市', '鎌倉市', '藤沢市', '小田原市', '茅ヶ崎市', '逗子市', '三浦市'],
    '愛知県': ['名古屋市', '豊橋市', '岡崎市', '一宮市', '瀬戸市', '半田市', '春日井市', '豊川市', '津島市', '碧南市', '刈谷市', '豊田市', '安城市'],
    // 他の都道府県も同様に設定可能
  }
};

// ===== メイン関数 =====

/**
 * WordPress投稿の変更を受信するWebhook
 */
function receiveWordPressWebhook(e) {
  try {
    // リクエストの検証
    if (!e || !e.postData) {
      throw new Error('無効なリクエストです');
    }
    
    const payload = JSON.parse(e.postData.contents);
    
    // セキュリティチェック
    if (!validateWebhookSecurity(e, payload)) {
      throw new Error('セキュリティ検証に失敗しました');
    }
    
    // アクションに応じて処理
    switch (payload.action) {
      case 'create':
      case 'update':
        updateSheetRow(payload.data);
        break;
      case 'delete':
        deleteSheetRow(payload.data.id);
        break;
      default:
        throw new Error('不明なアクション: ' + payload.action);
    }
    
    return ContentService
      .createTextOutput(JSON.stringify({success: true, message: '同期完了'}))
      .setMimeType(ContentService.MimeType.JSON);
      
  } catch (error) {
    console.error('Webhook処理エラー:', error);
    return ContentService
      .createTextOutput(JSON.stringify({success: false, error: error.toString()}))
      .setMimeType(ContentService.MimeType.JSON);
  }
}

/**
 * シート行を更新
 */
function updateSheetRow(data) {
  const sheet = getOrCreateSheet();
  const headers = getSheetHeaders(sheet);
  
  // 既存行を検索
  const existingRow = findRowByPostId(sheet, data.id);
  
  // データ行を準備
  const rowData = prepareRowData(data, headers);
  
  if (existingRow > 0) {
    // 既存行を更新
    const range = sheet.getRange(existingRow, 1, 1, rowData.length);
    range.setValues([rowData]);
    console.log(`投稿ID ${data.id} を行 ${existingRow} に更新`);
  } else {
    // 新しい行を追加
    sheet.appendRow(rowData);
    console.log(`投稿ID ${data.id} を新しい行として追加`);
  }
}

/**
 * シート行を削除（実際は空にする）
 */
function deleteSheetRow(postId) {
  const sheet = getOrCreateSheet();
  const existingRow = findRowByPostId(sheet, postId);
  
  if (existingRow > 0) {
    const numColumns = sheet.getLastColumn();
    const range = sheet.getRange(existingRow, 1, 1, numColumns);
    range.clear();
    console.log(`投稿ID ${postId} を行 ${existingRow} から削除`);
  }
}

// ===== ユーティリティ関数 =====

/**
 * シートを取得または作成
 */
function getOrCreateSheet() {
  const spreadsheet = SpreadsheetApp.getActiveSpreadsheet();
  let sheet = spreadsheet.getSheetByName(CONFIG.sheetName);
  
  if (!sheet) {
    sheet = spreadsheet.insertSheet(CONFIG.sheetName);
    setupDefaultHeaders(sheet);
  }
  
  return sheet;
}

/**
 * デフォルトヘッダーを設定
 */
function setupDefaultHeaders(sheet) {
  const headers = [
    'ID', 'タイトル', '内容', 'カテゴリ', '都道府県', '市町村', 
    'タグ', '募集開始日', '募集終了日', '公開状況', '最終更新'
  ];
  
  const headerRange = sheet.getRange(CONFIG.headerRow, 1, 1, headers.length);
  headerRange.setValues([headers]);
  
  // ヘッダー行のスタイル設定
  headerRange.setBackground('#4285f4');
  headerRange.setFontColor('white');
  headerRange.setFontWeight('bold');
  
  // 列幅の自動調整
  sheet.autoResizeColumns(1, headers.length);
}

/**
 * シートのヘッダーを取得
 */
function getSheetHeaders(sheet) {
  const headerRange = sheet.getRange(CONFIG.headerRow, 1, 1, sheet.getLastColumn());
  const headerValues = headerRange.getValues()[0];
  return headerValues.filter(header => header !== ''); // 空のセルを除外
}

/**
 * 投稿IDで行を検索
 */
function findRowByPostId(sheet, postId) {
  const dataRange = sheet.getDataRange();
  const values = dataRange.getValues();
  
  for (let i = CONFIG.headerRow; i < values.length; i++) {
    if (values[i][0] && parseInt(values[i][0]) === parseInt(postId)) {
      return i + 1; // Sheetsは1から開始
    }
  }
  
  return -1; // 見つからない
}

/**
 * 行データを準備
 */
function prepareRowData(data, headers) {
  const rowData = [];
  
  // ヘッダーに基づいてデータを配置
  headers.forEach(header => {
    switch (header) {
      case 'ID':
        rowData.push(data.id || '');
        break;
      case 'タイトル':
        rowData.push(data.title || '');
        break;
      case '内容':
        rowData.push(data.content || '');
        break;
      case 'カテゴリ':
        rowData.push(Array.isArray(data.categories) ? data.categories.join(', ') : '');
        break;
      case '都道府県':
        rowData.push(Array.isArray(data.prefectures) ? data.prefectures.join(', ') : '');
        break;
      case '市町村':
        rowData.push(Array.isArray(data.municipalities) ? data.municipalities.join(', ') : '');
        break;
      case 'タグ':
        rowData.push(Array.isArray(data.tags) ? data.tags.join(', ') : '');
        break;
      case '募集開始日':
        rowData.push(data.start_date || '');
        break;
      case '募集終了日':
        rowData.push(data.end_date || '');
        break;
      case '公開状況':
        rowData.push(data.status || '');
        break;
      case '最終更新':
        rowData.push(data.modified || new Date().toISOString());
        break;
      default:
        rowData.push(''); // 不明なヘッダー
    }
  });
  
  return rowData;
}

/**
 * Webhookのセキュリティを検証
 */
function validateWebhookSecurity(e, payload) {
  try {
    // シンプルなセキュリティチェック
    const headers = e.parameter || {};
    const providedSecret = headers['X-Webhook-Secret'] || payload.secret;
    
    if (providedSecret !== CONFIG.webhookSecret) {
      console.error('セキュリティキーが一致しません');
      return false;
    }
    
    return true;
  } catch (error) {
    console.error('セキュリティ検証エラー:', error);
    return false;
  }
}

// ===== WordPressとの双方向同期 =====

/**
 * シートの変更をWordPressに送信
 */
function syncSheetChangesToWordPress() {
  try {
    const sheet = getOrCreateSheet();
    const dataRange = sheet.getDataRange();
    const values = dataRange.getValues();
    const headers = values[CONFIG.headerRow - 1]; // 配列は0から開始
    
    // データ行を処理（ヘッダー行以降）
    for (let i = CONFIG.headerRow; i < values.length; i++) {
      const rowData = values[i];
      
      // 空の行をスキップ
      if (!rowData[0]) continue;
      
      const postData = prepareWordPressData(rowData, headers);
      
      // WordPressに送信
      sendToWordPress(postData);
    }
    
    console.log('シートからWordPressへの同期が完了しました');
  } catch (error) {
    console.error('シート同期エラー:', error);
  }
}

/**
 * WordPressデータを準備
 */
function prepareWordPressData(rowData, headers) {
  const data = {};
  
  headers.forEach((header, index) => {
    const value = rowData[index] || '';
    
    switch (header) {
      case 'ID':
        data.id = value;
        break;
      case 'タイトル':
        data.title = value;
        break;
      case '内容':
        data.content = value;
        break;
      case 'カテゴリ':
        data.categories = value ? value.split(',').map(cat => cat.trim()) : [];
        break;
      case '都道府県':
        data.prefectures = value ? value.split(',').map(pref => pref.trim()) : [];
        break;
      case '市町村':
        data.municipalities = value ? value.split(',').map(muni => muni.trim()) : [];
        break;
      case 'タグ':
        data.tags = value ? value.split(',').map(tag => tag.trim()) : [];
        break;
      case '募集開始日':
        data.start_date = value;
        break;
      case '募集終了日':
        data.end_date = value;
        break;
      case '公開状況':
        data.status = value;
        break;
    }
  });
  
  return data;
}

/**
 * WordPressに데이터を送信
 */
function sendToWordPress(data) {
  try {
    const url = CONFIG.wordpressUrl + '/wp-admin/admin-ajax.php';
    
    const payload = {
      action: 'gi_receive_sheets_update',
      secret: CONFIG.webhookSecret,
      data: data
    };
    
    const options = {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Webhook-Secret': CONFIG.webhookSecret
      },
      payload: JSON.stringify(payload)
    };
    
    const response = UrlFetchApp.fetch(url, options);
    
    if (response.getResponseCode() !== 200) {
      console.error('WordPress送信エラー:', response.getContentText());
    }
    
  } catch (error) {
    console.error('WordPress送信失敗:', error);
  }
}

// ===== 手動実行用の関数 =====

/**
 * 手動でWordPressからデータを取得
 */
function manualSyncFromWordPress() {
  try {
    const url = CONFIG.wordpressUrl + '/wp-admin/admin-ajax.php';
    
    const payload = {
      action: 'gi_export_all_grants',
      secret: CONFIG.webhookSecret
    };
    
    const options = {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      payload: JSON.stringify(payload)
    };
    
    const response = UrlFetchApp.fetch(url, options);
    const data = JSON.parse(response.getContentText());
    
    if (data.success) {
      const sheet = getOrCreateSheet();
      
      // 既存データをクリア（ヘッダー以外）
      if (sheet.getLastRow() > CONFIG.headerRow) {
        const clearRange = sheet.getRange(CONFIG.headerRow + 1, 1, sheet.getLastRow() - CONFIG.headerRow, sheet.getLastColumn());
        clearRange.clear();
      }
      
      // 新しいデータを追加
      data.grants.forEach(grant => {
        updateSheetRow(grant);
      });
      
      console.log(`${data.grants.length}件の助成金データを同期しました`);
    }
    
  } catch (error) {
    console.error('手動同期エラー:', error);
  }
}

/**
 * 都道府県リストを取得
 */
function getPrefectures() {
  return Object.keys(CONFIG.prefectures);
}

/**
 * 指定した都道府県の市町村リストを取得
 */
function getMunicipalities(prefecture) {
  return CONFIG.prefectures[prefecture] || [];
}

// ===== セットアップ用関数 =====

/**
 * 初期セットアップ
 */
function setupSheet() {
  const sheet = getOrCreateSheet();
  
  // ヘッダーが設定されていない場合は設定
  if (sheet.getLastRow() === 0) {
    setupDefaultHeaders(sheet);
  }
  
  console.log('シートのセットアップが完了しました');
}

/**
 * WebアプリとしてデプロイするためのdoGet/doPost
 */
function doGet(e) {
  return ContentService
    .createTextOutput(JSON.stringify({message: 'Google Sheets Sync API', version: '2.0.0'}))
    .setMimeType(ContentService.MimeType.JSON);
}

function doPost(e) {
  return receiveWordPressWebhook(e);
}