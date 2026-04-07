<!DOCTYPE html>
<?php
/**
 * data/admin.php — Statistics & Management Dashboard
 * Access via direct URL only — no links from the public site.
 */
session_start();
error_reporting(0);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Robots-Tag: noindex, nofollow');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/idObfuscation.php';

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_HTML5, 'UTF-8'); }
function redir(): void { header('Location: '.strtok($_SERVER['REQUEST_URI'],'?')); exit; }

/**
 * Parse ISP name cleanly from the processedString JSON field.
 * processedString format: "1.2.3.4 - ISP Name, Country (120 km)"
 * Returns just "ISP Name, Country" — no IP, no distance.
 */
function parseIsp(?string $ispjson): array {
    $obj = json_decode($ispjson ?? '', true);
    $ps  = $obj['processedString'] ?? '';

    // Extract pure IP (before the first space or dash)
    $parts = explode(' - ', $ps, 2);
    $ip    = trim($parts[0]);

    // ISP part is everything after " - "
    $isp = isset($parts[1]) ? trim($parts[1]) : '';

    // Strip trailing "(120 km)" or "(120 mi)"
    $paren = strrpos($isp, ' (');
    if ($paren !== false) $isp = substr($isp, 0, $paren);

    return ['ip' => $ip, 'isp' => $isp];
}

$blocked  = ($stats_password === 'CHANGE_ME');
$loggedIn = ($_SESSION['admin_ok'] === true);

if (!$blocked && !$loggedIn) {
    if (($_GET['op'] ?? '') === 'login' && ($_POST['password'] ?? '') === $stats_password) {
        $_SESSION['admin_ok'] = true; redir();
    }
}
if (($_GET['op'] ?? '') === 'logout') { $_SESSION['admin_ok'] = false; redir(); }

$stats   = null;
$recent  = [];
$perDay  = [];
$topIsps = [];
$noData  = false;
$dbErr   = null;

