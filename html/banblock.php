<?php


$configfile = "/usr/system/web/nnrpcheck/config.php";

include("inc/head.php");
include($configfile);
include("../lib/aioe.php");
include("../lib/checknnrp.php");

if (!isset($_POST["command"])) {
	$output = show_banblock_form($config);
	echo $output;
	include("inc/tail.php");
	exit(0);
}

if (isset($_POST["command"])) {
	$command = $_POST["command"];

	if ($command == "Back") header("Location: index.php");
	elseif ($command == "Clear") header("Location: banblock.php");

	if ($command == "Update") {
		$newfile = generate_new_configuration_file($config);
		if(!file_put_contents($configfile, $newfile)) {
			echo "Unable to save $configfile, aborting";
			exit(5);
		}
		header("Location: index.php");
	}
}




?>

