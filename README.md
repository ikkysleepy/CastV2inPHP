## Synopsis
Functions to control a Chromecast with PHP using a reverse engineered Castv2 protocol. Provides ability to control a Chromecast either locally or remotely from a server. 

This project is a modified fork from [CastV2inPHP](https://github.com/ChrisRidings/CastV2inPHP).
 I refactored the code and created it such that I can pause and play any media quickly.
 
Use the PHP class to do the following:
* Play
* Pause
* Mute
* Unmute
* Set Volume
* Increase Volume
* Decrease Volume
* Launch App
* Play Video 

## Code Examples

Pause 

```php
require_once("Chromecast.php");

$cc = new Chromecast();
$cc->connect("192.168.10.135");
$cc->sendCommand(["PAUSE"]);
```

Open Youtube and watch Video 
```php
require_once("Chromecast.php");

$cc = new Chromecast();
$cc->connect("192.168.10.135");
$cc->sendCommand(["WATCH", "233637DE","jke8ILaBRWA","BUFFERED","x-youtube/video",true,0]);
```

Play/Restart video with friendly name instead of IP

```php
require_once("Chromecast.php");

$cc = new Chromecast();
$cc->connect("Living Room TV");
$cc->sendCommand(["PLAY"]);
```

## NOTES
The default port a Chromecast uses is 8009.
Using Friendly Name causes 2-3 seconds delay to connect because it's using mDNS to find all Google Devices with that name.
