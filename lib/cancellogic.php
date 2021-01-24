<?php

function cancel_by_messageid($mid, $host, $newshost, $sendnocem, $configfile) {

	$status = array();
	$nnrp = connect_nntp($host, 119, 1);
	if (!$nnrp) error_and_exit("Unable to connect $host!");

	$header = get_HDR($nnrp, "Xref", $mid);
	if (!$header) error_and_exit("Unable to get HDR (Xref)");

	$structa = explode(" ", $header);
	$structb = explode(":", $structa[1]);
	$group  = $structb[0];
	$number = $structb[1];

	$last = get_GROUP($nnrp, $group);
        if (!$last) error_and_exit("Unable to set group: $group");
        $xover = get_OVER($nnrp, $number);
        if (!$xover) error_and_exit("Unable to get overview for $number");
	$xover["Group"] = $group;	

        $cancel = create_cancel($xover, $group);        

	$header = get_HDR($nnrp, "Path", $mid);
        $xover["Path"] = trim($header);


        $nntp = connect_nntp($newshost, 119, 0);
	if (!$nntp) error_and_exit("Unable to connect $newshost!");

	$status[] = "CANCEL: Message $mid cancelled";

        $success = send_IHAVE($nntp, $cancel["cancel"], $cancel["mexmid"]);
        if(!$success) error_and_exit("Unable to send cancel message");
        quit_nntp($nntp);

//////////////

	$k = 1;
        $nocem = add_nocem_entry($xover, "Message cancelled because it's an abuse");
	if ($sendnocem == 1 ) $exit = build_nocem($nocem, $k, $newshost, $configfile, 1 );

	$exit_strings = array_merge($status, $exit);
        quit_nntp($nnrp);
	exit_page($exit_strings);
}

function cancel_by_range($group, $start, $end, $host, $newshost, $sendnocem, $configfile) {

	$nnrp = connect_nntp($host, 119, 1);
        if (!$nnrp) error_and_exit("Unable to connect $host!");

	$articleslist = get_LISTGROUP($nnrp, $group, $start, $end);
	if ($articleslist === false) error_and_exit("Unable to get LISTGROUP output");
	if (is_null($articleslist)) error_and_exit("No messages to cancel between $group:$start and $group:$end");

	$status = array();
	$nocem = "";

	$k = 0;
	foreach( $articleslist as $number) {
		$nntp = connect_nntp($newshost, 119, 0);
	        if (!$nntp) error_and_exit("Unable to connect $newshost!");
		$xover = get_OVER($nnrp, $number);
		if (!$xover) error_and_exit("Unable to get overview for $number");
		$xover["Group"] = $group;
////////////
		$mycancel = create_cancel($xover, $group);
		$midtocancel = $xover["Mid"];

		$text = $mycancel["cancel"];
		$cancelmid = $mycancel["mexmid"];

		$success = send_IHAVE($nntp, $text, $cancelmid);
        	if(!$success) error_and_exit("Unable to send cancel message");
		$status[] = "CANCEL: $group:$number article $midtocancel cancelled";

		quit_nntp($nntp);
////////////
		if ($sendnocem == 1) {
			$header = get_HDR($nnrp, "Path", $midtocancel);
        		$xover["Path"] = trim($header);
			$nocem .= add_nocem_entry($xover, "Message cancelled because it's an abuse");
			$k++;
		}
		
	}

	if ($sendnocem == 1 ) $exit = build_nocem($nocem, $k, $newshost, $configfile, 1 );

	$exit_strings = array_merge($status, $exit);
	quit_nntp($nnrp);
	exit_page($exit_strings);

}

