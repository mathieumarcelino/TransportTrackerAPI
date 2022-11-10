<?php
require_once ('../../conf.php');
require_once ('../../utils.php');

function getMessagesIDFM($key, $line, $strTransport, $strLine, $mysqli){
    $headers = array('Accept: application/json', 'apikey: '.$key);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://prim.iledefrance-mobilites.fr/marketplace/general-message?LineRef=STIF%3ALine%3A%3A'.$line.'%3A');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $result = curl_exec($ch);
    curl_close ($ch);
    
    $arr = json_decode($result, true);
    $arr = keyToLowerArr($arr);

    $countMessages = count($arr['siri']['servicedelivery']['generalmessagedelivery'][0]['infomessage']);
    $currentTime = formatDate($arr['siri']['servicedelivery']['responsetimestamp']);
    $allMessages = array();
    $allIdMessages = array();


    for($i=0; $i<$countMessages; $i++){
        $id =   hash('md2', $arr['siri']['servicedelivery']['generalmessagedelivery'][0]['infomessage'][$i]['itemidentifier']);
        $text = $arr['siri']['servicedelivery']['generalmessagedelivery'][0]['infomessage'][$i]['content']['message'][0]['messagetext']['value'];
        $type = getTypeOfMessage($text);

        $messages = array(
            'id_api' => $id,
            'type' => $type,
            'text' => $text,
        );

        if($type != 'travaux'){
            array_push($allIdMessages, $id);
            array_push($allMessages, $messages);
            var_dump($messages);
        }
    }
    checkInBDD($allMessages, $allIdMessages, $strTransport, $strLine, $currentTime, $mysqli);
}

