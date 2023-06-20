<?php
require_once 'config.php';

class LaundryServiceAPI{
    private $mongoClient;
    private $aCollection;
    private $uCollection;
    private $dCollection;

    public function __construct($dbName, $adminCollection, $usersCollection, $driversCollection){
        $this->mongoClient = server($dbName, $usersCollection, $adminCollection, $driversCollection); 
        $this->uCollection = $this->mongoClient->$usersCollection;
        $this->aCollection = $this->mongoClient->$adminCollection;  
        $this->dCollection = $this->mongoClient->$driversCollection;          
                    
    }

    // User authentication: Signup
    public function signup($name, $email, $password){
        $user = [
            'name' => $name,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
        ];

        $result = $this->aCollection->insertOne($user);

        return $result->getInsertedId();
    }

    // Find all admin users
    public function findAllAdmin(){
        $users = $this->aCollection->find();
    
        $userList = [];
        foreach ($users as $user) {
            $userList[] = $user;
        }
    
        return $userList;
    }

    //Add dispatched driver
    public function addDriver($name, $email, $phone){
        // Check if the email already exists
        $existingUser = $this->dCollection->findOne(['email' => $email]);
        if ($existingUser) {
            return [
                'message' => 'Email already exists',
            ];
        }
        $user = [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
        ];

        $result = $this->dCollection->insertOne($user);

        $userId = $result->getInsertedId();

        return [
            'userId' => $userId,
            'message' => 'Driver Added',
        ];
    }

    // Find all customers
    public function findAllUsers(){
        $users = $this->uCollection->find();
        
        $userList = [];
        foreach ($users as $user) {
            $userList[] = $user;
        }
        
        return $userList;
    }

    // Delete customer by Email
    public function deleteUser($email){
        // $result = $this->uCollection->deleteOne(['_id' => new MongoDB\BSON\ObjectID($userID)]);
        $result = $this->uCollection->deleteOne(['email' => $email]);

        return $result->getDeletedCount();
    }

    // Approve pending pickup request and send email to the both the customer and the rider
    public function approveRequest($requestNumber, $adminID){
        // Find the admin user
        $admin = $this->aCollection->findOne(['_id' => new MongoDB\BSON\ObjectID($adminID)]);

        // Check if the admin user exists and is authorized
        if ($admin) {
            // Find the user with the given request number
            $user = $this->uCollection->findOne(['pickup_requests.request_number' => $requestNumber]);

            if ($user) {
                // Update the status of the request
                $pickupRequests = $user['pickup_requests'];
                $requestFound = false;

                foreach ($pickupRequests as &$pickupRequest) {
                    if (isset($pickupRequest['request_number']) && $pickupRequest['request_number'] === $requestNumber) {
                        $pickupRequest['status'] = 'Approved for pickup';
                        $requestFound = true;
                        break;
                    }
                }

                if ($requestFound) {
                    // Update the modified pickupRequests array back into the user document
                    $this->uCollection->updateOne(
                        ['_id' => $user['_id']],
                        ['$set' => ['pickup_requests' => $pickupRequests]]
                    );

                    // Get the customer's email
                    $customerMail = $user['email'];
                    $customerPhone = $user['phone'];

                    // Get a random driver from the driver's collection
                    $randomDriver = $this->dCollection->aggregate([['$sample' => ['size' => 1]]]);
                    $driver = current($randomDriver->toArray());

                    if ($driver) {
                        // Notify the user to get ready for pickup
                        $requestNo = $requestNumber;
                        $driverMail = $driver['email'];
                        $driverName = $driver['name'];
                        $driverPhone = $driver['phone'];

                        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                        $mail->isSMTP();
                        $mail->Host = 'smtp.gmail.com';
                        $mail->SMTPAuth = true;
                        $mail->Username = 'essen653@gmail.com';
                        $mail->Password = 'yrumbywwifhqpwoa';
                        $mail->Port = 465;
                        $mail->SMTPSecure = 'ssl';

                        $mail->setFrom('essen653@gmail.com', 'Golden Laundry Service');
                        $mail->addAddress($customerMail);

                        $mail->Subject = 'Request approved';
                        $mail->Body = 'Your request with item number: ' . $requestNo . ' is now ready for pickup. Rider Mr. ' . $driverName . ' and phone number ' . $driverPhone . ' will call you soon for pickup.';

                        // Notify the driver for pickup
                        $mail2 = new PHPMailer\PHPMailer\PHPMailer(true);
                        $mail2->isSMTP();
                        $mail2->Host = 'smtp.gmail.com';
                        $mail2->SMTPAuth = true;
                        $mail2->Username = 'essen653@gmail.com';
                        $mail2->Password = 'yrumbywwifhqpwoa';
                        $mail2->Port = 465;
                        $mail2->SMTPSecure = 'ssl';

                        $mail2->setFrom('essen653@gmail.com', 'Golden Laundry Service');
                        $mail2->addAddress($driverMail);

                        $mail2->Subject = 'Item Ready for pickup';
                        $mail2->Body = 'Item request with number: ' . $requestNo . ' is now ready for pickup. Please call the customer on ' . $customerPhone . ' to plan pickup.';
                        
                        // Send emails
                        $mail->send();
                        $mail2->send();
                        
                        return "Request approved and emails has been sent to both the customer and the dispatched rider.";
                    } else {
                        return "No available drivers found.";
                    }
                } else {
                    return "Request not found.";
                }
            } else {
                return "User not found.";
            }
        } else {
            return "Admin not found or unauthorized.";
        }
    }

