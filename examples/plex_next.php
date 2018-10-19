<?php
require_once("Chromecast.php");

$cc = new Chromecast();
$cc->connect("192.168.10.135");
$cc->sendCommand(["CUSTOM","NEXT", "urn:x-cast:plex","9AC194DC"]);
