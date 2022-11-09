<?php
require_once ('../../conf.php');
require_once ('../../utils.php');

$dateFirstDay = date("Y-m-d", strtotime('monday last week')).' 00:00:00';
$dateLastDay = date("Y-m-d", strtotime('sunday last week')).' 23:59:59';

echo(getDisruptionsTimeInSeconds('rerB', $dateFirstDay, $dateLastDay, $mysqli));


?>