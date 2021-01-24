<?php


function show_bandb_data($config) {

	$filename = $config["settings"]["bandb"];

	$file = file($filename);
	if (!$file) error_and_exit("Unable to read data from $filename, aborting");

	$banned  = array();
	$blocked = array();

	foreach($file as $line) {

		$struct = explode("\t", $line);
		$type = $struct[0];
		$ip = $struct[1];
		$reason = $struct[2];
		$valid_until = $struct[3];
		if ($type == "ban") $banned[$ip] = array( "reason" => $reason, "valid_until" => $valid_until);
		elseif ($type == "block") $blocked[$ip] = array( "reason" => $reason, "valid_until" => $valid_until);
	}


        $output = "<table class=\"flood\">\n";
        $output .= "<tr><th colspan=\"4\" class=\"table_name\">Banned IP addresses</th></tr>\n";
        $output .= "   <tr><th class=\"flood_th\">Commands</th><th class=\"flood_th\">IP</th><th class=\"flood_th\">
Error</th><th class=\"flood_th\">Valid Until</th></tr>\n";

	foreach($banned as $ip => $struct) {
		$output .= "   <tr><td class=\"commands\">";
	        $time = time();
        	$remaining_seconds = $banned[$ip]["valid_until"] - $time;
        	$remaining_string = secondsToTime($remaining_seconds);
		$output .= add_commands($config, $ip);
		$output .= "</td><td>$ip</td><td>" . $banned[$ip]["reason"] . "</td><td>" . $remaining_string . "</td></tr>\n";
	}

	$output .= "</table>\n";

	$output .= "<table class=\"flood\">\n";
        $output .= "<tr><th colspan=\"4\" class=\"table_name\">Blocked IP addresses</th></tr>\n";
        $output .= "   <tr><th class=\"flood_th\">Commands</th><th class=\"flood_th\">IP</th><th class=\"flood_th\">Error</th><th class=\"flood_th\">Valid Until</th></tr>\n";

        foreach($blocked as $ip => $struct) {
                $output .= "   <tr><td class=\"commands\">";
                $time = time();
                $remaining_seconds = $blocked[$ip]["valid_until"] - $time;
                $remaining_string = secondsToTime($remaining_seconds);

		$output .= add_commands($config, $ip);
                $output .= "</td><td>$ip</td><td>" . $blocked[$ip]["reason"] . "</td><td>" . $remaining_string . "</td></tr>\n";
        }

	$output .= "</table>\n";
	
	return $output;

}

function add_commands($config, $ip) {
	$output = "";
        $output .= '<a href="bannedip.php?ip=' . $ip . '&amp;action=delete">	<i style="padding-right: 10px;" class="fas fa-trash-alt fa-2x"></i></a>';
        $output .= '<a href="bannedip.php?ip=' . $ip . '&amp;action=redo">	<i style="padding-right: 10px;" class="fas fa-redo fa-2x"></i></a>';
	$output .= '<a href="bannedip.php?ip=' . $ip . '&amp;action=oneday">    <i style="padding-right: 10px;" class="fas fa-calendar-day fa-2x"></i></a>';
	$output .= '<a href="bannedip.php?ip=' . $ip . '&amp;action=onemonth">	<i style="padding-right: 10px;" class="fas fa-calendar-plus fa-2x"></i></a>';
 	$output .= '<a href="bannedip.php?ip=' . $ip . '&amp;action=forever">	<i class="fas fa-infinity fa-2x"></i></a>';

	return $output;
}

function secondsToTime($inputSeconds) {
    $secondsInAMinute = 60;
    $secondsInAnHour = 60 * $secondsInAMinute;
    $secondsInADay = 24 * $secondsInAnHour;

    // Extract days
    $days = floor($inputSeconds / $secondsInADay);

    // Extract hours
    $hourSeconds = $inputSeconds % $secondsInADay;
    $hours = floor($hourSeconds / $secondsInAnHour);

    // Extract minutes
    $minuteSeconds = $hourSeconds % $secondsInAnHour;
    $minutes = floor($minuteSeconds / $secondsInAMinute);

    // Extract the remaining seconds
    $remainingSeconds = $minuteSeconds % $secondsInAMinute;
    $seconds = ceil($remainingSeconds);

    // Format and return
    $timeParts = [];
    $sections = [
        'day' => (int)$days,
        'hour' => (int)$hours,
        'minute' => (int)$minutes,
        'second' => (int)$seconds,
    ];

    foreach ($sections as $name => $value){
        if ($value > 0){
            $timeParts[] = $value. ' '.$name.($value == 1 ? '' : 's');
        }
    }

    return implode(', ', $timeParts);
}

function check_valid_ip($ip) {

	$struct = explode(".", $ip);

	for( $k = 0; $k < 4; $k++) {
		if ( (!is_numeric($struct[$k])) or ($struct[$k] < 0) or ($struct[$k] > 255)) return FALSE;
	}

	return $ip;

}

function delete_ip($config, $ip) {
	$filename = $config["settings"]["bandb"];

        $file = file($filename);
        if (!$file) error_and_exit("Unable to read data from $filename, aborting");

	$newlines = array();

	foreach($file as $line) {
		if (strpos($line, $ip) === false) $newlines[] = $line;
	}

	if(!file_put_contents($filename, $newlines)) error_and_exit("Unable to write data into $filename, aborting");

}

function redo_ban($config, $ip, $type) {
	$filename = $config["settings"]["bandb"];

        $file = file($filename);
        if (!$file) error_and_exit("Unable to read data from $filename, aborting");

        $newlines = array();

        foreach($file as $line) {
		if (strpos($line, $ip) !== false) {
			$struct = explode("\t", $line);
			if ($struct[0] == "ban") $banlimit = $config["Rules"]["Ban"]["ban_duration"];
			if ($struct[0] == "block") $banlimit = $config["Rules"]["Block"]["ban_duration"];
			if ($type == 0) $valid_until = time() + $banlimit;
			elseif ($type == 1) $valid_until = time() * 2;
			elseif ($type == 2) $valid_until = time() + (86400*30);
			elseif ($type == 3) $valid_until = time() + 86400;
			$time = time();
			$line = $struct[0] . "\t" . $struct[1] . "\t" . $struct[2] . "\t" . $valid_until . "\t" . $time . "\n";
		}
		$newlines[] = $line;
        }

        if(!file_put_contents($filename, $newlines)) error_and_exit("Unable to write data into $filename, aborting");
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


?>
