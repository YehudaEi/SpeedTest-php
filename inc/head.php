<?php require_once __DIR__ . '/../config.php'; ?>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="theme-color" content="#07090f">

  <title>בדיקת מהירות אינטרנט | SpeedTest — הורדה, העלאה, פינג</title>
  <meta name="description"
        content="בדוק את מהירות האינטרנט שלך בחינם — מהירות הורדה, העלאה, פינג וג'יטר. כלי מדידה מהיר, מדויק ופשוט לשימוש.">
  <meta name="keywords"
        content="בדיקת מהירות אינטרנט, מהירות אינטרנט, speedtest, בדיקת פינג, מהירות הורדה, בדיקת רשת">
  <meta name="author"   content="Yehuda">
  <link rel="canonical" href="<?= SITE_URL ?>/">

  <meta property="og:type"        content="website">
  <meta property="og:url"         content="<?= SITE_URL ?>/">
  <meta property="og:title"       content="בדיקת מהירות אינטרנט | SpeedTest">
  <meta property="og:description" content="בדוק את מהירות האינטרנט שלך — הורדה, העלאה, פינג וג'יטר.">
  <meta property="og:image"       content="<?= SITE_URL ?>/assets/logo.png">
  <meta property="og:locale"      content="he_IL">

  <meta name="twitter:card"        content="summary">
  <meta name="twitter:title"       content="בדיקת מהירות אינטרנט | SpeedTest">
  <meta name="twitter:image"       content="<?= SITE_URL ?>/assets/logo.png">

  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "WebApplication",
    "name": "SpeedTest",
    "url": "<?= SITE_URL ?>/",
    "description": "בדיקת מהירות אינטרנט — הורדה, העלאה, פינג וג'יטר",
    "applicationCategory": "UtilityApplication",
    "operatingSystem": "Any",
    "isAccessibleForFree": true,
    "offers": { "@type": "Offer", "price": "0", "priceCurrency": "ILS" },
    "creator": { "@type": "Person", "name": "Yehuda", "email": "<?= SITE_EMAIL ?>" }
  }
  </script>

  <link rel="icon"             type="image/png" href="assets/logo.png">
  <link rel="apple-touch-icon"                  href="assets/logo.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="css/style.css">

  <script>
    (function(){ var t=localStorage.getItem('st-theme')||'dark'; document.documentElement.setAttribute('data-theme',t); }());
  </script>
</head>
