<?php
require_once ('../../conf.php');
require_once ('../../utils.php');

$dateFirstDay = date('Y-m-d', strtotime('first day of last month')).' 00:00:00';
$dateLastDay = date('Y-m-d', strtotime('last day of last month')).' 23:59:59';

$dateTime = new DateTime($dateFirstDay);
$month = $dateTime->format('m');
$year = $dateTime->format('Y');

for($i=0; $i<count($allTransports); $i++){
    for($j=0; $j<count($allLines[$i]); $j++){
        $item = [];

        $transport = $allTransports[$i];
        $line = $allLines[$i][$j];
        $transportLine = $transport.$line;

        $totalTime = getDisruptionsTimeInSeconds($transportLine, $dateFirstDay, $dateLastDay, $mysqli);

        $insertMonthlyReportSQL = 'INSERT INTO `monthly_reports` (`id`, `transport_line`, `transport`, `line`, `month`, `year`, `first_day_time`, `last_day_time`, `total_time`) VALUES (NULL, "'.$transportLine.'", "'.$transport.'", "'.$line.'", "'.$month.'", "'.$year.'", "'.$dateFirstDay.'", "'.$dateLastDay.'", "'.$totalTime.'")';
                
        if ($mysqli->query($insertMonthlyReportSQL) === TRUE) {
            writeLog('[OK] INSERT MONTHLY REPORT', $mysqli->insert_id);
        } else {
            writeLog('[KO] INSERT MONTHLY REPORT : '.$insertMonthlyReportSQL, $mysqli->error);
        }
    }
}

?>