    // Notify the customer that the item is now ready for delivery
    public function readyForDelivery($requestNumber, $adminID){
        // Find the admin user
        $admin = $this->aCollection->findOne(['_id' => new MongoDB\BSON\ObjectID($adminID)]);

        // Check if the admin user exists and is authorized
        if ($admin) {
            // Find the user with the given request number
            $user = $this->uCollection->findOne(['pickup_requests.request_number' => $requestNumber]);

            if ($user) {
                // Update the status of the request
                $pickupRequests = $user['pickup_requests'];
                $requestFound = false;

                foreach ($pickupRequests as &$pickupRequest) {
                    if (isset($pickupRequest['request_number']) && $pickupRequest['request_number'] === $requestNumber) {
                        $pickupRequest['status'] = 'Ready for Delivery';
                        $requestFound = true;
                        break;
                    }
                }

                if ($requestFound) {
                    // Update the modified pickupRequests array back into the user document
                    $this->uCollection->updateOne(
                        ['_id' => $user['_id']],
                        ['$set' => ['pickup_requests' => $pickupRequests]]
                    );

                    // Get the customer's email
                    $customerMail = $user['email'];
                    $customerPhone = $user['phone'];

                    // Get a random driver from the driver's collection
                    $randomDriver = $this->dCollection->aggregate([['$sample' => ['size' => 1]]]);
                    $driver = current($randomDriver->toArray());

                    if ($driver) {
                        // Notify the user to get ready for pickup
                        $requestNo = $requestNumber;
                        $driverMail = $driver['email'];
                        $driverName = $driver['name'];
                        $driverPhone = $driver['phone'];

                        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                        $mail->isSMTP();
                        $mail->Host = 'smtp.gmail.com';
                        $mail->SMTPAuth = true;
                        $mail->Username = 'essen653@gmail.com';
                        $mail->Password = 'yrumbywwifhqpwoa';
                        $mail->Port = 465;
                        $mail->SMTPSecure = 'ssl';

                        $mail->setFrom('essen653@gmail.com', 'Golden Laundry Service');
                        $mail->addAddress($customerMail);

                        $mail->Subject = 'Package Ready';
                        $mail->Body = 'Your package with item number: ' . $requestNo . ' is now ready for delivery. Rider Mr. ' . $driverName . ' and phone number ' . $driverPhone . ' will call you soon for delivery.';

                        // Notify the driver for pickup
                        $mail2 = new PHPMailer\PHPMailer\PHPMailer(true);
                        $mail2->isSMTP();
                        $mail2->Host = 'smtp.gmail.com';
                        $mail2->SMTPAuth = true;
                        $mail2->Username = 'essen653@gmail.com';
                        $mail2->Password = 'yrumbywwifhqpwoa';
                        $mail2->Port = 465;
                        $mail2->SMTPSecure = 'ssl';

                        $mail2->setFrom('essen653@gmail.com', 'Golden Laundry Service');
                        $mail2->addAddress($driverMail);

                        $mail2->Subject = 'Item Ready for delivery';
                        $mail2->Body = 'Item with number: ' . $requestNo . ' is now ready for delivery. Please call the customer on ' . $customerPhone . ' to plan delivery.';
                        
                        // Send emails
                        $mail->send();
                        $mail2->send();
                        
                        return "Request approved and emails has been sent to both the customer and the dispatched rider.";
                    } else {
                        return "No available drivers found.";
                    }
                } else {
                    return "Request not found.";
                }
            } else {
                return "User not found.";
            }
        } else {
            return "Admin not found or unauthorized.";
        }
    }

