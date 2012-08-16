<?php
include( '../public/db.inc.php' );
include( '../public/lib.inc.php' );

$period = 120;
$repeat = 6;

for( $i=0; $i<$repeat; $i++ ){
    main();
    sleep( $period );
}

function main(){
	$db = new database( getDbcredentials() );
    $cert = '../public/ck.pem';
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
