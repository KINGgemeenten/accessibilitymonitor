<?php
    $url=$argv[1];
    $datum=time();
    $json='[{"url":"'.$url.'","id":"'.$url.'","tested":{"set":'.$datum.'}}]';
    print $json;

