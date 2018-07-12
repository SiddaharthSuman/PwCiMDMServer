<?php
header('Access-Control-Allow-Origin: *');
require('../generalEncryptorDecryptor.php');
require('../dbhelper.php');

$inputs = json_decode(file_get_contents('php://input'), true);
if(!isset($inputs['method'])) {
    die("You are doing wrong!");
}

$method = $inputs['method'];

switch ($method) {
    case 'getAllReservations':
        fetchAllReservations();
        break;

    case 'getActiveReservations':
        fetchActiveReservationsForUser();
        break;

    case 'getActiveWebReservations':
        fetchActiveReservationsForWebUser();
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

    case 'getNewCode':
        getNewCode();
        break;

    default:
        echo 'Please provide proper method!';
        break;
}

function fetchAllReservations() {
    global $conn;
    /**
     * SELECT r.*, d.device_name, u.username, v.username as previousUsername 
     * FROM reservations r 
     * INNER JOIN devices d 
     * ON r.device=d.id 
     * INNER JOIN users u 
     * ON r.user=u.id 
     * LEFT OUTER JOIN users v 
     * ON r.previous_user=v.id
     */
    $joiner_query = 'SELECT r.*, d.device_name, u.username, v.username as previousUsername FROM reservations r INNER JOIN devices d ON r.device=d.id INNER JOIN users u ON r.user=u.id LEFT OUTER JOIN users v ON r.previous_user=v.id ORDER BY r.timestamp DESC';
    $result = $conn->query($joiner_query);
    $num_rows = $result->num_rows;
    $array = array();
    for ($i=0; $i < $num_rows; $i++) { 
        $result->data_seek($i);
        $row = $result->fetch_assoc();
        $active_value = strcmp($row['active'], '0') == 0 ? false : true;
        $device = array(
            'id' => $row['id'], 
            'device' => $row['device'],
            'user' => $row['user'],
            'startTime' => $row['start_time'],
            'endTime' => $row['end_time'],
            'previousUser' => $row['previous_user'],
            'active' => $active_value, 
            'timestamp' => $row['timestamp'],
            'deviceName' => $row['device_name'],
            'username' => $row['username'],
            'previousUsername' => $row['previousUsername']
        );
        $array[$i] = $device;
    }
    echo json_encode($array);
}

function fetchActiveReservationsForUser() {
    global $conn, $inputs;

    if(!isset($inputs['data'])) {
        die('You are doing wrong!');
    } else {
        $params = $inputs['data'];
        if(!isset($params['deviceId'])) {
            die('You are doing wrong!');
        }
    }

    $deviceId = $params['deviceId'];

    $result = $conn->query("SELECT * FROM reservations WHERE active<>0 AND user IN (SELECT id FROM users WHERE device_id='". $deviceId ."')");
    $num_rows = $result->num_rows;
    $array = array();
    for ($i=0; $i < $num_rows; $i++) { 
        $result->data_seek($i);
        $row = $result->fetch_assoc();
        $reservation = array(
            'id' => $row['id'], 
            'device' => $row['device'],
            'user' => $row['user'],
            'startTime' => $row['start_time'],
            'endTime' => $row['end_time']
        );
        $array[$i] = $reservation;
    }
    echo json_encode($array);
}

function fetchActiveReservationsForWebUser() {
    global $conn, $inputs;

    if(!isset($inputs['data'])) {
        die('You are doing wrong!');
    } else {
        $params = $inputs['data'];
        if(!isset($params['id'])) {
            die('You are doing wrong!');
        }
    }

    $userId = $params['id'];
    $linkedId = $params['linkId'];

    if ($linkedId === NULL) {
        $result = $conn->query("SELECT * FROM reservations WHERE active<>0 AND webuser_id_used<>0 AND user='". $userId ."'");
    } else {
        // Check with union
        $q1 = "SELECT * FROM reservations WHERE active<>0 AND webuser_id_used<>0 AND user='". $userId ."'";
        $q2 = "SELECT * FROM reservations WHERE active<>0 AND webuser_id_used=0 AND user='". $linkedId ."'";
        $result = $conn->query($q1 . " UNION " . $q2);
    }

    $num_rows = $result->num_rows;
    $array = array();
    for ($i=0; $i < $num_rows; $i++) { 
        $result->data_seek($i);
        $row = $result->fetch_assoc();
        $reservation = array(
            'id' => $row['id'], 
            'device' => $row['device'],
            'user' => $row['user'],
            'startTime' => $row['start_time'],
            'endTime' => $row['end_time']
        );
        $array[$i] = $reservation;
    }
    echo json_encode($array);
}

