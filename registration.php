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

    case 'checkSessionId':
        checkSessionId();
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

function loginWebUser() {
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
        
        // Decrypt the password and check in db

        $encrypted_pass = encrypt($password);
        
        $result = $conn->query("SELECT * FROM web_users WHERE username='".$username."' AND password='". $encrypted_pass ."'");
        $num_rows = $result->num_rows;
    
        if ($num_rows !== 0) {
            // send an encrypted form of username and the login time
            date_default_timezone_set('Asia/Kolkata');
            $dt = date('d-m-Y H:i:s');
            $UserSessionData = $username . ' ' . $dt;
            $sessionId = encrypt($UserSessionData);
            echo "{ \"code\": \"" . $sessionId . "\" }";
        } else {
            echo 'The username or password you have entered is incorrect!';
        }
    } else {
        echo 'The username or password you have entered has illegal characters in it, please enter valid data!';
    }
}

function checkSessionId() {
    global $conn, $inputs;
    // check if username is set
    if(!isset($inputs['data'])) {
        die('You are doing wrong!');
    } else {
        $params = $inputs['data'];
        if(!isset($params['sessionId'])) {
            die('You are doing wrong!');
        }
    }

    $enc_sessionId = $params['sessionId'];
    // Decrypt the session and check in db

    $decrypted_session = decrypt($enc_sessionId);
    $userdata = explode(" ", $decrypted_session);
    if(count($userdata) === 3) {
        $username = $userdata[0];
        $result = $conn->query("SELECT * FROM web_users WHERE username='".$username."'");
        $num_rows = $result->num_rows;
        if ($num_rows !== 0) {
            echo 'true';
        } else {
            echo 'false';
        }
    } else {
        echo 'false';
    }
}

$conn->close();
?>