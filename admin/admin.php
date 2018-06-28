<?php
header('Access-Control-Allow-Origin: *'); 
require('../dbhelper.php');

$inputs = json_decode(file_get_contents('php://input'), true);
if(!isset($inputs['method'])) {
    die("You are doing wrong!");
}

$method = $inputs['method'];

switch ($method) {
    case 'getAllDevices':
        fetchAllDevices();
        break;

    case 'registerDevice':
        setDeviceSerialAndRegister();
        break;

    case 'getDeviceDetails':
        getDeviceDetails($inputs);
        break;

    case 'registerUser':
        setUserData();
        break;

    case 'getAllUsers':
        getUsers();
        break;

    case 'deviceRegistrationStatus':
        checkDeviceRegistration();
        break;

    case 'saveUserChanges':
        saveUserChanges();
        break;

    case 'getDeviceByTableId':
        fetchDeviceByTableId();
        break;
    
    case 'getDeviceListForLocking':
        fetchDevicesForLocking();
        break;
    
    case 'saveDeviceStatusReport':
        saveDeviceStatusReport();
        break;
    
    case 'getLatestReport':
        getActiveReport();
        break;

    case 'getSiteSettings':
        getSiteSettings();
        break;

    case 'saveSiteSettings':
        saveSiteSettings();
        break;

    default:
        echo 'Please provide proper method!';
        break;
}

function fetchAllDevices() {
    global $conn;
    $result = $conn->query('SELECT * FROM devices');
    $num_rows = $result->num_rows;
    $array = array();
    for ($i=0; $i < $num_rows; $i++) { 
        $result->data_seek($i);
        $row = $result->fetch_assoc();
        $verified_value = strcmp($row['verified'], '0') == 0 ? false : true;
        $device = array(
            'id' => $row['id'], 
            'deviceId' => $row['device_id'],
            'deviceName' => $row['device_name'],
            'deviceModel' => $row['device_model'],
            'deviceManufacturer' => $row['device_manufacturer'],
            'deviceSerial' => $row['device_serial_imei'],
            'verified' => $verified_value, 
            'dateAdded' => $row['date_added']
        );
        $array[$i] = $device;
    }
    echo json_encode($array);
}

function getDeviceDetails($inputs) {
    global $conn;
    if(!isset($inputs['data'])) {
        die('You are doing wrong!');
    } else {
        $params = $inputs['data'];
        if(!isset($params['deviceId'])) {
            die('You are doing wrong!');
        }
    }
    $deviceId = $inputs['data']['deviceId'];
    $result = $conn->query("SELECT * FROM devices WHERE device_id='".$deviceId."'");
    $num_rows = $result->num_rows;
    
    if ($num_rows === 0) die('Specified device does not exist!');

    $result->data_seek(0);
    $row = $result->fetch_assoc();
    $verified_value = strcmp($row['verified'], '0') == 0 ? false : true;
    $device = array(
        'id' => $row['id'], 
        'deviceId' => $row['device_id'],
        'deviceName' => $row['device_name'],
        'deviceModel' => $row['device_model'],
        'deviceManufacturer' => $row['device_manufacturer'],
        'deviceSerial' => $row['device_serial_imei'],
        'verified' => $verified_value, 
        'dateAdded' => $row['date_added']
    );
    echo json_encode($device);
}

function setDeviceSerialAndRegister() {
    global $conn, $inputs;
    // sanitize inputs
    $params;
    if(!isset($inputs['data'])) {
        die('You are doing wrong!');
    } else {
        $params = $inputs['data'];
        if(!isset($params['id']) || !isset($params['serial'])) {
            die('You are doing wrong!');
        }
    }

    $serial = $params['serial'];
    $id = $params['id'];
    
    $stmt = $conn->prepare('UPDATE devices SET device_serial_imei=?, verified=1 WHERE id=?');
    $stmt->bind_param('si', $serial, $id);
    $stmt->execute();
    $stmt->close();

    // fetch updated details
    $result = $conn->query("SELECT * FROM devices WHERE id='$id'");
    $result->data_seek(0);
    $row = $result->fetch_assoc();
    $verified_value = strcmp($row['verified'], '0') == 0 ? false : true;
    $device = array(
        'id' => $row['id'], 
        'deviceId' => $row['device_id'],
        'deviceName' => $row['device_name'],
        'deviceModel' => $row['device_model'],
        'deviceManufacturer' => $row['device_manufacturer'],
        'deviceSerial' => $row['device_serial_imei'],
        'verified' => $verified_value, 
        'dateAdded' => $row['date_added']
    );
    echo json_encode($device);
}

