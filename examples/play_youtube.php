<?php
require_once("Chromecast.php");

$cc = new Chromecast();
$cc->connect("192.168.10.135");
$cc->sendCommand(["WATCH", "233637DE","rOSWcnL-MOc","BUFFERED","x-youtube/video",true,0]);
