<?php
header('Access-Control-Allow-Origin: *'); 
require('dbhelper.php');

$key = "ssuman018";
$tag = "SidPwC";
$cipher = "AES-256-CBC";
$iv = "";

function encrypt($plaintext) {
    global $conn, $cipher, $key, $iv;
    $result = $conn->query('SELECT * FROM general');
    if($result->num_rows > 0){
        $result->data_seek(0);
        $row = $result->fetch_assoc();
        fetchAndSet($row);
        $ciphertext = openssl_encrypt($plaintext, $cipher, $key, $options=0, $iv);
        return $ciphertext;
    } else {
        if (in_array($cipher, openssl_get_cipher_methods()))
        {
            $ivlen = openssl_cipher_iv_length($cipher);
            $iv = openssl_random_pseudo_bytes($ivlen);
        }
        pushAndPrepare();
    }

}

function decrypt($ciphertext) {
    global $conn, $cipher;

    $result = $conn->query('SELECT * FROM general');
    if($result->num_rows > 0){
        $result->data_seek(0);
        $row = $result->fetch_assoc();
        $key = $row['pkey'];
        $tag = $row['tag'];
        $iv = $row['vector'];
        $original_plaintext = openssl_decrypt($ciphertext, $cipher, $key, $options=0, $iv);
        return $original_plaintext;
    } else {
        echo 'There are no values present';
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

$conn->close();
?>