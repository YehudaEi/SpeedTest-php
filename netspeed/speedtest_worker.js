/**
 * speedtest_worker.js
 * ─────────────────────────────────────────────────────────────────────────────
 * Web Worker that runs the actual speed-test measurements entirely off the
 * main thread so that the UI never blocks.
 *
 * Communication with the main thread:
 *   Incoming messages (from main thread → worker):
 *     "start [jsonSettings]"  – Begin the test.  Optional JSON overrides default settings.
 *     "status"                – Return current state snapshot as JSON.
 *     "abort"                 – Stop all activity immediately.
 *
 *   Outgoing messages (worker → main thread):
 *     Only sent in response to a "status" request — a JSON string containing
 *     the fields listed in postStatus() below.
 *
 * Test sequence (controlled by settings.test_order):
 *   I  →  getIP()    – Fetch client IP / ISP info.
 *   D  →  dlTest()   – Measure download speed.
 *   U  →  ulTest()   – Measure upload speed.
 *   P  →  pingTest() – Measure ping and jitter.
 *   _  →  1-second pause.
 *
 * Default order: "IP_D_U"  (IP lookup → 1 s pause → download → upload)
 * ─────────────────────────────────────────────────────────────────────────────
 */

'use strict';

// ── Public state (exposed via "status" messages) ───────────────────────────────

var testState    = -1;  // -1=idle, 0=starting, 1=download, 2=ping, 3=upload, 4=done, 5=aborted
var dlStatus     = '';  // Current download speed string (Mbps, 2 decimals)
var ulStatus     = '';  // Current upload speed string
var pingStatus   = '';  // Current ping (ms, 2 decimals)
var jitterStatus = '';  // Current jitter (ms, 2 decimals)
var clientIp     = '';  // IP address string returned by getIP.php
var dlProgress   = 0;   // 0–1 fraction through the download test
var ulProgress   = 0;   // 0–1 fraction through the upload test
var pingProgress = 0;   // 0–1 fraction through the ping test
var testId       = null; // Result ID returned by the telemetry endpoint after save

// ── Telemetry log (appended throughout the test) ───────────────────────────────

var log = '';

/**
 * Append a timestamped line to the telemetry log (level ≥ 2).
 * @param {string} msg
 */
function tlog(msg) {
    if (settings.telemetry_level >= 2) {
        log += Date.now() + ': ' + msg + '\n';
    }
}

/**
 * Verbose log — only active at level 3 (debug).
 * @param {string} msg
 */
function tverb(msg) {
    if (settings.telemetry_level >= 3) {
        log += Date.now() + ': ' + msg + '\n';
    }
}

/**
 * Warning — also prints to the browser console.
 * @param {string} msg
 */
function twarn(msg) {
    if (settings.telemetry_level >= 2) {
        log += Date.now() + ' WARN: ' + msg + '\n';
    }
    console.warn(msg);
}

// ── Default settings ───────────────────────────────────────────────────────────
// All of these can be overridden by passing a JSON object with the "start"
// command, e.g.:  worker.postMessage('start {"time_dl_max":10}')

