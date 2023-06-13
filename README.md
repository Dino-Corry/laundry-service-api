# Laundry Service API

#### The Laundry Service API is a PHP-based API that provides functionality for a laundry service application. It includes features such as user authentication (signup, login, forgot password, reset password), handling pickup requests, checking item status, and administrative actions like approving pickup requests etc. The API utilizes MongoDB as the database for storing user and request information. It also integrates with the PHPMailer library for sending email notifications to users and dispatched rider to pickup item and deliver as well when ready.



Class: LaundryServiceAPI

Constructor: __construct($mongoHost, $dbName, $adminCollection, $usersCollection, $driversCollection)
            Initializes the MongoDB connection and sets the collection.

Method: 
    signup($name, $email, $password)
        -Creates a new user account with the provided name, email, and password.
        -Stores the user's information in the users collection.
        -Returns the inserted user's ID.

Method: 
    login($email, $password)
        -Authenticates a user by verifying their email and password.
        -Returns the user's ID if the authentication is successful; otherwise, returns     false.
        -Method: forgotPassword($email)
        -Handles the password reset process for a user by sending a reset token via email.
        -Generates a random reset token, updates the user's document in the collection with the token, and sends an email containing the token to the user.
        -Returns true if the email is sent successfully; otherwise, returns false.


Method: 
    resetPassword($email, $resetToken, $newPassword)
        -Resets the password for a user with the provided email and reset token.
        -Verifies the reset token and updates the user's password in the collection.
        -Returns true if the password is reset successfully; otherwise, returns false.


Method: 
    getItemStatus($requestNumber)
        -Retrieves the status and details of a pickup request based on the given request number.
        -Returns an array with information about the pickup request, including the number of items, amount, status, pickup date, and user email.
        -Returns 'Item not found' if the request number is not found.


Method: 
    makePickupRequest($userId, $numberOfItem, $phone, $pickupAddress)
        -Creates a new pickup request for a user with the provided user ID and the number of items.
        -Generates a request number, calculates the amount, and sets the pickup date and initial status.
        -Adds the pickup request to the user's document in the MongoDB collection.
        -Sends an email to the user with the request details.
        -Returns a message indicating the successful initiation of the pickup request or an error message if the user is not found.

Method: 
    generateRandomToken($length)
        -Generates a random token of the specified length.
        -Used internally by the API to generate reset tokens and request numbers.

Method: 
    handleRequest($method, $endpoint, $data)
        -Handles incoming requests and dispatches them to the appropriate API method based on the endpoint.
        -Accepts the HTTP method (GET, POST, etc.), endpoint, and request data as parameters.
        -Returns the result of the corresponding API method as JSON-encoded data.
        -The API supports the following endpoints and corresponding HTTP methods:

        The API supports the following endpoints and corresponding HTTP methods:

            Endpoint: /users

            Method: POST
            Description: Creates a new user account
            Parameters: name, email, password
            Endpoint: /login

            Method: POST
            Description: Authenticates a user
            Parameters: email, password
            Endpoint: /forgot-password

            Method: POST
            Description: Sends a password reset token via email
            Parameters: email
            Endpoint: /reset-password

            Method: POST
            Description: Resets a user's password
            Parameters: email, `resetToken