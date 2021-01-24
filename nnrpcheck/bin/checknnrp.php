<?php


$shortdata = array();
$data = array();


// INCLUDE config.php
include(dirname(__DIR__).'/config.php');
include(dirname(__DIR__).'/lib/common.php');

// check whether another instance is running
if (!check_whether_to_start($config,"checknnrp")) exit(4);

$last = 0;

$firstpass = 1;
$next_stats = 0;

declare(ticks = 1);
 
pcntl_signal(SIGTERM, 'unix_signals');// Termination ('kill' was called)
pcntl_signal(SIGHUP, 'unix_signals'); // Terminal log-out
pcntl_signal(SIGINT, 'unix_signals'); // Interrupted (Ctrl-C is pressed
pcntl_signal(SIGUSR1, 'unix_signals');


while (true) {

	// PHP caches file information in order to provide faster
	// performance; In order to read the new filesize, we need
	// to flush this cache.
	clearstatcache(false, $config["settings"]["news.notice"]);

	// Reload $config if requested
	if ($config["settings"]["reload_config"] == 1) include(dirname(__DIR__).'/config.php');

	$stats["good"] = 0;
	$stats["bad"]  = 0;
	$stats["local"] = 0;
	$stats["nossl"] = 0;

    	// Get the current size of file
    	$current = filesize($config["settings"]["news.notice"]);

	// First run, db must be created
	if ($last == 0) {
		$success = init_database($config);
		$last = $current;
		continue;
	}

	// No new lines
	if ($last == $current) {
		// Sleep till the next run
        	sleep($config["settings"]["sleep_time"]);
		continue;
	}


    	// Reseted the file?
    	if ($current < $last) {
		$last = $current;
	}

	// If there's new content in the file
    	elseif ($current > $last) {
		// Open the file in read mode
		$fp = fopen($config["settings"]["news.notice"], 'r');
		if (!$fp) {
			log_string("crit", "Unable to open news.notice: " . $config["settings"]["news.notice"] );
			say_bye($config, "checknnrp");
		}


        	// Set the file position indicator to the last position
        	fseek($fp, $last);

        	// While not reached the end of the file
        	while (! feof($fp)) {
            	
			// Read a line
            		$line = fgets($fp);

			// Check whether is an nnrpd line
			if (is_nnrp_line($line))
			{

				// Update Database
				$success = scan_line($line);

				// Update counter of good lines
				if ($success == 0) $stats["good"]++;

				// If it's an attempt to esablish a plain connection over the SSL port
				if ($success == 1) $stats["nossl"]++;
			} else {
                                // Update counter of useless lines
                                $stats["bad"]++;
                        }


			// con gli operatori logici else non funziona
			if ((strpos($line, '127.0.0.1') !== false) or (strpos($line, ' ?') !== false)) {
				// local messages counter
				$stats["local"]++;
			} 
		}

        	// Store the current position of the file for the next pass
       		$last = ftell($fp);

        	// Close the file pointer
        	fclose($fp);
    	}


	// A few statistical data are sent to syslog
	$total_lines = $stats["good"] + $stats["bad"] + $stats["local"] + $stats["nossl"];
	$string = "Scanning news.notice: $total_lines total lines, " . $stats["good"] . " nnrpd, " . $stats["bad"] . " other, " . $stats["local"] . " coming from localhost, " . $stats["nossl"] . " PLAIN over SSL port"; 
	log_string("notice", $string);

	// Note: flood check MUST be disabled during the first scan because this script counts only the lines but no times
	// and this avoid false positive results

	if (($firstpass == 0) and (isset($shortdata))) check_short($config);
	$firstpass = 0;

	// If there're new log lines
	if (isset($shortdata)) {

		// Append short time buffer ($shortdata) to global buffer ($data[UNIX_TIME][$ip]);
		$time = time();
		$data[$time][] = array();

		if (isset($shortdata)) {
			foreach ($shortdata as $ip => $struct) $data[$time][$ip] = $shortdata[$ip];

			// Debug
			foreach($data as $time => $stuff) {
				foreach($stuff as $ip => $other) {
					if ($ip == 0) unset($data[$time][0]);
				}
			}
		}
	}

	// Expire old records
	foreach($data as $time => $stuff) {
		$exp = time() - $config["settings"]["expire"];
		if ($time < $exp) {
			// Ripulice $data dei record troppo vecchi
			unset($data[$time]); // va fatto qui perché siamo fuori da funzioni, altrimenti non si può fare
			log_string("notice", "Bag $time is expired, deleting");
		}
	}
	
	// if set, print $data on screen
	if ($config["debug"]["show_current_data"] == 1) {
		foreach($data as $time => $stuff) {
			foreach($stuff as $ip => $struct) {
				echo "$time\t$ip";
					foreach($struct as $key => $value) {
						echo "\t$value";
					}
				echo "\n";
			}
		}
	} 


	// Check whether some ip is to ban (Ban)
	if (isset($shortdata)) check_ban($config);

        // Remove contents from  short time buffer
        unset($shortdata);

	// Check processes	
	$processes = get_process_list();
	if (count($processes) > 0) {
        	// foreach nnrpd process...
        	foreach($processes as $pid) {
                	$duration = get_process_info("duration", $pid); 
                	if (($duration === false) or (!isset($duration))) continue;
                	$cputime  = get_process_info("cputime", $pid);
                	if (($cputime === false) or (!isset($cputime))) continue;
                	$ip       = get_process_info("ip", $pid );
                	if (($ip === false) or (!isset($cputime))) continue;

			// If cputime of a still running process exceeded some thresholds...
			if ($cputime > $config["Rules"]["Ban"]["one_process_cputime"]) {
				ban_ip("firewall", $ip, "one_process_cputime", $config["Rules"]["Ban"]["ban_duration"]);
				log_string("notice", "Ban $ip: one process cputime $cputime, max is " . $config["Rules"]["Ban"]["one_process_cputime"]);
				posix_kill($pid, 9);
			} elseif ($cputime > $config["Rules"]["Block"]["one_process_cputime"]) {
				ban_ip("protocol", $ip, "one_process_cputime", $config["Rules"]["Block"]["ban_duration"]);
                                log_string("notice", "Block $ip: one process cputime $cputime, max is " . $config["Rules"]["Block"]["one_process_cputime"]);
				posix_kill($pid, 9);
			}

                        if ($duration > $config["Rules"]["Ban"]["one_process_duration"]) {
                                ban_ip("firewall", $ip, "one_process_duration", $config["Rules"]["Ban"]["ban_duration"]);
                                log_string("notice", "Ban $ip: one process duration $duration, max is " . $config["Rules"]["Ban"]["one_process_duration"]);
				posix_kill($pid, 9);
                        } elseif ($duration > $config["Rules"]["Block"]["one_process_duration"]) {
                                ban_ip("protocol", $ip, "one_process_duration", $config["Rules"]["Block"]["ban_duration"]);
                                log_string("notice", "Block $ip: one process duration $duration, max is " . $config["Rules"]["Block"]["one_process_duration"]);
				posix_kill($pid, 9);
                        }
        	}
	}

	// check whether to create stats file

	if ($config["stats"]["do_stats"] == 1) {
		if (time() > $next_stats) {
			
			$next_stats = time() + $config["stats"]["frequency"];
			$success = write_stats_file($data, $config);
		}

	}

	// Sleep till the next run
	sleep($config["settings"]["sleep_time"]);
}