if (!$blocked && $loggedIn && $db_type === 'sqlite') {
    try {
        $db = new PDO("sqlite:{$sqlite_db_file}");
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Table may not exist before first test
        $exists = (bool)$db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='speedtest_results'")->fetchColumn();
        if (!$exists) { $noData = true; }
        else {
            $stats = $db->query("SELECT COUNT(*) AS total,
                ROUND(AVG(CAST(dl AS REAL)),1) AS avg_dl, ROUND(AVG(CAST(ul AS REAL)),1) AS avg_ul,
                ROUND(AVG(CAST(ping AS REAL)),1) AS avg_ping, ROUND(MAX(CAST(dl AS REAL)),1) AS max_dl,
                ROUND(MAX(CAST(ul AS REAL)),1) AS max_ul, DATE(MIN(timestamp)) AS first_date
                FROM speedtest_results")->fetch(PDO::FETCH_ASSOC);

            if ((int)$stats['total'] === 0) { $noData = true; }
            else {
                $perDay  = $db->query("SELECT DATE(timestamp) AS day, COUNT(*) AS cnt, ROUND(AVG(CAST(dl AS REAL)),1) AS avg_dl FROM speedtest_results WHERE timestamp>=DATE('now','-30 days') GROUP BY day ORDER BY day")->fetchAll(PDO::FETCH_ASSOC);
                $topIsps = $db->query("SELECT ip, ispinfo, COUNT(*) AS cnt, ROUND(AVG(CAST(dl AS REAL)),1) AS avg_dl FROM speedtest_results WHERE ispinfo IS NOT NULL AND ispinfo!='' GROUP BY ispinfo ORDER BY cnt DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);

                $search = trim($_GET['id'] ?? '');
                if ($search) {
                    $lid  = $enable_id_obfuscation ? deobfuscateId($search) : (int)$search;
                    $stmt = $db->prepare("SELECT id,timestamp,ip,ispinfo,ua,dl,ul,ping,jitter FROM speedtest_results WHERE id=:id");
                    $stmt->execute([':id'=>$lid]); $recent=$stmt->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    $recent=$db->query("SELECT id,timestamp,ip,ispinfo,ua,dl,ul,ping,jitter FROM speedtest_results ORDER BY timestamp DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
                }
            }
        }
        $db = null;
    } catch (Exception $e) { $dbErr = $e->getMessage(); }
}
?>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>SpeedTest — לוח ניהול</title>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Heebo:wght@400;600;700&family=JetBrains+Mono:wght@400;700&display=swap');
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    :root{--bg:#07090f;--bg2:#0c1220;--bg3:#111c30;--border:rgba(255,255,255,.08);--text:#e8f2ff;--text2:#7a95b8;--text3:#354d68;--cyan:#00d4ff;--green:#00e676;--amber:#ffb300;--red:#ff5252}
    html{background:var(--bg);color:var(--text);font-family:'Heebo',sans-serif;direction:rtl}
    body{min-height:100vh;display:flex;flex-direction:column}
    a{color:var(--cyan);text-decoration:none}a:hover{text-decoration:underline}
    .top-bar{background:var(--bg2);border-bottom:1px solid var(--border);padding:14px 24px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px}
    .top-bar h1{font-size:1.1rem;font-weight:700}
    .top-bar-act{display:flex;gap:8px}
    .content{max-width:1060px;margin:0 auto;padding:28px 20px;flex:1;width:100%}
    .stat-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(155px,1fr));gap:12px;margin-bottom:28px}
    .stat-card{background:var(--bg2);border:1px solid var(--border);border-radius:14px;padding:18px 16px;text-align:center}
    .stat-num{font-family:'JetBrains Mono',monospace;font-size:1.65rem;font-weight:700;color:var(--cyan)}
    .stat-label{font-size:.72rem;color:var(--text3);margin-top:4px;text-transform:uppercase;letter-spacing:.07em}
    .chart-card{background:var(--bg2);border:1px solid var(--border);border-radius:14px;padding:20px;margin-bottom:24px}
    .ct{font-size:.75rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--text3);margin-bottom:14px}
    canvas.chart{width:100%;height:150px;display:block}
    .two-col{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:24px}
    @media(max-width:640px){.two-col{grid-template-columns:1fr}}
    .table-card{background:var(--bg2);border:1px solid var(--border);border-radius:14px;overflow:hidden;margin-bottom:24px}
    .th{padding:14px 20px;display:flex;align-items:center;justify-content:space-between;gap:10px;border-bottom:1px solid var(--border);flex-wrap:wrap}
    .th h3{font-size:.82rem;font-weight:700;color:var(--text2)}
    table{width:100%;border-collapse:collapse;font-size:.78rem}
    th,td{padding:9px 14px;text-align:right;border-bottom:1px solid var(--border)}
    th{background:var(--bg3);color:var(--text3);font-weight:600;font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;white-space:nowrap}
    td{color:var(--text2);font-family:'JetBrains Mono',monospace;font-size:.73rem;white-space:nowrap}
    tr:last-child td{border-bottom:none} tr:hover td{background:rgba(255,255,255,.02)}
    .vdl{color:var(--cyan)!important}.vul{color:var(--green)!important}.vping{color:var(--amber)!important}
    /* ISP cell: plain text, not monospace */
    .isp-cell{font-family:'Heebo',sans-serif!important;font-size:.78rem!important;white-space:normal;max-width:180px;overflow:hidden;text-overflow:ellipsis}
    input[type=text],input[type=password]{padding:8px 12px;border-radius:8px;border:1px solid var(--border);background:var(--bg3);color:var(--text);font-size:.83rem;outline:none;font-family:'Heebo',sans-serif}
    input:focus{border-color:var(--cyan)}
    .btn{padding:8px 18px;border-radius:8px;border:none;font-family:'Heebo',sans-serif;font-size:.83rem;font-weight:600;cursor:pointer;transition:all .18s}
    .bp{background:var(--cyan);color:#001e2b}.bp:hover{filter:brightness(1.1)}
    .bg{background:transparent;border:1px solid var(--border);color:var(--text2)}.bg:hover{background:var(--bg3);color:var(--text)}
    .bd{background:transparent;border:1px solid rgba(255,82,82,.3);color:var(--red)}.bd:hover{background:rgba(255,82,82,.09)}
    .bar-row{display:flex;align-items:center;gap:8px;margin-bottom:6px;font-size:.73rem}
    .bar-lbl{width:92px;color:var(--text2);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex-shrink:0}
    .bar-trk{flex:1;height:7px;background:var(--bg3);border-radius:4px;overflow:hidden}
    .bar-fil{height:100%;border-radius:4px;background:var(--cyan)}
    .bar-val{width:52px;text-align:left;color:var(--text3);font-family:'JetBrains Mono',monospace;font-size:.7rem;flex-shrink:0}
    .login-wrap{flex:1;display:flex;align-items:center;justify-content:center;padding:40px 20px}
    .login-card{background:var(--bg2);border:1px solid var(--border);border-radius:18px;padding:36px 28px;width:100%;max-width:340px;text-align:center}
    .login-card h2{font-size:1.15rem;margin-bottom:6px} .login-card p{font-size:.83rem;color:var(--text2);margin-bottom:22px}
    .lf{display:flex;flex-direction:column;gap:9px}
    .alert{padding:14px 18px;border-radius:10px;font-size:.85rem;margin-bottom:20px}
    .ai{background:rgba(0,212,255,.06);border:1px solid rgba(0,212,255,.22);color:var(--cyan)}
    .ae{background:rgba(255,82,82,.07);border:1px solid rgba(255,82,82,.28);color:var(--red)}
    .aw{background:rgba(255,179,0,.07);border:1px solid rgba(255,179,0,.28);color:var(--amber)}
    .empty{padding:48px;text-align:center;color:var(--text3)}
  </style>
</head>
<body>

<?php if ($blocked): ?>
  <div class="login-wrap">
    <div class="login-card">
      <h2>⚠️ לא מוגדר</h2>
      <p>יש להגדיר סיסמה ב-<code>config.php</code></p>
      <a href="../" class="btn bg" style="display:inline-block;margin-top:8px">← חזרה לבדיקה</a>
    </div>
  </div>

<?php elseif (!$loggedIn): ?>
  <div class="login-wrap">
    <div class="login-card">
      <h2>📊 לוח ניהול</h2>
      <p>הזן סיסמה להמשך</p>
      <form method="POST" action="?op=login" class="lf">
        <input type="password" name="password" placeholder="סיסמה" autofocus>
        <button type="submit" class="btn bp">כניסה</button>
      </form>
    </div>
  </div>

<?php else: ?>
  <div class="top-bar">
    <h1>📊 לוח ניהול SpeedTest</h1>
    <div class="top-bar-act">
      <a href="../" class="btn bg">← חזרה לבדיקה</a>
      <a href="?op=logout" class="btn bd">יציאה</a>
    </div>
  </div>

  <div class="content">
    <?php if ($dbErr): ?><div class="alert ae">⚠️ שגיאה: <?=h($dbErr)?></div><?php endif; ?>

    <?php if ($noData): ?>
    <div class="alert ai" style="text-align:center;padding:32px">
      <div style="font-size:2.5rem;margin-bottom:12px">🏁</div>
      <strong>אין בדיקות עדיין</strong><br>
      <span style="font-size:.85rem;opacity:.8;margin-top:6px;display:block">ברגע שמשתמש יבצע את הבדיקה הראשונה הנתונים יופיעו כאן.</span>
    </div>

    <?php elseif ($stats): ?>

    <div class="stat-grid">
      <div class="stat-card"><div class="stat-num"><?=number_format((int)$stats['total'])?></div><div class="stat-label">סך בדיקות</div></div>
      <div class="stat-card"><div class="stat-num" style="color:var(--cyan)"><?=h($stats['avg_dl'])?></div><div class="stat-label">הורדה ממוצעת Mbps</div></div>
      <div class="stat-card"><div class="stat-num" style="color:var(--green)"><?=h($stats['avg_ul'])?></div><div class="stat-label">העלאה ממוצעת Mbps</div></div>
      <div class="stat-card"><div class="stat-num" style="color:var(--amber)"><?=h($stats['avg_ping'])?></div><div class="stat-label">פינג ממוצע ms</div></div>
      <div class="stat-card"><div class="stat-num" style="color:var(--cyan)"><?=h($stats['max_dl'])?></div><div class="stat-label">שיא הורדה Mbps</div></div>
      <div class="stat-card"><div class="stat-num"><?=h($stats['first_date']??'—')?></div><div class="stat-label">תאריך פתיחה</div></div>
    </div>

    <?php if(!empty($perDay)): ?>
    <div class="chart-card">
      <div class="ct">בדיקות לפי יום — 30 ימים אחרונים</div>
      <canvas class="chart" id="chartCv"></canvas>
    </div>
    <?php endif; ?>

    <div class="two-col">
      <?php if(!empty($topIsps)):
        $maxC=max(array_column($topIsps,'cnt'))?:1;
        $maxD=max(array_column($topIsps,'avg_dl'))?:1;
      ?>
      <div class="chart-card" style="margin:0">
        <div class="ct">ספקים מובילים</div>
        <?php foreach($topIsps as $isp):
          $parsed = parseIsp($isp['ispinfo']);
          $name = $parsed['isp'] ?: $parsed['ip'];
        ?><div class="bar-row"><div class="bar-lbl" title="<?=h($name)?>"><?=h(mb_substr($name,0,18))?></div><div class="bar-trk"><div class="bar-fil" style="width:<?=round($isp['cnt']/$maxC*100)?>%"></div></div><div class="bar-val"><?=h($isp['cnt'])?>x</div></div><?php endforeach;?>
      </div>
      <div class="chart-card" style="margin:0">
        <div class="ct">הורדה ממוצעת לפי ספק (Mbps)</div>
        <?php foreach($topIsps as $isp):
          $parsed = parseIsp($isp['ispinfo']);
          $name = $parsed['isp'] ?: $parsed['ip'];
          $pct = $isp['avg_dl'] ? round($isp['avg_dl']/$maxD*100) : 0;
        ?><div class="bar-row"><div class="bar-lbl" title="<?=h($name)?>"><?=h(mb_substr($name,0,18))?></div><div class="bar-trk"><div class="bar-fil" style="width:<?=$pct?>%"></div></div><div class="bar-val"><?=h($isp['avg_dl'])?></div></div><?php endforeach;?>
      </div>
      <?php endif;?>
    </div>

    <!-- Results table: IP and ISP in separate columns -->
    <div class="table-card">
      <div class="th">
        <h3>תוצאות אחרונות (50)</h3>
        <form method="GET" style="display:flex;gap:7px">
          <input type="text" name="id" placeholder="חפש לפי ID" value="<?=h($_GET['id']??'')?>">
          <button type="submit" class="btn bp">חפש</button>
          <?php if(!empty($_GET['id'])):?><a href="admin.php" class="btn bg">הצג הכל</a><?php endif;?>
        </form>
      </div>
      <?php if(!empty($recent)):?>
      <div style="overflow-x:auto">
      <table>
        <thead><tr><th>ID</th><th>תאריך</th><th>כתובת IP</th><th>ספק</th><th>⬇ Mbps</th><th>⬆ Mbps</th><th>Ping ms</th><th>Jitter ms</th></tr></thead>
        <tbody>
        <?php foreach($recent as $row):
          $pid = $enable_id_obfuscation ? obfuscateId((int)$row['id']) : $row['id'];
          $parsed = parseIsp($row['ispinfo'] ?? '');
          // Use the db ip column as the authoritative IP address
          $ipAddr = $row['ip'];
          $ispName = $parsed['isp'] ?: '—';
        ?>
        <tr>
          <td><?=h($pid)?></td>
          <td><?=h(substr($row['timestamp'],0,16))?></td>
          <td><?=h($ipAddr)?></td>
          <td class="isp-cell" title="<?=h($ispName)?>"><?=h(mb_substr($ispName,0,28))?></td>
          <td class="vdl"><?=h($row['dl'])?></td>
          <td class="vul"><?=h($row['ul'])?></td>
          <td class="vping"><?=h($row['ping'])?></td>
          <td><?=h($row['jitter'])?></td>
        </tr>
        <?php endforeach;?>
        </tbody>
      </table>
      </div>
      <?php else:?><div class="empty">🔍 לא נמצאו תוצאות</div><?php endif;?>
    </div>

    <?php endif;?>
  </div>

  <?php if(!empty($perDay)):?>
  <script>
  (function(){
    var data=<?=json_encode($perDay)?>, cv=document.getElementById('chartCv');
    if(!cv)return;
    var dpr=window.devicePixelRatio||1, W=cv.clientWidth, H=cv.clientHeight||150;
    cv.width=W*dpr; cv.height=H*dpr; var ctx=cv.getContext('2d'); ctx.scale(dpr,dpr);
    var pad={t:10,r:10,b:28,l:38}, cW=W-pad.l-pad.r, cH=H-pad.t-pad.b;
    var max=Math.max.apply(null,data.map(function(d){return d.cnt}))||1;
    var bW=Math.max(2,cW/data.length-2);
    for(var g=0;g<=4;g++){var gy=pad.t+cH-(g/4)*cH;ctx.strokeStyle='rgba(255,255,255,.05)';ctx.lineWidth=1;ctx.beginPath();ctx.moveTo(pad.l,gy);ctx.lineTo(W-pad.r,gy);ctx.stroke();ctx.fillStyle='#354d68';ctx.font='9px monospace';ctx.textAlign='right';ctx.textBaseline='middle';ctx.fillText(Math.round(max*g/4),pad.l-4,gy);}
    data.forEach(function(d,i){
      var bh=(d.cnt/max)*cH, bx=pad.l+i*(cW/data.length)+1, by=pad.t+cH-bh;
      var gr=ctx.createLinearGradient(0,by,0,by+bh);gr.addColorStop(0,'rgba(0,212,255,.8)');gr.addColorStop(1,'rgba(0,80,130,.3)');ctx.fillStyle=gr;
      if(ctx.roundRect)ctx.roundRect(bx,by,bW,bh,[3,3,0,0]);else ctx.rect(bx,by,bW,bh); ctx.fill();
      if(i%5===0){ctx.fillStyle='#354d68';ctx.font='8px monospace';ctx.textAlign='center';ctx.textBaseline='top';ctx.fillText(d.day.slice(5),bx+bW/2,H-pad.b+4);}
    });
  }());
  </script>
  <?php endif;?>

<?php endif;?>
</body>
</html>