function setUserData() {
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
    // sanitize username entered
    $mysqlEscapedUsername = mysqli_real_escape_string($conn, $username);
    $htmlFormattedUsername = htmlentities($mysqlEscapedUsername);
    if($username === $htmlFormattedUsername) {
        // This username is safe to be used for storage
        // Check if username is already taken
        $result = $conn->query("SELECT * FROM users WHERE username='".$username."'");
        $num_rows = $result->num_rows;
    
        if ($num_rows !== 0) {
            die('This username has already been taken!');
        } else {
            // Username is safe for insertion
            // Check for other details entered
            $params = $inputs['data'];
            if(
                !isset($params['uuid']) ||
                !isset($params['name']) ||
                !isset($params['model']) ||
                !isset($params['manufacturer'])
            ) {
                die('Please provide all the neccessary details first!');
            } else {
                $stmt = $conn->prepare('INSERT INTO users(device_id, username, device_name, device_model, device_manufacturer) VALUES (?,?,?,?,?)');
                $stmt->bind_param('sssss', $params['uuid'], $username, $params['name'], $params['model'], $params['manufacturer']);
                $stmt->execute();
                $stmt->close();
                echo "{ \"code\": \"" . $params['uuid'] . "\" }";
            }
        }
    } else {
        echo 'The username you have entered has illegal characters in it, please enter a valid username!';
    }
}

function getUsers() {
    global $conn;

    $result = $conn->query('SELECT * FROM users');
    $num_rows = $result->num_rows;
    $array = array();
    for ($i=0; $i < $num_rows; $i++) { 
        $result->data_seek($i);
        $row = $result->fetch_assoc();
        $activeValue = strcmp($row['active'], '0') == 0 ? false : true;
        $user = array(
            'id' => $row['id'], 
            'deviceId' => $row['device_id'],
            'username' => $row['username'],
            'deviceName' => $row['device_name'],
            'deviceModel' => $row['device_model'],
            'deviceManufacturer' => $row['device_manufacturer'],
            'active' => $activeValue, 
            'dateAdded' => $row['date_added']
        );
        $array[$i] = $user;
    }

    echo json_encode($array);
}

function checkDeviceRegistration() {
    global $conn, $inputs;
    
    if(!isset($inputs['data'])) {
        die('You are doing wrong!');
    } else {
        $params = $inputs['data'];
        if(!isset($params['uuid'])) {
            die('You are doing wrong!');
        }
    }
    $deviceId = $params['uuid'];
    $result = $conn->query("SELECT * FROM users WHERE device_id='".$deviceId."'");
    $num_rows = $result->num_rows;

    if ($num_rows === 0) {
        // Device has not been registered yet!
        echo 'This device is not registered yet';
    } else {
        // Device is registered but active status is unknown, check
        $result->data_seek(0);
        $row = $result->fetch_assoc();
        $activeValue = strcmp($row['active'], '0') == 0 ? false : true;
        if($activeValue) {
            // device is active
            echo 'This device is registered and is active';
        } else {
            echo 'This device is registered but is inactive';
        }
    }
}

function saveUserChanges() {
    global $conn, $inputs;
    if(!isset($inputs['data'])) {
        die('You are doing wrong!');
    } else {
        $params = $inputs['data'];
        if(!isset($params['users'])) {
            die('You are doing wrong!');
        }
    }

    $modifiedUsers = $params['users'];
    $num_of_users = count($modifiedUsers);

    $stmt = $conn->prepare('UPDATE users SET active=? WHERE id=?');

    for ($i=0; $i < $num_of_users; $i++) { 
        $user = $modifiedUsers[$i];
        $activeValue = $user['active'] ? 1 : 0;
        $stmt->bind_param('ii', $activeValue, $user['id']);
        $stmt->execute();
    }
    $stmt->close();
    echo 'success';
}

function fetchDeviceByTableId() {
    global $conn, $inputs;

    if(!isset($inputs['data'])) {
        die('You are doing wrong!');
    } else {
        $params = $inputs['data'];
        if(!isset($params['id'])) {
            die('You are doing wrong!');
        }
    }

    $id = $params['id'];

    $result = $conn->query("SELECT * FROM devices WHERE id='" . $id . "'");
    if ($result->num_rows > 0) {
        $result->data_seek(0);
        $row = $result->fetch_assoc();
        $device = array(
            'id' => $row['id'], 
            'deviceId' => $row['device_id'],
            'deviceName' => $row['device_name'],
            'deviceModel' => $row['device_model'],
            'deviceManufacturer' => $row['device_manufacturer'],
            'deviceSerial' => $row['device_serial_imei'],
            'verified' => $verified_value, 
            'dateAdded' => $row['date_added']
        );
        echo json_encode($device);
    } else {
        echo 'There is no such device!';
    }
}

