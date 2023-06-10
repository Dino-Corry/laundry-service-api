<?php
require 'vendor/autoload.php'; 
use MongoDB\Client;

class AdminHandler
{
    private $collection;

    public function __construct($mongoHost, $dbName, $mongoCollection)
    {
        $mongoConnectionString = "mongodb://$mongoHost";
        $mongoClient = new Client($mongoConnectionString);
        $this->collection = $mongoClient->$dbName->$mongoCollection;
    }

    public function adminHandler($requestNumber, $status){
        $criteria = [
            'pickup_requests.request_number' => $requestNumber,
        ];

        // Execute the findOne query
        $user = $this->collection->findOne($criteria);

        if ($user) {
            $pickupRequests = $user['pickup_requests'];
            $requestFound = false;

            // Iterate over pickup requests to find the matching one
            foreach ($pickupRequests as &$pickupRequest) {
                // Check if the request number matches
                if (isset($pickupRequest['request_number']) && $pickupRequest['request_number'] === $requestNumber) {
                    $pickupRequest['status'] = $status;

                    // Save the modified document
                    $this->collection->replaceOne(
                        ['_id' => $user['_id']],
                        $user
                    );

                    $requestFound = true;
                    break;
                }
            }

            if ($requestFound) {
                if ($status == 1) {
                    echo "Your item has been approved for pickup. Dispatch driver will call you for pickup";
                } else if ($status == 2) {
                    echo "Your item is now ready for delivery. Wait for our call";
                } else {
                    echo "Incorrect value. Value must be 1 or 2";
                }
            } else {
                echo "Request not found";
            }
        } else {
            echo "User not found";
        }
    }
}
