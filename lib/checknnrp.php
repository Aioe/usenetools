<?php

function write_current_banblock_conf($config) {

	$commands = array(
                        "connessioni"           => "Maximum number of connections per day",
                        "connection_time"       => "Maximum connection time",
                        "bad_auth"              => "Maximum number of failed attempts to authenticate",
                        "unknown_commands"      => "Maximum number of unknown or invalid commands",
                        "already_banned"        => "Maximum number of connection attempts made by an already banned IP address",
                        "articles_size"         => "Maximum size of downloaded articles",
                        "articles_hits"         => "Maximum number of articles per day",
                        "overview_size"         => "Maximum size of overview data downloaded from server",
                        "overview_hits"         => "Maximum number of overview queries",
                        "sys_time"              => "System time",
                        "usr_time"              => "User Time", 
                        "groups"                => "Groups",
                        "one_process_duration"  => "Maximum connection time for a single connection",
                        "one_process_cputime"   => "Maximum CPU time for a single connection",
                        "ban_duration"          => "Amount of time before being unbanned");

	$units = array(      
                        "connessioni"           => "connections",
                        "connection_time"       => "seconds",
                        "bad_auth"              => "attempts",
                        "unknown_commands"      => "commands",
                        "already_banned"        => "connections",
                        "articles_size"         => "bytes",
                        "articles_hits"         => "articles",
                        "overview_size"         => "bytes",
                        "overview_hits"         => "queries",
                        "sys_time"              => "seconds",
                        "usr_time"              => "seconds",
                        "groups"                => "groups",
                        "one_process_duration"  => "seconds",
                        "one_process_cputime"   => "seconds",
                        "ban_duration"          => "seconds");

	$output = "<table class=\"banblock\">\n";
	$output .= "   <tr><th colspan=\"4\" class=\"table_name\">Ban and block settings</th></tr>\n";
	$output .= "   <tr><th class=\"banblock_th\">Description</th><th class=\"banblock_th\">Ban</th><th class=\"banblock_th\">Block</th><th class=\"banblock_th\">Unit</th></tr>\n";

	foreach($commands as $command => $description) {

		$output .= "   <tr><td class=\"banblock_description\">$description</td>";
		$output .= "<td class=\"ban_limit\">";
		$value = $config["Rules"]["Ban"][$command];
		$output .= "<input type=\"text\" maxlength=\"9\" name=\"ban_$command\" value=\"$value\" class=\"baninput\"></td>";

		$output .= "<td class=\"block_limit\">";
                $value = $config["Rules"]["Block"][$command];
                $output .= "<input type=\"text\" maxlength=\"9\" name=\"block_$command\" value=\"$value\" class=\"blockinput\"></td>";

		$output .= "<td class=\"banunits\">$units[$command]</td></tr>\n";

	}


	$output .= "</table>\n";

	return $output;

} 

function write_current_flood_conf($config) {

/*
$config["Rules"]["Flood"]["connections"]         = 25;
$config["Rules"]["Flood"]["bad_auth"]            = 2;
$config["Rules"]["Flood"]["groups"]              = 500;
$config["Rules"]["Flood"]["already_banned"]      = 10;
$config["Rules"]["Flood"]["ban_duration"]        = 86400;
*/

	$commands = array("connections" 	=> "Maximum number of connections",
			  "bad_auth"    	=> "Maximum number of failed attempts to authenticate",
			  "already_banned" 	=> "Maximum number of connection attempts made by an already banned IP address",
			  "ban_duration"	=> "Amount of time before being unbanned",
			  "groups"		=> "Requested groups");

	$units = array(	"connections" 		=> "connections",
			"bad_auth"    		=> "attempts",
			"already_banned" 	=> "connections",
			"ban_duration"  	=> "seconds",
			"groups"		=> "groups" );

	$output = "<table class=\"flood\">\n";
	$output .= "<tr><th colspan=\"3\" class=\"table_name\">Flood settings</th></tr>\n";
        $output .= "   <tr><th class=\"flood_th\">Description</th><th class=\"flood_th\">Threshold</th><th class=\"flood_th\">Unit</th></tr>\n";

        foreach($commands as $command => $description) {

                $output .= "   <tr><td class=\"flood_description\">$description</td>";
                $output .= "<td class=\"flood_limit\">";
                $value = $config["Rules"]["Flood"][$command];
                $output .= "<input type=\"text\" name=\"flood_$command\" value=\"$value\" class=\"floodinput\"></td>";
                $output .= "<td class=\"floodunits\">$units[$command]</td></tr>\n";

        }


        $output .= "</table>\n";


	return $output;

}

