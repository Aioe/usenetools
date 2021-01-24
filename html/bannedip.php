<?php

$configfile = "/usr/system/news/checknnrp/config.php";

include("inc/head.php");
include($configfile);
include("../lib/aioe.php");
include("../lib/bannedip.php");

$output = add_navigation_bar('<a href="index.php"><i class="fas fa-home fa-3x"></i></a>');

if ((!isset($_GET["ip"])) or (!isset($_GET["action"]))) {
	$output .= show_bandb_data($config);
	echo $output;
	include("inc/tail.php");
	exit(0);
}

$ip = check_valid_ip($_GET["ip"]);
if (!$ip) error_and_exit("Invalid parameter $ip for IP ADDRESS, aborting");

$command = $_GET["action"];

if ($command == "delete") {
	delete_ip($config, $ip);
	header("Location: bannedip.php");
}

if ($command == "redo") {
	redo_ban($config, $ip, 0);
	header("Location: bannedip.php");
}

if ($command == "forever") {
        redo_ban($config, $ip, 1);
	header("Location: bannedip.php");
}

if ($command == "onemonth") {
        redo_ban($config, $ip, 2);
	header("Location: bannedip.php");
}

if ($command == "oneday") {
        redo_ban($config, $ip, 3);
	header("Location: bannedip.php");
}

?>