function check_ban($config) {
	global $shortdata;
	$knownips = array();

	// STEP 1: fetch from $shortdata all ips that it includes
	foreach($shortdata as $ip => $stuff) $knownips[] = $ip;

	// STEP 2: for each ip has to read $data values
	foreach($knownips as $ip) {
		$traffic = array();
		$traffic = get_data_from_spool($ip);

		if ($config["debug"]["show_ip_activity"] == 1) {
			echo "$ip";
			foreach($traffic as $key => $value) echo "\t$value";
			echo "\n";
		}

		// Main checks
		foreach($traffic as $key => $value) {

			// if an ip has exceeded the thresholds inside $conf["Rules"]["Ban"][] (see config.php), it has to be banned at firewall level
			if ($value > $config["Rules"]["Ban"][$key]) {
				ban_ip("firewall", $ip, $key, $config["Rules"]["Ban"]["ban_duration"]);
				log_string("notice", "IP $ip banned due $key: current value is $value, maximum is " . $config["Rules"]["Ban"][$key]);

				// in order to avoid a loop, i need to cancel old data after an ip being banned
				clear_ip_from_data($ip);
			}

			// if an ip has exceeded the thresholds defined by $conf["Rules"]["Block"][] it has to be blocked at protocol level (nntp)
			if ($value > $config["Rules"]["Block"][$key]) {
				ban_ip("protocol", $ip, $key, $config["Rules"]["Block"]["ban_duration"]);
				log_string("notice", "IP $ip blocked due $key: current value is $value, maximum is " . $config["Rules"]["Block"][$key]);
				// Okkio: qui non devi ripulire $data dagli ip perché i dati potrebbero servire per bandirlo
			}
		}
	}
}

