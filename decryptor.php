<?php
header('Access-Control-Allow-Origin: *'); 
require('dbhelper.php');

//Get from POST
$inputs = json_decode(file_get_contents('php://input'), true);
if(!isset($inputs['cipher'])) {
    die("You are doing wrong!");
}
$ciphertext = $inputs['cipher'];
$cipher = "AES-256-CBC";

$result = $conn->query('SELECT * FROM general');
if($result->num_rows > 0){
    $result->data_seek(0);
    $row = $result->fetch_assoc();
    $key = $row['pkey'];
    $tag = $row['tag'];
    $iv = $row['vector'];
    $original_plaintext = openssl_decrypt($ciphertext, $cipher, $key, $options=0, $iv);
    echo processCode($original_plaintext);

} else {
    echo 'There are no values present';
}

function processCode($code) {
    date_default_timezone_set('Asia/Kolkata');
    $dt = date('d-m-Y H:i:s');
    try {
        $values = explode(' ', $code);
        $dateValues = explode('-', $values[1]);
        $timeValues = explode(':', $values[2]);
        $codeDate = date('d-m-Y H:i:s', mktime($timeValues[0], $timeValues[1], $timeValues[2], $dateValues[1], $dateValues[0], $dateValues[2]));
        if ((abs(strtotime($dt) - strtotime($codeDate))/60) > 10) {
            return 'This code is too old!';
        } else {
            return $code;
        }
    } catch (Exception $e) {
        return 'Faced exception: '.$e->getMessage();
    }
}

$conn->close();
?>