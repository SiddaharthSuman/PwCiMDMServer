<?php
header('Access-Control-Allow-Origin: *');
require('generalEncryptorDecryptor.php');
require('dbhelper.php');

$inputs = json_decode(file_get_contents('php://input'), true);
if(!isset($inputs['method'])) {
    die("You are doing wrong!");
}

$method = $inputs['method'];

switch ($method) {
    case 'registerWebUser':
        registerWebUser();
        break;

    case 'loginWebUser':
        loginWebUser();
        break;

    case 'getAllActiveReservations':
        fetchAllActiveReservations();
        break;

    case 'reserveDevice':
        reserveDeviceForUser();
        break;
    
    case 'releaseDevice':
        releaseDevice();
        break;

    default:
        echo 'Please provide proper method!';
        break;
}

function registerWebUser() {
    global $conn, $inputs;
    // check if username is set
    if(!isset($inputs['data'])) {
        die('You are doing wrong!');
    } else {
        $params = $inputs['data'];
        if(!isset($params['username'])) {
            die('You are doing wrong!');
        }
    }

    $username = $params['username'];
    $password = $params['password'];

    // sanitize username & password entered
    $mysqlEscapedUsername = mysqli_real_escape_string($conn, $username);
    $htmlFormattedUsername = htmlentities($mysqlEscapedUsername);

    $mysqlEscapedPassword = mysqli_real_escape_string($conn, $password);
    $htmlFormattedPassword = htmlentities($mysqlEscapedPassword);

    if($username === $htmlFormattedUsername && $password === $htmlFormattedPassword) {
        // This username is safe to be used for storage
        // Check if username is already taken
        $result = $conn->query("SELECT * FROM web_users WHERE username='".$username."'");
        $num_rows = $result->num_rows;
    
        if ($num_rows !== 0) {
            die('This username has already been taken!');
        } else {
            // Username is safe for insertion
            $enc_pass = encrypt($password);
            $stmt = $conn->prepare('INSERT INTO web_users(username, password) VALUES (?,?)');
            $stmt->bind_param('ss', $username, $enc_pass);
            $stmt->execute();
            $stmt->close();
            echo "success";
        }
    } else {
        echo 'The username or password you have entered has illegal characters in it, please enter valid data!';
    }
}

$conn->close();
?>