var settings = {
    // Order of tests.  Letters: D=download, U=upload, P=ping, I=IP, _=1 s pause.
    test_order: 'IP_D_U',

    // Maximum test durations in seconds.
    time_dl_max:  15,
    time_ul_max:  15,

    // If true, tests stop early on fast connections (once the result is stable).
    time_auto: true,

    // Grace periods: ignore measurements during these first N seconds so TCP
    // slow-start and upload buffer fill don't skew the result.
    time_dlGraceTime: 1.5,
    time_ulGraceTime: 3,

    // Number of ICMP-like HTTP round-trips for the ping test.
    count_ping: 10,

    // Paths to server-side scripts (relative to this worker file).
    url_dl:    'garbage.php',  // Download source — streams random bytes
    url_ul:    'empty.php',    // Upload target  — discards the body
    url_ping:  'empty.php',    // Ping target    — tiny HEAD/GET response
    url_getIp: 'getIP.php',    // IP/ISP lookup

    // Whether to request ISP info and distance from getIP.php.
    getIp_ispInfo:          true,
    getIp_ispInfo_distance: 'km', // 'km', 'mi', or false

    // Number of simultaneous XHR streams for download and upload.
    xhr_dlMultistream:    6,
    xhr_ulMultistream:    3,

    // Delay in ms between starting consecutive streams (staggers them so they
    // don't all begin and end at the exact same moment).
    xhr_multistreamDelay: 300,

    // Error handling: 0=fail on first error, 1=restart failed stream, 2=ignore all errors.
    xhr_ignoreErrors: 1,

    // If true, download data is stored in a Blob instead of an ArrayBuffer.
    // Uses less RAM but writes to disk — useful for very large transfers.
    xhr_dlUseBlob: false,

    // Size in MiB of each upload blob.  Forced to 4 MiB on Chrome Mobile.
    xhr_ul_blob_megabytes: 20,

    // Size of each garbage.php chunk in MiB (passed as ?ckSize=).
    garbagePhp_chunkSize: 100,

    // Apply browser-specific quirks automatically (unless already overridden).
    enable_quirks: true,

    // Try to use the Performance API for more accurate ping timing.
    // Works well in Chrome; poorly in Edge; not at all in Firefox.
    ping_allowPerformanceApi: true,

    // Compensation factor for transport overhead (TCP/IP headers, HTTP framing).
    // 1.06 = assume ~6 % overhead.  Set to 1 to disable.
    overheadCompensationFactor: 1.06,

    // Report speed in mebibits/s (MiB) instead of megabits/s (MB).
    useMebibits: false,

    // Telemetry: 0=off, 1=save results only, 2=save with timing log, 3=debug (full log).
    telemetry_level: 2,

    // Path to the PHP endpoint that stores results.
    url_telemetry: '../data/data.php',

    // Optional extra data to store alongside the result (e.g. a session tag).
    telemetry_extra: '',
};

// ── Internal state ─────────────────────────────────────────────────────────────

var xhr          = null;  // Array (or single XHR) of currently active requests
var interval     = null;  // setInterval handle used in download/upload tests
var test_pointer = 0;     // Index into settings.test_order for runNextTest()

/**
 * Returns '?' or '&' depending on whether the URL already has a query string.
 * Used to append cache-busting random parameters.
 * @param {string} url
 * @returns {string}
 */
function urlSep(url) {
    return url.indexOf('?') !== -1 ? '&' : '?';
}

// ── Message listener ───────────────────────────────────────────────────────────

self.addEventListener('message', function (e) {
    var parts = e.data.split(' ');
    var cmd   = parts[0];

    // ── "status" — snapshot of current measurements ──────────────────────────
    if (cmd === 'status') {
        postStatus();
        return;
    }

    // ── "start" — begin a new test ───────────────────────────────────────────
    if (cmd === 'start' && testState === -1) {
        testState = 0;
        applySettings(e.data.substring(6)); // strip "start " prefix
        runTests();
        return;
    }

    // ── "abort" — cancel everything ──────────────────────────────────────────
    if (cmd === 'abort') {
        tlog('manually aborted');
        clearRequests();
        if (interval) clearInterval(interval);
        // Save a partial telemetry entry so the abort is recorded.
        if (settings.telemetry_level > 1) sendTelemetry(function () {});
        testState    = 5;
        dlStatus     = '';
        ulStatus     = '';
        pingStatus   = '';
        jitterStatus = '';
    }
});

/**
 * Post the current state snapshot back to the main thread.
 */