function cancel_by_value($group, $header, $value, $host, $newshost, $sendnocem, $configfile) {
	// STEP 1: Establish connection with NNRP server
 	$nnrp = connect_nntp($host, 119, 1);
        if (!$nnrp) error_and_exit("Unable to connect $host!");

	// STEP 2: Set group
	$last = get_GROUP($nnrp, $group);
        if (!$last) error_and_exit("Unable to read group $group");

	// STEP 3: read XPAT
	$numbers = get_XPAT($nnrp, $header, "1-", $value);
        if ($numbers === false) error_and_exit("Error in XPAT reply");
	if (is_null($numbers)) error_and_exit("$header doesn't match $value inside any message");

	// STEP 4: Process all articles
	$status = array();
	$k = 0;
	foreach($numbers as $number) {
                // STEP 4.1: leggo l'overview
                $xover = get_OVER($nnrp, $number);
                if (!$xover) error_and_exit("Unable to get overview for $number");
                $xover["Group"] = $group;
		$mid = $xover["Mid"];

                // STEP 4.2: leggo il path
                $header = get_HDR($nnrp, "Path", $mid);
                $xover["Path"] = trim($header);

                // STEP 4.3: creo il cancel message
                $status[] = "CANCEL: $group:$number message $mid deleted";

                // STEP 4.3.1: apro la connessione col news server
                $nntp = connect_nntp($newshost, 119, 0);
                if (!$nntp) error_and_exit("Unable to connect $newshost!");

                // STEP 4.3.2: creo il cancel message
                $mycancel = create_cancel($xover, $group);
                $midtocancel = $xover["Mid"];
                $text = $mycancel["cancel"];
                $cancelmid = $mycancel["mexmid"];
                
                // STEP 4.3.3: invio il cancel
                $success = send_IHAVE($nntp, $text, $cancelmid);
                if(!$success) error_and_exit("Unable to send cancel message");

                quit_nntp($nntp);

                // STEP 4.4: creo la bag nocem
                if ($sendnocem == 1) {
                        $nocem .= add_nocem_entry($xover, "Message cancelled because it's an abuse");
                        $k++;
                }
        }

        quit_nntp($nnrp);

        // STEP 5: Creo ed invio il messaggio NOCEM
        if ($sendnocem == 1 ) {
                $exit = build_nocem($nocem, $k, $newshost, $configfile, 1 );
        }
        $status = array_merge($status, $exit);
        exit_page($status);

}

function cancel_by_header($mid, $headertocancel, $host, $newshost, $sendnocem, $configfile) {
	$status = array();
	// STEP 1: apro la connessione col news server (NNRP)
	$nnrp = connect_nntp($host, 119, 1);
        if (!$nnrp) error_and_exit("Unable to connect $host!");
	
	// STEP 2: estraggo Newsgroups:
	$header = get_HDR($nnrp, "Xref", $mid);
        if (!$header) error_and_exit("Unable to get HDR (Xref)");
        $struct = explode(" ", $header);
        $data = $struct[1];
        $struct = explode(":", $data);
        $group = $struct[0];
	$number = trim($struct[1]);
	
	// STEP 3: trovo il pattern
	if ($headertocancel == "References") $pattern = $mid;
	elseif ($headertocancel == "From") {
		$last = get_GROUP($nnrp, $group);
        	if (!$last) error_and_exit("Unable to read group $group");
		$xover = get_OVER($nnrp, $number);
                if (!$xover) error_and_exit("Unable to get overview for $number");
		$pattern = $xover[$headertocancel];
	}
	// STEP 4: seleziono il gruppo
	$last = get_GROUP($nnrp, $group);
        if (!$last) error_and_exit("Unable to read group $group");

	// STEP 5: verifico quali messaggi nel gruppo contengono il mid in References
	$numbers = get_XPAT($nnrp, $headertocancel, "1-", $pattern);
	if ($numbers === false) error_and_exit("Error in XPAT reply ($headertocancel:" . htmlentities($pattern) .   ")");
	if (is_null($numbers)) error_and_exit("No messages include " . htmlentities($pattern) . " inside References header");

	// Aggiungo all'elenco il messaggio in testa al thread
	if ($headertocancel == "References") $numbers[] = $number; 

	// STEP 6: Per ciascun messaggio da cancellare:
	$k = 0;
	foreach($numbers as $number) {
		// STEP 6.1: leggo l'overview
		$xover = get_OVER($nnrp, $number);
                if (!$xover) error_and_exit("Unable to get overview for $number");
                $xover["Group"] = $group;

		// STEP 6.2: leggo il path
		$header = get_HDR($nnrp, "Path", $mid);
                $xover["Path"] = trim($header);

		// STEP 6.3: creo il cancel message
		$status[] = "CANCEL: $group:$number message $mid deleted";

		// STEP 6.3.1: apro la connessione col news server
		$nntp = connect_nntp($newshost, 119, 0);
                if (!$nntp) error_and_exit("Unable to connect $newshost!");

		// STEP 6.3.2: creo il cancel message
                $mycancel = create_cancel($xover, $group);
                $midtocancel = $xover["Mid"];
                $text = $mycancel["cancel"];
                $cancelmid = $mycancel["mexmid"];
		
		// STEP 6.3.3: invio il cancel
                $success = send_IHAVE($nntp, $text, $cancelmid);
                if(!$success) error_and_exit("Unable to send cancel message");

		quit_nntp($nntp);

		// STEP 6.4: creo la bag nocem
		if ($sendnocem == 1) {
                	$nocem .= add_nocem_entry($xover, "Message cancelled because it's an abuse");
			$k++;
		}
	}

	quit_nntp($nnrp);

	// STEP 7: Creo ed invio il messaggio NOCEM
	if ($sendnocem == 1 ) {
		$exit = build_nocem($nocem, $k, $newshost, $configfile, 1 );
	}
	$status = array_merge($status, $exit);
	exit_page($status);
}

