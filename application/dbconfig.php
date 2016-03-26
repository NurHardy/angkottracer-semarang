<?php
global $mysqli;
$mysqli = new mysqli("localhost", "root", "", "db_angkottracer");

/* check connection */
if (mysqli_connect_errno()) {
    printf("Connect failed: %s\n", mysqli_connect_error());
    exit();
}
