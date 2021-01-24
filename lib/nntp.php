<?php


function connect_nntp($host, $port, $type)
{
        $fp = fsockopen ($host, $port, $errno, $errstr, 1);
        if (!$fp) return FALSE;
        $welcome = fgets($fp, 1024);
        if (!preg_match("/^200/", $welcome)) return FALSE;
	if ($type == 1) {
        	fputs($fp, "MODE READER\r\n");
        	$welcome = fgets($fp, 1024);
        	if (!preg_match("/^200/", $welcome)) return FALSE;
	}
        return $fp;
}

function get_GROUP($fh, $group) {
        fputs($fh, "GROUP $group\r\n");
        $banner = fgets($fh, 1024);
        if (!preg_match("/^211/", $banner)) return FALSE;
        $elems = explode(" ", $banner );
        $max = $elems[3];
        $min = $elems[2];
	return $max;
}

function get_BODY($fh, $number, $stato) {
	$output = "";
	$outputarray = array();
	fputs($fh, "BODY $number\r\n");
	$banner = fgets($fh, 1024);
	if (!preg_match("/^222/", $banner)) return FALSE;
	do {
                $line = fgets($fh, 8192);
		if (preg_match("/^\.\r\n$/", $line)) break;
		if ($stato == 0) $outputarray[] = $line;
		if (($stato == 1) or ($stato == 2)) {
			if ($stato == 1) $output .= $line;
			if ($stato == 2) $output .= htmlentities($line);
		}
        } while ($line);

	if ($stato == 0) return $outputarray;
	if (($stato == 1) or ($stato == 2)) return $output;
}

function get_OVER($fh, $number) {
	fputs($fh, "OVER $number\r\n");
	$banner = fgets($fh, 1024);
        if (!preg_match("/^224/", $banner)) return FALSE;
	$line = fgets($fh, 8192);
	$elems = explode("\t", $line );
        $xover = array();
	$xover["number"] 	  = $elems[0];
        $xover["Subject"]         = $elems[1];
        $xover["From"]            = $elems[2];
        $xover["Date"]            = $elems[3];
        $xover["Mid"]             = $elems[4];
        $xover["References"]      = $elems[5];
        $xover["Size"]            = $elems[6];
	$xover["Lines"]		  = $elems[7];

	$xrefcrude = explode(" ", $elems[8]);
	$xover["Xref"]		  = trim($xrefcrude[2]);

	do {
		$line = fgets($fh, 8192);
		if (preg_match("/^\.\r\n/", $line)) break;
	} while ($line);

	return $xover;
}

function get_LISTGROUP($fh, $group, $first, $last) {

	$command = "LISTGROUP";
	if (strlen($group) > 0) $command .= " $group"; 
	if (($first >= 0) and ($last > 0)) $command .= " $first-$last";

	$command .= "\r\n";

	fputs($fh, $command);
	$banner = fgets($fh, 1024);
        if (!preg_match("/^211/", $banner)) return FALSE;

	$listgroup = array();
	
	do {
                $line = fgets($fh, 8192);
                if (preg_match("/^\.\r\n/", $line)) break;
		$listgroup[] = trim($line);
        } while($line);

	if (count($listgroup) == 0) return NULL;

	return $listgroup;
}

function send_IHAVE($fp, $text, $mid) {

	$IHAVEcommand = "IHAVE" . " " . $mid . "\r\n";

	fputs($fp, $IHAVEcommand);
        $intro = fgets($fp, 18000);
        if (!preg_match("/^335/i", $intro)) {
		echo "\nCOMMAND: $IHAVEcommand\n";
		echo "BANNER: $intro\n";
		return FALSE;

	}
        $lines = explode("\n", $text);
        foreach($lines as $line) {
                $lung = strlen($line);
                if ($lung  > 0) {
                        fputs($fp, "$line\r\n");
                } else {
                        fputs($fp, "\r\n");
                }
        }

        fputs($fp, "\r\n.\r\n");

        $bpp = fgets($fp, 18000);
        if (!preg_match("/^235/", $bpp)) {
		echo "POST: $bpp\n";
		return FALSE;
	}
	return TRUE;

}

function get_HDR($fh, $header, $messageid) {

// HDR Xref <r59skv$e21$1@paganini.bofh.team>
// 225 Header information for Xref follows (from the article)
// 0 control.aioe.org aioe.test:3833
// .

	$command = "HDR $header $messageid\r\n";
	fputs($fh, $command);
	$banner = fgets($fh, 18000);
//	echo htmlentities("$command -> $banner");
	if (!preg_match("/^225/i", $banner)) return FALSE;

	
	$line = fgets($fh, 18000);
	$output = $line;

	$struct = explode(" ", $output, 2);
	$output = trim($struct[1]);

        do {
                $line = fgets($fh, 8192);
                if (preg_match("/^\.\r\n/", $line)) break;
        } while ($line);

	return $output;
}

//xpat references 1- *<r54u0n$16le$1@gioia.aioe.org>*
// 221 Header or metadata information for references follows (from overview)
function get_XPAT($fh, $header, $range, $string) {
	$command = "XPAT $header $range *$string*\r\n";
	fputs($fh, $command);
	$banner = fgets($fh, 18000);
//	echo htmlentities("$command -> $banner");

	if (!preg_match("/^221/i", $banner)) return FALSE;
	$numbers = array();

	do {
		$line = fgets($fh, 8192);
                if (preg_match("/^\.\r\n/", $line)) break;
		$struct = explode(" ", $line);
		$numbers[] = $struct[0];
	} while ($line);

	if (count($numbers) == 0) return NULL;

	return($numbers);
}


function quit_nntp($fh) {

	fputs($fh, "QUIT\r\n");
	$bpp = fgets($fh, 18000);	
	// 205

	fclose($fh);
}

function random_string(
    int $length = 64,
    string $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'): string {
    if ($length < 1) {
        throw new \RangeException("Length must be a positive integer");
    }
    $pieces = [];
    $max = mb_strlen($keyspace, '8bit') - 1;
    for ($i = 0; $i < $length; ++$i) {
        $pieces []= $keyspace[random_int(0, $max)];
    }
    return implode('', $pieces);
}

// list active aioe.test
// 215 Newsgroups in form "group high low status"
// aioe.test 0000003864 0000002655 y
// .

function check_free_posting($fh, $group) {

	if (
		(strpos($group, "control.") 		!== false) or
		(strpos($group, "aioe.news.nocem") 	!== false) or
		(strpos($group, "news.lists.filters") 	!== false)
	   ) {
		log_string("notice", "Group $group is a control group, skip check");
		return FALSE;
	}

        $command = "LIST ACTIVE $group\r\n";
        fputs($fh, $command);
        $banner = fgets($fh, 1024);
        if (!preg_match("/^215/", $banner)) {
                log_string("err", "Sent: '$command', received '$banner'");
                return FALSE; 
        }

        $line = fgets($fh, 1024);
        $struct = explode(" ", $line);
        $flag = trim($struct[3]); 
        $line = fgets($fh, 1024); // il punto

        if ($flag == "y") {
                log_string("notice", "Group $group is unmoderated");
                return TRUE;
        } elseif ($flag == "m") {
                log_string("notice", "Group $group is moderated, skip check");
                return FALSE;
        } elseif ($flag == "n") {
                log_string("notice", "Group $group is closed, skip check");
                return FALSE; 
        }
}



?>