function postStatus() {
    self.postMessage(JSON.stringify({
        testState:    testState,
        dlStatus:     dlStatus,
        ulStatus:     ulStatus,
        pingStatus:   pingStatus,
        clientIp:     clientIp,
        jitterStatus: jitterStatus,
        dlProgress:   dlProgress,
        ulProgress:   ulProgress,
        pingProgress: pingProgress,
        testId:       testId,
    }));
}

// ── Settings application ───────────────────────────────────────────────────────

/**
 * Merges user-supplied JSON overrides into `settings` and applies
 * browser-specific quirks.
 * @param {string} jsonStr  Everything after "start " in the message.
 */
function applySettings(jsonStr) {
    // Parse the optional JSON override block.
    var overrides = {};
    if (jsonStr && jsonStr.trim()) {
        try {
            overrides = JSON.parse(jsonStr);
        } catch (err) {
            twarn('Could not parse custom settings JSON: ' + err.message);
        }
    }

    // Copy recognised keys into settings; warn about unknown ones.
    for (var key in overrides) {
        if (key in settings) {
            settings[key] = overrides[key];
        } else {
            twarn('Unknown setting ignored: ' + key);
        }
    }

    // Normalise telemetry_level if passed as a string alias.
    if (typeof overrides.telemetry_level === 'string') {
        var lvlMap = { basic: 1, full: 2, debug: 3 };
        settings.telemetry_level = lvlMap[overrides.telemetry_level] || 0;
    }

    // test_order must be uppercase for the switch-case comparison.
    settings.test_order = settings.test_order.toUpperCase();

    // Apply browser quirks unless the user already overrode the affected setting.
    if (settings.enable_quirks) {
        applyBrowserQuirks(overrides);
    }

    tverb('Applied settings: ' + JSON.stringify(settings));
}

/**
 * Detects browser quirks from the user-agent string and patches settings.
 * @param {Object} overrides  The user-supplied overrides (used to avoid
 *                            overwriting something the user explicitly set).
 */
function applyBrowserQuirks(overrides) {
    var ua = navigator.userAgent;

    if (/Firefox\/(\d+\.\d+)/i.test(ua)) {
        // Firefox is more accurate with a single upload stream.
        if (!('xhr_ulMultistream' in overrides))    settings.xhr_ulMultistream    = 1;
        // Firefox's Performance API entries are unreliable for ping.
        if (!('ping_allowPerformanceApi' in overrides)) settings.ping_allowPerformanceApi = false;
    }

    if (/Edg\/(\d+\.\d+)/i.test(ua)) {
        // Edge is more accurate with 3 download streams.
        if (!('xhr_dlMultistream' in overrides))    settings.xhr_dlMultistream    = 3;
        // Edge 15 introduced a bug causing missing onprogress events.
        settings.forceIE11Workaround = true;
    }

    if (/Chrome\/(\d+)/i.test(ua) && self.fetch) {
        // Chrome is most accurate with 5 download streams.
        if (!('xhr_dlMultistream' in overrides))    settings.xhr_dlMultistream    = 5;
    }

    if (/PlayStation 4\/(\d+\.\d+)/i.test(ua)) {
        // PS4 browser has the same progress-event bug as legacy Edge.
        settings.forceIE11Workaround = true;
    }

    if (/Chrome\/(\d+)/i.test(ua) && /Android|iPhone|iPad|iPod|Windows Phone/i.test(ua)) {
        // Chrome Mobile limits upload blob size to ~4 MiB.
        settings.xhr_ul_blob_megabytes = 4;
    }
}

// ── Test sequencer ─────────────────────────────────────────────────────────────

/**
 * Starts the test sequence based on settings.test_order.
 * Each test calls runNextTest() when done, advancing test_pointer.
 */