function cancel_by_body($group, $pattern, $host, $newshost, $sendnocem, $configfile) {
	$status = array();
        // STEP 1: apro la connessione col news server (NNRP)
        $nnrp = connect_nntp($host, 119, 1);
        if (!$nnrp) error_and_exit("Unable to connect $host!");

	// STEP 2: trovo tutti gli articoli nel gruppo
	$articleslist = get_LISTGROUP($nnrp, $group, "1", "");
        if ($articleslist === false) error_and_exit("Unable to get LISTGROUP output");
        if (is_null($articleslist)) error_and_exit("No messages to cancel in the group $group");

	$nocem = "";

	$k = 0;
	foreach($articleslist as $number) {
		$body = get_BODY($nnrp, $number, 1);
		if (preg_match("/$pattern/i", $body)) {
			// STEP 3.1: leggo l'overview
                	$xover = get_OVER($nnrp, $number);
                	if (!$xover) error_and_exit("Unable to get overview for $number");
                	$xover["Group"] = $group;

                	// STEP 3.2: leggo il path
                	$header = get_HDR($nnrp, "Path", $mid);
                	$xover["Path"] = trim($header);
			$mid = $xover["Mid"];

                	// STEP 3.3: creo il cancel message
                	$status[] = "CANCEL: $group:$number message $mid deleted";

                	// STEP 3.3.1: apro la connessione col news server
                	$nntp = connect_nntp($newshost, 119, 0);
                	if (!$nntp) error_and_exit("Unable to connect $newshost!");

                	// STEP 3.3.2: creo il cancel message
                	$mycancel = create_cancel($xover, $group);
                	$midtocancel = $xover["Mid"];
                	$text = $mycancel["cancel"];
                	$cancelmid = $mycancel["mexmid"];

	                // STEP 3.3.3: invio il cancel
        	        $success = send_IHAVE($nntp, $text, $cancelmid);
                  	if(!$success) error_and_exit("Unable to send cancel message");

                	quit_nntp($nntp);

                	// STEP 3.4: creo la bag nocem
                	if ($sendnocem == 1) {
                        	$nocem .= add_nocem_entry($xover, "Message cancelled because it's an abuse");
                        	$k++;
                	}
		}
	}

	// STEP 4: Creo ed invio il messaggio NOCEM
        if ($sendnocem == 1 ) {
                $exit = build_nocem($nocem, $k, $newshost, $configfile, 1);
        }
        $status = array_merge($status, $exit);
        exit_page($status);

	quit_nntp($nnrp);

}


function exit_page($status) {
        include("inc/head.php");
	echo "<div class=\"landingmessages\">";
	echo "<div class=\"landinghead\">Server replies</div>";
	echo "<pre>\n";
	
	$k = 1;
	foreach($status as $line ) {
		printf("%03d: ", $k);
		echo htmlentities($line) . "\n";
		$k++;
	}

	$refer = "";
	

	if (isset($_GET["group"])) {
		$group = $_GET["group"];
		$number = $_GET["number"];
		$refer = "$pagename?group=$group&amp;number=$number";
	} 

	if (!isset($_GET["group"])) $refer = $_SERVER['HTTP_REFERER'];

	echo "</pre>";
	echo "<div class=\"landingtail\"><a href=\"$refer\">Go back</a></div>\n</div>\n";
        include("inc/tail.php");

	exit(0);
}

function check_messageid($string) {
	if (!isset($_POST[$string])) error_and_exit("A Message-ID to cancel is needed");
        $mid = $_POST[$string];
        if (strpos($mid, "@") === false) error_and_exit("Message-ID $mid doesn't include '@'"); 
        if (strpos($mid, ".") === false) error_and_exit("Message-ID $mid doesn't include a dot");
        if ($mid[0] != '<') error_and_exit("Message-ID $mid doesn't start with '<'");
        if (strpos($mid, ">") === false) error_and_exit("Message-ID $mid doesn't end with '<'");
        preg_match( '/\<(.+)\>/', $mid, $matches);
        $mid = "<" . $matches[1] . ">";
	return $mid;
}

?>
