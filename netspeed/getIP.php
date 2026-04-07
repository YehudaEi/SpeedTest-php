<?php
/**
 * getIP.php  —  Client IP and ISP lookup
 * ─────────────────────────────────────────────────────────────────────────────
 * Called by the speed-test worker at the start of every test.
 * Returns a JSON object with two fields:
 *
 *   processedString  – Human-readable string, e.g.:
 *                      "1.2.3.4 – Bezeq International, IL (120 km)"
 *   rawIspInfo       – The full JSON object from ipinfo.io, or "" if ISP
 *                      lookup was not requested / failed.
 *
 * Query parameters (all optional):
 *   isp=true            – Include ISP / organisation name and country.
 *   distance=km|mi      – Append an estimated client↔server distance.
 *                         Requires isp=true.
 *
 * ipinfo.io API key (optional):
 *   If the file  getIP_ipInfo_apikey.php  exists in the same directory
 *   and defines  $IPINFO_APIKEY, that key is appended to every ipinfo.io
 *   request, raising the free-tier rate limit from 50 k to 150 k/month.
 * ─────────────────────────────────────────────────────────────────────────────
 */

error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

// ── Determine the real client IP ──────────────────────────────────────────────
// Priority: HTTP_CLIENT_IP → X-Real-IP → HTTP_X_FORWARDED_FOR → REMOTE_ADDR
// X-Forwarded-For may contain a comma-separated list; we want only the first
// entry (the actual client).

$ip = '';

if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
    $ip = $_SERVER['HTTP_CLIENT_IP'];
} elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
    $ip = $_SERVER['HTTP_X_REAL_IP'];
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    // Take the leftmost (originating client) address.
    $ip = preg_replace('/,.*/', '', $_SERVER['HTTP_X_FORWARDED_FOR']);
} else {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// Strip the IPv4-mapped IPv6 prefix (::ffff:1.2.3.4 → 1.2.3.4).
$ip = preg_replace('/^::ffff:/i', '', trim($ip));

// ── Early-return for loopback / private / link-local addresses ───────────────
// These addresses cannot be resolved to an ISP, so we return a descriptive
// label immediately without calling ipinfo.io.

$privateLabels = [
    '::1'      => 'localhost IPv6',
    'fe80:'    => 'link-local IPv6',   // matched with stripos below
    '127.'     => 'localhost IPv4',    // 127.0.0.0/8
    '10.'      => 'private IPv4',      // 10.0.0.0/8
    '192.168.' => 'private IPv4',      // 192.168.0.0/16
    '169.254.' => 'link-local IPv4',   // 169.254.0.0/16
];

// Exact match for ::1.
if ($ip === '::1') {
    echo json_encode(['processedString' => "$ip – localhost IPv6", 'rawIspInfo' => '']);
    exit;
}

// Prefix matches.
foreach ($privateLabels as $prefix => $label) {
    if (stripos($ip, $prefix) === 0) {
        echo json_encode(['processedString' => "$ip – $label", 'rawIspInfo' => '']);
        exit;
    }
}

// 172.16.0.0/12  (172.16.x.x – 172.31.x.x)
if (preg_match('/^172\.(1[6-9]|2\d|3[01])\./', $ip)) {
    echo json_encode(['processedString' => "$ip – private IPv4", 'rawIspInfo' => '']);
    exit;
}

// ── Helper: calculate great-circle distance between two lat/lon pairs ─────────

/**
 * Returns the distance in kilometres between two geographic coordinates.
 * Uses the spherical law of cosines (fast and accurate enough for ISP labels).
 *
 * @param float $lat1  Latitude  of point 1 in decimal degrees.
 * @param float $lon1  Longitude of point 1 in decimal degrees.
 * @param float $lat2  Latitude  of point 2.
 * @param float $lon2  Longitude of point 2.
 * @return float Distance in km.
 */
function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
{
    $rad   = M_PI / 180;
    $theta = $lon1 - $lon2;
    $dist  = sin($lat1 * $rad) * sin($lat2 * $rad)
           + cos($lat1 * $rad) * cos($lat2 * $rad) * cos($theta * $rad);
    return acos(max(-1.0, min(1.0, $dist))) / $rad * 60 * 1.853;
}

// ── Helper: append the ipinfo.io API key to a URL if one is configured ────────

function ipInfoUrl(string $path): string
{
    $keyFile = __DIR__ . '/getIP_ipInfo_apikey.php';
    if (file_exists($keyFile)) {
        require_once $keyFile;
        if (!empty($IPINFO_APIKEY)) {
            $sep = str_contains($path, '?') ? '&' : '?';
            return $path . $sep . 'token=' . urlencode($IPINFO_APIKEY);
        }
    }
    return $path;
}

// ── ISP lookup via ipinfo.io ──────────────────────────────────────────────────

if (!empty($_GET['isp'])) {

    $isp        = '';
    $rawIspInfo = null;

    try {
        // Fetch JSON data for the client's IP.
        $json    = file_get_contents(ipInfoUrl("https://ipinfo.io/{$ip}/json"));
        $details = json_decode($json, true);

        if (!is_array($details)) {
            throw new RuntimeException('Invalid JSON from ipinfo.io');
        }

        $rawIspInfo = $details;

        // "org" field looks like "AS1234 Bezeq International" — strip the AS number.
        $isp = isset($details['org'])
            ? preg_replace('/^AS\d+\s+/', '', $details['org'])
            : 'Unknown ISP';

        // Append country code if available.
        if (isset($details['country'])) {
            $isp .= ', ' . $details['country'];
        }

        // ── Optionally append client↔server distance ───────────────────────────

        if (!empty($_GET['distance']) && isset($details['loc'])) {
            $unit      = $_GET['distance'];          // "km" or "mi"
            $clientLoc = explode(',', $details['loc']);

            try {
                // Fetch this server's own location.
                $serverJson    = file_get_contents(ipInfoUrl('https://ipinfo.io/json'));
                $serverDetails = json_decode($serverJson, true);

                if (isset($serverDetails['loc'])) {
                    $serverLoc = explode(',', $serverDetails['loc']);

                    $distKm = haversineKm(
                        (float)$clientLoc[0],  (float)$clientLoc[1],
                        (float)$serverLoc[0],  (float)$serverLoc[1]
                    );

                    if ($unit === 'mi') {
                        $distMi = $distKm / 1.609344;
                        $label  = ($distMi < 15) ? '<15 mi' : round($distMi, -1) . ' mi';
                    } else {
                        $label = ($distKm < 20) ? '<20 km' : round($distKm, -1) . ' km';
                    }

                    $isp .= " ({$label})";
                }
            } catch (Exception) {
                // Distance lookup failed — continue without it.
            }
        }

    } catch (Exception) {
        $isp = 'Unknown ISP';
    }

    echo json_encode([
        'processedString' => "{$ip} – {$isp}",
        'rawIspInfo'      => $rawIspInfo ?? '',
    ]);

} else {
    // ISP lookup not requested — return the IP address only.
    echo json_encode([
        'processedString' => $ip,
        'rawIspInfo'      => '',
    ]);
}