function fetchAllActiveReservations() {
    global $conn;

    $result = $conn->query('SELECT * FROM reservations WHERE active<>0');
    $num_rows = $result->num_rows;
    $array = array();
    for ($i=0; $i < $num_rows; $i++) { 
        $result->data_seek($i);
        $row = $result->fetch_assoc();
        $reservation = array(
            'id' => $row['id'], 
            'device' => $row['device'],
            'user' => $row['user'],
            'startTime' => $row['start_time'],
            'endTime' => $row['end_time']
        );
        $array[$i] = $reservation;
    }
    echo json_encode($array);
}

function reserveDeviceForUser() {
    global $conn, $inputs;

    if(!isset($inputs['data'])) {
        die('You are doing wrong!');
    } else {
        $params = $inputs['data'];
        if(!isset($params['reservation'])) {
            die('You are doing wrong!');
        }
    }

    $reservationData = $params['reservation'];

    $result = $conn->query("SELECT id FROM users WHERE device_id='". $reservationData['user'] ."'");
    $result->data_seek(0);
    $row = $result->fetch_assoc();
    $user = $row['id'];

    // Check if the device is already reserved by someone else
    $result = $conn->query("SELECT * FROM reservations WHERE active<>0 AND device='". $reservationData['device'] ."'");
    $num_rows = $result->num_rows;

    if($num_rows == 0) {
        // insert without previous user id
        $stmt = $conn->prepare('INSERT INTO reservations(device, user, start_time, end_time, active) VALUES (?,?,?,?,1)');
        $stmt->bind_param('iiss', $reservationData['device'], $user, $reservationData['startTime'], $reservationData['endTime']);
        $stmt->execute();
        $stmt->close();
    } else {
        $result->data_seek(0);
        $row = $result->fetch_assoc();
        $previous_user = $row['user'];

        // mark old reservation complete
        $stmt = $conn->query("UPDATE reservations SET active=0, end_time=null WHERE active<>0 AND device='". $reservationData['device'] ."'");

        // insert with previous user id
        $stmt = $conn->prepare('INSERT INTO reservations(device, user, start_time, end_time, previous_user, active) VALUES (?,?,?,?,?,1)');
        $stmt->bind_param('iissi', $reservationData['device'], $user, $reservationData['startTime'], $reservationData['endTime'], $previous_user);
        $stmt->execute();
        $stmt->close();
    }
    
    $result = $conn->query("SELECT * FROM reservations WHERE active<>0 AND user='" . $user . "'");
    $num_rows = $result->num_rows;
    $array = array();
    for ($i=0; $i < $num_rows; $i++) { 
        $result->data_seek($i);
        $row = $result->fetch_assoc();
        $reservation = array(
            'id' => $row['id'], 
            'device' => $row['device'],
            'user' => $row['user'],
            'startTime' => $row['start_time'],
            'endTime' => $row['end_time']
        );
        $array[$i] = $reservation;
    }
    echo json_encode($array);
}

function releaseDevice() {
    global $conn, $inputs;

    if(!isset($inputs['data'])) {
        die('You are doing wrong!');
    } else {
        $params = $inputs['data'];
        if(!isset($params['reservation'])) {
            die('You are doing wrong!');
        }
    }

    $reservationData = $params['reservation'];

    $stmt = $conn->query("UPDATE reservations SET active=0, end_time=null WHERE active<>0 AND device='". $reservationData['device'] ."'");

    echo 'success';

}

function getNewCode() {
    global $inputs;

    if(!isset($inputs['data'])) {
        die('You are doing wrong!');
    } else {
        $params = $inputs['data'];
        if(!isset($params['sessionId'])) {
            die('You are doing wrong!');
        }
    }

    $sessionId = $params['sessionId'];

    $decrypted_session = decrypt($sessionId);
    $userdata = explode(" ", $decrypted_session);
    if(count($userdata) === 3) {
        $username = $userdata[0];
        date_default_timezone_set('Asia/Kolkata');
        $dt = date('d-m-Y H:i:s');
        $UserSessionData = $username . ' ' . $dt;
        $code = encrypt($UserSessionData);

        echo $code;
    } else {
        echo 'Some error occurred in getting new code';
    }
}

$conn->close();
?>