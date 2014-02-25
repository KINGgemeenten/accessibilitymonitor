<?php
    $url=$argv[1];
    $datum=time();
    $server=parse_url($url);
    $hostname=$server["host"];
    $json='[{"url":"'.$url.'","id":"'.$url.'","tested":{"set":'.$datum.'}}]';
    print $json;

