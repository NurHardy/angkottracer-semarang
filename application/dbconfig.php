<?php
global $mysqli;
$mysqli = new mysqli("localhost", "root", "", "db_angkottracer");

/* check connection */
if (mysqli_connect_errno()) {
    printf("Connect failed: %s\n", mysqli_connect_error());
    exit();
}

// System contants
//define('GOOGLEMAP_APIKEY', "AIzaSyCB_Tzs_EZ1exoXELhuq_sOlkqhrifjezw");
define('GOOGLE_APIKEY', 'AIzaSyB2LvXICy-Je6QQFgeIi32FnbA8r-dnqU4');
define('APPVER', 'v0.5.3697.21628');
