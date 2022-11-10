<?php

require_once ('../../conf.php');
require_once ('../../utils.php');

$dateFirstDay = date('Y-m-d', strtotime('yesterday')).' 00:00:00';
$dateLastDay = date('Y-m-d', strtotime('yesterday')).' 23:59:59';

$dateTime = new DateTime($dateFirstDay);
$date = $dateTime->format('Y-m-d');

for($i=0; $i<count($allTransports); $i++){
    for($j=0; $j<count($allLines[$i]); $j++){
        $item = [];

        $transport = $allTransports[$i];
        $line = $allLines[$i][$j];
        $transportLine = $transport.$line;

        $totalTime = getDisruptionsTimeInSeconds($transportLine, $dateFirstDay, $dateLastDay, $mysqli);

        $insertDailyReportSQL = 'INSERT INTO `daily_reports` (`id`, `transport_line`, `transport`, `line`, `date`, `first_day_time`, `last_day_time`, `total_time`) VALUES (NULL, "'.$transportLine.'", "'.$transport.'", "'.$line.'", "'.$date.'", "'.$dateFirstDay.'", "'.$dateLastDay.'", "'.$totalTime.'")';
                
        if ($mysqli->query($insertDailyReportSQL) === TRUE) {
            writeLog('[OK] INSERT DAILY REPORT', $mysqli->insert_id);
        } else {
            writeLog('[KO] INSERT DAILY REPORT : '.$insertDailyReportSQL, $mysqli->error);
        }
    }
}

?>