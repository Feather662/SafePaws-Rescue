<?php
// config/connection.php
$db_hostname = "localhost";
$db_name = "safepaws_rescue";
$db_username = "safepaws";
$db_password = "safepaws";

$dsn = "mysql:host=$db_hostname;dbname=$db_name";
$dbh = new PDO($dsn, "$db_username", "$db_password");

function displayPDOError(PDOException $exception): void {
    // Set appropriate headers and HTTP response to display error message
    header('HTTP/1.1 400 Bad Request');
    header('Content-Type:text/plain');

    // Show error messages of the exception when execution is failed
    echo $exception->getMessage();
}