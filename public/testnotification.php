<?php
exit;
include('db.inc.php');
include('lib.inc.php');

$message = "You've been invited to a game!!!!!";

$msglist = array(
    '178893bc87866f0f44d5d7bb4c3e5b84c0c3fa31b45e7e397a683f820919d89e' => 'This is message 1',
    '178893bc87866f0f44d5d7bb4c3e5b84c0c3fa31b45e7e397a683f820919d89e' => 'This is message 2',
    '178893bc87866f0f44d5d7bb4c3e5b84c0c3fa31b45e7e397a683f820919d89e' => 'This is message 3',
    '178893bc87866f0f44d5d7bb4c3e5b84c0c3fa31b45e7e397a683f820919d89e' => 'This is message 4',
    '178893bc87866f0f44d5d7bb4c3e5b84c0c3fa31b45e7e397a683f820919d89e' => 'This is message 5'
);

//sendIosNotification('178893bc87866f0f44d5d7bb4c3e5b84c0c3fa31b45e7e397a683f820919d89e', $message);
//sendIosNotificationMultiple( $msglist );
foreach( $msglist as $device=>$msg ){
    echo sendIosNotification($device, $msg);
}
