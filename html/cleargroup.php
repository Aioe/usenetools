<?php

$host = "127.0.0.1";
$newshost = "newsfeed.aioe.org";
$banlist = "/usr/system/news/cancelbot/banlist.conf";
$sendnocem = 1;
$configfile = "/usr/system/web/html/etc/cancelbot.conf";

include("../lib/nntp.php");
include("../lib/aioe.php");
include("../lib/cancel.php");
include("../lib/cancellogic.php");

if (isset($_GET["action"])) {
	$action = $_GET["action"];

	if ($action != "submit") {
        	if (!isset($_GET["group"])) error_and_exit("Group is not set");
        	$group = $_GET["group"];

        	if ((!isset($_GET["number"]))||(!is_numeric($_GET["number"]))) error_and_exit("Article number isn't set");
       		$number = $_GET["number"];
	} 
	if ($action == "delete") { 
	
		// STEP 1: Connect with reader server
		$nnrp = connect_nntp($host, 119, 1);
		if (!$nnrp) error_and_exit("Unable to connect $host!"); 

		// STEP 2: Select group
		$last = get_GROUP($nnrp, $group);
		if (!$last) error_and_exit("Unable to read group: '$group'");

		// STEP 3: Scarica il Message-ID
		$mid = get_HDR($nnrp, "Message-ID", $number);
		if (!$mid) error_and_exit("Unable to fetch Message-ID (HDR1)");

		// STEP 4: Cancella il messaggio
		$success = cancel_by_messageid($mid, $host, $newshost, $sendnocem, $configfile); 
		exit(0);

	} elseif (($action == "banfromsingle") or ($action == "banfromall")) {
		// STEP 1: Connect with reader server
                $nnrp = connect_nntp($host, 119, 1);
                if (!$nnrp) error_and_exit("Unable to connect $host!");

		// STEP 2: Select group
                $last = get_GROUP($nnrp, $group);
                if (!$last) error_and_exit("Unable to read group: '$group'");


		// STEP 3: Read overview
		$xover = get_OVER($nnrp, $number);
		if (!$xover) error_and_exit("Unable to get overview data for message $group:$number");

		// STEP 4: Ban user
		// if ban is made only for a group or else for all groups
		if ($action == "banfromsingle") $bangroup = $group;
		else $bangroup = "";

		$success = cancelbot_ban($banlist, "From", $xover["From"], $bangroup );

		// STEP 5: Summary page
		$status = array();
	
		if ($bangroup == "") $bangroup = "all groups";
		$status[] = "User: " . $xover["From"] . " banned on $bangroup";
		exit_page($status);
	
	} elseif ($action == "banthread") {
                // STEP 1: Connect with reader server
                $nnrp = connect_nntp($host, 119, 1);
                if (!$nnrp) error_and_exit("Unable to connect $host!");

                // STEP 2: Select group
                $last = get_GROUP($nnrp, $group);
                if (!$last) error_and_exit("Unable to read group: '$group'");

                // STEP 3: Read overview
                $xover = get_OVER($nnrp, $number);
                if (!$xover) error_and_exit("Unable to get overview data for message $group:$number");

                // STEP 4: Ban thread
		// Note ban must applied to all groups due crosspost
                $success = cancelbot_ban($banlist, "References", $xover["Mid"], "" );

                // STEP 5: Summary page
                $status = array();
                $status[] = "Thread started by: " . $xover["Mid"] . " is banned";

                exit_page($status);

	} elseif ($action == "deletethread") {

		// STEP 1: Connect with reader server
                $nnrp = connect_nntp($host, 119, 1);
                if (!$nnrp) error_and_exit("Unable to connect $host!"); 

                // STEP 2: Select group
                $last = get_GROUP($nnrp, $group);
                if (!$last) error_and_exit("Unable to read group: '$group'");

		// STEP 3: Scarica il Message-ID
                $mid = get_HDR($nnrp, "Message-ID", $number);
                if (!$mid) error_and_exit("Unable to fetch Message-ID (HDR1)");
		
		// STEP 4: Cancella il messaggio
		$success = cancel_by_header($mid, "References", $host, $newshost, $sendnocem, $configfile);
                quit_nntp($nnrp);
		exit(0);
	} elseif ($action == "deletesender") {
		// STEP 1: Connect with reader server
                $nnrp = connect_nntp($host, 119, 1);
                if (!$nnrp) error_and_exit("Unable to connect $host!");

		// STEP 2: Select group
                $last = get_GROUP($nnrp, $group);
                if (!$last) error_and_exit("Unable to read group: '$group'");

                // STEP 3: Scarica il From
                $mid = get_HDR($nnrp, "Message-ID", $number);
                if (!$mid) error_and_exit("Unable to fetch Message-ID ($group:$number)");

		// STEP 4: Cancella il messaggio
                $success = cancel_by_header($mid, "From", $host, $newshost, $sendnocem, $configfile);
                quit_nntp($nnrp);
		exit(0);
      	} elseif ($action == "submit") {


	} else error_and_exit("Unknown action $action!");
}


if (!isset($_GET["group"])) show_selection_group_form();

$group = $_GET["group"];

if ((!isset($_GET["number"])) or (!is_numeric($_GET["number"]))) $number = 0;
if (isset($_GET["number"])) {
	if (is_numeric($_GET["number"])) $number = $_GET["number"];
	else $number = 0;
}

$nnrp = connect_nntp($host, 119, 1);
if (!$nnrp) error_and_exit("Unable to connect $host!"); 
$last = get_GROUP($nnrp, $group);
if (!$last) error_and_exit("Unable to read group $group");

