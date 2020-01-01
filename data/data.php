<?php
include_once('settings.php');
require 'idObfuscation.php';

$ip = ($_SERVER['REMOTE_ADDR'] ?? "");
$ispinfo = ($_POST["ispinfo"] ?? "");
$extra = ($_POST["extra"] ?? "");
$ua = ($_SERVER['HTTP_USER_AGENT'] ?? "");
$lang=""; if(isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) $lang = ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? "");
$dl = ($_POST["dl"] ?? "");
$ul = ($_POST["ul"] ?? "");
$ping = ($_POST["ping"] ?? "");
$jitter = ($_POST["jitter"] ?? "");
$log = ($_POST["log"] ?? "");

if($db_type=="sqlite"){
    $conn = new PDO("sqlite:$Sqlite_db_file") or die("1");
    $conn->exec("
        CREATE TABLE IF NOT EXISTS `speedtest_users` (
        `id`    INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
		`ispinfo`    text,
		`extra`    text,
        `timestamp`     timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `ip`    text NOT NULL,
        `ua`    text NOT NULL,
        `lang`  text NOT NULL,
        `dl`    text,
        `ul`    text,
        `ping`  text,
        `jitter`        text,
        `log`   longtext
        );
    ");
    $stmt = $conn->prepare("INSERT INTO speedtest_users (ip,ispinfo,extra,ua,lang,dl,ul,ping,jitter,log) VALUES (?,?,?,?,?,?,?,?,?,?)") or die("2");
    $stmt->execute(array($ip,$ispinfo,$extra,$ua,$lang,$dl,$ul,$ping,$jitter,$log)) or die("3");
	$id=$conn->lastInsertId();
	echo "id ".($enable_id_obfuscation?obfuscateId($id):$id);
    $conn = null;
}
else die("-1");
?>
