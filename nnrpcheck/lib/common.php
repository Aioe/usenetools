<?php

function check_whether_to_start($config,$program) {
        $pid_directory = $config["settings"]["pid_directory"];
        $pid_file = $pid_directory . "/" . $program;
        // if a pid file doesn'exist,
        if (!file_exists($pid_file)) {
                // get my pid
                $currentpid = getmypid();
                // put my pid in a pidfile or log and exit
                if(!file_put_contents($pid_file, $currentpid)) {
                        log_string("crit", "Unable to create PID file $pid_file");
                        return FALSE;
                }
                // otherwise return TRUE and program starts
                return TRUE;
        }
        
        // if pid file exists refuses to start
        $oldpid = file_get_contents($pid_file);
        log_string("err", "Unable to start: PID file $pid_file esists and says $oldpid");
        return FALSE;
}

function log_string($facility, $string)
{
        openlog('NNRP_Check', LOG_PID, LOG_NEWS);

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

function say_bye($config, $program) 
{
        $pidfile = $config["settings"]["pid_directory"] . "/" . $program;
        if (!unlink($pidfile)) {
                log_string("err", "Unable to delete pid file $pidfile");
                exit(6);
        }
	exit(5);
}

?>
