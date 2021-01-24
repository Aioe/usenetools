<?php

declare(ticks = 1);

// INCLUDE config.php
include(dirname(__DIR__).'/config.php');
include(dirname(__DIR__).'/lib/common.php');

pcntl_signal(SIGTERM, 'unix_signals');// Termination ('kill' was called)
pcntl_signal(SIGHUP, 'unix_signals'); // Terminal log-out
pcntl_signal(SIGINT, 'unix_signals'); // Interrupted (Ctrl-C is pressed)

$program = "bannnrp"; //va cambiato anche nella funzione che gestisce i sgnali

// check whether another instance is running
if (!check_whether_to_start($config,$program)) exit(4);

$bandb = $config["settings"]["bandb"];

// if bandb doesn't exist wait until it's created
if (!file_exists($bandb)) {
	$sleep = $config["settings"]["sleep_time"];
	log_string("notice", "Bandb ($bandb) doesn't exist: wait $sleep seconds");
	do {
		sleep($sleep);
	} while(!file_exists($bandb));
}

// Main loop
while(1) {
	$file = file($bandb);
	if (!$file) {
		log_string("crit", "Unable to read Bandb: $bandb, aborting");
		say_bye($config, $program);
	}


	$file = expire_old_records($file);

	$already_banned = get_already_banned();
	$already_scanned = array();
	$already_blocked = array();
	$current_banlist = array();
	$new_banlist	 = array();

	foreach($file as $line) {
		$struct = explode("\t", $line);
// ban     180.150.68.130  bad_auth        1584803244      1584716844
		if ($struct[0] == "ban") {
			$ip = $struct[1];
			if (in_array($ip, $already_scanned)) {
				$reason = $struct[2];
				log_string("notice", "Duplicate record for $ip: reason $reason");
				continue;
			}
			else {
				$already_scanned[] = $ip;
				$new_banlist[] = $line;
			}

			if (!in_array($ip, $already_banned)) {
				log_string("notice", "Ban $ip due " . $struct[2] . " until " . $struct[3] );
				$success = ufw_ban($ip,1);
				if (!$success) {
					log_string("err", "Error: unable to ban $ip");
					continue;
				}
			} else {
				log_string("debug", "IP $ip already banned");
				continue;
			}
		}

		if ($struct[0] == "block") {
                        $ip = $struct[1];
			if (in_array($ip, $already_banned)) {
				log_string("notice", "IP $ip is already banned, it's useless to block it");
				continue;
			}
                        if (in_array($ip, $already_blocked)) {
                                $reason = $struct[2];
                                log_string("notice", "Duplicate record for $ip: reason $reason");
                                continue;
                        }
                        else {
				$already_blocked[] = $ip;
				$new_banlist[] = $line;
			}
			$reason = $struct[2];
			$current_banlist[$reason][] = $ip;
		}
	}

	build_new_readers_conf($current_banlist, $config);

	$file = $new_banlist;

	if(!file_put_contents($bandb, $file)) {
		log_string("crit", "Unable to save data into bandb $bandb, aborting");
		say_bye($config, $program);
	}

	sleep($config["settings"]["sleep_time"]);
}



function get_already_banned() {

	$bannedip = array();
	exec("ufw show added", $output);
// ufw deny from 78.107.255.173
	foreach($output as $line) {
		$struct = explode(" ", $line);
		if (($struct[1] == "deny") and ($struct[2] == "from")) $bannedip[] = trim($struct[3]);
	}

	return $bannedip;
}


function unix_signals($signal) {

        global $config;

        switch ($signal) {
                case SIGTERM:
                case SIGHUP:
                case SIGINT:
                                log_string("notice", "Aborting due signal");
				$already_banned = get_already_banned();
				foreach($already_banned as $ip) {
					$success = ufw_ban($ip,0);

				}
                                say_bye($config, "bannnrp");
                     break;

     }
}


function ufw_ban($ip,$tipo) {

	if ($tipo==1) $string="ufw insert 1 deny from $ip to any";
	else $string="ufw delete deny from $ip";

	log_string("notice", "Send: $string");
	$last_line = shell_exec($string);
	$output = trim($last_line);
	if ($tipo == 1) log_string("notice", "Banning $ip server says: $output");
	else log_string("notice", "Unbanning $ip server says: $output");

	return TRUE;
}