function runTests() {
    test_pointer = 0;
    var ran = { I: false, D: false, U: false, P: false };

    function runNextTest() {
        if (testState === 5) return; // aborted

        // All steps complete — save telemetry and finish.
        if (test_pointer >= settings.test_order.length) {
            if (settings.telemetry_level > 0) {
                sendTelemetry(function (id) {
                    testState = 4;
                    if (id !== null) testId = id;
                });
            } else {
                testState = 4;
            }
            return;
        }

        var step = settings.test_order.charAt(test_pointer++);

        switch (step) {
            case 'I':
                if (ran.I) { runNextTest(); return; }
                ran.I = true;
                getIp(runNextTest);
                break;

            case 'D':
                if (ran.D) { runNextTest(); return; }
                ran.D = true;
                testState = 1;
                dlTest(runNextTest);
                break;

            case 'U':
                if (ran.U) { runNextTest(); return; }
                ran.U = true;
                testState = 3;
                ulTest(runNextTest);
                break;

            case 'P':
                if (ran.P) { runNextTest(); return; }
                ran.P = true;
                testState = 2;
                pingTest(runNextTest);
                break;

            case '_':
                // 1-second pause between tests.
                setTimeout(runNextTest, 1000);
                break;

            default:
                // Unknown character — skip.
                runNextTest();
        }
    }

    runNextTest();
}

// ── XHR cleanup ────────────────────────────────────────────────────────────────

/**
 * Aggressively aborts and destroys all active XHR objects.
 * Called when a test phase ends or when "abort" is received.
 */
function clearRequests() {
    tverb('clearRequests');
    if (!xhr) return;

    var list = Array.isArray(xhr) ? xhr : [xhr];

    list.forEach(function (x) {
        if (!x) return;
        try { x.onprogress = x.onload = x.onerror = null; } catch (e) {}
        try { x.upload.onprogress = x.upload.onload = x.upload.onerror = null; } catch (e) {}
        try { x.abort(); } catch (e) {}
    });

    xhr = null;
}

// ── IP / ISP lookup ────────────────────────────────────────────────────────────

var ipCalled = false;   // Prevents getIp() from running more than once
var ispInfo  = '';      // Raw ISP info object, stored for telemetry

/**
 * Fetches the client IP (and optionally ISP info) from getIP.php.
 * @param {function} done  Called when the request completes (success or error).
 */
function getIp(done) {
    tverb('getIp');
    if (ipCalled) return;
    ipCalled = true;

    var startT = Date.now();

    // Build the query string.
    var qs = settings.url_getIp + urlSep(settings.url_getIp);
    if (settings.getIp_ispInfo) {
        qs += 'isp=true';
        if (settings.getIp_ispInfo_distance) {
            qs += '&distance=' + settings.getIp_ispInfo_distance;
        }
        qs += '&';
    } else {
        qs += '';
    }
    qs += 'r=' + Math.random(); // cache buster

    xhr = new XMLHttpRequest();

    xhr.onload = function () {
        tlog('IP lookup: ' + xhr.responseText + ' (' + (Date.now() - startT) + ' ms)');
        try {
            var data = JSON.parse(xhr.responseText);
            clientIp = data.processedString;
            ispInfo  = data.rawIspInfo;
        } catch (e) {
            // Fallback: treat the entire response as the IP string.
            clientIp = xhr.responseText.trim();
            ispInfo  = '';
        }
        done();
    };

    xhr.onerror = function () {
        tlog('IP lookup failed (' + (Date.now() - startT) + ' ms)');
        done(); // continue the test even if IP lookup fails
    };

    xhr.open('GET', qs, true);
    xhr.send();
}

// ── Download test ──────────────────────────────────────────────────────────────

var dlCalled = false; // Prevents dlTest() from running more than once

/**
 * Opens multiple simultaneous download streams to garbage.php and measures
 * how many bytes per second arrive in total.
 *
 * The test has two phases:
 *   1. Grace period — bytes are downloaded but not counted, giving TCP
 *      slow-start time to reach full speed.
 *   2. Measurement period — bytes per second are converted to Mbps.
 *
 * @param {function} done  Called when the download test is complete.
 */
