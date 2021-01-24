<?php

function cancelbot_ban($banlist, $header, $value, $groups)  {

	$fh = fopen($banlist, "a");
	if (!$fh) error_and_exit("Unable to open banlist $banlist");
/*

[Passaporti]
Header    = "/Subject/i"
Groups	  = "it\.hobby\.fai\-da\-te"
Value     = "/passport|green\ card|resident\ permit|work\ permit|driving\ license/i"
Comment   = "Passport dealer"

*/
	$random = random_string(6);
	$output = "[Rule $random: /$header/i matches /\Q$value\E/i]\n";
	
	$output .= "Header  = \"/$header/i\"\n";
	$output .= "Value   = \"/\Q$value\E/i\"\n";
	if ((isset($groups)) and (strlen($groups) > 3)) $output .= "Groups  = \"\Q$groups\E/i\"\n";
	else $groups = "all groups";
	$output .= "Comment = \"Rule $random on $groups\"\n\n";

	$success = fwrite($fh, $output);
	if (!$success) error_and_exit("Unable to write data into banlist $banlist");

	fclose($fh);
	return TRUE;
}


function form_begin($method) {

        $output = "\n<form method=\"$method\" action=\"";
        $pagename = basename($_SERVER['PHP_SELF']);
        $output .= "$pagename\">\n";

        return $output;
}

function form_close() {

        return "</form>\n";
}

function error_and_exit($error) {
	$status = array();
	$status[] = "Unrecoverable system error:";
	$status[] = $error;
	exit_page($status);
	exit(0);
}

function log_string($facility, $string)
{
        openlog('Aioe.org', LOG_PID, LOG_NEWS);

        if ($facility == "emerg") $loglevel = 0;
        elseif ($facility == "alert") $loglevel = 1;
        elseif ($facility == "crit") $loglevel = 2;
        elseif ($facility == "err") $loglevel = 3;
        elseif ($facility == "warning") $loglevel = 4;
        elseif ($facility == "notice") $loglevel = 5;
        elseif ($facility == "info") $loglevel = 6;
        elseif ($facility == "debug") $loglevel = 7;
        else $loglevel = 5;     

        syslog($loglevel, $string);
        closelog();
}

?>
