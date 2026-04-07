<?php
/**
 * config.php — Single source of truth for all site configuration.
 *
 * Include this file wherever you need settings:
 *   require_once __DIR__ . '/config.php';          (from root)
 *   require_once __DIR__ . '/../config.php';        (from /data/ or /netspeed/)
 */

// ── Site identity ─────────────────────────────────────────────────────────────
define('SITE_NAME',  'SpeedTest');
define('SITE_URL',   'https://speedtest.yehudae.net');
define('SITE_EMAIL', 'speedtest@yehudae.net');

$copyrights = 'Yehuda © ' . date('Y');

// ── Database ──────────────────────────────────────────────────────────────────
// Only SQLite is supported out of the box.
// The file is created automatically on the first test run.
$db_type        = 'sqlite';
$sqlite_db_file = __DIR__ . '/data/speedtest_results.sqlite';

// ── Stats / Admin dashboard password ─────────────────────────────────────────
// IMPORTANT: Change this before going live.
// The admin page (/data/admin.php) is fully blocked until you do.
$stats_password = 'CHANGE_ME';

// ── ID obfuscation ────────────────────────────────────────────────────────────
// When true, database IDs in public URLs are scrambled so sequential
// integers are not exposed. Fully reversible server-side.
$enable_id_obfuscation = true;

// ── Rate limiting (data.php) ──────────────────────────────────────────────────
define('RATE_WINDOW',   60);   // seconds per window
define('RATE_MAX_HITS', 10);   // max test saves per IP per window

// ── CDN / Test servers ────────────────────────────────────────────────────────
// Each non-local entry needs the /netspeed/ files deployed at that hostname.
// Set local:true for the same-server entry (always works, no extra deploy needed).
//
// To add more servers in the future, simply append entries to this array.
$cdn_servers = [
    [
        'id'    => 'il',
        'label' => '🇮🇱 ישראל',
        'host'  => '',      // empty = same server (uses relative worker URLs)
        'local' => true,
    ],
    // Example future servers (comment out until deployed):
    // ['id' => 'ams', 'label' => '🇳🇱 אמסטרדם', 'host' => 'https://ams.speedtest.yehudae.net', 'local' => false],
    // ['id' => 'fra', 'label' => '🇩🇪 פרנקפורט', 'host' => 'https://fra.speedtest.yehudae.net', 'local' => false],
];
