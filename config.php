<?php
require 'vendor/autoload.php';
use MongoDB\Client;

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

function server($dbName) {
    $uri = 'mongodb+srv://dino:Dino-boy123@cluster0.ddqzrq5.mongodb.net/';
    
    try {
        // Create a new client and connect to the server
        $client = new Client($uri);

        // Select the database
        $db = $client->selectDatabase($dbName);

        return $db;
    } catch (Exception $e) {
        printf($e->getMessage());
    }
}
?>
