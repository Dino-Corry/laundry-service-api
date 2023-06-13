<?php
use MongoDB\Driver\ServerApi;
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
require 'vendor/autoload.php'; 


function server($dbName, $collections) {
    $uri = 'mongodb+srv://dino:Dino-boy123@cluster0.ddqzrq5.mongodb.net/';
    // Specify Stable API version 1
    $apiVersion = new ServerApi(ServerApi::V1);

    // Create a new client and connect to the server
    $client = new MongoDB\Client($uri, [], ['serverApi' => $apiVersion]);

    try {
        // Send a ping to confirm a successful connection
        $db = $client->selectDatabase($dbName);
        return $db;
    } catch (Exception $e) {
        printf($e->getMessage());
    }
    }

?>