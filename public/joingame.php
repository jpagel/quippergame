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
    echo json_encode( addUserToGame( $params ) );
}

function addUserToGame( $params ){
	$db = new database( getDbcredentials() );
    $userid = $db->getUserIdFromUserName( $params[ 'user' ] );
    if( $error = $db->addUserIdToGame( $params[ 'gameid' ] , $userid ) ){
        $status = 'failure';
    }
    else{
        $status = 'success';
        $error = '';
    }
    $info = array( 'status' => $status );
    if( $error ){
        $info[ 'error' ] = $error;
    }
    return $info;
}

function invalidateParams( $params ){
    $required = array( 'user', 'gameid' );
    $numeric = array( 'gameid' );
    return findError( $params, $required, $numeric );
}
