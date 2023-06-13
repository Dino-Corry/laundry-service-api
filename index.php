<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php'; 
require 'config.php';




class LaundryServiceAPI{
    private $mongoClient;
    private $collection;

    public function __construct($dbName, $usersCollection){
        $this->mongoClient = server($dbName, $usersCollection); 
        $this->collection = $this->mongoClient->$usersCollection;            
                    
    }

    // User authentication: Signup
    public function signup($name, $email, $password, $phone){
        // Check if the email already exists
        $existingUser = $this->collection->findOne(['email' => $email]);
        if ($existingUser) {
            return [
                'message' => 'Email already exists',
            ];
        }
        $user = [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'password' => password_hash($password, PASSWORD_DEFAULT),
        ];

        $result = $this->collection->insertOne($user);

        $userId = $result->getInsertedId();

        return [
            'userId' => $userId,
            'message' => 'Signup successful',
        ];
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
                    $numberOfItem = $pickupRequest['number_of_item'];
                    $amount = $pickupRequest['amount'];
                    $userEmail = $user['email'];
    
                    // Create the item details array
                    $itemDetails = [
                        'number_of_item' => $numberOfItem,
                        'amount' => $amount,
                        'status' => $status,
                        'user_email' => $userEmail
                    ];
                    return $itemDetails;
                    // if ($status == 1) {
                    //     $itemDetails = [
                    //         'number_of_item' => $numberOfItem,
                    //         'amount' => $amount,
                    //         'status' => "Your item has been approved for pickup. Dispatch driver will call you for pickup",
                    //         'user_email' => $userEmail
                    //     ];
    
                    //     return $itemDetails;
                    // } elseif ($status == 2) {
                    //     $itemDetails = [
                    //         'number_of_item' => $numberOfItem,
                    //         'amount' => $amount,
                    //         'status' => "Your item is now ready for delivery, wait for our call",
                    //         'user_email' => $userEmail
                    //     ];
    
                    //     return $itemDetails;
                    // } else {
                    //     $itemDetails = [
                    //         'number_of_item' => $numberOfItem,
                    //         'amount' => $amount,
                    //         'status' => "Pickup request is still pending",
                    //         'user_email' => $userEmail
                    //     ];
    
                    //     return $itemDetails;
                    // }
                }
            }
        }
    
        // Request not found
        return 'Item not found';
    }
    

    // Make a pickup request
    public function makePickupRequest($userId, $numberOfItem, $phone, $pickupAddress){
        $user = $this->collection->findOne(['_id' => new MongoDB\BSON\ObjectID($userId)]);


        if ($user) {
            // Create a new pickup request with a tracking code
            $requestNumber = $this->generateRandomToken();
            $totalCost = $numberOfItem * 700;
            $cost = "N700";
            $pickupRequest = [
                'request_number' => $requestNumber,
                'phone' => $phone,
                'address' => $pickupAddress,
                'delivery_date' => '',
                'number_of_item' => $numberOfItem,
                'amount' => $numberOfItem * 700,
                'date_created' => date('Y-m-d'),
                'status' => 'Pickup request sent. Waiting for approval',
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
                ' Request Number:' . $requestNumber .
                ' Address:' . $pickupAddress .
                ' Contact Number:' . $phone .
                ' Number of Items:' . $numberOfItem .
                ' Cost per Item: ' . $cost .
                ' Total Cost: ' . $totalCost;
            
            if ($mail->send()) {
                $name = $user['name'];
                $message = 'Pickup Request Initiated with the following details: <br>' .
                ' Name: ' . $name . '<br>' .
                ' Request Number : ' . $requestNumber . '<br>' .
                ' Address :' . $pickupAddress . '<br>' .
                ' Contact Number :' . $phone . '<br>' .
                ' Number of Items :' . $numberOfItem . '<br>' .
                ' Cost per Item :' . $cost . '<br>' .
                ' Total Cost :' . 'N'.$totalCost . '<br>' .
                ' Please check your mail for more details';
                return $message;
            } else {
                return false; 
            }


            return true;
        }

        // User not found
        return false;
    }


    //Generate random token to reset password
    //Generate request Number
    private function generateRandomToken($length = 6) {
        $characters = '0123456789ABCDE';
        $token = '';
        for ($i = 0; $i < $length; $i++) {
            $token .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $token;
    }

    //Handle all end-points
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
            
            case '/makePickupRequest':
                if ($method === 'POST') {
                    if (isset($data['userID']) && ($data['numberOfItem']) && ($data['phone']) && ($data['pickupAddress'])) { 
                        $userId = ($data['userID']);
                        $numberOfItem = ($data['numberOfItem']);
                        $phone = ($data['phone']);
                        $pickupAddress = ($data['pickupAddress']);
                        $details = $this->makePickupRequest($userId, $numberOfItem, $phone, $pickupAddress);
                        if ($details) {
                            return json_encode($details);
                        } else {
                            return 'Request failed to send';
                        }
                    } else {
                        return 'Missing parameters'; 
                    }
                }
                break;
            case '/signup':
                if ($method === 'POST') {
                    if (isset($data['name']) && ($data['email']) && ($data['password'])) { 
                        $email = $data['email'];
                        // die(var_dump($this->mongoClient));

                        // Check if email already exists
                        $existingUser = $this->collection->findOne(['email' => $email]);
                        if ($existingUser) {
                            return 'Email already exists'; // Return error message if email exists
                        } else {
                            $name = $data['name'];
                            $email = $data['email'];
                            $password = $data['password'];
                            $phone = $data['phone'];
                            $details = $this->signup($name, $email, $password, $phone);
                            if ($details) {
                                $user_id = $details['userId'];
                                // Request approved successfully
                                return "User registration was successful. Your user ID is: $user_id";
                            } else {
                                // Request not found or unable to approve
                                return 'Failed to register';
                            }
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
                            return 'Failed to send token';
                        }
                    } else {
                        return 'Missing parameters'; 
                    }
                }
                break;
            case '/resetPassword':
                if ($method === 'POST') {
                    if ($data['resetToken'] == null) {
                        if (isset($data['email']) && ($data['resetToken']) && ($data['newPassword'])){ 
                        $email = $data['email'];
                        $resetToken = $data['resetToken'];
                        $newPassword = $data['newPassword'];
                        $p_email = $this->resetPassword($email, $resetToken, $newPassword);
                        if ($p_email) {
                            return 'You\'ve successfully reset your password ';
                        } else {
                            return 'Password reset failed. Please confirm your Token';
                        }
                        } else {
                            return 'Missing parameters'; 
                        }
                    } else {
                        return 'Failed. Please go to forgot password to generate reset token.';
                    }
                    
                }
                break;
                         

            default:
                return 'Invalid endpoint';
        }
    }
    
}

// MongoDB configuration
$dbName = 'laundry_service';
$usersCollection = 'users';


$api = new LaundryServiceAPI($dbName, $usersCollection);

$method = $_SERVER['REQUEST_METHOD'];
$endpoint = parse_url($_SERVER['PATH_INFO'], PHP_URL_PATH);
$endpoint = rtrim($endpoint, '/');

$data = $_POST; 



// Handle the request
// var_dump($data);
$response = $api->handleRequest($method, $endpoint, $data);

// Set the appropriate headers
// header('Content-Type: application/json');

// Send the response
echo json_encode($response);
?>