function write_submit_button($config) {

	$output = "<input type=\"submit\" name=\"command\" value=\"Update\" class=\"submit\">&nbsp;";
	$output .= "<input type=\"submit\" name=\"command\" value=\"Clear\" class=\"submit\">&nbsp";
	$output .= "<input type=\"submit\" name=\"command\" value=\"Back\" class=\"submit\">&nbsp;";
	

	return $output;

}

function add_navigation_bar($content) {


	$output = "
<nav class=\"navbar navbar-expand-md navbar-dark bg-dark fixed-top\">
  <div class=\"container-fluid\">
	<div class=\"navbar-header\">
		$content
	</div>
  </div>
</nav>";

	return($output);
}

function show_banblock_form($config) {

	$output = form_begin("post");
	$icons  = write_submit_button($config);
	$output .= add_navigation_bar($icons);
	$output .= write_current_flood_conf($config);
	$output .= write_current_banblock_conf($config);
	$output .= form_close($config);

	return $output;
}


function generate_new_configuration_file($config) {

	$commands = array(
                        "connessioni",
                        "connection_time",
                        "bad_auth",
                        "unknown_commands",
                        "already_banned",
                        "articles_size",
                        "articles_hits",
                        "overview_size",
                        "overview_hits",
                        "sys_time",
                        "usr_time",
                        "groups",
                        "one_process_duration",
                        "one_process_cputime",
                        "ban_duration" );

	$flood_commands = array( "connections", "bad_auth", "already_banned", "ban_duration", "groups");

// Verifico se i parametri ci sono tutti e sono solo numeri

	foreach ($flood_commands as $command) {
		$floodflag = "flood_" . $command;
		if (!isset($_POST[$floodflag])) { 
                        echo "Missing flag $floodflag, aborting";
                        exit(5);
                }

		$floodflag_value        = $_POST[$floodflag];

                if (!is_numeric($floodflag_value)) {
                        echo "Flag $floodflag doesn't include a number ($floodflag_value), aborting";
                        exit(5);
                }
	}

	foreach($commands as $command) {
		$banflag 	= "ban_" . $command;
		$blockflag	= "block_" . $command;

		if (!isset($_POST[$banflag])) { 
			echo "Missing flag $banflag, aborting";
			exit(5);
		}

                if (!isset($_POST[$blockflag])) { 
                        echo "Missing flag $blockflag, aborting";
                        exit(5);
                }

		$banflag_value 		= $_POST[$banflag];
		$blockflag_value	= $_POST[$blockflag];

		if (!is_numeric($banflag_value)) {
			echo "Flag $banflag doesn't include a number ($banflag_value), aborting";
			exit(5);
		}

		if (!is_numeric($blockflag_value)) {
                        echo "Flag $blockflag doesn't include a number ($blockflag_value), aborting";
                        exit(5);
                }

	}

	$output = "<?php\n";

// Settings

	$output .= create_settings_lines($config, "settings");
	$output .= create_settings_lines($config, "stats");
	$output .= create_settings_lines($config, "debug");


// Rules
	$output .= create_rules($config, "Flood", $flood_commands);
	$output .= create_rules($config, "Ban", $commands);
	$output .= create_rules($config, "Block", $commands);

	$output .= "?>\n";

	return $output;

}

function create_rules($config, $type, $commands) {
	$output = "";
        foreach($commands as $command) {
                $banflag = $type . "_" . $command;
		$banflag = strtolower($banflag);
                $output .= add_rule_line($type, $command, $_POST[$banflag]);
        }

	return $output;
}

function create_settings_lines($config, $settings) {
	$output = "";
	foreach($config[$settings] as $key => $value ) {
                $output .= '$config["' . $settings . '"]["' . $key . '"] = "' . $value . '";';
                $output  .=  "\n";
        }

	return $output;
}

function add_rule_line($type, $command, $value) {
	$output = '$config["Rules"]["' . $type . '"]["' . $command . '"] = "' . $value . '";';
	$output .= "\n";
	return $output;
}

?>