    // Handle endpoints
    public function handleRequest($method, $endpoint, $data){
        // echo $endpoint;
        switch ($endpoint) {
            case '/findAllAdmin':
                if ($method === 'POST') {
                    $users = $this->findAllAdmin();
                        if ($users) {
                            // Item found, return the item details
                            return json_encode($users);
                        } else {
                            // Item not found
                            return 'No users yet';
                        }
                }
                break;
            case '/findAllUsers':
                if ($method === 'POST') {
                    $users = $this->findAllUsers();
                        if ($users) {
                            // Users found, return the users details
                            return json_encode($users);
                        } else {
                            // Users not found
                            return 'No Users yet';
                        }
                }
                break;
            case '/approveRequest':
                if ($method === 'POST') {
                    if (isset($data['requestNumber']) && isset($data['adminID']) && !empty($data['adminID'])) {
                        $requestNumber = $data['requestNumber'];
                        $adminID = $data['adminID'];
                        $result = $this->approveRequest($requestNumber, $adminID);
                                
                        return $result;
                    } else {
                        return 'Missing parameters';
                    }
                }
                break;                                
            
            case '/deleteUser':
                if ($method === 'POST') {
                    if (isset($data['email'])) { 
                        $userID = $data['email'];
                        $deletedCount = $this->deleteUser($userID);
                        if ($deletedCount > 0) {
                            // User deleted successfully
                            return 'User deleted';
                        } else {
                            // User not found or unable to delete
                            return 'User with the provided email is not found';
                        }
                    } else {
                        return 'Missing parameters'; 
                    }
                }
                break;
            case '/readyForDelivery':
                if ($method === 'POST') {
                    if (isset($data['requestNumber']) && isset($data['adminID']) && !empty($data['adminID'])) {
                        $requestNumber = $data['requestNumber'];
                        $adminID = $data['adminID'];
                        $result = $this->readyForDelivery($requestNumber, $adminID);
                                
                        return $result;
                    } else {
                        return 'Missing parameters';
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
            case '/addDriver':
                if ($method === 'POST') {
                    if (isset($data['name']) && ($data['email']) && ($data['phone'])) { 
                        $email = $data['email'];
    
                        // Check if email already exists
                        $existingUser = $this->dCollection->findOne(['email' => $email]);
                        if ($existingUser) {
                            return 'Email already exists'; // Return error message if email exists
                        } else {
                            $name = $data['name'];
                            $email = $data['email'];
                            $phone = $data['phone'];
                            $details = $this->addDriver($name, $email, $phone);
                            if ($details) {
                                $driver_id = $details['userId'];
                                // Request approved successfully
                                return "Driver registration was successful. Driver ID is: $driver_id";
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

            default:
                return 'Invalid endpoint';
        }
    }
}


// MongoDB configuration
$dbName = 'laundry_service';
$adminCollection = 'admin';
$usersCollection = 'users';
$driversCollection = 'driver';

$api = new LaundryServiceAPI($dbName, $adminCollection, $usersCollection, $driversCollection );

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
