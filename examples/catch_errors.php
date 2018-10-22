<?php
require_once("Chromecast.php");

$cc = new Chromecast();
try {
    $cc->connect("192.168.10.135");
    $cc->sendCommand(["WATCH", "233637DE","rOSWcnL-MOc","BUFFERED","x-youtube/video",true,0]);
    $cc->close(); // Optional
} catch (Exception $e) {
    $error = "Something Went Wrong\n";
}

echo $error;