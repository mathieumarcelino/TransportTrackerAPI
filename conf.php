<?php

date_default_timezone_set('Europe/Paris');

$key = 'API_KEY';

$host_name      = 'DB_HOSTNAME';
$db_username    = 'DB_USERNAME';
$db_password    = 'DB_PASSWORD';
$db_name        = 'DB_NAME';

// Connexion à la base de données
$mysqli = new mysqli($host_name, $db_username, $db_password, $db_name);
if ($mysqli->connect_error) {
    die('Error : ('. $mysqli->connect_errno .') '. $mysqli->connect_error);
}

?>