<!DOCTYPE html>
<html lang="he" dir="rtl">
<?php require_once 'inc/head.php'; ?>
<body>

<canvas id="confetti-canvas" aria-hidden="true"></canvas>
<div class="bg-layer" aria-hidden="true"><div class="bg-grid"></div><div class="bg-glow"></div></div>

<?php require_once 'inc/nav.php'; ?>

<section class="hero">
  <div class="hero-badge"><span class="badge-dot"></span>בדיקת מהירות בזמן אמת</div>
  <h1>כמה מהיר<br><em>האינטרנט שלך?</em></h1>
  <p class="hero-sub">הורדה · העלאה · פינג · ג'יטר</p>
</section>

<!-- ════════════════════════════════════════════════════════════════
     STAGE
     HTML order: pj-row FIRST (ping runs first, test_order IP_P_D_U)
     After ping test completes → JS FLIP animation swaps the rows.
     ════════════════════════════════════════════════════════════════ -->
<main class="stage" id="stage">

  <!-- Ping + Jitter — initially on top, animate DOWN after ping test -->
  <div id="pjRow" class="pj-row">
    <div class="pj-inner">
      <div class="pj-card pj-ping" id="pj-ping">
        <div class="pj-top">
          <span class="pj-label">📡 פינג</span>
          <div class="pj-val-group"><span class="pj-value" id="val-ping" aria-live="polite">—</span><span class="pj-unit">ms</span></div>
        </div>
        <div class="pj-track"><div class="pj-fill" id="bar-ping"></div></div>
      </div>
      <div class="pj-card pj-jit" id="pj-jit">
        <div class="pj-top">
          <span class="pj-label">〰 ג'יטר</span>
          <div class="pj-val-group"><span class="pj-value" id="val-jit" aria-live="polite">—</span><span class="pj-unit">ms</span></div>
        </div>
        <div class="pj-track"><div class="pj-fill" id="bar-jit"></div></div>
      </div>
    </div>
  </div>

  <!-- DL + UL circular dials — initially below, animate UP after ping test -->
  <div id="dialsRow" class="dials-row">
    <div class="dials-inner">
      <div class="dial dial-dl" id="dial-dl">
        <div class="dial-label">⬇ הורדה</div>
        <div class="dial-ring">
          <canvas id="cv-dl"></canvas>
          <div class="dial-center" aria-live="polite">
            <span class="dial-value" id="val-dl">—</span>
            <span class="dial-unit">Mbps</span>
          </div>
        </div>
        <div class="dial-isp" id="isp-dl"></div>
      </div>
      <div class="dial dial-ul" id="dial-ul">
        <div class="dial-label">⬆ העלאה</div>
        <div class="dial-ring">
          <canvas id="cv-ul"></canvas>
          <div class="dial-center" aria-live="polite">
            <span class="dial-value" id="val-ul">—</span>
            <span class="dial-unit">Mbps</span>
          </div>
        </div>
        <div class="dial-isp" id="isp-ul"></div>
      </div>
    </div>
  </div>

</main>

<div class="ip-strip" id="ipStrip">
  <div class="ip-badge">
    <span class="ip-dot"></span>
    <span id="ipText"></span>
    <span class="ipv6-tag" id="ipv6Tag" style="display:none">IPv6</span>
  </div>
</div>

<div class="quality-banner" id="qualityBanner">
  <div class="quality-inner" id="qualityInner">
    <div class="quality-emoji" id="qEmoji"></div>
    <div class="quality-text"><div class="quality-title" id="qTitle"></div><div class="quality-sub" id="qSub"></div></div>
    <div class="quality-stars" id="qStars"></div>
  </div>
</div>

<div class="controls">
  <button class="btn-main" id="btnMain" onclick="startStop()">
    <span id="btnIcon">▶</span><span id="btnLabel">התחל בדיקה</span>
  </button>
  <div class="progress-track" id="progressTrack"><div class="progress-fill" id="progressFill"></div></div>
</div>

<div class="results-row" id="resultsRow">
  <button class="btn-secondary" onclick="saveImage()">⬇ שמור תמונה</button>
  <button class="btn-secondary btn-share" onclick="doShare()">📤 שתף</button>
</div>

<section class="history-section" id="historySection">
  <div class="history-header">
    <span class="history-title">📋 היסטוריית בדיקות</span>
    <button class="history-clear" onclick="clearHistory()">נקה</button>
  </div>
  <div class="history-chart-wrap">
    <canvas id="historyChart" class="history-chart"></canvas>
  </div>
  <div class="history-legend">
    <span class="legend-item"><span class="legend-dot" style="background:var(--cyan)"></span>הורדה (Mbps)</span>
    <span class="legend-item"><span class="legend-dot" style="background:var(--green)"></span>העלאה (Mbps)</span>
  </div>
