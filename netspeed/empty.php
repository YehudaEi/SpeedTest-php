<?php
/**
 * empty.php  —  Upload & ping target
 * ─────────────────────────────────────────────────────────────────────────────
 * This file is the destination for:
 *   • Upload test  — the worker POSTs random binary data here and measures
 *                    how quickly the server acknowledges it.
 *   • Ping test    — the worker GETs this URL repeatedly and measures RTT.
 *
 * The response body is intentionally empty (204 would also work, but 200 is
 * safer across all browsers/workers). The critical headers are:
 *   Cache-Control / Pragma  — prevents the browser from serving a cached
 *                             response, which would give an artificially low
 *                             ping and no real upload measurement.
 *   Connection: keep-alive  — keeps the TCP connection open between pings so
 *                             that connection setup time is not included in
 *                             subsequent measurements.
 * ─────────────────────────────────────────────────────────────────────────────
 */

header('HTTP/1.1 200 OK');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false); // legacy IE quirk
header('Pragma: no-cache');
header('Connection: keep-alive');

// No body — the worker only cares about the HTTP response status and timing.
