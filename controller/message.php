<?php
// Message redirect / handler (used by index router)
// Usage: redirect with GET params ?m=Your+message
$msg = $_GET['m'] ?? 'Unknown message';
?>
<!doctype html>
<html><head><meta charset="utf-8"><title>Message</title></head>
<body style="background:#111;color:#eee;display:flex;align-items:center;justify-content:center;height:100vh;">
  <div style="max-width:900px;padding:20px;border:1px solid #333;background:#000;">
    <h2>Message</h2>
    <p><?=htmlspecialchars($msg, ENT_QUOTES)?></p>
    <p><a href="/onvif-ui/">Back</a></p>
  </div>
</body>
</html>