<?php
use MongoDB\Client;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php'; 




class LaundryServiceAPI{
    private $mongoClient;
    private $collection;

    public function __construct($mongoHost, $dbName, $mongoCollection){
        $mongoConnectionString = "mongodb://$mongoHost";
        $this->mongoClient = new Client($mongoConnectionString);
        $this->collection = $this->mongoClient->$dbName->$mongoCollection;
    }

    // User authentication: Signup
    public function signup($name, $email, $password){
        $user = [
            'name' => $name,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
        ];

        $result = $this->collection->insertOne($user);

        return $result->getInsertedId();
    }

    // User authentication: Login
    public function login($email, $password){
        $user = $this->collection->findOne(['email' => $email]);

        if ($user && password_verify($password, $user['password'])) {
            return $user['_id'];
        }

        return false;
    }

    // User authentication: Forgot Password
    public function forgotPassword($email) {
        $user = $this->collection->findOne(['email' => $email]);

        if ($user) {
            // Generate and save reset token
            $resetToken = $this->generateRandomToken();
            $this->collection->updateOne(
                ['email' => $email],
                ['$set' => ['reset_token' => $resetToken]]
            );

            // Sending email with the reset token
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'essen653@gmail.com';
            $mail->Password = 'yrumbywwifhqpwoa';
            $mail->Port = 465; 
            $mail->SMTPSecure = 'ssl';
            
            $mail->setFrom('essen653@gmail.com', 'Golden Laundry Service'); 
            $mail->addAddress($email); 
            
            $mail->Subject = 'Password Reset from Laundry API';
            $mail->Body = 'Your password reset token is ' . $resetToken;
            
            if ($mail->send()) {
                return true; 
            } else {
                return false; 
            }
        }

        // User not found
        return false;                                               
    }


    // User authentication: Reset Password
    public function resetPassword($email, $resetToken, $newPassword){
        $user = $this->collection->findOne(['email' => $email, 'reset_token' => $resetToken]);

        if ($user) {
            // Update password
            $this->collection->updateOne(
                ['email' => $email],
                ['$set' => ['password' => password_hash($newPassword, PASSWORD_DEFAULT), 'reset_token' => null]]
            );

            return true;
        }

        // Invalid token or user not found
        return false;
    }

    // Find all users
    public function findAllUsers(){
        $users = $this->collection->find();

        $userList = [];
        foreach ($users as $user) {
            $userList[] = $user;
        }

        return $userList;
    }
    

    public function getItemStatus($requestNumber){
        $criteria = [
            'pickup_requests.request_number' => $requestNumber,
        ];
    
        // Execute the findOne query
        $user = $this->collection->findOne($criteria);
    
        if ($user) {
            // Iterate over pickup requests to find the matching one
            foreach ($user['pickup_requests'] as $pickupRequest) {
                // Check if the request number matches
                if (isset($pickupRequest['request_number']) && $pickupRequest['request_number'] === $requestNumber) {
                    // Extract the item details
                    $status = $pickupRequest['status'];
                    $pickupDate = $pickupRequest['pickup_date'];
                    $numberOfItem = $pickupRequest['number_of_item'];
                    $amount = $pickupRequest['amount'];
                    $userEmail = $user['email'];
    
                    // Create the item details array
                    if ($status == 1) {
                        $itemDetails = [
                            'number_of_item' => $numberOfItem,
                            'amount' => $amount,
                            'status' => "Your item has been approved for pickup. Dispatch driver will call you for pickup",
                            'pickup_date' => $pickupDate,
                            'user_email' => $userEmail
                        ];
    
                        return $itemDetails;
                    } elseif ($status == 2) {
                        $itemDetails = [
                            'number_of_item' => $numberOfItem,
                            'amount' => $amount,
                            'status' => "Your item is now ready for delivery, wait for our call",
                            'pickup_date' => $pickupDate,
                            'user_email' => $userEmail
                        ];
    
                        return $itemDetails;
                    } else {
                        $itemDetails = [
                            'number_of_item' => $numberOfItem,
                            'amount' => $amount,
                            'status' => "Pickup request is still pending",
                            'pickup_date' => $pickupDate,
                            'user_email' => $userEmail
                        ];
    
                        return $itemDetails;
                    }
                }
            }
        }
    
        // Request not found
        return 'Item not found';
    }


