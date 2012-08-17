<?php
exit;
//http://dev/ghquipper/quippergame/public/remotepush.php?token=178893bc87866f0f44d5d7bb4c3e5b84c0c3fa31b45e7e397a683f820919d89e&msg=remote+push+test

date_default_timezone_set('Europe/London');
$serviceurl = "http://app-426-1344938578.orchestra.io";
$pushpage = 'remotepush.php';
$token = '178893bc87866f0f44d5d7bb4c3e5b84c0c3fa31b45e7e397a683f820919d89e';
$msg = date( 'Y m d H:i:s' ) . " remote push test";
$msg=urlencode( $msg );

$url = "$serviceurl/$pushpage?msg=$msg&token=$token";
echo $url;

var_dump(get_headers($url));