function check_short($config)
{
	global $shortdata;

	// Extract IP address
	foreach($shortdata as $ip => $stuff) {

		// Too many connections (flood)
		if ($shortdata[$ip]["connessioni"] > $config["Rules"]["Flood"]["connections"]) {
			log_string("notice", "$ip banned due flood: " . $shortdata[$ip]["connessioni"] . " connections, max " . $config["Rules"]["Flood"]["connections"]);
			ban_ip("firewall", $ip, "flood_connections", $config["Rules"]["Flood"]["ban_duration"]);
		}

		// Too many failed attempts to authenticate (flood)
        	if ($shortdata[$ip]["bad_auth"] > $config["Rules"]["Flood"]["bad_auth"]) {
                        log_string("notice", "$ip banned due flood: " . $shortdata[$ip]["bad_auth"] . " bad auths, max " . $config["Rules"]["Flood"]["bad_auth"]);
			ban_ip("firewall", $ip, "flood_bad_auth", $config["Rules"]["Flood"]["ban_duration"]);
                }

		// Too many groups (flood)
                if ($shortdata[$ip]["groups"] > $config["Rules"]["Flood"]["groups"]) {
                        log_string("notice", "$ip banned due flood: " . $shortdata[$ip]["groups"] . " groups, max " . $config["Rules"]["Flood"]["groups"]);
			ban_ip("firewall", $ip, "flood_too_groups", $config["Rules"]["Flood"]["ban_duration"]);
                }

                // Already banned
                if ($shortdata[$ip]["already_banned"] > $config["Rules"]["Flood"]["already_banned"]) {
                        log_string("notice", "$ip banned due flood: " . $shortdata[$ip]["already_banned"] . " connections, max " . $config["Rules"]["Flood"]["already_banned"]);
                        ban_ip("firewall", $ip, "flood_already_banned", $config["Rules"]["Flood"]["ban_duration"]);
                }
	}

}

function ban_ip($type, $ip, $reason, $duration)
{
	global $config;

	$prefix = "";
	if ($type == "firewall") $prefix = "ban";
	elseif ($type == "protocol") $prefix = "block";

	$bandb = $config["settings"]["bandb"];
	$fp = fopen($bandb, 'a');
	if (!$fp) {
		log_string("crit", "Unable to append data to $bandb, aborting");
		say_bye($config, "checknnrp");
	}

	$time = time();
	$validuntil = $time + $duration;
	fwrite($fp, "$prefix\t$ip\t$reason\t$validuntil\t$time\n");
	fclose($fp);
}

