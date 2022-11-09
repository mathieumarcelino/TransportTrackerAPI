<?php
require_once ('../../conf.php');
require_once ('../../utils.php');

function getMessages($key, $line, $strTransport, $strLine, $mysqli){
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

function checkInBDD($allMessages, $allIdMessages, $strTransport, $strLine, $currentTime ,$mysqli){

    $strTransportLine = $strTransport.$strLine;
    $allIdDistruptions = array();

    $id = '"'.implode('","', $allIdMessages).'"';
    $sqlMessage = $mysqli->query('SELECT `id`, `id_disruptions` FROM `messages` WHERE `id_api` NOT IN ('.$id.') AND `transport` = "'.$strTransport.'" AND `line` = "'.$strLine.'" AND `finished` = "0"');
   
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

        $sqlMessage = $mysqli->query('SELECT `id` FROM `messages` WHERE `id_api` = "'.$allMessages[$i]['id_api'].'"');
        $rowMessage = mysqli_fetch_row($sqlMessage);

        if(empty($rowMessage[0])){

            $currentTime10MinLess = formatDate10MinLess($currentTime);

            $sqlDisruptions = $mysqli->query('SELECT `id_disruptions` FROM `messages` WHERE `transport_line` = "'.$strTransportLine.'" AND `type` = "'.$allMessages[$i]['type'].'" AND `end_time` BETWEEN "'.$currentTime10MinLess.'" AND "'.$currentTime.'" AND `finished` = "1"');
            $rowDisruptions = mysqli_fetch_row($sqlDisruptions);
            if(empty($rowDisruptions[0])){

                $insertDisruptionsSQL = 'INSERT INTO `disruptions` (`id`, `transport_line`, `transport`, `line`, `type`, `start_time`, `end_time`, `total_time`, `finished`) VALUES (NULL, "'.$strTransportLine.'", "'.$strTransport.'", "'.$strLine.'", "'.$allMessages[$i]['type'].'", "'.$currentTime.'", NULL, NULL, "0")';
                
                if ($mysqli->query($insertDisruptionsSQL) === TRUE) {
                    $rowDisruptions[0] = $mysqli->insert_id;
                    writeLog('[OK] INSERT DISRUPTIONS', $mysqli->insert_id);
                } else {
                    writeLog('[KO] INSERT DISRUPTIONS : '.$insertDisruptionsSQL, $mysqli->error);
                }

            } else {

                $updateDisruptions = 'UPDATE `disruptions` SET `end_time` = NULL, `total_time` = NULL, `finished` = "0" WHERE (`id` = "'.$rowDisruptions[0].'")';

                if ($mysqli->query($updateDisruptions) === TRUE) {
                    writeLog('[OK] UPDATE DISRUPTIONS NOT YET FINISHED', $rowDisruptions[0]);
                } else {
                    writeLog('[KO] UPDATE DISRUPTIONS NOT YET FINISHED : '.$updateDisruptions, $mysqli->error);
                }

                if (($key = array_search($rowDisruptions[0], $allIdDistruptions)) !== false){
                    unset($allIdDistruptions[$key]);
                }

            }

            $insertMessageSQL = 'INSERT INTO `messages` (`id`, `id_disruptions`, `id_api`, `transport_line`, `transport`, `line`, `type`, `text`, `start_time`, `end_time`, `finished`) VALUES (NULL, "'.$rowDisruptions[0].'", "'.$allMessages[$i]['id_api'].'", "'.$strTransportLine.'", "'.$strTransport.'", "'.$strLine.'", "'.$allMessages[$i]['type'].'", "'.$allMessages[$i]['text'].'", "'.$currentTime.'", NULL, "0")';
            
            if ($mysqli->query($insertMessageSQL) === TRUE) {
                $rowDisruptions[0] = $mysqli->insert_id;
                writeLog('[OK] INSERT MESSAGES', $mysqli->insert_id);
            } else {
                writeLog('[KO] INSERT MESSAGES : '.$insertMessageSQL, $mysqli->error);
            }

        }

    }

    for($i=0; $i<count($allIdDistruptions); $i++){

        $sqlDisruptions = $mysqli->query('SELECT `start_time` FROM `disruptions` WHERE `id` = "'.$allIdDistruptions[$i].'"');
        $rowDisruptions = mysqli_fetch_row($sqlDisruptions);

        $totalTime  = strtotime($currentTime) - strtotime($rowDisruptions[0]);

        $updateDisruptionsSQL = 'UPDATE `disruptions` SET `end_time` = "'.$currentTime.'", `total_time` = "'.$totalTime.'", `finished` = "1" WHERE (`id` = "'.$allIdDistruptions[$i].'")';

        if ($mysqli->query($updateDisruptionsSQL) === TRUE) {
            writeLog('[OK] UPDATE DISRUPTIONS FINISHED', $allIdDistruptions[$i]);
        } else {
            writeLog('[KO] UPDATE DISRUPTIONS FINISHED : '.$updateDisruptionsSQL, $mysqli->error);
        }

    }
}

