<?php
/**
 * data.php — Telemetry endpoint.
 * Receives POST from the speed-test worker, saves to DB, returns obfuscated ID.
 * Built-in IP-based rate limiting (see RATE_WINDOW / RATE_MAX_HITS in config.php).
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/idObfuscation.php';

// ── Collect POST data ─────────────────────────────────────────────────────────
$ip      = $_SERVER['REMOTE_ADDR']          ?? '';
$ispinfo = $_POST['ispinfo'] ?? '';
$extra   = $_POST['extra']   ?? '';
$dl      = $_POST['dl']      ?? '';
$ul      = $_POST['ul']      ?? '';
$ping    = $_POST['ping']    ?? '';
$jitter  = $_POST['jitter']  ?? '';
$log     = $_POST['log']     ?? '';
$ua      = $_SERVER['HTTP_USER_AGENT']      ?? '';
$lang    = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';

if ($db_type !== 'sqlite') { die('-1'); }

$db = new PDO("sqlite:{$sqlite_db_file}");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ── Rate-limit table ──────────────────────────────────────────────────────────
$db->exec("
    CREATE TABLE IF NOT EXISTS rate_limit (
        ip     TEXT    NOT NULL,
        window INTEGER NOT NULL,
        hits   INTEGER NOT NULL DEFAULT 1,
        PRIMARY KEY (ip, window)
    )
");

$windowKey = (int)(floor(time() / RATE_WINDOW) * RATE_WINDOW);
$safeIp    = SQLite3::escapeString($ip);

$db->exec("
    INSERT INTO rate_limit (ip, window, hits) VALUES ('$safeIp', $windowKey, 1)
    ON CONFLICT(ip, window) DO UPDATE SET hits = hits + 1
");

$hits = (int)$db->query(
    "SELECT hits FROM rate_limit WHERE ip='$safeIp' AND window=$windowKey"
)->fetchColumn();

if ($hits > RATE_MAX_HITS) {
    http_response_code(429);
    echo 'rate_limited';
    exit;
}

// ── Results table ─────────────────────────────────────────────────────────────
$db->exec("
    CREATE TABLE IF NOT EXISTS speedtest_results (
        id        INTEGER  PRIMARY KEY AUTOINCREMENT,
        timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        ip        TEXT     NOT NULL,
        ispinfo   TEXT,
        ua        TEXT     NOT NULL,
        lang      TEXT     NOT NULL,
        dl        TEXT, ul TEXT, ping TEXT, jitter TEXT, log TEXT, extra TEXT
    )
");

// ── Insert ────────────────────────────────────────────────────────────────────
$stmt = $db->prepare("
    INSERT INTO speedtest_results (ip,ispinfo,extra,ua,lang,dl,ul,ping,jitter,log)
    VALUES (:ip,:ispinfo,:extra,:ua,:lang,:dl,:ul,:ping,:jitter,:log)
");
$stmt->execute([
    ':ip'=>$ip,':ispinfo'=>$ispinfo,':extra'=>$extra,
    ':ua'=>$ua,':lang'=>$lang,
    ':dl'=>$dl,':ul'=>$ul,':ping'=>$ping,':jitter'=>$jitter,':log'=>$log,
]);

$id = (int)$db->lastInsertId();
echo 'id ' . ($enable_id_obfuscation ? obfuscateId($id) : $id);
$db = null;
