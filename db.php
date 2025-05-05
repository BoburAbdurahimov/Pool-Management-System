<?php
$dbServername = "localhost";
$dbUsername = "root";
$dbPassword = "";
$dbName = "pool";

$db = mysqli_connect($dbServername, $dbUsername, $dbPassword, $dbName);
if (!$db) {
    die("Connection failed: " . mysqli_connect_error());
}   
?>