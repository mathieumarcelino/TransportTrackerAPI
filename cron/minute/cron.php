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
        }
    }
    checkInBDD($allMessages, $allIdMessages, $strTransport, $strLine, $currentTime, $mysqli);
}

function getMessagesNavitia($key, $line, $strTransport, $strLine, $mysqli){
    $headers = array('Accept: application/json', 'Authorization: '.$key);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.navitia.io/v1/coverage/fr-idf/lines/line%3AIDFM%3A'.$line.'/disruptions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $result = curl_exec($ch);
    curl_close ($ch);
    
    $arr = json_decode($result, true);
    $arr = keyToLowerArr($arr);

    $countMessages = count($arr['disruptions']);
    $currentTime = formatDate($arr['context']['current_datetime']);
    $allMessages = array();
    $allIdMessages = array();

    for($i=0; $i<$countMessages; $i++){
        if($arr['disruptions'][$i]['status'] == 'active'){
            $id =   hash('md2', $arr['disruptions'][$i]['disruption_id']);

            $strlen = strlen($arr['disruptions'][$i]['messages'][0]['text']);
            $text = $arr['disruptions'][$i]['messages'][0]['text'];
            for($j=0; $j<count($arr['disruptions'][$i]['messages']); $j++){
                $strlenCompare = strlen($arr['disruptions'][$i]['messages'][$j]['text']);
                if($strlenCompare > $strlen){
                    $text = urldecode(strip_tags($arr['disruptions'][$i]['messages'][$j]['text']));
                }
            }
            $type = getTypeOfMessage($text);
    
            $messages = array(
                'id_api' => $id,
                'type' => $type,
                'text' => $text,
            );
    
            if($type != 'travaux'){
                array_push($allIdMessages, $id);
                array_push($allMessages, $messages);
            }
        }
    }
    if(count($allMessages) != 0){
        checkInBDD($allMessages, $allIdMessages, $strTransport, $strLine, $currentTime, $mysqli);
    }
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
            writeLog('data', '[OK] UPDATE MESSAGES FINISHED', $rowMessage['id']);
        } else {
            writeLog('data', '[KO] UPDATE MESSAGES FINISHED : '.$updateMessageSQL, $mysqli->error);
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
                    writeLog('data', '[OK] INSERT DISRUPTIONS', $mysqli->insert_id);
                } else {
                    writeLog('data', '[KO] INSERT DISRUPTIONS : '.$insertDisruptionsSQL, $mysqli->error);
                }

            } else {

                $updateDisruptionsSQL = 'UPDATE `disruptions` SET `end_time` = NULL, `total_time` = NULL, `finished` = "0" WHERE (`id` = "'.$rowDisruptions['id_disruptions'].'")';

                if ($mysqli->query($updateDisruptionsSQL) === TRUE) {
                    writeLog('data', '[OK] UPDATE DISRUPTIONS NOT YET FINISHED BY TIME', $rowDisruptions['id_disruptions']);
                } else {
                    writeLog('data', '[KO] UPDATE DISRUPTIONS NOT YET FINISHED BY TIME : '.$updateDisruptionsSQL, $mysqli->error);
                }

                if (($key = array_search($rowDisruptions['id_disruptions'], $allIdDistruptions)) !== false){
                    unset($allIdDistruptions[$key]);
                }

            }

            $insertMessageSQL = 'INSERT INTO `messages` (`id`, `id_disruptions`, `id_api`, `transport_line`, `transport`, `line`, `type`, `text`, `start_time`, `end_time`, `finished`) VALUES (NULL, "'.$rowDisruptions['id_disruptions'].'", "'.$allMessages[$i]['id_api'].'", "'.$strTransportLine.'", "'.$strTransport.'", "'.$strLine.'", "'.$allMessages[$i]['type'].'", "'.$allMessages[$i]['text'].'", "'.$currentTime.'", NULL, "0")';
            
            if ($mysqli->query($insertMessageSQL) === TRUE) {
                writeLog('data', '[OK] INSERT MESSAGES', $mysqli->insert_id);
            } else {
                writeLog('data', '[KO] INSERT MESSAGES : '.$insertMessageSQL, $mysqli->error);
            }

        } else if ($rowMessage['finished'] == 1){

            $updateMessageSQL = 'UPDATE `messages` SET `end_time` = NULL, `finished` = "0" WHERE (`id` = "'.$rowMessage['id'].'")';

            if ($mysqli->query($updateMessageSQL) === TRUE) {
                writeLog('data', '[OK] UPDATE MESSAGES NOT YET FINISHED BY ID', $rowMessage['id']);
            } else {
                writeLog('data', '[KO] UPDATE MESSAGES NOT YET FINISHED BY ID : '.$updateMessageSQL, $mysqli->error);
            }

            $updateDisruptionsSQL = 'UPDATE `disruptions` SET `end_time` = NULL, `total_time` = NULL, `finished` = "0" WHERE (`id` = "'.$rowMessage['id_disruptions'].'")';

            if ($mysqli->query($updateDisruptionsSQL) === TRUE) {
                writeLog('data', '[OK] UPDATE DISRUPTIONS NOT YET FINISHED BY ID', $rowMessage['id_disruptions']);
            } else {
                writeLog('data', '[KO] UPDATE DISRUPTIONS NOT YET FINISHED BY ID : '.$updateDisruptionsSQL, $mysqli->error);
            }

        }

    }

    for($i=0; $i<count($allIdDistruptions); $i++){

        $sqlDisruptions = $mysqli->query('SELECT `start_time` FROM `disruptions` WHERE `id` = "'.$allIdDistruptions[$i].'"');
        $rowDisruptions = mysqli_fetch_assoc($sqlDisruptions);

        $totalTime  = strtotime($currentTime) - strtotime($rowDisruptions['start_time']);

        $updateDisruptionsSQL = 'UPDATE `disruptions` SET `end_time` = "'.$currentTime.'", `total_time` = "'.$totalTime.'", `finished` = "1" WHERE (`id` = "'.$allIdDistruptions[$i].'")';

        if ($mysqli->query($updateDisruptionsSQL) === TRUE) {
            writeLog('data', '[OK] UPDATE DISRUPTIONS FINISHED', $allIdDistruptions[$i]);
        } else {
            writeLog('data', '[KO] UPDATE DISRUPTIONS FINISHED : '.$updateDisruptionsSQL, $mysqli->error);
        }

    }
}