function dlTest(done) {
    tverb('dlTest');
    if (dlCalled) return;
    dlCalled = true;

    var totLoaded    = 0;      // Total bytes received during measurement phase
    var startT       = Date.now();
    var bonusT       = 0;      // Accumulated "shortcut" ms on fast connections
    var graceTimeDone = false;
    var failed       = false;

    xhr = [];

    /**
     * Opens one download stream with an optional start delay.
     * On completion, restarts itself to keep data flowing until the timer ends.
     */
    function openStream(index, delay) {
        setTimeout(function () {
            if (testState !== 1) return; // test already ended

            var prevLoaded = 0;
            var x = new XMLHttpRequest();
            xhr[index] = x;

            // Track bytes as they arrive.
            x.onprogress = function (ev) {
                if (testState !== 1) { try { x.abort(); } catch (e) {} return; }
                var diff = ev.loaded > 0 ? ev.loaded - prevLoaded : 0;
                if (!isNaN(diff) && isFinite(diff) && diff >= 0) {
                    totLoaded += diff;
                    prevLoaded = ev.loaded;
                }
            };

            // File fully received — restart the stream.
            x.onload = function () {
                try { xhr[index].abort(); } catch (e) {}
                openStream(index, 0);
            };

            x.onerror = function () {
                tverb('dl stream ' + index + ' error');
                if (settings.xhr_ignoreErrors === 0) { failed = true; return; }
                try { xhr[index].abort(); } catch (e) {}
                delete xhr[index];
                if (settings.xhr_ignoreErrors === 1) openStream(index, 0);
            };

            try {
                x.responseType = settings.xhr_dlUseBlob ? 'blob' : 'arraybuffer';
            } catch (e) {}

            x.open('GET',
                settings.url_dl + urlSep(settings.url_dl) +
                'r=' + Math.random() +
                '&ckSize=' + settings.garbagePhp_chunkSize,
                true);
            x.send();

        }, 1 + delay);
    }

    // Open all streams, staggered by xhr_multistreamDelay.
    for (var i = 0; i < settings.xhr_dlMultistream; i++) {
        openStream(i, settings.xhr_multistreamDelay * i);
    }

    // Poll every 200 ms to update dlStatus and check if the test is done.
    interval = setInterval(function () {
        var elapsed = Date.now() - startT;

        if (graceTimeDone) {
            dlProgress = (elapsed + bonusT) / (settings.time_dl_max * 1000);
        }

        if (elapsed < 200) return; // wait for first meaningful chunk

        if (!graceTimeDone) {
            if (elapsed > settings.time_dlGraceTime * 1000) {
                if (totLoaded > 0) {
                    // Reset counters — start measuring from now.
                    startT    = Date.now();
                    bonusT    = 0;
                    totLoaded = 0;
                }
                graceTimeDone = true;
            }
            return;
        }

        // Compute speed: bytes/s → bits/s → Mbps.
        var bps   = totLoaded / (elapsed / 1000);
        var mbps  = (bps * 8 * settings.overheadCompensationFactor)
                    / (settings.useMebibits ? 1048576 : 1000000);

        // Shorten the test on fast connections.
        if (settings.time_auto) {
            var bonus = (6.4 * bps) / 100000;
            bonusT += Math.min(bonus, 800);
        }

        dlStatus = mbps.toFixed(2);
        tverb('dl=' + dlStatus + ' Mbps');

        if ((elapsed + bonusT) / 1000 >= settings.time_dl_max || failed) {
            if (failed || isNaN(dlStatus)) dlStatus = 'Fail';
            clearRequests();
            clearInterval(interval);
            dlProgress = 1;
            tlog('dlTest done: ' + dlStatus + ' Mbps in ' + (Date.now() - startT) + ' ms');
            done();
        }

    }, 200);
}

// ── Upload test ────────────────────────────────────────────────────────────────

