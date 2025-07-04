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
      --primary-light: #d9ae4;
      --text-color: #333;
      --header-bg: #d9ae4c;
      --header-text: #fff;
      --border-radius: 8px;
      --button-padding: 0.6em 1.2em;
      --transition: 0.2s ease;
    }
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }
    body {
      background-color: var(--bg-color);
      font-family: 'Roboto', sans-serif;
      color: var(--text-color);
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      padding: 1em;
    }
    .container {
      width: 100%;
      max-width: 1000px;
      background: var(--card-bg);
      border-radius: var(--border-radius);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
      overflow: hidden;
    }
    header {
      background: var(--header-bg);
      padding: 1.2em;
      text-align: center;
    }
    header h1 {
      color: var(--header-text);
      font-size: 1.5em;
      font-weight: 500;
    }
    .controls {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      gap: 0.5em;
      padding: 1em;
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
      transition: background var(--transition), transform var(--transition);
      flex: 1 1 calc(50% - 1em);
      min-width: 120px;
    }
    button:hover {
      background: #d9fffff;
      transform: translateY(-2px);
    }
    button:active {
      transform: translateY(0);
    }
    #status {
      margin: 0 1em 1em;
      background: #eff2f5;
      padding: 1em;
      border-radius: var(--border-radius);
      font-family: monospace;
      white-space: pre-wrap;
      min-height: 4em;
    }
  </style>
</head>
<body>
  <div class="container">
    <header>
      <h1>AIM Commonsサイト 管理ツール</h1>
    </header>
    <div class="controls">
      <button onclick="send('build')">ビルド</button>
      <button onclick="send('start')">起動</button>
      <button onclick="send('stop')">停止</button>
      <button onclick="send('restart')">再起動</button>
      <button onclick="send('status')">状態確認</button>
    </div>
    <div id="status">-- ステータス表示 --</div>
  </div>

  <script>
    async function send(action) {
      const res = await fetch('api.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ action })
      });
      const text = await res.text();
      document.getElementById('status').textContent = text;
    }
  </script>
</body>
</html>
