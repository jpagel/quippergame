<?php
include( 'lib.inc.php' );
include( 'db.inc.php' );

$req = $_GET;
$params = array(
    'username' => getArrayValue( $req, 'user' ),
    'jewels' => getArrayValue( $req, 'jewels' ),
    'coins' => getArrayValue( $req, 'coins' )
);

if( $errors = invalidateParams( $params ) ){
    exit(json_encode(array('error'=>$errors)));
}
else{
    echo json_encode( updateUser( $params ) );
}

function updateUser( $params ){
	$db = new database( getDbcredentials() );
    $error = $db->updateUserByUsername( $params );
    if( $error ){
        return array( 'error' => $error );
    }
    else return array( 'status' => 'success' );
    
}

function invalidateParams( $params ){
    $required = array( 'user' );
    $numeric = array( 'coins', 'jewels' );
    return findError( $params, $required, $numeric );
}