var ulCalled = false; // Prevents ulTest() from running more than once

/**
 * Opens multiple simultaneous upload streams to empty.php and measures how
 * many bytes per second the server can receive.
 *
 * The approach mirrors dlTest():
 *   1. Grace period — let upload buffers fill before measuring.
 *   2. Measurement period — bytes acknowledged per second → Mbps.
 *
 * @param {function} done  Called when the upload test is complete.
 */
function ulTest(done) {
    tverb('ulTest');
    if (ulCalled) return;
    ulCalled = true;

    // Pre-generate random blob data (generated once, reused across streams).
    var maxUint32 = Math.pow(2, 32) - 1;

    // Large blob — used by the normal path.
    var largeBuffer = new ArrayBuffer(1048576);
    try {
        var largeView = new Uint32Array(largeBuffer);
        for (var i = 0; i < largeView.length; i++) largeView[i] = Math.random() * maxUint32;
    } catch (e) {}
    var largeParts = [];
    for (var j = 0; j < settings.xhr_ul_blob_megabytes; j++) largeParts.push(largeBuffer);
    var largeBlob = new Blob(largeParts);

    // Small blob — used by the IE11/Edge workaround (256 KiB).
    var smallBuffer = new ArrayBuffer(262144);
    try {
        var smallView = new Uint32Array(smallBuffer);
        for (var k = 0; k < smallView.length; k++) smallView[k] = Math.random() * maxUint32;
    } catch (e) {}
    var smallBlob = new Blob([smallBuffer]);

    var totLoaded    = 0;
    var startT       = Date.now();
    var bonusT       = 0;
    var graceTimeDone = false;
    var failed       = false;

    xhr = [];

    /**
     * Opens one upload stream with a start delay.
     */
    function openStream(index, delay) {
        setTimeout(function () {
            if (testState !== 3) return;

            var prevLoaded = 0;
            var x = new XMLHttpRequest();
            xhr[index] = x;

            // Detect IE11/Edge workaround requirement.
            var needsWorkaround = settings.forceIE11Workaround;
            if (!needsWorkaround) {
                try { void x.upload.onprogress; }
                catch (e) { needsWorkaround = true; }
            }

            if (needsWorkaround) {
                // IE11 / old Edge: upload.onprogress is broken.
                // Send many small 256 KiB requests and count their completions instead.
                x.onload = x.onerror = function () {
                    totLoaded += smallBlob.size;
                    openStream(index, 0);
                };
                x.open('POST', settings.url_ul + urlSep(settings.url_ul) + 'r=' + Math.random(), true);
                try { x.setRequestHeader('Content-Encoding', 'identity'); } catch (e) {}
                try { x.setRequestHeader('Content-Type', 'application/octet-stream'); } catch (e) {}
                x.send(smallBlob);

            } else {
                // Standard path: track acknowledged bytes via upload.onprogress.
                x.upload.onprogress = function (ev) {
                    if (testState !== 3) { try { x.abort(); } catch (e) {} return; }
                    var diff = ev.loaded > 0 ? ev.loaded - prevLoaded : 0;
                    if (!isNaN(diff) && isFinite(diff) && diff >= 0) {
                        totLoaded += diff;
                        prevLoaded = ev.loaded;
                    }
                };
                x.upload.onload  = function () { openStream(index, 0); };
                x.upload.onerror = function () {
                    if (settings.xhr_ignoreErrors === 0) { failed = true; return; }
                    try { xhr[index].abort(); } catch (e) {}
                    delete xhr[index];
                    if (settings.xhr_ignoreErrors === 1) openStream(index, 0);
                };
                x.open('POST', settings.url_ul + urlSep(settings.url_ul) + 'r=' + Math.random(), true);
                try { x.setRequestHeader('Content-Encoding', 'identity'); } catch (e) {}
                try { x.setRequestHeader('Content-Type', 'application/octet-stream'); } catch (e) {}
                x.send(largeBlob);
            }
        }, 1 + delay);
    }

    for (var s = 0; s < settings.xhr_ulMultistream; s++) {
        openStream(s, settings.xhr_multistreamDelay * s);
    }

    // Poll every 200 ms.
    interval = setInterval(function () {
        var elapsed = Date.now() - startT;

        if (graceTimeDone) {
            ulProgress = (elapsed + bonusT) / (settings.time_ul_max * 1000);
        }

        if (elapsed < 200) return;

        if (!graceTimeDone) {
            if (elapsed > settings.time_ulGraceTime * 1000) {
                if (totLoaded > 0) {
                    startT    = Date.now();
                    bonusT    = 0;
                    totLoaded = 0;
                }
                graceTimeDone = true;
            }
            return;
        }

        var bps  = totLoaded / (elapsed / 1000);
        var mbps = (bps * 8 * settings.overheadCompensationFactor)
                   / (settings.useMebibits ? 1048576 : 1000000);

        if (settings.time_auto) {
            var bonus = (6.4 * bps) / 100000;
            bonusT += Math.min(bonus, 800);
        }

        ulStatus = mbps.toFixed(2);
        tverb('ul=' + ulStatus + ' Mbps');

        if ((elapsed + bonusT) / 1000 >= settings.time_ul_max || failed) {
            if (failed || isNaN(ulStatus)) ulStatus = 'Fail';
            clearRequests();
            clearInterval(interval);
            ulProgress = 1;
            tlog('ulTest done: ' + ulStatus + ' Mbps in ' + (Date.now() - startT) + ' ms');
            done();
        }

    }, 200);
}

