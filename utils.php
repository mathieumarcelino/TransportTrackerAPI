<?php

$metro = array(
    '1' => 'C01371',
    '2' => 'C01372',
    '3' => 'C01373',
    '3bis' => 'C01386',
    '4' => 'C01374',
    '5' => 'C01375',
    '6' => 'C01376',
    '7' => 'C01377',
    '7bis' => 'C01387',
    '8' => 'C01378',
    '9' => 'C01379',
    '10' => 'C01380',
    '11' => 'C01381',
    '12' => 'C01382',
    '13' => 'C01383',
    '14' => 'C01384',
);

$rer = array(
    'A' => 'C01742',
    'B' => 'C01743',
    'C' => 'C01727',
    'D' => 'C01728',
    'E' => 'C01729',
);

$tramway = array(
    '1' => 'C01389',
    '2' => 'C01390',
    '3a' => 'C01391',
    '3b' => 'C01679',
    '4' => 'C01843',
    '5' => 'C01684',
    '6' => 'C01794',
    '7' => 'C01774',
    '8' => 'C01795',
    '9' => 'C02317',
    '11' => 'C01999',
    '13' => 'C02344',
);

$transilien = array(
    'H' => 'C01737',
    'J' => 'C01739',
    'K' => 'C01738',
    'L' => 'C01740',
    'N' => 'C01736',
    'P' => 'C01730',
    'R' => 'C01731',
    'U' => 'C01741',
);

$allTransports = ['metro', 'rer', 'tramway', 'transilien'];
$allLines = [['1', '2', '3', '3bis', '4', '5', '6', '7','7bis', '8', '9', '10', '11', '12', '13', '14'], ['A', 'B', 'C', 'D', 'E'], ['1', '2', '3a', '3b', '4', '5', '6', '7', '8', '9', '11', '13'], ['H', 'J', 'K', 'L', 'N', 'P', 'R', 'U']];

function keyToLowerArr($arr){
    return array_map(function($item){
        if(is_array($item))
            $item = keyToLowerArr($item);
        return $item;
    },array_change_key_case($arr));
}

function formatDate($date){
    $formatedDate = new DateTime($date);
    $formatedDate->setTimezone(new DateTimeZone('Europe/Paris'));
    return $formatedDate->format('Y-m-d H:i:s');
}

function formatDate10MinLess($date){
    $formatedDate = new DateTime($date);
    $formatedDate->modify('-10 minutes');
    return $formatedDate->format('Y-m-d H:i:s');
}

function getTypeOfMessage($str){
    $str = strtolower($str);
    if (strpos($str, 'travaux')){
        return 'travaux';
    } else if (strpos($str, 'incident d\'exploitation')) {
        return 'incident_exploitation';
    } else if (strpos($str, 'difficultés d\'exploitation')) {
        return 'difficultes_exploitation';
    } else if (strpos($str, 'voyageur')){
        return 'malaise_voyageur';
    } else if (strpos($str, 'incident technique')){
        return 'incident_technique';
    } else if (strpos($str, 'divers incidents')){
        return 'divers_incidents';
    } else if (strpos($str, 'incident')){
        return 'autre_incident';
    } else if (strpos($str, 'panne de signalisation')){
        return 'panne_de_signalisation';
    } else if (strpos($str, 'panne mécanique')){
        return 'panne_mecanique';
    } else if (strpos($str, 'panne électrique')){
        return 'panne_electrique';
    } else if (strpos($str, 'train en panne') || strpos($str, 'métro en panne') || strpos($str, 'tram en panne') || strpos($str, 'panne de train') || strpos($str, 'panne de métro') || strpos($str, 'panne de tram')){
        return 'train_en_panne';
    } else if (strpos($str, 'panne')){
        return 'autre_panne';
    } else if (strpos($str, 'bagage') || strpos($str, 'colis')){
        return 'bagage_oublie';
    } else if (strpos($str, 'social')){
        return 'mouvement_social';
    } else if (strpos($str, 'obstacle')){
        return 'obstacle_sur_la_voie';
    } else if (strpos($str, 'personnes')){
        return 'personnes_sur_les_voies';
    } else if (strpos($str, 'alarme')){
        return 'signal_d_alarme';
    } else if (strpos($str, 'malveillance')){
        return 'acte_de_malveillance';
    } else if (strpos($str, 'forces de l\'ordre')){
        return 'forces_de_l_ordre';
    } else if (strpos($str, 'accident grave')){
        return 'accident_grave';
    } else if (strpos($str, 'régulation')){
        return 'regulation';
    } else if (strpos($str, 'bagarre')){
        return 'bagarre';
    } else if (strpos($str, 'manifestation') || strpos($str, 'manif')){
        return 'manifestation';
    } else {
        return 'autre';
    }
}

function writeLog($subject, $data){
    $date = date('Y-m-d H:i:s');
    $file = fopen('../../log/log.txt','a+');
    $str = $date." - ".$subject." - ".$data."\n";
    fwrite($file,$str);
    fclose($file);
}

function getDisruptionsTimeInSeconds($line, $dateFirstDay, $dateLastDay, $mysqli){
    $time = [];
    $disruptionTimeInSeconds = 0;

    $sql = $mysqli->query('SELECT `start_time`, `end_time` FROM `disruptions` WHERE `transport_line` = "'.$line.'" AND ((`start_time` BETWEEN "'.$dateFirstDay.'" AND "'.$dateLastDay.'") OR (`end_time` BETWEEN "'.$dateFirstDay.'" AND "'.$dateLastDay.'"))');
    while ($row = $sql->fetch_assoc()){
        $found = FALSE;
        for($i=0; $i<count($time); $i++){
            if($row['start_time'] < $time[$i][0] && $row['end_time'] > $time[$i][0] && $row['end_time'] < $time[$i][1]){
                $time[$i][0] = $row['start_time'];
                $found = TRUE;
            }
            else if ($row['end_time'] > $time[$i][1] && $row['start_time'] > $time[$i][0] && $row['start_time'] < $time[$i][1]){
                $time[$i][1] = $row['end_time'];
                $found = TRUE;
            }
            else if ($row['start_time'] < $time[$i][0] && $row['end_time'] > $time[$i][1]){
                $time[$i][0] = $row['start_time'];
                $time[$i][1] = $row['end_time'];
                $found = TRUE;
            }
            else if ($row['start_time'] > $time[$i][0] && $row['end_time'] < $time[$i][1]){
                $found = TRUE;
            }
        }
        if($found == FALSE){
            array_push($time, [$row['start_time'], $row['end_time']]);
        }
    }

    if(!empty($time)){
        if($time[0][0] < $dateFirstDay){
            $time[0][0] = $dateFirstDay;
        }
        if($time[count($time)-1][1] > $dateLastDay){
            $time[count($time)-1][1] = $dateLastDay;
        }

        for($i=0; $i<count($time); $i++){
            $diffInSeconds = strtotime($time[$i][1]) - strtotime($time[$i][0]);
            $disruptionTimeInSeconds += $diffInSeconds;
        }
    }

    return $disruptionTimeInSeconds;
}


?>