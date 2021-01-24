<?php

$banlist = "/usr/system/news/cancelbot/banlist.conf";

include("inc/head.php");


$html = create_home_page($banlist);
echo $html;

include("inc/tail.php");


function create_home_page($banlist) {
	$html = "<table border=1>";

	$flags = array( "Header", "Value", "Groups", "Exclude", "Comment");

	$data = parse_ini_file($banlist, true);


	foreach( $data as $rule => $array ) {
		$html .= "<tr><th>$rule</th>\n";

		foreach($flags as $flag) {
			if (isset($data[$rule][$flag])) $html .= "<td>" . $data[$rule][$flag] . "</td>\n";
			if (!isset($data[$rule][$flag])) $html .= "<td>&nbsp;</td>\n";
		}
		$html .= "</tr>\n";
	}

	$html .= "</table>\n";
	return $html; 
}


?>