</section>

<?php require_once 'inc/footer.php'; ?>

<script>
'use strict';

/* ══════════════════════════════════════════════════════════════════
   GAUGE DRAWING
   270° arc. startAngle = canvas 135° = 7:30 on clock face.
   Fill: clockwise 7:30 → 9:00 → 12:00 → 3:00 → 4:30 (speedometer).
   Gap centered at 6:00 (bottom). No bottom-to-top weirdness.
   ══════════════════════════════════════════════════════════════════ */
var ARC_S = Math.PI * 0.75;   // 135° canvas = 7:30 clock
var ARC_E = Math.PI * 1.5;    // 270° sweep

var DARK_P  = { dl:{arc:'#00d4ff',glow:'rgba(0,212,255,.6)',track:'#07293a'}, ul:{arc:'#00e676',glow:'rgba(0,230,118,.6)',track:'#072a14'} };
var LIGHT_P = { dl:{arc:'#0092b8',glow:'rgba(0,146,184,.5)',track:'#c2e6f0'}, ul:{arc:'#00934a',glow:'rgba(0,147,74,.5)',track:'#bce8d0'} };

function pal(k){ return document.documentElement.getAttribute('data-theme')==='light' ? LIGHT_P[k] : DARK_P[k]; }

function drawDial(cv, frac, k) {
  var p=pal(k), dpr=window.devicePixelRatio||1, sz=cv.clientWidth; if(!sz)return;
  if(cv.width!==sz*dpr) cv.width=cv.height=sz*dpr;
  var ctx=cv.getContext('2d'); ctx.clearRect(0,0,cv.width,cv.height);
  var cx=cv.width/2, cy=cv.height/2, r=cx*.76, lw=cx*.135;
  ctx.beginPath(); ctx.arc(cx,cy,r,ARC_S,ARC_S+ARC_E);
  ctx.strokeStyle=p.track; ctx.lineWidth=lw; ctx.lineCap='round'; ctx.stroke();
  if(frac<.008)return;
  var end=ARC_S+frac*ARC_E;
  ctx.beginPath(); ctx.arc(cx,cy,r,ARC_S,end);
  ctx.strokeStyle=p.arc; ctx.lineWidth=lw; ctx.lineCap='round';
  ctx.shadowColor=p.glow; ctx.shadowBlur=18*dpr; ctx.stroke(); ctx.shadowBlur=0;
  ctx.beginPath(); ctx.arc(cx+Math.cos(end)*r, cy+Math.sin(end)*r, lw*.5, 0, Math.PI*2);
  ctx.fillStyle=p.arc; ctx.shadowColor=p.glow; ctx.shadowBlur=14*dpr; ctx.fill(); ctx.shadowBlur=0;
}

function mbpsFrac(v){ return 1-1/Math.pow(1.3,  Math.sqrt(+v||0)); }
function msFrac(v)  { return 1-1/Math.pow(1.08, Math.sqrt(+v||0)); }

/* ══════════════════════════════════════════════════════════════════
   FLIP ANIMATION — swaps pjRow and dialsRow in the DOM using the
   FLIP technique: First, Last, Invert, Play.
   Works at any screen width because it uses measured pixel offsets.
   ══════════════════════════════════════════════════════════════════ */
var swapDone = false;

function doFLIPSwap() {
  var pjRow   = document.getElementById('pjRow');
  var dialRow = document.getElementById('dialsRow');
  if (!pjRow || !dialRow || swapDone) return;
  swapDone = true;

  // FIRST — record current viewport positions
  var pjF   = pjRow.getBoundingClientRect();
  var dialF = dialRow.getBoundingClientRect();

  // LAST — swap in DOM (dialRow goes before pjRow)
  pjRow.parentNode.insertBefore(dialRow, pjRow);

  // Measure new positions
  var pjL   = pjRow.getBoundingClientRect();
  var dialL = dialRow.getBoundingClientRect();

  // INVERT — push elements back to where they visually were
  var pjDY   = pjF.top   - pjL.top;
  var dialDY = dialF.top - dialL.top;

  pjRow.style.transform   = 'translateY(' + pjDY   + 'px)';
  dialRow.style.transform = 'translateY(' + dialDY + 'px)';

  // Force a reflow so the browser registers the transforms before transitioning
  pjRow.getBoundingClientRect();

  // PLAY — add transitions and animate to natural position (transform: none)
  var ease = 'cubic-bezier(0.34, 1.56, 0.64, 1)';
  pjRow.style.transition   = 'transform 0.7s ' + ease;
  dialRow.style.transition = 'transform 0.7s ' + ease;

  requestAnimationFrame(function() {
    pjRow.style.transform   = 'translateY(0)';
    dialRow.style.transform = 'translateY(0)';

    // Clean up after animation completes
    setTimeout(function() {
      pjRow.style.transition   = '';
      dialRow.style.transition = '';
    }, 720);
  });
}

