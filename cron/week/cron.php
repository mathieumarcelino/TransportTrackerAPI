<?php
require_once ('../../conf.php');
require_once ('../../utils.php');

$dateFirstDay = date('Y-m-d', strtotime('monday last week')).' 00:00:00';
$dateLastDay = date('Y-m-d', strtotime('sunday last week')).' 23:59:59';

$dateTime = new DateTime($dateFirstDay);
$week = $dateTime->format('W');
$year = $dateTime->format('Y');

for($i=0; $i<count($allTransports); $i++){
    for($j=0; $j<count($allLines[$i]); $j++){
        $item = [];

        $transport = $allTransports[$i];
        $line = $allLines[$i][$j];
        $transportLine = $transport.$line;

        $totalTime = getDisruptionsTimeInSeconds($transportLine, $dateFirstDay, $dateLastDay, $mysqli);

        $insertWeeklyReportSQL = 'INSERT INTO `weekly_reports` (`id`, `transport_line`, `transport`, `line`, `week`, `year`, `first_day_time`, `last_day_time`, `total_time`) VALUES (NULL, "'.$transportLine.'", "'.$transport.'", "'.$line.'", "'.$week.'", "'.$year.'", "'.$dateFirstDay.'", "'.$dateLastDay.'", "'.$totalTime.'")';
                
        if ($mysqli->query($insertWeeklyReportSQL) === TRUE) {
            writeLog('[OK] INSERT WEEKLY REPORT', $mysqli->insert_id);
        } else {
            writeLog('[KO] INSERT WEEKLY REPORT : '.$insertWeeklyReportSQL, $mysqli->error);
        }
    }
}

?>