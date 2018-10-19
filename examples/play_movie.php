<?php
require_once("Chromecast.php");

$cc = new Chromecast();
$cc->connect("192.168.10.135");
$cc->sendCommand(["WATCH", "CC1AD845", "https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4","BUFFERED","video/mp4",true,0]);