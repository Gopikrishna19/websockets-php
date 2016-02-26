<?php
    include "common.php";

    $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    @socket_connect($socket, "localhost", 2429) or die("Connect could not be opened");

    $arr = ["Hello", "I", "am", "Gopikrishna", "Sathyamurthy"];
    $count = 20;    
    while($count-- > 0) {
        $msg = json_encode(["msg" => $arr[rand(0, 4)]]);
        $msg = strlen($msg).$msg;
        echo "sending $msg \n";
        socket_write($socket, $msg, strlen($msg));
    }
?>