function undoSwap() {
  // Called on reset — if already swapped, put pjRow back on top without animation
  var pjRow   = document.getElementById('pjRow');
  var dialRow = document.getElementById('dialsRow');
  if (!pjRow || !dialRow) return;
  // If dialsRow is currently first (after swap), put pjRow before it
  if (pjRow.previousElementSibling === dialRow) {
    pjRow.parentNode.insertBefore(pjRow, dialRow);
  }
  pjRow.style.transform = pjRow.style.transition = '';
  dialRow.style.transform = dialRow.style.transition = '';
  swapDone = false;
}

/* ══════════════════════════════════════════════════════════════════
   ISP BENCHMARKS (Israel 2025)
   ══════════════════════════════════════════════════════════════════ */
var ISP_DB = [
  // ======================
  // ספקי אינטרנט קווי (בית)
  // ======================
  { keys:['bezeq','בזק'],               nameHe:'בזק',        dl:200,  ul:60  },
  { keys:['hot','הוט'],                 nameHe:'הוט',        dl:220,  ul:70  },
  { keys:['partner','פרטנר'],           nameHe:'פרטנר',      dl:300,  ul:120 },
  { keys:['cellcom','סלקום'],           nameHe:'סלקום',      dl:280,  ul:110 },
  { keys:['unlimited','IBC','סיבים'],   nameHe:'Unlimited (IBC)', dl:500, ul:300 },

  { keys:['012','012 smile'],           nameHe:'012',        dl:180,  ul:70  },
  { keys:['013','netvision'],           nameHe:'013 Netvision', dl:200, ul:80  },
  { keys:['xfone','אקספון'],            nameHe:'Xfone',      dl:150,  ul:60  },
  { keys:['019'],                       nameHe:'019',        dl:160,  ul:70  },
  { keys:['rimon','רימון'],             nameHe:'רימון',      dl:120,  ul:50  },

  // ======================
  // ספקי סלולר (מובייל)
  // ======================
  { keys:['pelephone','פלאפון'],        nameHe:'פלאפון',     dl:75,   ul:15  },
  { keys:['partner mobile','פרטנר מובייל'], nameHe:'פרטנר מובייל', dl:70, ul:15 },
  { keys:['cellcom mobile','סלקום מובייל'], nameHe:'סלקום מובייל', dl:65, ul:12 },
  { keys:['hot mobile','הוט מובייל'],   nameHe:'HOT מובייל', dl:80,   ul:20  },
  { keys:['golan','גולן טלקום'],        nameHe:'גולן טלקום', dl:60,   ul:10  }
];

var currentIsp = null;

function detectIsp(str) {
  var low=(str||'').toLowerCase();
  for(var i=0;i<ISP_DB.length;i++){
    var e=ISP_DB[i];
    for(var j=0;j<e.keys.length;j++){ if(low.indexOf(e.keys[j])!==-1) return e; }
  }
  return null;
}

function renderIspCmp(key, val) {
  var lineEl=document.getElementById('isp-'+key);
  if(!lineEl||!currentIsp||!val||isNaN(+val))return;
  var bench=key==='dl'?currentIsp.dl:key==='ul'?currentIsp.ul:null;
  if(!bench)return;
  var pct=Math.round(+val/bench*100);
  lineEl.textContent=pct+'% מממוצע '+currentIsp.nameHe;
  lineEl.className='dial-isp '+(pct>=90?'good':pct>=60?'warn':'poor');
}

/* ══════════════════════════════════════════════════════════════════
   QUALITY GRADES
   ══════════════════════════════════════════════════════════════════ */
