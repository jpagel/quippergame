<?php
define('CRON_PATH', dirname(__DIR__));
include( CRON_PATH . '/public/db.inc.php' );
include( CRON_PATH . '/public/lib.inc.php' );
/*
include( '../public/db.inc.php' );
include( '../public/lib.inc.php' );
*/

$period = 60;
$repeat = 15;

for( $i=0; $i<$repeat; $i++ ){
    main();
    sleep( $period );
}

function main(){

    $cert = '../ck.pem';
    $testdevice = '178893bc87866f0f44d5d7bb4c3e5b84c0c3fa31b45e7e397a683f820919d89e';
    $testmessage = "message sent at " . time( 'H:i:s' );
    sendIosNotification( $testdevice, $testmessage, $cert, false );

	$db = new database( getDbcredentials() );
    $db->log( time( 'H:i:s' ) . " running clearPushQueue";
    $pushlist = $db->getPushList();
    $n = count( $pushlist );
    echo "\n";
    foreach($pushlist as $row){
        $device = trim($row[ 'device_id' ]);
        $message = trim($row[ 'message' ] );
        echo "sending message $message to device $device\n";
        if( $device && $message ){
            echo sendIosNotification( $device, $message, $cert, false );
        }
        echo "\n";
    }
    $db->log( "sent $n push notifications" );
}