function build_new_readers_conf($commands = array(), $config) {


        $exit_strings = array(
                        "connessioni"           => "Too many conections",
                        "connection_time"       => "Too much connection time",
                        "bad_auth"              => "Too many attempts to authenticate",
                        "unknown_commands"      => "Too many unknown commands sent to the server",
                        "already_banned"        => "Too many attemts to connect when already banned",
                        "articles_size"          => "Too much article size downloaded",
                        "articles_hists"         => "Too many articles downloaded",
                        "overview_size"         => "Too much overview data downloaded",
                        "overview_hits"         => "Too many overview queries",
                        "groups"                => "Too many groups",
                        "sys_time"              => "Too much system CPU time",
                        "usr_time"              => "Too much user CPU time",
                        "one_process_cputime"   => "Too much CPU time for a single process",
                        "one_process_duration"  => "Too much connection time for a single process",
                        "flood_connections"     => "Too many connections (burst)",
                        "flood_bad_auth"        => "Too many attempts to authenticate (burst)",
                        "flood_too_groups"      => "Too many groups (burst)",
                        "flood_already_banned"  => "Too many attempts to connect when aready banned (burst)"
                );

        $authfile = $config["settings"]["readers_auth"];
        $fh = fopen($authfile, "w");
        if (!$fh) {
                log_string("crit", "Unable to write " . $config["settings"]["readers_auth"] . ", aborting");
		say_bye($config, "bannnrp");
        }
        $accessfile = $config["settings"]["readers_access"];
        $fg = fopen($accessfile, "w");
        if (!$fg) {
                log_string("crit", "Unable to write " . $config["settings"]["readers_access"] . ", aborting");
		say_bye($config, "bannnrp");
        }

	fwrite($fh, "# Auth structures for locally banned clients\n\n");
	fwrite($fg, "# Access structures for locally banned clients\n\n");


        foreach ($commands as $reason => $iplist) {
                $ipset = "";
                foreach($iplist as $ip) $ipset = add_ip_address($ip, $ipset);
                fwrite($fh, "auth \"$reason\" {\n");
                fwrite($fh, "\tlocaladdress: \"*\"\n");
                fwrite($fh, "\thosts: \"$ipset\"\n");
                fwrite($fh, "\tdefault: \"$reason\"\n");
                fwrite($fh, "\trequire_ssl: \"true\"\n");
                fwrite($fh, "}\n");

                fwrite($fh, "auth \"$reason\" {\n");
                fwrite($fh, "\tlocaladdress: \"*\"\n");
                fwrite($fh, "\thosts: \"$ipset\"\n");
                fwrite($fh, "\tdefault: \"$reason\"\n");
                fwrite($fh, "\trequire_ssl: \"false\"\n");
                fwrite($fh, "}\n\n");

                $error_string = $exit_strings[$reason];

                fwrite($fg, "access \"$reason\" {\n");
                fwrite($fg, "\tusers: \"$reason\"\n");
                fwrite($fg, "\treject_with: \"$error_string\"\n");
                fwrite($fg, "}\n\n");

        }

        fclose($fh);
        fclose($fg);
}

function add_ip_address($ip, $ipset) {
        if (strlen($ipset) == 0) return $ip;
        if (strpos($ipset, $ip) !== false) return $ipset;
        $ipset .= ",$ip";
        return $ipset;
}

function expire_old_records($lines) {

	$records = array();
	$stats = array("total" => 0, "good" => 0, "expired" => 0);
	foreach($lines as $line) {
		$stats["total"]++;
		$struct = explode("\t", $line);
		if (time() < $struct[3]) {
			$records[] = $line;
			$stats["good"]++;
		} else {
			$stats["expired"]++;
			$ip = $struct[1];
			if ($struct[0] == "ban") {
				ufw_ban($ip,0);
				log_string("notice", "Unban $ip: current time " . time() . ", valid until " . $struct[3]);
			}
		}
	}

	$string = "Expiring bandb: " .  $stats["total"] . " total, " . $stats["good"] . " good, " . $stats["expired"] . " expired records";
	log_string("notice", $string);

	return $records;
}


?>
