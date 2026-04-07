<?php
/**
 * garbage.php  —  Download-test data generator
 * ─────────────────────────────────────────────────────────────────────────────
 * Streams random binary data to the browser so that the worker can measure
 * the download speed by tracking how many bytes arrive per second.
 *
 * Query parameter:
 *   ckSize (optional, integer, default 4, max 1024)
 *     Number of 1 MiB chunks to send.  The worker requests 100 by default,
 *     but the test ends early once a confident measurement is obtained, so
 *     the full amount is rarely transferred.
 *
 * Why random data?
 *   HTTP compression (gzip/deflate) is disabled below because compressible
 *   data would inflate the apparent download speed.  Random bytes cannot be
 *   compressed, so the measured speed reflects real network throughput.
 *
 * Why flush() after each chunk?
 *   PHP's output buffer would otherwise hold the entire payload in memory
 *   before sending it.  Flushing every 1 MiB lets the worker see progress
 *   events incrementally.
 * ─────────────────────────────────────────────────────────────────────────────
 */

// Disable all output compression — compressed random data is meaningless.
@ini_set('zlib.output_compression', 'Off');
@ini_set('output_buffering', 'Off');
@ini_set('output_handler', '');

// Tell the browser this is a binary file download with no caching.
header('HTTP/1.1 200 OK');
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename=random.dat');
header('Content-Transfer-Encoding: binary');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false); // legacy IE quirk
header('Pragma: no-cache');

// ── Determine chunk count ─────────────────────────────────────────────────────

$chunks = isset($_GET['ckSize']) ? (int)$_GET['ckSize'] : 4;

// Clamp to a safe range.
if ($chunks < 1)    $chunks = 1;
if ($chunks > 1024) $chunks = 1024;

// ── Generate one 1 MiB block of random data ───────────────────────────────────
// We generate it once and reuse it for every chunk to avoid repeated
// expensive calls to openssl_random_pseudo_bytes().

$block = openssl_random_pseudo_bytes(1048576); // 1 MiB = 1024 × 1024 bytes

// ── Stream the requested number of chunks ────────────────────────────────────

for ($i = 0; $i < $chunks; $i++) {
    echo $block;
    flush(); // push chunk to the network immediately
}