getMessages($key, $metro['1'], 'metro', '1', $mysqli);
getMessages($key, $metro['2'], 'metro', '2', $mysqli);
getMessages($key, $metro['3'], 'metro', '3', $mysqli);
getMessages($key, $metro['3bis'], 'metro', '3bis', $mysqli);
getMessages($key, $metro['4'], 'metro', '4', $mysqli);
getMessages($key, $metro['5'], 'metro', '5', $mysqli);
getMessages($key, $metro['6'], 'metro', '6', $mysqli);
getMessages($key, $metro['7'], 'metro', '7', $mysqli);
getMessages($key, $metro['7bis'], 'metro', '7bis', $mysqli);
getMessages($key, $metro['8'], 'metro', '8', $mysqli);
getMessages($key, $metro['9'], 'metro', '9', $mysqli);
getMessages($key, $metro['10'], 'metro', '10', $mysqli);
getMessages($key, $metro['11'], 'metro', '11', $mysqli);
getMessages($key, $metro['12'], 'metro', '12', $mysqli);
getMessages($key, $metro['13'], 'metro', '13', $mysqli);
getMessages($key, $metro['14'], 'metro', '14', $mysqli);

getMessages($key, $rer['A'], 'rer', 'A', $mysqli);
getMessages($key, $rer['B'], 'rer', 'B', $mysqli);
getMessages($key, $rer['C'], 'rer', 'C', $mysqli);
getMessages($key, $rer['D'], 'rer', 'D', $mysqli);
getMessages($key, $rer['E'], 'rer', 'E', $mysqli);

getMessages($key, $tramway['1'], 'tramway', '1', $mysqli);
getMessages($key, $tramway['2'], 'tramway', '2', $mysqli);
getMessages($key, $tramway['3a'], 'tramway', '3a', $mysqli);
getMessages($key, $tramway['3b'], 'tramway', '3b', $mysqli);
getMessages($key, $tramway['4'], 'tramway', '4', $mysqli);
getMessages($key, $tramway['5'], 'tramway', '5', $mysqli);
getMessages($key, $tramway['6'], 'tramway', '6', $mysqli);
getMessages($key, $tramway['7'], 'tramway', '7', $mysqli);
getMessages($key, $tramway['8'], 'tramway', '8', $mysqli);
getMessages($key, $tramway['9'], 'tramway', '9', $mysqli);
getMessages($key, $tramway['11'], 'tramway', '11', $mysqli);
getMessages($key, $tramway['13'], 'tramway', '13', $mysqli);

getMessages($key, $transilien['H'], 'transilien', 'H', $mysqli);
getMessages($key, $transilien['J'], 'transilien', 'J', $mysqli);
getMessages($key, $transilien['K'], 'transilien', 'K', $mysqli);
getMessages($key, $transilien['L'], 'transilien', 'L', $mysqli);
getMessages($key, $transilien['N'], 'transilien', 'N', $mysqli);
getMessages($key, $transilien['P'], 'transilien', 'P', $mysqli);
getMessages($key, $transilien['R'], 'transilien', 'R', $mysqli);
getMessages($key, $transilien['U'], 'transilien', 'U', $mysqli);

?>