getMessagesIDFM($keyIDFM, $metro['1'], 'metro', '1', $mysqli);
getMessagesIDFM($keyIDFM, $metro['2'], 'metro', '2', $mysqli);
getMessagesIDFM($keyIDFM, $metro['3'], 'metro', '3', $mysqli);
getMessagesIDFM($keyIDFM, $metro['3bis'], 'metro', '3bis', $mysqli);
getMessagesIDFM($keyIDFM, $metro['4'], 'metro', '4', $mysqli);
getMessagesIDFM($keyIDFM, $metro['5'], 'metro', '5', $mysqli);
getMessagesIDFM($keyIDFM, $metro['6'], 'metro', '6', $mysqli);
getMessagesIDFM($keyIDFM, $metro['7'], 'metro', '7', $mysqli);
getMessagesIDFM($keyIDFM, $metro['7bis'], 'metro', '7bis', $mysqli);
getMessagesIDFM($keyIDFM, $metro['8'], 'metro', '8', $mysqli);
getMessagesIDFM($keyIDFM, $metro['9'], 'metro', '9', $mysqli);
getMessagesIDFM($keyIDFM, $metro['10'], 'metro', '10', $mysqli);
getMessagesIDFM($keyIDFM, $metro['11'], 'metro', '11', $mysqli);
getMessagesIDFM($keyIDFM, $metro['12'], 'metro', '12', $mysqli);
getMessagesIDFM($keyIDFM, $metro['13'], 'metro', '13', $mysqli);
getMessagesIDFM($keyIDFM, $metro['14'], 'metro', '14', $mysqli);

getMessagesIDFM($keyIDFM, $rer['A'], 'rer', 'A', $mysqli);
getMessagesIDFM($keyIDFM, $rer['B'], 'rer', 'B', $mysqli);
getMessagesNavitia($keyNavitia, $rer['C'], 'rer', 'C', $mysqli);
getMessagesNavitia($keyNavitia, $rer['D'], 'rer', 'D', $mysqli);
getMessagesNavitia($keyNavitia, $rer['E'], 'rer', 'E', $mysqli);

getMessagesIDFM($keyIDFM, $tramway['1'], 'tramway', '1', $mysqli);
getMessagesIDFM($keyIDFM, $tramway['2'], 'tramway', '2', $mysqli);
getMessagesIDFM($keyIDFM, $tramway['3a'], 'tramway', '3a', $mysqli);
getMessagesIDFM($keyIDFM, $tramway['3b'], 'tramway', '3b', $mysqli);
getMessagesNavitia($keyNavitia, $tramway['4'], 'tramway', '4', $mysqli);
getMessagesIDFM($keyIDFM, $tramway['5'], 'tramway', '5', $mysqli);
getMessagesIDFM($keyIDFM, $tramway['6'], 'tramway', '6', $mysqli);
getMessagesIDFM($keyIDFM, $tramway['7'], 'tramway', '7', $mysqli);
getMessagesIDFM($keyIDFM, $tramway['8'], 'tramway', '8', $mysqli);
getMessagesNavitia($keyNavitia, $tramway['9'], 'tramway', '9', $mysqli);
getMessagesNavitia($keyNavitia, $tramway['11'], 'tramway', '11', $mysqli);
getMessagesNavitia($keyNavitia, $tramway['13'], 'tramway', '13', $mysqli);

getMessagesNavitia($keyNavitia, $transilien['H'], 'transilien', 'H', $mysqli);
getMessagesNavitia($keyNavitia, $transilien['J'], 'transilien', 'J', $mysqli);
getMessagesNavitia($keyNavitia, $transilien['K'], 'transilien', 'K', $mysqli);
getMessagesNavitia($keyNavitia, $transilien['L'], 'transilien', 'L', $mysqli);
getMessagesNavitia($keyNavitia, $transilien['N'], 'transilien', 'N', $mysqli);
getMessagesNavitia($keyNavitia, $transilien['P'], 'transilien', 'P', $mysqli);
getMessagesNavitia($keyNavitia, $transilien['R'], 'transilien', 'R', $mysqli);
getMessagesNavitia($keyNavitia, $transilien['U'], 'transilien', 'U', $mysqli);

?>