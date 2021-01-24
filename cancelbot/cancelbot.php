<?php

$file = "/var/spool/news/outgoing/acancelbot";
$sleeptime = 60;

include("../lib/nntp.php");
include("../lib/aioe.php");

$last = 0;

while (true) {

        // PHP caches file information in order to provide faster
        // performance; In order to read the new filesize, we need
        // to flush this cache.
        clearstatcache(false, $file);

        // Get the current size of file
        $current = filesize($file);


        // First run, db must be created
        if ($last == 0) {

        }

        // No new lines
        if ($last == $current) {
		log_string("notice", "Nothing to do now");
                // Sleep till the next run
                sleep($sleeptime);
                continue;
        }


        // Reseted the file?
        if ($current < $last) {
		log_string("notice", "File $file was rotated");
                $last = $current;
        } // New contents
        elseif ($current > $last) {
                // Open the file in read mode
                $fp = fopen($file, 'r');
		if (!$fp) {
			log_string("crit", "Unable to read $file, aborting");
			exit(5);	
		}
		
		// Set the file position indicator to the last position
                fseek($fp, $last);

		$articles = array();
                // While not reached the end of the file
                while (! feof($fp)) {

                        // Read a line
                        $line = fgets($fp);	
			$articles[] = trim($line);
		}

                // Store the current position of the file for the next pass
                $last = ftell($fp);

                // Close the file pointer
                fclose($fp);

		if (count($articles) > 0) {
			// STEP 1: Connect with reader server
	        	$nnrp = connect_nntp($host, $port, 1);
        		if (!$nnrp) {
                		log_string("crit", "Unable to connect $host:$port, aborting");
                		exit(5);
        		}
			$tocancel = do_cancelbot_check($nnrp, $articles);

			quit_nntp($nnrp);
		} else	log_string("notice", "No messages need to be checked");
	}

}

function do_cancelbot_check($nnrp, $articles = array()) {
	
	$num = count($articles);
	log_string("notice", "Processing $num new messages");

	foreach($articles as $article) {
		if (strlen($article) < 3) continue;
		list($mid, $group, $number) = decode_newsfeed_line($article);
		log_string("notice", "Processing $group:$number $mid");
		if (!check_free_posting($nnrp, $group)) continue;



	}
}


function decode_newsfeed_line($line) {
	$struct = explode(" ", $line);
        $mid = $struct[0];
        $pp = explode(":", $struct[1]);
        $group = $pp[0];
        $number = trim($pp[1]);
	return array($mid, $group, $number);
}



?>
