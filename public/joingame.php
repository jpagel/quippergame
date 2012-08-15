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

function invalidateParams( $params ){
    $required = array( 'user', 'gameid' );
    $numeric = array( 'gameid' );
    return findError( $params, $required, $numeric );
}
