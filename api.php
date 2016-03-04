<?php

date_default_timezone_set('America/Los_Angeles');
error_reporting(E_ALL);
ini_set("display_errors", 1);

header('Content-Type: application/json');

session_start();

if ($_SESSION['pass'] != "5092ab784ba9643669c982ee084baef9d9d2979a2cc29cc64b69e27270602b52") {
    exit();
}

if (!array_key_exists("route", $_GET)) {
    exit();
}

$apiRoute = $_GET["route"];

if (!$apiRoute) {
    exit();
}

switch ($apiRoute) {
    case "sniperPid":
        $mystring = "rev-daemon.php";
        exec("ps aux | grep '$mystring' | grep -v grep | awk '{ print $2 }' | head -1", $out);
        if (is_numeric($out[0])) {
            echo json_encode(array("response"=>$out[0]));
        } else {
            echo json_encode(array("response"=>"Bot not running"));
        }
        break;
    case "lastRun":
        $filename = 'rev-log.log';
        if (file_exists($filename)) {
            $filemtime = filemtime($filename);
            echo json_encode(array(
                "response" => array(
                    "timestamp"=> $filemtime,
                    "readable" => date ("F d Y H:i:s.", $filemtime),
                    "offset"   => max(0,(time()-$filemtime))
                )
            ));
        } else {
            echo json_encode(array(
                "response" => "never"
            ));
        }
        break;
    default:
        echo json_encode(array("response"=>"Invalid API call."));
        break;
}

?>
