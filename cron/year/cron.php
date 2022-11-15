<?php

require_once ('../../conf.php');
require_once ('../../utils.php');

$dateFirstDay = date('Y-m-d', strtotime('first day of january last year')).' 00:00:00';
$dateLastDay = date('Y-m-d', strtotime('last day of december last year')).' 23:59:59';

$dateTime = new DateTime($dateFirstDay);
$year = $dateTime->format('Y');

for($i=0; $i<count($allTransports); $i++){
    for($j=0; $j<count($allLines[$i]); $j++){
        $item = [];

        $transport = $allTransports[$i];
        $line = $allLines[$i][$j];
        $transportLine = $transport.$line;

        $totalTime = getDisruptionsTimeInSeconds($transportLine, $dateFirstDay, $dateLastDay, $mysqli);

        $insertYearlyReportSQL = 'INSERT INTO `yearly_reports` (`id`, `transport_line`, `transport`, `line`, `year`, `first_day_time`, `last_day_time`, `total_time`) VALUES (NULL, "'.$transportLine.'", "'.$transport.'", "'.$line.'", "'.$year.'", "'.$dateFirstDay.'", "'.$dateLastDay.'", "'.$totalTime.'")';
                
        if ($mysqli->query($insertYearlyReportSQL) === TRUE) {
            writeLog('report', '[OK] INSERT YEARLY REPORT', $mysqli->insert_id);
        } else {
            writeLog('report', '[KO] INSERT YEARLY REPORT : '.$insertYearlyReportSQL, $mysqli->error);
        }
    }
}

?>