var GRADES=[
  {emoji:'❌',title:'חיבור חלש מאוד', sub:'לא מתאים לגלישה רגילה',              col:'#ff5252',rgb:'255,82,82',  stars:1},
  {emoji:'⚠️',title:'גלישה בסיסית',  sub:'מתאים להודעות ואימייל בלבד',          col:'#ff9800',rgb:'255,152,0', stars:2},
  {emoji:'👍',title:'מהירות תקינה',  sub:'מתאים לסטרימינג HD',                   col:'#ffb300',rgb:'255,179,0', stars:3},
  {emoji:'✅',title:'מהירות טובה',   sub:'מתאים לסטרימינג 4K ולמשחקים אונליין', col:'#00e676',rgb:'0,230,118', stars:4},
  {emoji:'🚀',title:'מהירות מעולה', sub:'מושלם לכל שימוש: 4K, גיימינג ועבודה', col:'#00d4ff',rgb:'0,212,255', stars:5},
];

function scoreOf(dl,ul,ping){
  var d=+dl||0,u=+ul||0,p=+ping||999;
  var ds=d>200?4:d>80?3:d>25?2:d>5?1:0;
  var us=u>50?4:u>20?3:u>5?2:u>1?1:0;
  var ps=p<20?4:p<50?3:p<100?2:p<200?1:0;
  return GRADES[Math.max(0,Math.min(4,Math.round((ds*2+us+ps)/4)))];
}

function showQuality(g){
  var inner=document.getElementById('qualityInner');
  inner.style.setProperty('--q-col',g.col); inner.style.setProperty('--q-rgb',g.rgb);
  document.getElementById('qEmoji').textContent=g.emoji;
  document.getElementById('qTitle').textContent=g.title;
  document.getElementById('qSub').textContent=g.sub;
  var stars=document.getElementById('qStars'); stars.innerHTML='';
  for(var i=1;i<=5;i++){ var d=document.createElement('div'); d.className='quality-star'+(i<=g.stars?' lit':''); stars.appendChild(d); }
  document.getElementById('qualityBanner').classList.add('show');
}

/* ══════════════════════════════════════════════════════════════════
   CONFETTI
   ══════════════════════════════════════════════════════════════════ */
var CCOLS=['#00d4ff','#00e676','#ffb300','#ff5252','#b388ff','#fff'];
function confetti(){
  var cv=document.getElementById('confetti-canvas');
  cv.width=window.innerWidth; cv.height=window.innerHeight; cv.style.display='block';
  var ps=[]; for(var i=0;i<120;i++) ps.push({x:Math.random()*cv.width,y:-10-Math.random()*200,vx:(Math.random()-.5)*3,vy:2+Math.random()*3,rot:Math.random()*Math.PI*2,vr:(Math.random()-.5)*.15,w:6+Math.random()*6,h:3+Math.random()*4,color:CCOLS[Math.floor(Math.random()*CCOLS.length)],op:.9});
  var ctx=cv.getContext('2d'),raf;
  (function tick(){ ctx.clearRect(0,0,cv.width,cv.height); var alive=false; ps.forEach(function(p){ if(p.y>cv.height+20)return; alive=true; p.x+=p.vx;p.y+=p.vy;p.vy+=.04;p.rot+=p.vr; if(p.y>cv.height*.6)p.op-=.012; ctx.save();ctx.translate(p.x,p.y);ctx.rotate(p.rot);ctx.globalAlpha=Math.max(0,p.op);ctx.fillStyle=p.color;ctx.fillRect(-p.w/2,-p.h/2,p.w,p.h);ctx.restore(); }); if(alive)raf=requestAnimationFrame(tick);else cv.style.display='none'; })();
  setTimeout(function(){ cancelAnimationFrame(raf); cv.style.display='none'; },4000);
}

/* ══════════════════════════════════════════════════════════════════
   DOM HELPERS
   ══════════════════════════════════════════════════════════════════ */
function el(id){return document.getElementById(id);}
function set(id,v){var e=el(id);if(e)e.textContent=v;}
function cls(id,c,on){var e=el(id);if(e)e.classList[on?'add':'remove'](c);}

/* ══════════════════════════════════════════════════════════════════
   WORKER STATE
   ══════════════════════════════════════════════════════════════════ */
var worker=null, data=null, prevState=-1;

function reset(){
  undoSwap();
  ['dl','ul'].forEach(function(k){ set('val-'+k,'—'); el('dial-'+k).classList.remove('has-val','active'); el('isp-'+k).textContent=''; el('isp-'+k).className='dial-isp'; drawDial(el('cv-'+k),0,k); });
  ['ping','jit'].forEach(function(k){ set('val-'+k,'—'); el('pj-'+k).classList.remove('has-val','active'); el('bar-'+k).style.width='0%'; });
  el('progressFill').style.width='0%';
  cls('progressTrack','show',false); cls('ipStrip','show',false);
  cls('qualityBanner','show',false); cls('resultsRow','show',false);
  prevState=-1;
}

