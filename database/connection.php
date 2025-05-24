<?php

DEFINE('HOSTNAME', 'localhost');
DEFINE('USERNAME', 'root');
DEFINE('PASSWORD', ''); 
DEFINE('DATABASE', 'agriloan');


function db_agriloan_connect()
{
    $conn = mysqli_connect(HOSTNAME, USERNAME, PASSWORD, DATABASE) or die('CONNECTION TO SonaTapV4 DATABASE FAILED!');
    return $conn;
}
?>
