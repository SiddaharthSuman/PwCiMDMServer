<?php
header('Access-Control-Allow-Origin: *'); 
require('dbhelper.php');

$inputs = json_decode(file_get_contents('php://input'), true);
if(!isset($inputs['deviceID']) || !isset($inputs['deviceName']) || !isset($inputs['deviceModel']) || !isset($inputs['deviceManufacturer'])) {
    die("You are doing wrong!");
}
$plaintext = $inputs['deviceID']; // "2A78049C-D72C-411D-ADDC-C43BCCD42E0C"; //Take from POST data
$deviceName = $inputs['deviceName'];
$deviceModel = $inputs['deviceModel'];
$deviceManufacturer = $inputs['deviceManufacturer'];
$key = "ssuman018";
$tag = "SidPwC";
$cipher = "AES-256-CBC";
$iv = "";

//After acquiring the deviceID, check if the device is present on the server
checkIfDevicePresentInDB($plaintext, $deviceName, $deviceModel, $deviceManufacturer);

date_default_timezone_set('Asia/Kolkata');
$dt = date('d-m-Y H:i:s');
encrypt($dt);

function encrypt($dt) {
    global $conn, $plaintext, $cipher, $key, $iv;
    $result = $conn->query('SELECT * FROM general');
    if($result->num_rows > 0){
        $result->data_seek(0);
        $row = $result->fetch_assoc();
        fetchAndSet($row);
        $ciphertext = openssl_encrypt($plaintext.' '.$dt, $cipher, $key, $options=0, $iv);
        echo $ciphertext;
    } else {
        if (in_array($cipher, openssl_get_cipher_methods()))
        {
            $ivlen = openssl_cipher_iv_length($cipher);
            $iv = openssl_random_pseudo_bytes($ivlen);
        }
        pushAndPrepare();
    }

}

function fetchAndSet($row) {
    global $key, $tag, $iv;
    $key = $row['pkey'];
    $tag = $row['tag'];
    $iv = $row['vector'];
}

function pushAndPrepare() {
    global $conn, $key, $tag, $iv;
    $stmt = $conn->prepare("INSERT INTO general(pkey,tag,vector) VALUES(?,?,?)");
    $stmt->bind_param("sss", $key, $tag, $iv);
    $stmt->execute();
    $stmt->close();
}

function checkIfDevicePresentInDB($deviceId, $deviceName, $deviceModel, $deviceManufacturer) {
    global $conn;
    $result = $conn->query("SELECT * FROM devices WHERE device_id='$deviceId'");
    if($result->num_rows == 0) {
        //Add this device in the list
        $stmt = $conn->prepare("INSERT INTO devices(device_id,device_name,device_model,device_manufacturer) VALUES(?,?,?,?)");
        $stmt->bind_param("ssss", $deviceId, $deviceName, $deviceModel, $deviceManufacturer);
        $stmt->execute();
        $stmt->close();
    }
}

$conn->close();
?>