<?php require_once __DIR__ . '/../config.php'; ?>
<div class="nav-outer">
  <nav class="nav">
    <a href="./" class="nav-logo" aria-label="SpeedTest — דף הבית">
      <img src="assets/logo.png" width="32" height="32" alt="" class="nav-logo-img" aria-hidden="true">
      SpeedTest
    </a>
    <div class="nav-right">
      <div class="nav-links" id="navLinks">
        <a href="./">בדיקת מהירות</a>
        <a href="mailto:<?= SITE_EMAIL ?>?subject=SpeedTest — פנייה" class="nav-btn-contact">✉ צור קשר</a>
      </div>
      <button class="theme-toggle" id="themeToggle" title="החלף ערכת נושא" aria-label="Toggle theme">🌙</button>
      <button class="nav-hamburger" id="navHamburger" aria-label="תפריט"><span></span><span></span><span></span></button>
    </div>
  </nav>

  <div class="server-bar" role="group" aria-label="בחירת שרת בדיקה">
    <span class="server-bar-label">שרת:</span>
    <div class="server-chips" id="serverChips">
      <?php foreach ($cdn_servers as $s): ?>
      <button class="server-chip<?= $s['local'] ? ' active' : '' ?>"
              data-host="<?= htmlspecialchars($s['host']) ?>"
              data-id="<?= htmlspecialchars($s['id']) ?>"
              aria-pressed="<?= $s['local'] ? 'true' : 'false' ?>">
        <?= htmlspecialchars($s['label']) ?>
      </button>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<script>
/* Theme */
(function(){
  var btn=document.getElementById('themeToggle');
  function apply(t){ document.documentElement.setAttribute('data-theme',t); btn.textContent=t==='dark'?'🌙':'☀️'; localStorage.setItem('st-theme',t); }
  apply(localStorage.getItem('st-theme')||'dark');
  btn.addEventListener('click',function(){ apply(document.documentElement.getAttribute('data-theme')==='dark'?'light':'dark'); });
}());

/* Hamburger */
(function(){
  var btn=document.getElementById('navHamburger'), nav=document.getElementById('navLinks');
  if(!btn)return;
  btn.addEventListener('click',function(){ var o=nav.classList.toggle('open'); btn.classList.toggle('open',o); });
  nav.querySelectorAll('a').forEach(function(a){ a.addEventListener('click',function(){ nav.classList.remove('open'); btn.classList.remove('open'); }); });
}());

/* CDN selection */
window.selectedServer={host:'',id:'il'};
document.querySelectorAll('.server-chip').forEach(function(c){
  c.addEventListener('click',function(){
    document.querySelectorAll('.server-chip').forEach(function(x){ x.classList.remove('active'); x.setAttribute('aria-pressed','false'); });
    c.classList.add('active'); c.setAttribute('aria-pressed','true');
    window.selectedServer={host:c.dataset.host,id:c.dataset.id};
  });
});
</script>
