<?php
// Class to represent a protobuf object for a command.
class CastMessage
{

    public $protocol_version; 
    public $source_id;
    public $receiver_id;
    public $urn_namespace;
    public $payload_type;
    public $payload_utf8;

    public function __construct()
    {
        $this->protocol_version = 0; // CASTV2_1_0 - It's always this
        $this->payload_type = 0; // PayloadType String=0 Binary = 1
    }

    public function encode()
    {
        // Deliberately represent this as a binary first (for readability and so it's obvious what's going on.
        // speed impact doesn't really matter!)
        // First the protocol version
        $r = "00001"; // Field Number 1
        $r .= "000"; // Int

        // Value is always 0
        $r .= $this->int_bin($this->protocol_version);

        // Now the Source id
        $r .= "00010"; // Field Number 2
        $r .= "010"; // String
        $r .= $this->str_bin($this->source_id);

        // Now the Receiver id
        $r .= "00011"; // Field Number 3
        $r .= "010"; // String
        $r .= $this->str_bin($this->receiver_id);

        // Now the namespace
        $r .= "00100"; // Field Number 4
        $r .= "010"; // String
        $r .= $this->str_bin($this->urn_namespace);

        // Now the payload type
        $r .= "00101"; // Field Number 5
        $r .= "000"; // VarInt
        $r .= $this->int_bin($this->payload_type);

        // Now payloadutf8
        $r .= "00110"; // Field Number 6
        $r .= "010"; // String
        $r .= $this->str_bin($this->payload_utf8);

        // Ignore payload_binary field 7 as never used

        // Now convert it to a binary packet
        $hex_str = "";
        for ($i = 0; $i < strlen($r); $i = $i + 8) {
            $this_chunk = substr($r, $i, 8);
            $hex = dechex(bindec($this_chunk));
            if (strlen($hex) == 1) {
                $hex = "0" . $hex;
            }
            $hex_str .= $hex;
        }
        
        $l = strlen($hex_str) / 2;
        $l = dechex($l);
        while (strlen($l) < 8) {
            $l = "0" . $l;
        }
        
        $hex_str = $l . $hex_str;
        return hex2bin($hex_str);
    }

    private function int_bin($number)
    {
        // Convert an integer to a binary varint
        // A variant is returned least significant part first.
        // Number is represented in 7 bit portions. The 8th (MSB) of a byte represents if there
        // is a following byte.
        $r = [];
        while ($number / 128 > 1) {
            $this_number = ($number - ($number % 128)) / 128;
            array_push($r, $this_number);
            $number = $number - ($this_number * 128);
        }
        
        array_push($r, $number);
      
        $r = array_reverse($r);
        $bin_str = "";
        $c = 1;
        foreach ($r as $num) {
            if ($c != sizeof($r)) {
                $num = $num + 128;
            }
            
            $tv = decbin($num);
            while (strlen($tv) < 8) {
                $tv = "0" . $tv;
            }

            $c++;
            $bin_str .= $tv;
        }

        return $bin_str;
    }
    
    private function str_bin($string)
    {
        // Convert a string to a Binary string
        // First the length (note this is a binary varint)
        $len = strlen($string);
        $ret = $this->int_bin($len);
        for ($i = 0; $i < $len; $i++) {
            $n = decbin(ord(substr($string, $i, 1)));
            while (strlen($n) < 8) {
                $n = "0" . $n;
            }
            $ret .= $n;
        }
        
        return $ret;
    }

}