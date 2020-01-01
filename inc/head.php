<head>
	<?php require_once ('config.php')?>
	<meta charset="UTF-8">
	<title><?php echo $site_name ;?></title>
	<meta name="viewport"				content="width=device-width,initial-scale=1,maximum-scale=1">	
	<meta name="description"			content="<?php echo "$site_descr"; ?>" />
	<meta property="og:type"			content="article" />
	<meta property="og:title"			content="<?php echo "$site_name"; ?>" />
	<meta property="og:description"		content="<?php echo "$site_descr"; ?>" />
	<meta property="og:image"			content="assets/fbbanner.png" />
	<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.2/css/all.css" >	
	<link rel="icon" type="image/png" href="assets/logo-w.png" />
	<link rel="stylesheet" href="css/style.css"> 	
</head>
<div class="intro">
	<a href="./"><img width="120px" src="assets/logo-b.png" /></a>
	<h1><?php echo $site_name; ?></h1>
	<p><?php echo "$site_descr"; ?></p>
</div>