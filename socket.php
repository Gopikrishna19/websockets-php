<?php
    include "common.php";

    $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    $ipsock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

    socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
    socket_set_option($ipsock, SOL_SOCKET, SO_REUSEADDR, 1);

    socket_bind($socket, "localhost", 2428);
    socket_bind($ipsock, "localhost", 2429);

    socket_listen($socket);
    socket_listen($ipsock);

    $clients = array($socket);
    $feeds = array($ipsock);

    echo $socket."\n";

    while(true) {
        $new_clients = $clients;
        $new_feeds = $feeds;

        socket_select($new_clients, $null, $null, 0, 10);
        socket_select($new_feeds, $null, $null, 0, 10);

        if(in_array($socket, $new_clients)) {
            echo "Found new client\n";
            $new_socket = socket_accept($socket);
            perform_handshaking(socket_read($new_socket, 1024), $new_socket);
            $clients[] = $new_socket;

            send_message(json_encode(["msg" => "Welcome"]));

            unset($new_clients[array_search($socket, $new_clients)]);
        }

        if(in_array($ipsock, $new_feeds)) {
            echo "Found new Broadcaster\n";
            $new_socket = socket_accept($ipsock);
            $feeds[] = $new_socket;
            unset($new_feeds[array_search($socket, $new_feeds)]);
        }

        foreach($new_clients as $client) {
            while(socket_recv($client, $buf, 1024, 0) >= 1) {
                send_message(unmask($buf), $client);
                break 2;
            }

            $buf = @socket_read($client, 1024, PHP_NORMAL_READ);
            if ($buf === false) {
                unset($clients[array_search($client, $clients)]);
                echo "Client closed \n";
            }
        }

        foreach($new_feeds as $feed) {
            while(socket_recv($feed, $buf, 1024, 0) >= 1) {
                while($buf != "") {
                    preg_match("/^\d+/", $buf, $matches);
                    $len = $matches[0];
                    $obj = substr($buf, strlen($len), $len);
                    $buf = substr($buf, strlen($len) + $len);
                    echo $obj." ".$len."\n";
                    send_message($obj, $feed);
                }
                //send_message($buf, $feed);
                break 2;
            }

            $buf = @socket_read($feed, 1024, PHP_NORMAL_READ);
            if ($buf === false) {
                unset($feeds[array_search($feed, $feeds)]);
                echo "Broadcaster closed \n";
            }
        }
    }

    socket_close($socket);

    function send_message($msg, $origclient = NULL) {
        $msg = mask($msg);
        global $clients;
        foreach($clients as $client) {
            if($origclient != $client) @socket_write($client, $msg, strlen($msg));
        }
    }

    function perform_handshaking($header, $client) {
        $host = "localhost";
        $port = 2428;

        $secAccept = getAcceptKey($header);

        $upgrade  = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
                    "Upgrade: websocket\r\n" .
                    "Connection: Upgrade\r\n" .
                    "WebSocket-Origin: $host\r\n" .
                    "WebSocket-Location: ws://$host:$port/index.php\r\n".
                    "Sec-WebSocket-Accept: $secAccept\r\n\r\n";

        @socket_write($client, $upgrade,strlen($upgrade));
    }

    function getAcceptKey($header) {
        $headers = array();
        $lines = preg_split("/\r\n/", $header);
        foreach($lines as $line) {
            $line = chop($line);
            if(preg_match('/\A(\S+): (.*)\z/', $line, $matches)) {
                $headers[$matches[1]] = $matches[2];
            }
        }
        $secKey = $headers['Sec-WebSocket-Key'];
        return base64_encode(pack('H*', sha1($secKey.'258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
    }
?>