function scan_line($line)
{
	// When a day of month is one digit long, syslog adds a space (Dec  1 -> Dec 17) that must be stripped
	$line = str_replace("  ", " ", $line);

	// AN IP is connectiong...
	// Dec 17 10:43:37 gioia nnrpd[27723]: 79.45.158.7 (79.45.158.7) connect - port 119
	if (strpos($line, ' connect ') !== false) {
		if (strpos($line, "("))	{
			$first  = strpos($line, "(") + 1;
			$last   = strpos($line, ")");
			$lenght = $last - $first;
			$ip     = substr($line, $first, $lenght);
			$success = update_database($ip, "connessioni", 1);
		}
	}

	// An user has sent wrong userid/password
	// Dec 17 14:37:52 gioia nnrpd-ssl[27954]: 104.43.249.91 bad_auth
	if (strpos($line, 'bad_auth') !== false) {
		$words = explode(" ", $line);
		$ip = $words[5];
                $success = update_database($ip, "bad_auth", 1);
	}


	// An user has sent some unknown command
	// Dec 17 14:43:06 gioia nnrpd-ssl[29582]: 52.129.34.145 unrecognized XYZZY
	if (strpos($line, 'unrecognized') !== false) {
		$words = explode(" ", $line);
		$ip = $words[5];
		$success = update_database($ip, "unknown_commands", 1);
	}


	// An user who was already banned is trying to connect again
	// Dec 17 04:19:28 gioia nnrpd[58557]: 178.235.55.163 rejected by rule (Too much user time)
	if (strpos($line, 'rejected by rule') !== false) {
		$words = explode(" ", $line);
		$ip = $words[5];
		$success = update_database($ip, "already_banned", 1);
	}

	
	// NNRPD stats line about IP, number of articles, size of articles downloaded by this connection
	// Dec 17 15:08:01 gioia nnrpd[36386]: 82.69.119.192 artstats get 4 time 0 size 7254
	if (strpos($line, 'artstats') !== false) {
		$words = explode(" ", $line);
		$ip = $words[5];
		$arthits = $words[8];
		$artsize = $words[12];
		$success = update_database($ip, "articles_size", $artsize);
	}


	// NNRPD stats line about overview: IP, number of queries, size of replies
	// Dec 17 15:13:42 gioia nnrpd[37246]: 97.119.34.101 overstats count 1 hit 24 miss 0 time 0 size 13221 dbz 0 seek 0 get 0 artcheck 0
	if (strpos($line, 'overstats') !== false) {
		$words = explode(" ", $line);
		$ip = $words[5];
		$overhits = $words[8];
		$oversize = $words[16];
		$success  = update_database($ip, "overview_hits", $overhits);
		$success  = update_database($ip, "overview_size", $oversize);
	}

	
	// An user requested a group
	// Mar 20 09:42:59 gioia nnrpd-ssl[55169]: 93.137.220.71 group hr.rec.vinarstvo 0
	if (strpos($line, ' group ') !== false) {
		$words = explode(" ", $line);
		$ip = $words[5];
		$arthits =  $words[8];
		$success = update_database($ip, "groups", 1);
		$success = update_database($ip, "articles_hits", $arthits);
	}

	// Final statistic line: connection time, CPU Time user/system
	// Dec  4 12:46:22 gioia nnrpd[36430]: 79.50.216.219 times user 0.008 system 0.008 idle 0.000 elapsed 931.864
	if (strpos($line, ' times user ') !== false) {
		$words = explode(" ", $line);
		
		// INN 2.5.4 has a bug that prevent an IP address to be recorded in the log when it tries to esablish a plain connection over
		// SSL port (i.e telnet nntp.aioe.org 563), these lines must be stripped at the moment
		$x = count($words);
		if ($x == 14) return(1);

		$ip = $words[5];
		$sys_time = $words[10];
		$usr_time = $words[8];
		$con_time = $words[14];
		$conn_tim = trim($con_time);
		$success  = update_database($ip, "connection_time", $conn_tim);
		$success  = update_database($ip, "usr_time", $usr_time);
		$success  = update_database($ip, "sys_time", $sys_time);
	}

	return(0);
}

function update_database($ip, $tipo, $numero)
{
	global $shortdata;

	// if an IP is not seen before, initialize $shortdata[$ip]
	if (!isset($shortdata[$ip]))
	{
        	$shortdata[$ip]["connessioni"] = "0";
        	$shortdata[$ip]["connection_time"] = "0";
        	$shortdata[$ip]["bad_auth"] = "0";
        	$shortdata[$ip]["unknown_commands"] = "0";
        	$shortdata[$ip]["already_banned"] = "0";
        	$shortdata[$ip]["articles_size"] = "0";
        	$shortdata[$ip]["articles_hits"] = "0";
        	$shortdata[$ip]["overview_hits"] = "0";
        	$shortdata[$ip]["overview_size"] = "0";
        	$shortdata[$ip]["groups"] = "0";
        	$shortdata[$ip]["sys_time"] = "0";
        	$shortdata[$ip]["usr_time"] = "0";
	}

	// Update record $tipo of row $ip

	$num = sprintf("%f", $numero);

	if (is_numeric($num)) {
		if (!isset($shortdata[$ip][$tipo])) $shortdata[$ip][$tipo] =  $num;
		if ( isset($shortdata[$ip][$tipo])) $shortdata[$ip][$tipo] += $num;
	} else log_string("err", "Somewhat went wrong updating database: value is $numero ($num)");

	return 0;
}

function unix_signals($signal) {

	global $data;
	global $shortdata;
	global $config;

	switch ($signal) {
         	case SIGTERM:
	        case SIGHUP:
         	case SIGINT:
				log_string("notice", "Received signal $signal");
				say_bye($config, "checknnrp");
             	     break;

		case SIGUSR1:
				log_string("notice", "Reload signal received");
				// reload config.php
				include(dirname(__DIR__).'/config.php');
				// new stats
				if ($config["stats"]["do_stats"] == 1) write_stats_file($data, $config);
		    break;
     }
}

