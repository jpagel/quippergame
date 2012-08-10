<?php

include('db.inc.php');
include('lib.inc.php');
$req = $_GET;
$params = array(
	'user' => getArrayValue( $req, 'user' ),
    'gameid' => getArrayValue( $req, 'game' )
);

if( $errors = invalidateParams( $params ) ){
    exit(json_encode(array('error'=>$errors)));
}
else{
    echo json_encode( rejectInvitatation( $params ) );
}

function rejectInvitatation( $params ){
    //delete the relevant invitation
    $username = $params[ 'user' ];
    $gameid = $params[ 'gameid' ];
	$db = new database( getDbcredentials() );
    if( $db->deleteInvitation( $gameid, $username ) ){
        return array( 'status' => 'success' );
    }
    else{
        //don't error: it's probably ok
        //return array( 'error' => 'no invitation deleted' );
    }
}

function invalidateParams( $params ){
    $required = array( 'user', 'gameid' );
    $numeric = array( 'gameid' );
    return findError( $params, $required, $numeric );
}
