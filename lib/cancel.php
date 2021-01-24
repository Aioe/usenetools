<?php

function add_nocem_entry($xover, $comment)
{
        $sender         = $xover["From"];
        $date           = $xover["Date"];
        $subject        = $xover["Subject"];
        $path           = $xover["Path"];
        $groups         = $xover["Group"];
        $mid            = $xover["Mid"];


        $elem = explode("!", $path);
        $rev_path = array_reverse($elem);
        $short_path = "";

        $num = count($elem);
        if ($num >= 4) $num = 4;
        $num--;

        for ($n = $num; $n >= 0; $n--) $short_path .= "!$rev_path[$n]";

	$short_path = preg_replace("/!!/", "!", $short_path );

        $nocem = "#\tSender: $sender\n#\tDate: $date\n#\tSubject: $subject\n#\tPath: $short_path\n#\tReason: $comment\n$mid $groups\n";

        return $nocem;
}

function add_nocem_bag($count, $id, $key, $owner, $expire) {

        $str = <<<EOD
Aioe.org issues cancel messages in the NoCem format against USENET articles that 
include spam or other abuses posted on Italian groups or on the aioe.* hierarchy

This NoCem bag was signed with following the GPG key:
Owner:       $owner
Fingerprint: $key
Expire:      $expire

The (public) GPG key needed to verify the signature of *all* cancels issued by 
aioe.org is available at http://news.aioe.org/hierarchy/nocem.txt

Those who need to report messages erroneously cancelled by the cancelbot should 
contact usenet@aioe.org


@@BEGIN NCM HEADERS
Version: 0.93
Issuer: nocem@aioe.org
Type: aioe-spam
Action: hide
Count: $count
Notice-ID: $id
@@BEGIN NCM BODY

EOD;

        return $str;
}

function sign_bag($bag, $conf) {
        $filename = tempnam("/tmp", "acancelbotbag");
        $file = fopen($filename, "w+");
        if (!$file) {
                error_and_exit("Unable to create tmp file: $filename");
                exit(5);                
        }

        fputs($file, $bag);
        fclose($file);

        $cli = $conf["Settings"]["gpg"] . " $filename";

        exec($cli, $output, $retvalue);

	$sm = $conf["Settings"]["sm"];

        if ($retvalue != 0) {
                error_and_exit("Unable to execute $cli: error $retvalue, aborting");
                exit($retvalue);
        }

        $fileasc = $filename . ".asc";
        $signed_bag = file_get_contents($fileasc);
        unlink($filename);
        unlink($fileasc);

        return $signed_bag;
}



function build_nocem_message($nocem, $id, $count) {
        $elem1 = rand(0, 999999);
        $elem2 = rand(0, 999999);
        $elem3 = rand(0, 999999);

        $subject = "";
        if ($count == 1) $subject = "(1 article)";
        else $subject = "($count articles)";

        $mid = "<$elem1$elem2$elem3@nocem.aioe.org>";

        $date = date("r");
        $str = <<<EOD
From: Aioe.org Public News Server (NOCEM Service) <nocem@aioe.org>
Newsgroups: aioe.news.nocem,news.lists.filters
Subject: @@NCM NoCeM notice $id aioe-spam/hide $subject
Date: $date
Path: not-for-mail
Followup-To: news.admin.net-abuse.usenet,aioe.news.helpdesk
Content-Type: text/plain; charset=utf-8
Message-ID: $mid

$nocem

EOD;

        return $str;

}

function build_nocem($nocem, $count, $newshost, $configfile, $sendbag ) {

	$status = array();
	$conf = parse_ini_file($configfile, true);
	$nocemtoshow = trim($nocem);
	$status[] = "NOCEM: Nocem data follows:\n$nocemtoshow";
        $id  = substr(md5(rand()), 0, 7);
        $id2 = substr(md5(rand()), 0, 7);
        $nocem_bag = add_nocem_bag($count, $id, $conf["Settings"]["key"], $conf["Settings"]["owner"], $conf["Settings"]["expire"]);
        $nocem_bag .= $nocem;
        $nocem_bag .= "@@END NCM BODY\n";
        $nocem_signed_bag = sign_bag($nocem_bag, $conf);
        $nocem_cancel = build_nocem_message($nocem_signed_bag, $id, $count);
        $nntp = connect_nntp($newshost, 119, 0);
        if (!$nntp) error_and_exit("Unable to connect $newshost!");
        $cancelmid = "<$id$id2@$id.aioe.org>";
	if ($sendbag == 1) {
        	$success = send_IHAVE($nntp, $nocem_cancel, $cancelmid);
        	if(!$success) error_and_exit("Unable to send NOCEM message");
		$status[] = "NOCEM: message $cancelmid sent";
	} else {
		$status[] = "NOCEM: running in TEST MODE, message was NOT sent";
	}
       	quit_nntp($nntp);
	return $status;
}

function calculate_article_sequence($fh, $number) {

        $listgroup = get_LISTGROUP($fh, "", 0, 0);
        $k = 0;

        foreach($listgroup as $article) {
                $k++;
                if ($article == $number) {
                        break;
                }
        }

        $total = count($listgroup);

        $next = 0;
        $k--;
        if ($k == $total) {             // se è l'ultimo
                $next = $number;
                $prev = $listgroup[$k-1];
        } elseif ($k == 0) {
                $prev = $number; // se è il primo
                $next = $listgroup[1];
        } else {
                $prev = $listgroup[$k-1];

                if (isset($listgroup[$k+1])) $next = $listgroup[$k+1];
                if (!isset($listgroup[$k+1])) $next = $number; // se il primo è stato cancellato
        }

        $output["prev"] = $prev;
        $output["next"] = $next;
        return $output;
}

function create_cancel($xover,$group) {

        $current_date = date(DATE_RFC822);
        $old_newsgroups = $xover["Newsgroups"];
        $old_from = $xover["From"];
        $mid = $xover["Mid"];
        $mexmid = random_string(48) . "@control.aioe.org";
        $old_newsgroups = $group;
        $old_date = $xover["Date"];

        $str = <<<EOD
Date: $current_date (CET)
References: $mid
Subject: a SPAM cancel from aioe.org
Lines: 7
X-Complaints-To: abuse@aioe.org
Newsgroups: $old_newsgroups
Control: cancel $mid
From: $old_from
Message-ID: <$mexmid>
Path: control.aioe.org!cyberspam!.POSTED!not-for-mail

This cancel message deletes the USENET post identified by the Message-ID
'$mid' and sent to
the USENET newsgroup '$group' on $old_date
because it seems abusive to some aioe.org administrator.  Since this 
message is issued by hands, those who need to know why it was marked as
an abuse have to write to usenet@aioe.org

.
EOD;

        $output["cancel"] = $str;
        $output["mexmid"] = "<$mexmid>";
        return $output;
}

?>
