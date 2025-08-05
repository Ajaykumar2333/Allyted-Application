<?php
$host = "localhost:3307";
$user = "root";
$pass = "";
$dbname = "company_portal";

$mysqli = mysqli_connect($host, $user, $pass, $dbname);

if (!$mysqli) {
    die("Connection failed: " . mysqli_connect_error());
}

return $mysqli;