function updateUI(forced){
  if(!forced&&(!data||!worker))return;
  var s=data.testState;

  // ── Trigger FLIP when ping test (state 2) ends and download (state 1) begins ──
  if(prevState===2 && s===1 && !swapDone){
    requestAnimationFrame(doFLIPSwap);
  }
  prevState=s;

  // IP
  if(data.clientIp&&!el('ipStrip').classList.contains('show')){
    set('ipText',data.clientIp);
    var rawIp=data.clientIp.split(' ')[0];
    var isV6=rawIp.indexOf(':')!==-1&&rawIp.indexOf('.')===-1;
    el('ipv6Tag').style.display=isV6?'':'none';
    cls('ipStrip','show',true);
    if(!currentIsp) currentIsp=detectIsp(data.clientIp);
  }

  // Progress
  var prog=(parseFloat(data.dlProgress||0)+parseFloat(data.ulProgress||0)+parseFloat(data.pingProgress||0))/3;
  el('progressFill').style.width=(prog*100).toFixed(1)+'%';

  // Active highlights
  ['dl','ul'].forEach(function(k){el('dial-'+k).classList.remove('active');});
  ['ping','jit'].forEach(function(k){el('pj-'+k).classList.remove('active');});
  if(s===1){el('dial-dl').classList.add('active');}
  if(s===3){el('dial-ul').classList.add('active');}
  if(s===2){el('pj-ping').classList.add('active');el('pj-jit').classList.add('active');}

  // DL
  var dv=data.dlStatus; set('val-dl',(s===1&&+dv===0)?'...':dv||'—');
  if(dv&&+dv>0){el('dial-dl').classList.add('has-val');if(s===4)renderIspCmp('dl',dv);}
  drawDial(el('cv-dl'),mbpsFrac(dv),'dl');

  // UL
  var uv=data.ulStatus; set('val-ul',(s===3&&+uv===0)?'...':uv||'—');
  if(uv&&+uv>0){el('dial-ul').classList.add('has-val');if(s===4)renderIspCmp('ul',uv);}
  drawDial(el('cv-ul'),mbpsFrac(uv),'ul');

  // Ping / Jitter
  var pv=data.pingStatus; set('val-ping',pv||'—');
  if(pv&&+pv>0) el('pj-ping').classList.add('has-val');
  el('bar-ping').style.width=(msFrac(pv)*100).toFixed(1)+'%';

  var jv=data.jitterStatus; set('val-jit',jv||'—');
  if(jv&&+jv>0) el('pj-jit').classList.add('has-val');
  el('bar-jit').style.width=(msFrac(jv)*100).toFixed(1)+'%';
}

/* ══════════════════════════════════════════════════════════════════
   START / STOP
   ══════════════════════════════════════════════════════════════════ */
function startStop(){
  if(worker!==null){
    worker.postMessage('abort'); worker=null; data=null;
    el('btnMain').classList.remove('running');
    set('btnIcon','▶'); set('btnLabel','התחל בדיקה');
    cls('progressTrack','show',false); reset(); return;
  }
  currentIsp=null; reset();
  el('btnMain').classList.add('running');
  set('btnIcon','■'); set('btnLabel','עצור');
  cls('progressTrack','show',true);

  var host=(window.selectedServer&&window.selectedServer.host)||'';
  var cfg={
    url_telemetry: '../data/data.php',
    test_order:    'IP_P_D_U'      // Ping first → then Download → then Upload
  };
  if(host){
    cfg.url_dl=host+'/netspeed/garbage.php';
    cfg.url_ul=host+'/netspeed/empty.php';
    cfg.url_ping=host+'/netspeed/empty.php';
    cfg.url_getIp=host+'/netspeed/getIP.php';
  }
  // When no external host, worker defaults (garbage.php etc.) resolve correctly
  // from the worker's own directory (netspeed/) — no path passed = no 404.

  worker=new Worker('netspeed/speedtest_worker.min.js');
  worker.postMessage('start '+JSON.stringify(cfg));
  worker.onmessage=function(e){
    data=JSON.parse(e.data);
    if(data.testState>=4){
      updateUI(true); worker=null;
      el('btnMain').classList.remove('running');
      set('btnIcon','↺'); set('btnLabel','בדוק שוב');
      el('progressFill').style.width='100%';
      setTimeout(onDone,350);
    }
  };
}

