<?php
include('db.inc.php');
include('lib.inc.php');
$req = $_GET;
$params = array(
	'from' => getArrayValue( $req, 'from' ),
    'to' => getArrayValue( $req, 'to' )
);

if( $errors = invalidateParams( $params ) ){
    exit(json_encode(array('error'=>$errors)));
}
else{
    echo json_encode( sendNudge( $params ) );
}

function sendNudge( $params ){
    $from = $params[ 'from' ];
    $to = $params[ 'to' ];
    $db = new database( getDbcredentials() );
    $displayname = $db->getDisplayNameFromUsername( $from );
    $msg = "$displayname just send you a nudge";
    $targetdevice = $db->getDeviceIdForUsername( $to );
    sendIosNotification( $targetdevice, $msg );
    return array( 'msg' => $msg, 'device' => $targetdevice );
}

function invalidateParams( $params ){
    $required = array( 'from', 'to' );
    $errorlist = findError( $params, $required );
    if( !$errorlist ){
	    $db = new database( getDbcredentials() );
        foreach( array( $params[ 'from'], $params[ 'to' ] ) as $username ){
            if( !$db->usernameExists( $username ) ){
                $errorlist[] = array( "user $username does not exist" );
            }
        }
    }
    return $errorlist;
}
