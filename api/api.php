<?php

require_once ('../conf.php');

$error = FALSE;

if($_GET['param'] == 'stats'){
    if(isset($_GET['disruptions_type'])){
        $arr = [];

        $sql = $mysqli->query('SELECT COUNT(`id`) AS `count` FROM `disruptions`');
        $row = $sql->fetch_assoc();
        $countAllDisruptions = $row['count'];

        $sql = $mysqli->query('SELECT `type`, SUM(`total_time`) AS `time`, COUNT(`id`) AS `count` FROM `disruptions` GROUP BY `type`');
        while ($row = $sql->fetch_assoc()){
            $arrType = array("type"=>$row['type'], "count"=>intval($row['count']), "percentage"=>floatval(($row['count']/$countAllDisruptions)*100), "average_times"=>intval($row['time']/$row['count']));
            array_push($arr, $arrType);
        }
    } else if(isset($_GET['most_disruptions']) == 'last_week'){
        
    }
}

if($error == TRUE){
    $arr = array("statusCode"=>404, "error"=>"Not Found");
}

header_remove(); 
header("Content-type: application/json; charset=utf-8");

$json = json_encode($arr);

echo $json;

?>