function onDone(){
  if(!data)return;
  var g=scoreOf(data.dlStatus,data.ulStatus,data.pingStatus);
  showQuality(g);
  if(g.stars>=4) confetti();
  saveHistory({dl:data.dlStatus,ul:data.ulStatus,ping:data.pingStatus,jit:data.jitterStatus,ts:Date.now()});
  renderHistoryGraph();
  cls('resultsRow','show',true);
}

/* Render loop + poll */
setInterval(function(){if(worker)worker.postMessage('status');},200);
(function loop(){requestAnimationFrame(loop);updateUI();}());
window.addEventListener('load',function(){ setTimeout(function(){ drawDial(el('cv-dl'),0,'dl'); drawDial(el('cv-ul'),0,'ul'); renderHistoryGraph(); },80); });

// Redraw dials on theme toggle
new MutationObserver(function(){
  drawDial(el('cv-dl'),data?mbpsFrac(data.dlStatus):0,'dl');
  drawDial(el('cv-ul'),data?mbpsFrac(data.ulStatus):0,'ul');
  renderHistoryGraph();
}).observe(document.documentElement,{attributes:true,attributeFilter:['data-theme']});

/* ══════════════════════════════════════════════════════════════════
   HISTORY — localStorage + canvas line chart
   ══════════════════════════════════════════════════════════════════ */
var HIST_KEY='st-hist', HIST_MAX=10;
function loadHistory(){ try{return JSON.parse(localStorage.getItem(HIST_KEY))||[];}catch(e){return[];} }
function saveHistory(entry){ var h=loadHistory(); h.unshift(entry); if(h.length>HIST_MAX)h=h.slice(0,HIST_MAX); localStorage.setItem(HIST_KEY,JSON.stringify(h)); }
function clearHistory(){ localStorage.removeItem(HIST_KEY); renderHistoryGraph(); }

function renderHistoryGraph(){
  var h=loadHistory();
  var sec=el('historySection');
  if(!h.length){ cls('historySection','show',false); return; }
  cls('historySection','show',true);

  var cv=el('historyChart'); if(!cv)return;
  var dpr=window.devicePixelRatio||1;
  var W=cv.clientWidth||600, H=cv.clientHeight||130;
  cv.width=W*dpr; cv.height=H*dpr;
  var ctx=cv.getContext('2d'); ctx.scale(dpr,dpr);

  // Data oldest → newest (left → right)
  var pts=h.slice().reverse();
  var maxVal=0;
  pts.forEach(function(p){ maxVal=Math.max(maxVal,+p.dl||0,+p.ul||0); });
  if(!maxVal) maxVal=100;
  maxVal=Math.ceil(maxVal*1.15/10)*10;

  var pad={t:14,r:14,b:26,l:42};
  var cW=W-pad.l-pad.r, cH=H-pad.t-pad.b;
  var isDark=document.documentElement.getAttribute('data-theme')!=='light';
  var C={dl:isDark?'#00d4ff':'#0092b8', ul:isDark?'#00e676':'#00934a'};

  ctx.clearRect(0,0,W,H);

  // Gridlines + Y labels
  for(var g=0;g<=4;g++){
    var gy=pad.t+cH-(g/4)*cH;
    ctx.beginPath(); ctx.moveTo(pad.l,gy); ctx.lineTo(W-pad.r,gy);
    ctx.strokeStyle='rgba(128,160,200,'+(isDark?.07:.12)+')'; ctx.lineWidth=1; ctx.stroke();
    ctx.font='9px "JetBrains Mono",monospace'; ctx.fillStyle=isDark?'#354d68':'#8aa0bc';
    ctx.textAlign='right'; ctx.textBaseline='middle';
    ctx.fillText(Math.round(maxVal*g/4),pad.l-4,gy);
  }

  function xP(i){ return pts.length===1 ? pad.l+cW/2 : pad.l+(i/(pts.length-1))*cW; }
  function yP(v){ return pad.t+cH-(Math.min(+v||0,maxVal)/maxVal)*cH; }

  function drawLine(key, color){
    if(!pts.length)return;
    // Area fill
    ctx.beginPath(); ctx.moveTo(xP(0),yP(pts[0][key]));
    for(var i=1;i<pts.length;i++) ctx.lineTo(xP(i),yP(pts[i][key]));
    ctx.lineTo(xP(pts.length-1),pad.t+cH); ctx.lineTo(xP(0),pad.t+cH); ctx.closePath();
    var gr=ctx.createLinearGradient(0,pad.t,0,pad.t+cH);
    var rgb=key==='dl'?(isDark?'0,212,255':'0,146,184'):(isDark?'0,230,118':'0,147,74');
    gr.addColorStop(0,'rgba('+rgb+',.22)'); gr.addColorStop(1,'rgba('+rgb+',0)');
    ctx.fillStyle=gr; ctx.fill();
    // Line
    ctx.beginPath(); ctx.moveTo(xP(0),yP(pts[0][key]));
    for(var i=1;i<pts.length;i++) ctx.lineTo(xP(i),yP(pts[i][key]));
    ctx.strokeStyle=color; ctx.lineWidth=2; ctx.lineCap='round'; ctx.lineJoin='round';
    ctx.shadowColor=color+'88'; ctx.shadowBlur=6; ctx.stroke(); ctx.shadowBlur=0;
    // Dots
    for(var i=0;i<pts.length;i++){
      if(!pts[i][key]||+pts[i][key]===0)continue;
      ctx.beginPath(); ctx.arc(xP(i),yP(pts[i][key]),3.5,0,Math.PI*2);
      ctx.fillStyle=color; ctx.shadowColor=color+'aa'; ctx.shadowBlur=8; ctx.fill(); ctx.shadowBlur=0;
    }
  }

  drawLine('dl',C.dl);
  drawLine('ul',C.ul);

  // X axis date labels
  var step=pts.length>6?Math.ceil(pts.length/5):1;
  for(var i=0;i<pts.length;i+=step){
    var d=new Date(pts[i].ts);
    var lbl=(d.getDate()).toString().padStart(2,'0')+'/'+(d.getMonth()+1).toString().padStart(2,'0');
    ctx.font='8px "JetBrains Mono",monospace'; ctx.fillStyle=isDark?'#354d68':'#8aa0bc';
    ctx.textAlign='center'; ctx.textBaseline='top'; ctx.fillText(lbl,xP(i),H-pad.b+3);
  }
}