    // Admin to approve when service is done and ready for delivery
    //Status 0 == Pending Request, 1 == Your service is in progress.., 2 == Ready for Delivery
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
                    return "Your item has been approved for pickup. Dispatch driver will call you for pickup";
                } else if ($status == 2) {
                    return "Your item is now ready for delivery. Wait for our call";
                } else {
                    return "Incorrect value. Value must be 1 or 2";
                }
            } else {
                return "Request not found";
            }
        }
    
        // User not found
        return false;
    } 


    // Make a pickup request
    public function makePickupRequest($userId, $numberOfItem){
        $user = $this->collection->findOne(['_id' => new MongoDB\BSON\ObjectID($userId)]);


        if ($user) {
            // Create a new pickup request with a tracking code
            $requestNumber = $this->generateRandomToken();
            $amount = $numberOfItem * 700;
            $pickupRequest = [
                'request_number' => $requestNumber,
                'delivery_date' => '',
                'number_of_item' => $numberOfItem,
                'amount' => $numberOfItem * 700,
                'pickup_date' => date('Y-m-d'),
                'status' => 'Pickup request sent. Waiting for pickup',
            ];

            // Add the pickup request to the user's array of requests
            $this->collection->updateOne(
                ['_id' => new MongoDB\BSON\ObjectID($userId)],
                ['$push' => ['pickup_requests' => $pickupRequest]]
            );
            // Sending email to the user with request number
            $email = $user['email'];
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'essen653@gmail.com';
            $mail->Password = 'yrumbywwifhqpwoa';
            $mail->Port = 465; 
            $mail->SMTPSecure = 'ssl';
            
            $mail->setFrom('essen653@gmail.com', 'Golden Laundry Service'); 
            $mail->addAddress($email); 
            
            $mail->Subject = 'Pickup Request Initiated';
            $mail->Body = 'Your request was sent successful with the following details: ' . 
                'Request Number:' . $requestNumber .
                'Number of Items:' . $numberOfItem .
                'Cost Amount: ' . $amount;
            
            if ($mail->send()) {
                return "Pickup Request Initiated. Please check your mail for details"; 
            } else {
                return false; 
            }


            return true;
        }

        // User not found
        return false;
    }



    private function generateRandomToken($length = 6) {
        $characters = '0123456789ABCDE';
        $token = '';
        for ($i = 0; $i < $length; $i++) {
            $token .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $token;
    }


    public function handleRequest($method, $endpoint, $data){
        // echo $endpoint;
        switch ($endpoint) {
            case '/getItemStatus':
                if ($method === 'POST') {
                    if (isset($data['requestNumber'])) { 
                        $requestNumber = $data['requestNumber'];
                        $itemStatus = $this->getItemStatus($requestNumber);
                        if ($itemStatus) {
                            // Item found, return the item details
                            return json_encode($itemStatus);
                        } else {
                            // Item not found
                            return 'Item not found';
                        }
                    } else {
                        return 'Missing requestNumber parameter'; 
                    }
                }
                break;
            case '/findAllUsers':
                if ($method === 'POST') {
                    $users = $this->findAllUsers();
                        if ($users) {
                            // Item found, return the item details
                            return json_encode($users);
                        } else {
                            // Item not found
                            return 'Item not found';
                        }
                }
            case '/makePickupRequest':
                if ($method === 'POST') {
                    if (isset($data['userID']) && ($data['numberOfItem'])) { 
                        $userId = ($data['userID']);
                        $numberOfItem = ($data['numberOfItem']);
                        $details = $this->makePickupRequest($userId, $numberOfItem);
                        if ($details) {
                            // Item found, return the item details
                            return json_encode($details);
                        } else {
                            // Item not found
                            return 'Item not found';
                        }
                    } else {
                        return 'Missing parameters'; 
                    }
                }
                break;
            case '/adminHandler':
                if ($method === 'POST') {
                    $requestNumber = $data['requestNumber'];
                    $status = $data['status'];
                    $d_Status = $this->adminHandler($requestNumber, $status);
                    if ($status == 1) {
                        if ($d_Status) {
                            // Request approved successfully
                            return "Your item has been approved for pickup. Dispatch driver will call you for pickup";
                        } else {
                            // Request not found or unable to approve
                            return 'Failed';
                        }
                        
                    } else if ($status == 2) {
                        if ($d_Status) {
                            // Request approved successfully
                            return "Your item is now ready for delivery, wait for our call";
                        } else {
                            // Request not found or unable to approve
                            return 'Failed';
                        }
                        
                    } else {
                        return "Incorrect value. Value must be 1 or 2";
                    }
                }
                break;
            case '/signup':
                if ($method === 'POST') {
                    if (isset($data['name']) && ($data['email']) && ($data['password'])) { 
                        $name = $data['name'];
                        $email = $data['email'];
                        $password = $data['password'];
                        $details = $this->signup($name, $email, $password);
                        if ($details) {
                            // Request approved successfully
                            return 'User registration was successful';
                        } else {
                            // Request not found or unable to approve
                            return 'Failed to register';
                        }
                    } else {
                        return 'Missing parameters'; 
                    }
                }
                break;
            case '/login':
                if ($method === 'POST') {
                    if (isset($data['email']) && ($data['password'])) { 
                        $email = $data['email'];
                        $password = $data['password'];
                        $details = $this->login($email, $password);
                        if ($details) {
                            // Request approved successfully
                            return 'Login was successful';
                        } else {
                            // Request not found or unable to approve
                            return 'Failed to login';
                        }
                    } else {
                        return 'Missing parameters'; 
                    }
                }
                break;
            case '/forgotPassword':
                if ($method === 'POST') {
                    if (isset($data['email'])){ 
                        $email = $data['email'];
                        $p_email = $this->forgotPassword($email);
                        if ($p_email) {
                            return 'Forgot password token was sent to your email';
                        } else {
                            // Request not found or unable to approve
                            return 'Failed to send token';
                        }
                    } else {
                        return 'Missing parameters'; 
                    }
                }
                break;
            case '/resetPassword':
                if ($method === 'POST') {
                    if (isset($data['email']) && ($data['resetToken']) && ($data['newPassword'])){ 
                        $email = $data['email'];
                        $resetToken = $data['resetToken'];
                        $newPassword = $data['newPassword'];
                        $p_email = $this->resetPassword($email, $resetToken, $newPassword);
                        if ($p_email) {
                            return 'You\'ve successfully reset your password ';
                        } else {
                            // Request not found or unable to approve
                            return 'Password reset failed';
                        }
                    } else {
                        return 'Missing parameters'; 
                    }
                }
                break;
                         

            default:
                return 'Invalid endpoint';
        }
    }
    //
    
}

// MongoDB configuration
$mongoHost = 'localhost';
$dbName = 'laundry_service';
$mongoCollection = 'users';

$api = new LaundryServiceAPI($mongoHost, $dbName, $mongoCollection);

$method = $_SERVER['REQUEST_METHOD'];
$endpoint = parse_url($_SERVER['PATH_INFO'], PHP_URL_PATH);
$endpoint = rtrim($endpoint, '/');

$data = $_POST; 



// Handle the request
// var_dump($data);
$response = $api->handleRequest($method, $endpoint, $data);

// Set the appropriate headers
header('Content-Type: application/json');

// Send the response
echo json_encode($response);
?>