// ── Ping / jitter test ─────────────────────────────────────────────────────────

var ptCalled = false; // Prevents pingTest() from running more than once

/**
 * Sends settings.count_ping sequential HTTP GET requests to empty.php and
 * measures the round-trip time for each.
 *
 * Ping is updated as the running minimum of all observed RTTs (with a small
 * weighted-average smoothing to avoid noise from a single lucky fast sample).
 *
 * Jitter is the running weighted average of |current_RTT − previous_RTT|,
 * giving more weight to spikes (which is what users actually feel).
 *
 * @param {function} done  Called when all pings are complete.
 */
function pingTest(done) {
    tverb('pingTest');
    if (ptCalled) return;
    ptCalled = true;

    var startT      = Date.now();
    var prevT       = null;   // Timestamp of the previous pong
    var ping        = 0;
    var jitter      = 0;
    var i           = 0;      // Number of completed pings
    var prevInstspd = 0;      // Previous instant RTT (for jitter calculation)

    xhr = [];

    function doPing() {
        pingProgress = i / settings.count_ping;
        prevT = Date.now();

        var x = new XMLHttpRequest();
        xhr[0] = x;

        x.onload = function () {
            tverb('pong ' + i);

            if (i === 0) {
                // Discard the first ping — it includes DNS + TCP setup time.
                prevT = Date.now();
            } else {
                // Measure instant RTT.
                var instspd = Date.now() - prevT;

                // Try to improve accuracy with the Performance API.
                if (settings.ping_allowPerformanceApi) {
                    try {
                        var entries = performance.getEntries();
                        var last    = entries[entries.length - 1];
                        var perfMs  = last.responseStart - last.requestStart;
                        if (perfMs <= 0) perfMs = last.duration;
                        if (perfMs > 0 && perfMs < instspd) instspd = perfMs;
                    } catch (e) {
                        tverb('Performance API unavailable');
                    }
                }

                // Clamp impossibly-low values (some browsers report 0 ms).
                if (instspd < 1) instspd = prevInstspd;
                if (instspd < 1) instspd = 1;

                var instjitter = Math.abs(instspd - prevInstspd);

                if (i === 1) {
                    ping = instspd;
                } else {
                    // Take the lower of the instant RTT and a rolling average.
                    ping = instspd < ping
                        ? instspd
                        : ping * 0.8 + instspd * 0.2;

                    if (i === 2) {
                        // Discard first jitter reading (comparing first two pings is unreliable).
                        jitter = instjitter;
                    } else {
                        // Spikes get more weight (0.7) than the rolling baseline (0.3).
                        jitter = instjitter > jitter
                            ? jitter * 0.3 + instjitter * 0.7
                            : jitter * 0.8 + instjitter * 0.2;
                    }
                }

                prevInstspd = instspd;
            }

            pingStatus   = ping.toFixed(2);
            jitterStatus = jitter.toFixed(2);
            i++;

            if (i < settings.count_ping) {
                doPing();
            } else {
                pingProgress = 1;
                tlog('pingTest done: ping=' + pingStatus + ' ms, jitter=' + jitterStatus + ' ms, took ' + (Date.now() - startT) + ' ms');
                done();
            }
        };

        x.onerror = function () {
            tverb('ping request failed');

            if (settings.xhr_ignoreErrors === 0) {
                pingStatus = jitterStatus = 'Fail';
                clearRequests();
                pingProgress = 1;
                tlog('pingTest failed after ' + (Date.now() - startT) + ' ms');
                done();
                return;
            }
            if (settings.xhr_ignoreErrors === 1) { doPing(); return; }
            if (settings.xhr_ignoreErrors === 2) {
                i++;
                if (i < settings.count_ping) doPing();
                else {
                    pingProgress = 1;
                    tlog('ping: ' + pingStatus + ' jitter: ' + jitterStatus);
                    done();
                }
            }
        };

        x.open('GET', settings.url_ping + urlSep(settings.url_ping) + 'r=' + Math.random(), true);
        x.send();
    }

    doPing();
}