if ($number == 0) $number = $last;
if ($number > $last) $number = $last;

do {
	$xover = get_OVER($nnrp, $number);
	if (!$xover) $number--;
	if ($number == 0) error_and_exit("No more articles to read");
} while (!$xover);

$body = get_BODY($nnrp, $number, 2);
if (!$body) error_and_exit("Unable to download body ($number)");

$html_post = build_html_post($xover, $body, $group);

$following = calculate_article_sequence($nnrp, $number);
$next 		=  $following["next"];
$previous 	=  $following["prev"];

$html = build_article_page($html_post, $group, $number, $previous, $next);

include("inc/head.php");
echo $html;
include("inc/tail.php");
	quit_nntp($nnrp);


//////////////////////////////////


function build_article_page($article, $group, $number, $previous, $next) {
	$output = "";

	$pagename = basename($_SERVER['PHP_SELF']);

        $output .= build_toolbar("index.php", "fa-home");
        $output .= build_toolbar("cleargroup.php", "fa-sign-out-alt");

        $url = $pagename . '?group=' . $group . '&amp;number=' . $next;
        $output .= build_toolbar($url, "fa-chevron-circle-left");

        $url = $pagename . '?group=' . $group . '&amp;number=' . $previous;
        $output .= build_toolbar($url, "fa-chevron-circle-right");

        $url = $pagename . '?group=' . $group . '&amp;number=' . $number . '&amp;action=delete';
        $output .= build_toolbar($url, "fa-times-circle");

	$url = $pagename . '?group=' . $group . '&amp;number=' . $number . '&amp;action=deletethread';
        $output .= build_toolbar($url, "fa-bezier-curve");

	$url = $pagename . '?group=' . $group . '&amp;number=' . $number . '&amp;action=deletesender';
	$output .= build_toolbar($url, "fa-user-times");

	$output .= build_ban_dropdown($group, $number ); 

	$output = add_navigation_bar($output);	

	$output .= "<div class=\"cleargroup\">$article</div>\n";
	return $output;
}


function build_toolbar($url, $icon) {

	return '<li class="nav-item active"><a href="' . $url . '"><i class="fas ' . $icon . ' fa-3x"></i></a></li>' . "\n";
}

function show_selection_group_form() {
	include("inc/head.php");
	
	$output = add_navigation_bar('<a href="index.php"><i class="fas fa-home fa-3x"></i></a>');
	$output .= form_begin("get");
	$output .= show_selection_group();
	$output .= form_close();

	echo $output;
	include("inc/tail.php");
	exit(0);
}

function show_selection_group() {
	$output = "<table class=\"selectiongroup\">\n";
	$output .= "<tr><th colspan=\"2\">Select a newsgroup to inspect</th></tr>\n";
	$output .= "<tr><th>Name</th><td><input type=\"text\" name=\"group\"></td></tr>";
	$output .= "<tr><th>Start</th><td><input type=\"text\" name=\"number\"></td></tr>";
	$output .= "<tr><td colspan=\"2\"><input type=\"submit\" name=\"action\" value=\"submit\"></td></tr>";
	$output .= "</table>\n";
	
	return $output;
}

function add_navigation_bar($content) {


        $output = "
<nav class=\"navbar navbar-expand-md navbar-dark bg-dark fixed-top\">
  <div class=\"container-fluid\">
        <div class=\"navbar-header\">
		<ul class=\"navbar-nav mr-auto\">
                $content
		</ul>
        </div>
  </div>
</nav>";

        return($output);
}

function build_html_post($xover, $body, $ng) {
        $mid = $xover["Mid"];
        $from = $xover["From"];
        $date = $xover["Date"];
        $xref = $xover["Xref"];
        $from = htmlentities($from);
        $subject = $xover["Subject"];
        $nick = preg_replace("/(\<.+\>)/", "", $from);

        $mid = htmlentities($mid);

        $output = "<div class=\"fullarticle\"><div class=\"headers\"><b>From:</b>         $nick
<b>Newsgroup:</b>    $ng
<b>Subject:</b>      $subject
<b>Date:</b>         $date
<b>Message-ID:</b>   $mid
<b>Xref:</b>         $xref<hr /></div><div class=\"articlebody\">$body</div></div>";


        return $output;
}

function build_ban_dropdown($group, $number ) {

	$pagename = basename($_SERVER['PHP_SELF']);

	$urlbase = "$pagename?group=$group&amp;number=$number&amp;action=";

	$urlbanfromsingle = $urlbase . "banfromsingle";
	$urlbanfromall    = $urlbase . "banfromall";
	$urlbanthread    = $urlbase . "banthread";

	$output = "
<li class=\"nav-item dropdown\">
<a class=\"nav-link btn btn-dark dropdown-toggle\"  style=\"padding-top: 0px; padding-bottom: 0px; color: #007bff;\" href=\"masscancel.php\" id=\"navbarDropdownMenuLink\" data-toggle=\"dropdown\" aria-haspopup=\"true\" aria-expanded=\"false\">
<i class=\"fas fa-skull-crossbones fa-3x\"></i></a>
        <div class=\"dropdown-menu\" aria-labelledby=\"navbarDropdown\">
          <a class=\"dropdown-item\" href=\"$urlbanfromsingle\">Ban sender on $group</a>
	  <div class=\"dropdown-divider\"></div>
          <a class=\"dropdown-item\" href=\"$urlbanfromall\">Ban sender on all groups</a>
          <div class=\"dropdown-divider\"></div>
          <a class=\"dropdown-item\" href=\"$urlbanthread\">Ban all thread</a>
        </div>
</li>
";
	return $output;
}

?>
