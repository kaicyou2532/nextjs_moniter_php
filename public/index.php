<?php
require __DIR__ . '/auth.php';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>AIM Commonsサイト 管理ツール</title>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg-color: #f4f6f8;
      --card-bg: #ffffff;
      --primary: #d9ae4c;
      --text-color: #333;
      --header-bg: #d9ae4c;
      --header-text: #fff;
      --border-radius: 8px;
      --button-padding: 0.6em 1.2em;
      --transition: 0.2s ease;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      background: var(--bg-color);
      font-family: 'Roboto', sans-serif;
      color: var(--text-color);
      display: flex; justify-content: center; align-items: center;
      min-height: 100vh; padding: 1em;
    }
    .container {
      width: 100%; max-width: 1000px;
      background: var(--card-bg);
      border-radius: var(--border-radius);
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      overflow: hidden;
    }
    header {
      background: var(--header-bg);
      padding: 1.2em;
      text-align: center;
      position: relative;
    }
    header h1 {
      color: var(--header-text);
      font-size: 1.5em;
      font-weight: 500;
    }
    .auth-info {
      position: absolute;
      right: 1em;
      top: 50%;
      transform: translateY(-50%);
      display: flex;
      align-items: center;
      gap: 0.5em;
    }
    .auth-button {
      background: rgba(255, 255, 255, 0.2);
      color: var(--header-text);
      border: 1px solid rgba(255, 255, 255, 0.3);
      padding: 0.4em 0.8em;
      font-size: 0.8em;
      border-radius: 4px;
      cursor: pointer;
      transition: background var(--transition);
    }
    .auth-button:hover {
      background: rgba(255, 255, 255, 0.3);
    }
    .controls {
      display: flex; flex-wrap: wrap;
      justify-content: center; gap: 0.5em; padding: 1em;
    }
    button {
      background: var(--primary);
      color: #fff;
      border: none;
      border-radius: var(--border-radius);
      padding: var(--button-padding);
      font-size: 1em;
      font-weight: 500;
      cursor: pointer;
      transition: all var(--transition);
      flex: 1 1 calc(50% - 1em);
      min-width: 120px;
      position: relative;
      overflow: hidden;
    }
    button:hover {
      background: #c39a3e;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(217, 174, 76, 0.3);
    }
    button:active {
      transform: translateY(1px) scale(0.98);
      box-shadow: 0 2px 6px rgba(217, 174, 76, 0.2);
    }
    button:disabled {
      background: #ccc;
      cursor: not-allowed;
      transform: none;
      box-shadow: none;
    }
    button.loading {
      background: #b8943a;
      cursor: wait;
    }
    button.loading::after {
      content: '';
      position: absolute;
      top: 50%;
      left: 50%;
      width: 20px;
      height: 20px;
      margin: -10px 0 0 -10px;
      border: 2px solid transparent;
      border-top: 2px solid #fff;
      border-radius: 50%;
      animation: spin 1s linear infinite;
    }
    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
    .button-clicked {
      animation: buttonPulse 0.3s ease;
    }
    @keyframes buttonPulse {
      0% { transform: scale(1); }
      50% { transform: scale(0.95); }
      100% { transform: scale(1); }
    }
    #status {
      margin: 0 1em 1em;
      background: #eff2f5;
      padding: 1em;
      border-radius: var(--border-radius);
      font-family: monospace;
      white-space: pre-wrap;
      min-height: 4em;
      max-height: 400px;
      overflow-y: auto;
      border: 1px solid #ddd;
    }
    .log-controls {
      margin: 0 1em;
      display: flex;
      gap: 0.5em;
      align-items: center;
      padding: 0.5em 0;
    }
    .clear-log-btn {
      background: #6c757d;
      color: white;
      border: none;
      padding: 0.3em 0.8em;
      border-radius: 4px;
      font-size: 0.8em;
      cursor: pointer;
      flex: none;
    }
    .clear-log-btn:hover {
      background: #5a6268;
    }
    .auto-scroll {
      display: flex;
      align-items: center;
      gap: 0.3em;
      font-size: 0.8em;
    }
  </style>