// ── Telemetry save ─────────────────────────────────────────────────────────────

/**
 * Posts the final (or partial, if aborted) test results to url_telemetry.
 * The server responds with "id <token>" on success.
 *
 * @param {function} done  Called with the test ID string, or null on failure.
 */
function sendTelemetry(done) {
    if (settings.telemetry_level < 1) return;

    var x = new XMLHttpRequest();

    x.onload = function () {
        try {
            var parts = x.responseText.split(' ');
            done(parts[0] === 'id' ? parts[1] : null);
        } catch (e) {
            done(null);
        }
    };

    x.onerror = function () {
        console.warn('Telemetry request failed (HTTP ' + x.status + ')');
        done(null);
    };

    x.open('POST', settings.url_telemetry + urlSep(settings.url_telemetry) + 'r=' + Math.random(), true);

    // Build the telemetry payload.
    var ispPayload = JSON.stringify({
        processedString: clientIp,
        rawIspInfo:      (typeof ispInfo === 'object') ? ispInfo : '',
    });

    try {
        var fd = new FormData();
        fd.append('ispinfo', ispPayload);
        fd.append('dl',      dlStatus);
        fd.append('ul',      ulStatus);
        fd.append('ping',    pingStatus);
        fd.append('jitter',  jitterStatus);
        fd.append('log',     settings.telemetry_level > 1 ? log : '');
        fd.append('extra',   settings.telemetry_extra);
        x.send(fd);
    } catch (e) {
        // FormData not supported (very old browsers) — fall back to URL-encoded.
        var body = 'ispinfo=' + encodeURIComponent(ispPayload)
                 + '&dl='    + encodeURIComponent(dlStatus)
                 + '&ul='    + encodeURIComponent(ulStatus)
                 + '&ping='  + encodeURIComponent(pingStatus)
                 + '&jitter='+ encodeURIComponent(jitterStatus)
                 + '&log='   + encodeURIComponent(settings.telemetry_level > 1 ? log : '')
                 + '&extra=' + encodeURIComponent(settings.telemetry_extra);
        x.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        x.send(body);
    }
}