function checkInBDD($allMessages, $allIdMessages, $strTransport, $strLine, $currentTime, $mysqli){

    $strTransportLine = $strTransport.$strLine;
    $allIdDistruptions = array();

    $id = '"'.implode('","', $allIdMessages).'"';
    $sqlMessage = $mysqli->query('SELECT `id`, `id_disruptions` FROM `messages` WHERE `id_api` NOT IN ('.$id.') AND `transport_line` = "'.$strTransportLine.'" AND `finished` = "0"');
   
    while ($rowMessage = $sqlMessage->fetch_assoc()){

        array_push($allIdDistruptions, $rowMessage['id_disruptions']);

        $updateMessageSQL = 'UPDATE `messages` SET `end_time` = "'.$currentTime.'", `finished` = "1" WHERE (`id` = "'.$rowMessage['id'].'")';

        if ($mysqli->query($updateMessageSQL) === TRUE) {
            writeLog('[OK] UPDATE MESSAGES FINISHED', $rowMessage['id']);
        } else {
            writeLog('[KO] UPDATE MESSAGES FINISHED : '.$updateMessageSQL, $mysqli->error);
        }

    }

    for($i=0; $i<count($allMessages); $i++){

        $sqlMessage = $mysqli->query('SELECT `id`, `id_disruptions`, `finished` FROM `messages` WHERE `id_api` = "'.$allMessages[$i]['id_api'].'"');
        $rowMessage = mysqli_fetch_assoc($sqlMessage);

        if(empty($rowMessage['id'])){

            $currentTime10MinLess = formatDate10MinLess($currentTime);

            $sqlDisruptions = $mysqli->query('SELECT `id_disruptions` FROM `messages` WHERE `transport_line` = "'.$strTransportLine.'" AND `type` = "'.$allMessages[$i]['type'].'" AND `end_time` BETWEEN "'.$currentTime10MinLess.'" AND "'.$currentTime.'" AND `finished` = "1"');
            $rowDisruptions = mysqli_fetch_assoc($sqlDisruptions);
            if(empty($rowDisruptions['id_disruptions'])){

                $insertDisruptionsSQL = 'INSERT INTO `disruptions` (`id`, `transport_line`, `transport`, `line`, `type`, `start_time`, `end_time`, `total_time`, `finished`) VALUES (NULL, "'.$strTransportLine.'", "'.$strTransport.'", "'.$strLine.'", "'.$allMessages[$i]['type'].'", "'.$currentTime.'", NULL, NULL, "0")';
                
                if ($mysqli->query($insertDisruptionsSQL) === TRUE) {
                    $rowDisruptions['id_disruptions'] = $mysqli->insert_id;
                    writeLog('[OK] INSERT DISRUPTIONS', $mysqli->insert_id);
                } else {
                    writeLog('[KO] INSERT DISRUPTIONS : '.$insertDisruptionsSQL, $mysqli->error);
                }

            } else {

                $updateDisruptionsSQL = 'UPDATE `disruptions` SET `end_time` = NULL, `total_time` = NULL, `finished` = "0" WHERE (`id` = "'.$rowDisruptions['id_disruptions'].'")';

                if ($mysqli->query($updateDisruptionsSQL) === TRUE) {
                    writeLog('[OK] UPDATE DISRUPTIONS NOT YET FINISHED BY TIME', $rowDisruptions['id_disruptions']);
                } else {
                    writeLog('[KO] UPDATE DISRUPTIONS NOT YET FINISHED BY TIME : '.$updateDisruptionsSQL, $mysqli->error);
                }

                if (($key = array_search($rowDisruptions['id_disruptions'], $allIdDistruptions)) !== false){
                    unset($allIdDistruptions[$key]);
                }

            }

            $insertMessageSQL = 'INSERT INTO `messages` (`id`, `id_disruptions`, `id_api`, `transport_line`, `transport`, `line`, `type`, `text`, `start_time`, `end_time`, `finished`) VALUES (NULL, "'.$rowDisruptions['id_disruptions'].'", "'.$allMessages[$i]['id_api'].'", "'.$strTransportLine.'", "'.$strTransport.'", "'.$strLine.'", "'.$allMessages[$i]['type'].'", "'.$allMessages[$i]['text'].'", "'.$currentTime.'", NULL, "0")';
            
            if ($mysqli->query($insertMessageSQL) === TRUE) {
                writeLog('[OK] INSERT MESSAGES', $mysqli->insert_id);
            } else {
                writeLog('[KO] INSERT MESSAGES : '.$insertMessageSQL, $mysqli->error);
            }

        } else if ($rowMessage['finished'] == 1){

            $updateMessageSQL = 'UPDATE `messages` SET `end_time` = NULL, `finished` = "0" WHERE (`id` = "'.$rowMessage['id'].'")';

            if ($mysqli->query($updateMessageSQL) === TRUE) {
                writeLog('[OK] UPDATE MESSAGE NOT YET FINISHED BY ID', $rowMessage['id']);
            } else {
                writeLog('[KO] UPDATE MESSAGE NOT YET FINISHED BY ID : '.$updateMessageSQL, $mysqli->error);
            }

            $updateDisruptionsSQL = 'UPDATE `disruptions` SET `end_time` = NULL, `total_time` = NULL, `finished` = "0" WHERE (`id` = "'.$rowMessage['id_disruptions'].'")';

            if ($mysqli->query($updateDisruptionsSQL) === TRUE) {
                writeLog('[OK] UPDATE DISRUPTIONS NOT YET FINISHED BY ID', $rowMessage['id_disruptions']);
            } else {
                writeLog('[KO] UPDATE DISRUPTIONS NOT YET FINISHED BY ID : '.$updateDisruptionsSQL, $mysqli->error);
            }

        }

    }

    for($i=0; $i<count($allIdDistruptions); $i++){

        $sqlDisruptions = $mysqli->query('SELECT `start_time` FROM `disruptions` WHERE `id` = "'.$allIdDistruptions[$i].'"');
        $rowDisruptions = mysqli_fetch_assoc($sqlDisruptions);

        $totalTime  = strtotime($currentTime) - strtotime($rowDisruptions['start_time']);

        $updateDisruptionsSQL = 'UPDATE `disruptions` SET `end_time` = "'.$currentTime.'", `total_time` = "'.$totalTime.'", `finished` = "1" WHERE (`id` = "'.$allIdDistruptions[$i].'")';

        if ($mysqli->query($updateDisruptionsSQL) === TRUE) {
            writeLog('[OK] UPDATE DISRUPTIONS FINISHED', $allIdDistruptions[$i]);
        } else {
            writeLog('[KO] UPDATE DISRUPTIONS FINISHED : '.$updateDisruptionsSQL, $mysqli->error);
        }

    }
}