function init_database($config) {
	global $data;
	global $shortdata;

	$stats = array("good" => 0, "bad" => 0, "nossl" => 0, "bags" => 0, "total" => 0);

	$bag = 0;

        // Open the file in read mode at the beginning
        $fp = fopen($config["settings"]["news.notice"], 'r');
	if (!$fp) {
		log_string("crit", "Unable to open news.notice: " . $config["settings"]["news.notice"] );
		say_bye($config, "checknnrp");
	}


	$queue = array();

        // While not reached the end of the file
        while (! feof($fp)) {

        	// Read a line
                $line = fgets($fp);
		$stats["total"]++;

                // Check whether is an nnrpd line
		if (is_nnrp_line($line)) {
			// Calculate how much old is that line
			$unix_epoch = get_unix_time($line);

			// First run
			if ($bag == 0) $bag = $unix_epoch + $config["settings"]["sleep_time"];

			// Other lines within the same bag are stored inside $queue
			if ($unix_epoch < $bag) $queue[] = $line;
			else {
				$stats["bags"]++;
				// init $shortdata
				$shortdata = array();
				foreach($queue as $logline) {
					if (is_nnrp_line($logline)) {
        	                        
						// Update Database
	                                	$success = scan_line($logline);

                                		// Update counter of good lines
                                		if ($success == 0) $stats["good"]++;

                                		// If it's an attempt to esablish a plain connection over the SSL port
                                		if ($success == 1) $stats["nossl"]++;
                        		}
				}

				// Reset queue
				unset($queue);

				// copy last line fetched in $queue
				$queue[] = $line;

	                        // Copy shortdata inside $data[bag]
                                foreach ($shortdata as $ip => $struct) {
					$data[$bag][$ip] = $shortdata[$ip];
				}


				// update bag value
				$unix_epoch = get_unix_time($line);
				$bag = $unix_epoch + $config["settings"]["sleep_time"];
			}
		} else $stats["bad"]++;
	}

	log_string("notice", "Populating spool: " . $stats["bags"] . " bags, " . $stats["total"] . " total, " . $stats["good"] . " good, " . $stats["bad"] . " bad lines, " . $stats["nossl"] . " plain over SSL");

	$stats = get_stats_from_data();

	log_string("notice", "Spool size: " . $stats["size"] . " bytes, found " . $stats["ips"] . " ip addresses and " . $stats["bags"] . " bags");
	
	$output = "";
	foreach($stats["data"] as $key => $value) $output .= "$key $value ";
	log_string("notice", "Activity: $output");
}


// Calculate unix time of each log line
function get_unix_time($line)
{
	// When a day of month is one digit long, syslog adds a space (Dec  1 -> Dec 17) that must be stripped
        $line = str_replace("  ", " ", $line);

        // Dec 17 10:43:37 gioia nnrpd[27723]: 

	$words = explode(" ", $line);
	
	$month_word = $words[0];
	$day	    = $words[1];
	$time_word  = $words[2];
	$month 	    = 0;
	$year	    = date("Y");

	$months = array("", "Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Ago", "Sep", "Oct", "Nov", "Dec" );
	foreach($months as $mm) {
		if ($mm == $month_word)  break;
		$month++;
	}

	$times = explode(":", $time_word);
	$hour = $times[0];
	$min  = $times[1];
	$sec  = $times[2];

	$unix = mktime($hour, $min, $sec, $month, $day, $year);
	return($unix);
}


function get_data_from_spool($ip) {
	
	global $data;
	$result = array('connessioni' => 0,
		        'connection_time' => 0,
			'bad_auth' => 0,
			'unknown_commands' => 0,
			'already_banned' => 0,
			'articles_size' => 0,
			'articles_hits' => 0,
			'overview_size' => 0,
			'overview_hits' => 0,
			'groups' => 0,
			'sys_time' => 0,
			'usr_time' => 0 );

	foreach($data as $time => $struct ) {			// il primo livello è la marca temporale $data[unixtime]
		foreach($struct as $oldip => $intdata) {	// il secondo livello è l'ip $data[unixtime][ip]
			if ($ip != $oldip) continue; 		// se non è l'ip che cerchiamo passiamo direttamente al successivo
			// se l'ip è quello giusto, copio i dati che lo riguardano in $result
			foreach($intdata as $key => $value) {
				if (!isset($result[$key])) {
					$result[$key] = $value;
					continue;
				}
				$result[$key] += $value;
			}
		}
	}

	return($result);
}