function fetchDevicesForLocking(){
    global $conn;

    $result = $conn->query("SELECT device_name FROM devices WHERE id NOT IN ( SELECT device from reservations WHERE active<>0 )");
    $number_of_rows = $result->num_rows;
    $devices = array();

    for ($i=0; $i < $number_of_rows; $i++) { 
        $result->data_seek($i);
        $row= $result->fetch_assoc();
        $devices[$i] = $row['device_name'];
    }

    $json = array();
    $json['devices'] = $devices;
    echo json_encode($json);
}

function saveDeviceStatusReport(){
    global $conn, $inputs;
    if(!isset($inputs['data'])) {
        die('You are doing wrong!');
    } else {
        $params = $inputs['data'];
        if(!isset($params['deviceName'])) {
            die('You are doing wrong!');
        }
    }

    $deviceName=$params['deviceName'];
    $status = $params['status'];

    // Query to get device id
    $result = $conn->query("SELECT id FROM devices WHERE device_name='" . $deviceName . "'");

    // fetch id from result
    $result->data_seek(0);
    $row= $result->fetch_assoc();
    $deviceId = $row['id'];

    // Query to insert with fetched device id
    $stmt = $conn->prepare('INSERT INTO report(device_id, device_name, status) VALUES(?,?,?)');
    $stmt->bind_param('iss', $deviceId, $deviceName, $status);
    $stmt->execute();

    // Query to check if device is present in active report
    $result = $conn->query("SELECT id FROM active_report WHERE device_id='" . $deviceId . "'");
    if ($result->num_rows > 0) {

        // Query to replace the latest report of the device
        $stmt = $conn->prepare('UPDATE active_report SET status=?, timestamp=NULL WHERE device_id=?');
        $stmt->bind_param('si', $status, $deviceId);
        $stmt->execute();
    } else {

        $stmt = $conn->prepare('INSERT INTO active_report(device_id, device_name, status) VALUES(?,?,?)');
        $stmt->bind_param('iss', $deviceId, $deviceName, $status);
        $stmt->execute();
    }
    // done
    echo 'success';
}

function getActiveReport() {
    global $conn;

    $result = $conn->query("SELECT d.id, d.device_name, a.status, a.timestamp FROM active_report a RIGHT OUTER JOIN devices d ON a.device_name = d.device_name");
    $number_of_rows = $result->num_rows;

    $all_devices = array();
    for ($i=0; $i < $number_of_rows; $i++) { 
        $result->data_seek($i);
        $row = $result->fetch_assoc();

        $device = array(
            'id' => $row['id'],
            'deviceName' => $row['device_name'],
            'status' => $row['status'],
            'timestamp' => $row['timestamp']
        );

        $all_devices[$i] = $device;
    }

    echo json_encode($all_devices);
}

function getSiteSettings() {
    global $conn;

    // Check if site settings are present
    $result = $conn->query('SELECT * FROM site_settings');
    $number_of_rows = $result->num_rows;
    // If settings are not present, add defaults
    // else echo
    if ($number_of_rows == 0) {
        $conn->query('INSERT INTO site_settings VALUES(60, 0)');
        $result = $conn->query('SELECT * FROM site_settings');
    }

    $result->data_seek(0);
    $row = $result->fetch_assoc();
    
    $shutdownValue = strcmp($row['shutdown'], '0') == 0 ? false : true;

    $data = array(
        'timeout' => $row['wait_timeout'],
        'shutdown' => $shutdownValue
    );

    echo json_encode($data);
}

function saveSiteSettings() {
    global $conn, $inputs;

    if(!isset($inputs['data'])) {
        die('You are doing wrong!');
    } else {
        $params = $inputs['data'];
        if(!isset($params['timeout']) || !isset($params['shutdown'])) {
            die('You are doing wrong!');
        }
    }

    $timeout = $params['timeout'];
    $shutdown = $params['shutdown'] ? 1 : 0;

    $stmt = $conn->prepare('UPDATE site_settings SET wait_timeout=?, shutdown=?');
    $stmt->bind_param('ii', $timeout, $shutdown);
    $stmt->execute();

    echo 'success';
}

$conn->close();
?>