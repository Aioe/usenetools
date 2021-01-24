<?php

$host = "127.0.0.1";
$newshost = "newsfeed.aioe.org";
$port = 119;
$sendnocem = 1;
$configfile = "/usr/system/web/html/etc/cancelbot.conf";

include("../lib/aioe.php");
include("../lib/nntp.php");
include("../lib/cancel.php");
include("../lib/cancellogic.php");

if (isset($_POST["action"])) {

	$action = $_POST["action"];

	if ($action == "cancelbymessageid") {
		$mid = check_messageid("messageid");
		$success = cancel_by_messageid($mid, $host, $newshost, $sendnocem, $configfile);
	} elseif ($action == "cancelbyrange") {
		if ((!isset($_POST["rangegroup"])) or (strlen($_POST["rangegroup"]) < 3)) error_and_exit("A group is mandatory");
		$group = $_POST["rangegroup"];
		if ((!isset($_POST["rangestart"])) or (!is_numeric($_POST["rangestart"]))) error_and_exit("A start number is mandatory");
		$start = $_POST["rangestart"];
		if ((!isset($_POST["rangeend"])) or (!is_numeric($_POST["rangeend"]))) error_and_exit("An end number is mandatory");
                $end = $_POST["rangeend"];
		$success = cancel_by_range($group, $start, $end, $host, $newshost, $sendnocem, $configfile);
	} elseif ($action == "cancelbythread") {
		$mid = check_messageid("threadid");
		$success = cancel_by_header($mid, "References", $host, $newshost, $sendnocem, $configfile);
	} elseif ($action == "cancelbysender") {
		$mid = check_messageid("senderid");
                $success = cancel_by_header($mid, "From", $host, $newshost, $sendnocem, $configfile);
	} elseif ($action == "cancelbyheader")  {
		if ((!isset($_POST["headergroup"])) or (strlen($_POST["headergroup"]) < 3)) error_and_exit("A group is required");
		if (!isset($_POST["headername"])) error_and_exit("An header is required");
		if (!isset($_POST["headervalue"])) error_and_exit("An header value is mandatory");

		$group   = $_POST["headergroup"];
		$header  = $_POST["headername"];
		$value   = $_POST["headervalue"];
		$success = cancel_by_value($group, $header, $value, $host, $newshost, $sendnocem, $configfile);
	} elseif ($action == "cancelbybody") {
		if ((!isset($_POST["bodygroup"])) or (strlen($_POST["bodygroup"]) < 3)) error_and_exit("A group is required");
		if ((!isset($_POST["bodyvalue"])) or (strlen($_POST["bodyvalue"]) < 3)) error_and_exit("A pattern is required");
		$group = $_POST["bodygroup"];
		$pattern = $_POST["bodyvalue"];
		$success = cancel_by_body($group, $pattern, $host, $newshost, $sendnocem, $configfile);
	}

}

if (!isset($_POST["action"])) {
	include("inc/head.php");
	echo form_begin("post");
	include("inc/masscancel.html");
	echo form_close();
	include("inc/tail.php");
}


?>