function get_stats_from_data() {

	global $data;
	$results = array("bags" => 0,
			 "ips" => 0,
			 "size" => 0,
			 "data" => array(
			 		"groups" => 0,
			 		"bad_auth" => 0,
			 		"connessioni" => 0,
			 		"connection_time" => 0,
			 		"unknown_commands" => 0,
			 		"already_banned" => 0,
			 		"articles_size" => 0,
			 		"articles_hits" => 0,
			 		"overview_size" => 0,
			 		"overview_hits" => 0,
			 		"sys_time" => 0,
			 		"usr_time" => 0
				 )
		  );

	$results["size"] = mb_strlen(serialize((array)$data), '8bit');

	$iplist = array();
	foreach($data as $time => $struct ) {
		$results["bags"]++;
		foreach($struct as $oldip => $intdata) {
			if (!in_array($oldip, $iplist)) $iplist[] = $oldip;
			foreach($intdata as $key => $value) {
				if ($value > 0) $results["data"][$key] += $value;
			}
		}
	}
	$results["ips"] = count($iplist);

	return $results;
}


function is_nnrp_line($line) {
	if ((strpos($line, "nnrpd") !== false) or (strpos($line, "nnrpd-ssl") !== false)) {
		if (strpos($line, '127.0.0.1') !== false) return FALSE;
		return TRUE;
	} else return FALSE;
}

function clear_ip_from_data($ip) {

	global $data;
	foreach($data as $bag => $struct) {
		foreach($struct as $recordedip => $stuff) {
			if ($ip == $recordedip) $data[$bag][$ip] = array();
		}
	}
}

function get_process_list() {
	$output = array();
	exec("pgrep nnrpd", $output);
	return $output;
}


function get_process_info($type, $pid) {
	
	$flag = "cputime";

	if ($type == "duration") $flag = "etimes";
	elseif ($type == "cputime") $flag = "cputime"; 
	elseif ($type == "ip") $flag = "args";
	$CLI = "ps -o $flag= -p " . $pid;
	exec($CLI, $output);
	if (isset($output[0])) {
		if ($type == "duration") {
			$number = trim($output[0]);
			if ($number > 0) $time = sprintf("%d", $number );
			else return FALSE;
			return $time;
		} elseif ($type == "cputime") {
			$value = $output[0];
			if (strlen($value) == 8) {
				$numbers = explode(":", $value);
				$cputime = $numbers[2] + ($numbers[1] * 60) + ($numbers[0] * 3600);
				return $cputime;
			} else return FALSE;
		} elseif ($type == "ip") {
// Format: '- nnrpd: 95.252.39.249 ARTICLE' 
			$value = $output[0];
			$values = explode(" ", $value);
			if ((isset($values[2]) and (strlen($values[2]) > 7))) return $values[2];
			else return FALSE;
		}

	}
}

function write_stats_file($data, $config) {

	$bags = 0;
	$dbip = array();

	$rank = 0;
	$buffer = "";

	foreach ($data as $bag => $struct ) {
		foreach($struct as $ip => $data_ip) {
			foreach( $data_ip as $key => $value) {
					if (!isset($dbip[$ip][$key])) {
						$dbip[$ip][$key] = $value;
						continue;
					}
					$dbip[$ip][$key] += $value;
			}
		}
	}

	foreach($dbip as $ip => $struct) {
			$rank++;
			$buffer .= "<tr><td>$rank</td><td>$ip</td>";
			foreach($struct as $key => $value) $buffer .= draw_table_cell($config, $key, $value);
			$buffer .= "</tr>\n";
	}


	$file = $config["stats"]["stats_file"];
	if (!file_put_contents( $file, $buffer )) {
		log_string("crit", "Unable to write stats file $file, aborting");
		say_bye($config, "checknnrp");
	}
}

function draw_table_cell($config, $key, $value) {

	$threshold_90_block = $config["Rules"]["Block"][$key] 	* 0.9;
	$threshold_80_block = $config["Rules"]["Block"][$key]   * 0.75;
	$threshold_70_block = $config["Rules"]["Block"][$key]   * 0.5;
	$threshold_60_block = $config["Rules"]["Block"][$key]   * 0.25;
	$threshold_50_block = $config["Rules"]["Block"][$key]   * 0.01;

	if ($value > $threshold_50_block) $color = "#088f03";
	elseif ($value > $threshold_60_block) $color = "#3d8f03";
	elseif ($value > $threshold_70_block) $color = "#778f03";
	elseif ($value > $threshold_80_block) $color = "#ff8903";
	elseif ($value > $threshold_90_block) $color="#d61d00";
	else $color = "#000000";

	$line = "<td style=\"color: $color;\" title=\"$key\">$value</td>";
	return $line;
	

}

?>
