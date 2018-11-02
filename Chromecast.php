<?php

require_once("CastMessage.php");
require_once("mDNSChromecast.php");

class Chromecast
{
    public $socket;
    public $request_id;
    public $media_session_id;
    public $transport_id;
    public $session_id;
    public $last_ip;
    public $timeout;
    public $app_id;
    public $debug;
    public $is_idle;
    public $is_error;
    public $current_volume;
    public $start_time;
    public $max_timeout;

    public function __construct()
    {
        $this->request_id = 1;
        $this->timeout = 10;
        $this->max_timeout = 7;
        $this->is_idle = false;
        $this->is_error = false;
        $this->debug = false;
        $this->start_time = time();
    }

    /**
     * @param $ip
     * @param int $port
     * @throws Exception
     */
    public function connectToSocket($ip, $port = 8009)
    {
        $contextOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false,]]; // Ignore Chromecast's certificate.
        $context = stream_context_create($contextOptions);
        $this->socket = @stream_socket_client('ssl://' . $ip . ":" . $port, $errorCode, $errorMessage, $this->timeout, STREAM_CLIENT_CONNECT, $context);
        if (!$this->socket) {
            throw new Exception("$errorMessage ($errorCode)");
        }
    }

    public function connectToApp()
    {
        // No Response from Chromecast
        $this->sendCastMessage(["sender-0", $this->transport_id, "urn:x-cast:com.google.cast.tp.connection", 0, "{\"type\":\"CONNECT\"}"]);
    }

    public function connectToChromecast()
    {
        // No Response from Chromecast
        $this->sendCastMessage(["sender-0", "receiver-0", "urn:x-cast:com.google.cast.tp.connection", 0, "{\"type\":\"CONNECT\"}"], false);
    }

    public function connect($friendlyNameOrIp)
    {
        if (ip2long($friendlyNameOrIp)) {
            $this->last_ip = $ip = $friendlyNameOrIp;
        } else {
            $this->last_ip = $ip = $this->getIpFromFriendlyName($friendlyNameOrIp);
        }

        try {
            $this->connectToSocket($ip);
        } catch (Exception $e) {
            // Try to re-connect because sometimes the socket connection fails to connect to the ip
            try {
                $this->connectToSocket($ip);
            } catch (Exception $e) {
                throw new Exception($e);
                exit(0);
            }
        }

        // Init Connection
        $this->connectToChromecast();
        $r = $this->getStatus();
        $this->connectToApp();

        preg_match("/\"appId\":\"([^\"]*)/", $r, $m);
        $this->app_id = isset($m[1]) ? $m[1] : "";
    }

    public function scan($wait = 15)
    {
        $mDNSChromecast = new mDNSChromecast();
        return $mDNSChromecast->scan($wait);
    }

    public function launch($app_id)
    {
        if ($app_id != $this->app_id) {
            $this->app_id = $app_id;
            $this->sendCastMessage(["sender-0", "receiver-0", "urn:x-cast:com.google.cast.receiver", 0, "{\"type\":\"LAUNCH\",\"appId\":\"" . $app_id . "\",\"requestId\":" . $this->request_id . "}"]);

            // Verify the app launched
            $this->transport_id = "";
            $my_app_id = "0";
            $old_transport_id = $this->transport_id;
            while (($this->transport_id == "" || $this->transport_id == $old_transport_id || $this->app_id != $my_app_id) && !$this->is_error) {
                $r = $this->getCastMessage();
                preg_match("/\"appId\":\"([^\"]*)/", $r, $m);
                if ($m[1] == $this->app_id) {
                    $my_app_id = $m[1];
                }
            }
        } else {
            // App is already launched, so reconnect
            $this->getStatus();
            $this->connectToApp();
        }
    }

    public function getStatus()
    {
        $this->sendCastMessage(["sender-0", "receiver-0", "urn:x-cast:com.google.cast.receiver", 0, "{\"type\":\"GET_STATUS\",\"requestId\":" . $this->request_id . "}"]);

        $r = "";
        while ($this->transport_id == "") {
            $r = $this->getCastMessage();

            if (preg_match("/level/s", $r)) {
                preg_match("/\"level\":([^\,]*)/", $r, $o);
                $this->current_volume = round($o[1], 2);
            }

            if ((time() - $this->start_time) > 2) {
                break;
            }

        }

        return $r;
    }

    public function getCastMessage()
    {
        $this->checkSocketConnection();
        $r = fread($this->socket, 2000);
        if ($this->debug) {
            echo $r;
        }

        if (preg_match("/urn:x-cast:com.google.cast.tp.heartbeat/", $r) && preg_match("/\"PING\"/", $r)) {
            $this->sendPing();
        }

        if (preg_match("/transportId/s", $r)) {
            preg_match("/transportId\"\:\"([^\"]*)/", $r, $m);
            $this->transport_id = $m[1];
        }

        if (preg_match("/isIdleScreen/s", $r)) {
            preg_match("/\"isIdleScreen\":([^\,]*)/", $r, $n);
            if ($n[1] == "true") {
                $this->is_idle = true;
            }
        }

        if (preg_match("/type/s", $r)) {
            preg_match("/\"type\":([^\,]*)/", $r, $p);
            if (strpos($p[1], 'CLOSE') !== false) {
                $this->is_error = true;
            }
        }

        return $r;
    }

    public function getIpFromFriendlyName($friendlyName)
    {
        $results = $this->scan();
        $ip = "";
        foreach ($results as $result) {
            if ($result['friendly_name'] == $friendlyName) {
                $ip = $result['ip'];
                $this->last_ip = $ip;
                break;
            }
        }

        return $ip;
    }

    public function getMediaSessionId()
    {
        $this->sendMessage("urn:x-cast:com.google.cast.media", '{"type":"GET_STATUS", "requestId":1}'); // All media session IDs will be provided.

        while ($this->media_session_id == "" && !$this->is_idle) {

            $r = $this->getCastMessage();
            preg_match("/\"mediaSessionId\":([^\,]*)/", $r, $m);
            if (isset($m[1])) {
                $this->media_session_id = $m[1];

                if ($this->debug) {
                    echo $this->media_session_id;
                }

            }

            if ((time() - $this->start_time) > 2) {
                break;
            }
        }
    }

    public function sendMessage($urn, $message)
    {
        // Override - if the $urn is urn:x-cast:com.google.cast.receiver then
        // send to receiver-0 and not the running app
        $receiver_id = ($urn == "urn:x-cast:com.google.cast.receiver" || $urn == "urn:x-cast:com.google.cast.tp.connection") ? "receiver-0" : $this->transport_id;
        $this->sendCastMessage(["sender-0", $receiver_id, $urn, 0, $message]);
        $this->getCastMessage();
    }

    public function close()
    {
        stream_socket_shutdown($this->socket, STREAM_SHUT_WR);
    }

    public function sendPong()
    {

        $this->sendCastMessage(["sender-0", "receiver-0", "urn:x-cast:com.google.cast.tp.heartbeat", 0, "{\"type\":\"PONG\"}"]);
        $this->getCastMessage();
    }

    public function sendPing()
    {
        // Stop sending Ping after 7 seconds
        if ((time() - $this->start_time) > $this->max_timeout) {
            $this->is_error = true;
            $this->close();
        } else {
            // Officially run this every 5 seconds to keep the connection alive.
            $this->sendCastMessage(["sender-0", "receiver-0", "urn:x-cast:com.google.cast.tp.heartbeat", 0, "{\"type\":\"PING\"}"]);
            $this->getCastMessage();
        }
    }

    public function checkSocketConnection()
    {
        $status = socket_get_status($this->socket);
        if ($status['eof']) {
            stream_socket_shutdown($this->socket, STREAM_SHUT_WR);

            try {
                $this->connectToSocket($this->last_ip);
            } catch (Exception $e) {
                try {
                    $this->connectToSocket($this->last_ip);
                } catch (Exception $e) {
                    echo $e->getMessage(), "\n";
                    exit(0);
                }
            }
        }
    }

    public function sendCastMessage($msg = [], $updateRequestId = true)
    {
        if ($this->debug) {
            print_r($msg);
        }

        $this->checkSocketConnection();
        if (count($msg) == 5) {
            $c = new CastMessage();
            $c->source_id = $msg[0];
            $c->receiver_id = $msg[1];
            $c->urn_namespace = $msg[2];
            $c->payload_type = $msg[3];
            $c->payload_utf8 = $msg[4];
            fwrite($this->socket, $c->encode());
            fflush($this->socket);
            if ($updateRequestId) {
                $this->request_id++;
            }
        }
    }

    public function sendCustomCommand($command)
    {
        $urn = trim($command[0]);
        $type = trim($command[1]);
        $app_id = isset($command[2]) ? trim($command[2]) : "";

        if (!empty($app_id)) {
            $this->launch($app_id);
        }

        $this->sendMessage($urn, '{"type":"' . $type . '"}');
    }

    public function sendCommand($command)
    {

        if (count($command) >= 1) {
            $type = trim($command[0]);
            $app_id = isset($command[1]) ? trim($command[1]) : "";
            $extra = isset($command[2]) ? trim($command[2]) : "";

            if ($type == "LAUNCH") {
                $this->launch($app_id);
            } else if ($type == "WATCH") {
                array_shift($command);
                $this->play($command);
            } else if ($type == "CUSTOM") {
                array_shift($command);
                $this->sendCustomCommand($command);
            } else {
                $this->getStatus();
                $this->connectToApp();
                $this->getMediaSessionId();

                // Only continue if there is a media session id or system commands
                if (!empty($this->media_session_id) || in_array($type, ["SET_VOLUME", "MUTE", "UNMUTE", "INCREASE_VOLUME", "DECREASE_VOLUME"])) {
                    if ($type == "SET_VOLUME") {
                        $this->sendMessage("urn:x-cast:com.google.cast.receiver", '{"type":"SET_VOLUME", "volume": { "level": ' . $extra . ' } , "requestId":1 }');
                    } else if ($type == "INCREASE_VOLUME") {
                        if ($this->current_volume < 1) {
                            $new_volume = ($this->current_volume + 0.1) >= 1 ? 1 : round($this->current_volume + 0.1, 2);
                            $this->sendMessage("urn:x-cast:com.google.cast.receiver", '{"type":"SET_VOLUME", "volume": { "level": ' . $new_volume . ' } , "requestId":1 }');
                        }
                    } else if ($type == "DECREASE_VOLUME") {
                        if ($this->current_volume > 0) {
                            $new_volume = ($this->current_volume - 0.1) <= 0 ? 0 : round($this->current_volume - 0.1, 2);
                            $this->sendMessage("urn:x-cast:com.google.cast.receiver", '{"type":"SET_VOLUME", "volume": { "level": ' . $new_volume . ' } , "requestId":1 }');
                        }
                    } else if ($type == "MUTE") {
                        $this->sendMessage("urn:x-cast:com.google.cast.receiver", '{"type":"SET_VOLUME", "volume": { "muted": true }, "requestId":1 }');
                    } else if ($type == "UNMUTE") {
                        $this->sendMessage("urn:x-cast:com.google.cast.receiver", '{"type":"SET_VOLUME", "volume": { "muted": false }, "requestId":1 }');
                    } else {
                        $this->sendMessage("urn:x-cast:com.google.cast.media", '{"type":"' . $type . '", "mediaSessionId":' . $this->media_session_id . ', "requestId":1}');
                    }
                }

            }
        }

    }

    private function play($video)
    {
        if (count($video) >= 4) {
            $app_id = trim($video[0]);
            if (!empty($app_id)) {
                $this->launch($app_id);
            }

            $contentId = trim($video[1]);
            $streamType = isset($video[2]) ? trim($video[2]) : "BUFFERED";
            $contentType = isset($video[3]) ? trim($video[3]) : 'video/mp4';
            $autoPlay = isset($video[4]) ? trim($video[4]) ? true : false : true;
            $currentTime = isset($video[5]) ? trim($video[5]) : 0;
            $json = '{"type":"LOAD","media":{"contentId":"' . $contentId . '","streamType":"' . $streamType . '","contentType":"' . $contentType . '"},"autoplay":' . $autoPlay . ',"currentTime":' . $currentTime . ',"requestId":921489134}';
            $this->getStatus();
            $this->connectToApp();
            $this->sendMessage("urn:x-cast:com.google.cast.media", $json);

            $r = "";
            while (!preg_match("/\"playerState\":\"PLAYING\"/", $r) && !$this->is_error) {
                $r = $this->getCastMessage();
                sleep(1);
            }

            // Grab the mediaSessionId
            preg_match("/\"mediaSessionId\":([^\,]*)/", $r, $m);
            $this->media_session_id = $m[1];
        }
    }
}