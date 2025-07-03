<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>AIMcommonsサイト 管理ツール</title>
  <style>
    button { margin: .5em; padding: .5em 1em; }
    #status { margin-top: 1em; white-space: pre-wrap; font-family: monospace; }
  </style>
</head>
<body>
  <h1>AIMcommonsサイト 管理ツール</h1>
  <button onclick="send('build')">ビルド</button>
  <button onclick="send('start')">起動</button>
  <button onclick="send('stop')">停止</button>
  <button onclick="send('restart')">再起動</button>
  <button onclick="send('status')">状態確認</button>

  <div id="status">-- ステータス表示 --</div>

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