/* ══════════════════════════════════════════════════════════════════
   SAVE TO IMAGE (pure Canvas)
   ══════════════════════════════════════════════════════════════════ */
function rrect(ctx,x,y,w,h,r){ ctx.beginPath(); ctx.moveTo(x+r,y); ctx.lineTo(x+w-r,y); ctx.quadraticCurveTo(x+w,y,x+w,y+r); ctx.lineTo(x+w,y+h-r); ctx.quadraticCurveTo(x+w,y+h,x+w-r,y+h); ctx.lineTo(x+r,y+h); ctx.quadraticCurveTo(x,y+h,x,y+h-r); ctx.lineTo(x,y+r); ctx.quadraticCurveTo(x,y,x+r,y); ctx.closePath(); }

function saveImage(){
  if(!data)return;
  var W=860,H=440, cv=document.createElement('canvas');
  cv.width=W*2; cv.height=H*2;
  var ctx=cv.getContext('2d'); ctx.scale(2,2);
  var dl=data.dlStatus||'—', ul=data.ulStatus||'—', ping=data.pingStatus||'—', jit=data.jitterStatus||'—', ip=data.clientIp||'';

  var bg=ctx.createLinearGradient(0,0,W,H); bg.addColorStop(0,'#07090f'); bg.addColorStop(1,'#0c1428');
  ctx.fillStyle=bg; ctx.fillRect(0,0,W,H);
  ctx.strokeStyle='rgba(0,212,255,.04)'; ctx.lineWidth=1;
  for(var gx=0;gx<W;gx+=44){ctx.beginPath();ctx.moveTo(gx,0);ctx.lineTo(gx,H);ctx.stroke();}
  for(var gy=0;gy<H;gy+=44){ctx.beginPath();ctx.moveTo(0,gy);ctx.lineTo(W,gy);ctx.stroke();}
  var tg=ctx.createRadialGradient(W/2,-20,0,W/2,-20,260); tg.addColorStop(0,'rgba(0,212,255,.12)'); tg.addColorStop(1,'transparent'); ctx.fillStyle=tg; ctx.fillRect(0,0,W,H);
  rrect(ctx,16,16,W-32,H-32,18); ctx.strokeStyle='rgba(255,255,255,.09)'; ctx.lineWidth=1.5; ctx.stroke();

  // Logo
  var img=new Image(); img.src='assets/logo.png';
  // We'll draw text logo since image loading is async
  var lg=ctx.createLinearGradient(34,34,68,68); lg.addColorStop(0,'#00d4ff'); lg.addColorStop(1,'#005f80');
  rrect(ctx,34,34,36,36,8); ctx.fillStyle=lg; ctx.fill();
  ctx.font='17px monospace'; ctx.fillStyle='#001e2b'; ctx.textAlign='center'; ctx.textBaseline='middle'; ctx.fillText('⚡',52,52);
  ctx.font='bold 14px monospace'; ctx.fillStyle='#e8f2ff'; ctx.textAlign='left'; ctx.textBaseline='middle'; ctx.fillText('SpeedTest',80,52);
  var now=new Date(); ctx.font='10px monospace'; ctx.fillStyle='#354d68'; ctx.textAlign='right'; ctx.fillText(now.toLocaleDateString('he-IL')+' '+now.toLocaleTimeString('he-IL',{hour:'2-digit',minute:'2-digit'}),W-34,52);
  ctx.beginPath(); ctx.moveTo(34,86); ctx.lineTo(W-34,86); ctx.strokeStyle='rgba(255,255,255,.07)'; ctx.lineWidth=1; ctx.stroke();

  var metrics=[{label:'הורדה',val:dl,unit:'Mbps',col:'#00d4ff',glow:'rgba(0,212,255,.55)',x:W*.15,tf:mbpsFrac},{label:'העלאה',val:ul,unit:'Mbps',col:'#00e676',glow:'rgba(0,230,118,.55)',x:W*.38,tf:mbpsFrac},{label:'פינג',val:ping,unit:'ms',col:'#ffb300',glow:'rgba(255,179,0,.55)',x:W*.63,tf:msFrac},{label:"ג'יטר",val:jit,unit:'ms',col:'#ff5252',glow:'rgba(255,82,82,.55)',x:W*.85,tf:msFrac}];
  metrics.forEach(function(m){
    var cy=250,r=74,lw=12;
    ctx.beginPath(); ctx.arc(m.x,cy,r,ARC_S,ARC_S+ARC_E); ctx.strokeStyle='rgba(255,255,255,.07)'; ctx.lineWidth=lw; ctx.lineCap='round'; ctx.stroke();
    var frac=m.tf(m.val);
    if(frac>.01){ ctx.beginPath(); ctx.arc(m.x,cy,r,ARC_S,ARC_S+frac*ARC_E); ctx.strokeStyle=m.col; ctx.lineWidth=lw; ctx.lineCap='round'; ctx.shadowColor=m.glow; ctx.shadowBlur=14; ctx.stroke(); ctx.shadowBlur=0; }
    ctx.font='bold 28px "JetBrains Mono",monospace'; ctx.fillStyle=m.col; ctx.textAlign='center'; ctx.textBaseline='alphabetic'; ctx.shadowColor=m.glow; ctx.shadowBlur=12; ctx.fillText(m.val,m.x,cy+12); ctx.shadowBlur=0;
    ctx.font='bold 10px monospace'; ctx.fillStyle='#354d68'; ctx.fillText(m.unit,m.x,cy+27);
    ctx.font='12px Heebo,sans-serif'; ctx.fillStyle='#7a95b8'; ctx.fillText(m.label,m.x,cy-93);
  });

  var g=scoreOf(dl,ul,ping);
  ctx.font='13px Heebo,sans-serif'; ctx.fillStyle=g.col; ctx.textAlign='center'; ctx.textBaseline='middle'; ctx.shadowColor=g.col; ctx.shadowBlur=10; ctx.fillText(g.emoji+' '+g.title,W/2,H-54); ctx.shadowBlur=0;
  ctx.font='10px monospace'; ctx.fillStyle='#354d68'; ctx.fillText(ip,W/2,H-34);
  ctx.fillStyle='#1a2a3a'; ctx.fillText('speedtest.yehudae.net',W/2,H-18);

  var a=document.createElement('a'); a.download='speedtest-'+Date.now()+'.png'; a.href=cv.toDataURL('image/png'); a.click();
}

/* ══════════════════════════════════════════════════════════════════
   SHARE
   ══════════════════════════════════════════════════════════════════ */
function doShare(){
  if(!data)return;
  var t='🌐 תוצאות בדיקת מהירות האינטרנט שלי:\n\n'
    +'⬇ הורדה:  '+(data.dlStatus||'—')+' Mbps\n'
    +'⬆ העלאה:  '+(data.ulStatus||'—')+' Mbps\n'
    +'📡 פינג:   '+(data.pingStatus||'—')+' ms\n'
    +"〰 ג'יטר: "+(data.jitterStatus||'—')+' ms\n\n'
    +'בדוק גם אתה ← https://speedtest.yehudae.net';
  if(navigator.share) navigator.share({text:t}).catch(function(){});
  else window.open('https://wa.me/?text='+encodeURIComponent(t),'_blank');
}
</script>
</body>
</html>