</head>
<body>
  <div class="container">
    <header>
      <h1>AIM Commonsサイト 管理ツール</h1>
      <div class="auth-info">
        <button class="auth-button" onclick="logout()">ログアウト</button>
      </div>
    </header>
    <div class="controls">
      <button onclick="send('build')">ビルド(記事の公開)</button>
      <button onclick="send('stop')">WEBサーバー停止</button>
      <button onclick="send('Renewal')">GitHubから最新版を取得</button>
      <button onclick="send('nginx')">リバースプロキシ再起動</button>
      <button onclick="send('dev')">開発者モードで起動</button>
      <button onclick="send('install')">依存関係をインストール</button>
      <button onclick="send('status')">状態確認</button>
      <button onclick="send('env')">環境変数設定</button>
    </div>
    <div class="log-controls">
      <button class="clear-log-btn" onclick="clearLog()">ログクリア</button>
      <div class="auto-scroll">
        <input type="checkbox" id="autoScroll" checked>
        <label for="autoScroll">自動スクロール</label>
      </div>
    </div>
    <div id="status">-- ステータス表示 --</div>
  </div>

  <script>
    // ストリーミング対応のアクション（リアルタイムログが必要な処理）
    const streamingActions = ['build', 'dev', 'install'];
    
    async function send(action) {
      const button = event.target;
      const originalText = button.textContent;
      
      // ボタンクリック時のフィードバック
      button.classList.add('button-clicked');
      setTimeout(() => button.classList.remove('button-clicked'), 300);
      
      // ローディング状態を設定
      button.disabled = true;
      button.classList.add('loading');
      button.textContent = '処理中...';
      
      // ステータス表示を更新
      const statusEl = document.getElementById('status');
      
      try {
        if (streamingActions.includes(action)) {
          // ストリーミングレスポンス対応
          await sendWithStreaming(action, statusEl);
        } else {
          // 通常のレスポンス
          statusEl.textContent = `${originalText}を実行中...`;
          statusEl.style.color = '#666';
          
          const res = await fetch('api.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ action })
          });
          
          // 認証エラーの場合は再認証を促す
          if (res.status === 401) {
            statusEl.textContent = '認証が必要です。ページを再読み込みしてください。';
            statusEl.style.color = '#dc3545';
            setTimeout(() => {
              window.location.reload();
            }, 2000);
            return;
          }
          
          const text = await res.text();
          statusEl.textContent = text;
          statusEl.style.color = text.includes('[OK]') ? '#28a745' : 
                                text.includes('[ERR]') ? '#dc3545' : 
                                text.includes('[WARN]') ? '#ffc107' : '#333';
        }

      } catch (error) {
        statusEl.textContent = 'エラーが発生しました: ' + error.message;
        statusEl.style.color = '#dc3545';
      } finally {
        // ローディング状態を解除
        button.disabled = false;
        button.classList.remove('loading');
        button.textContent = originalText;
      }
    }

    // ストリーミングレスポンス処理
    async function sendWithStreaming(action, statusEl) {
      statusEl.textContent = '';
      statusEl.style.color = '#333';
      
      const res = await fetch('api.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ action, stream: true })
      });
      
      if (res.status === 401) {
        statusEl.textContent = '認証が必要です。ページを再読み込みしてください。';
        statusEl.style.color = '#dc3545';
        setTimeout(() => {
          window.location.reload();
        }, 2000);
        return;
      }
      
      const reader = res.body.getReader();
      const decoder = new TextDecoder();
      
      while (true) {
        const { done, value } = await reader.read();
        
        if (done) break;
        
        const chunk = decoder.decode(value, { stream: true });
        statusEl.textContent += chunk;
        
        // 自動スクロール
        if (document.getElementById('autoScroll').checked) {
          statusEl.scrollTop = statusEl.scrollHeight;
        }
        
        // 色の更新
        const text = statusEl.textContent;
        statusEl.style.color = text.includes('[OK]') ? '#28a745' : 
                              text.includes('[ERR]') ? '#dc3545' : 
                              text.includes('[WARN]') ? '#ffc107' : '#333';
      }
    }
    
    // ログクリア機能
    function clearLog() {
      document.getElementById('status').textContent = '-- ステータス表示 --';
      document.getElementById('status').style.color = '#333';
    }

    // ログアウト機能
    function logout() {
      if (confirm('ログアウトしますか？')) {
        // 認証情報をクリアするため、無効な認証でリクエストを送る
        fetch(window.location.href, {
          method: 'GET',
          headers: {
            'Authorization': 'Basic ' + btoa('logout:logout')
          }
        }).then(() => {
          window.location.reload();
        }).catch(() => {
          window.location.reload();
        });
      }
    }
  </script>
</body>
</html>