getMessagesIDFM($key, $metro['1'], 'metro', '1', $mysqli);
getMessagesIDFM($key, $metro['2'], 'metro', '2', $mysqli);
getMessagesIDFM($key, $metro['3'], 'metro', '3', $mysqli);
getMessagesIDFM($key, $metro['3bis'], 'metro', '3bis', $mysqli);
getMessagesIDFM($key, $metro['4'], 'metro', '4', $mysqli);
getMessagesIDFM($key, $metro['5'], 'metro', '5', $mysqli);
getMessagesIDFM($key, $metro['6'], 'metro', '6', $mysqli);
getMessagesIDFM($key, $metro['7'], 'metro', '7', $mysqli);
getMessagesIDFM($key, $metro['7bis'], 'metro', '7bis', $mysqli);
getMessagesIDFM($key, $metro['8'], 'metro', '8', $mysqli);
getMessagesIDFM($key, $metro['9'], 'metro', '9', $mysqli);
getMessagesIDFM($key, $metro['10'], 'metro', '10', $mysqli);
getMessagesIDFM($key, $metro['11'], 'metro', '11', $mysqli);
getMessagesIDFM($key, $metro['12'], 'metro', '12', $mysqli);
getMessagesIDFM($key, $metro['13'], 'metro', '13', $mysqli);
getMessagesIDFM($key, $metro['14'], 'metro', '14', $mysqli);

getMessagesIDFM($key, $rer['A'], 'rer', 'A', $mysqli);
getMessagesIDFM($key, $rer['B'], 'rer', 'B', $mysqli);
getMessagesIDFM($key, $rer['C'], 'rer', 'C', $mysqli);
getMessagesIDFM($key, $rer['D'], 'rer', 'D', $mysqli);
getMessagesIDFM($key, $rer['E'], 'rer', 'E', $mysqli);

getMessagesIDFM($key, $tramway['1'], 'tramway', '1', $mysqli);
getMessagesIDFM($key, $tramway['2'], 'tramway', '2', $mysqli);
getMessagesIDFM($key, $tramway['3a'], 'tramway', '3a', $mysqli);
getMessagesIDFM($key, $tramway['3b'], 'tramway', '3b', $mysqli);
getMessagesIDFM($key, $tramway['4'], 'tramway', '4', $mysqli);
getMessagesIDFM($key, $tramway['5'], 'tramway', '5', $mysqli);
getMessagesIDFM($key, $tramway['6'], 'tramway', '6', $mysqli);
getMessagesIDFM($key, $tramway['7'], 'tramway', '7', $mysqli);
getMessagesIDFM($key, $tramway['8'], 'tramway', '8', $mysqli);
getMessagesIDFM($key, $tramway['9'], 'tramway', '9', $mysqli);
getMessagesIDFM($key, $tramway['11'], 'tramway', '11', $mysqli);
getMessagesIDFM($key, $tramway['13'], 'tramway', '13', $mysqli);

getMessagesIDFM($key, $transilien['H'], 'transilien', 'H', $mysqli);
getMessagesIDFM($key, $transilien['J'], 'transilien', 'J', $mysqli);
getMessagesIDFM($key, $transilien['K'], 'transilien', 'K', $mysqli);
getMessagesIDFM($key, $transilien['L'], 'transilien', 'L', $mysqli);
getMessagesIDFM($key, $transilien['N'], 'transilien', 'N', $mysqli);
getMessagesIDFM($key, $transilien['P'], 'transilien', 'P', $mysqli);
getMessagesIDFM($key, $transilien['R'], 'transilien', 'R', $mysqli);
getMessagesIDFM($key, $transilien['U'], 'transilien', 'U', $mysqli);

?>