<?php
include('db.inc.php');
include('lib.inc.php');

$message = "You've been invited to a game!!!!!";

sendIosNotification('178893bc87866f0f44d5d7bb4c3e5b84c0c3fa31b45e7e397a683f820919d89e', $message);
