<?php
include('db.inc.php');
include('lib.inc.php');

$req = $_GET;
$params = array(
    'username' => getArrayValue( $req, 'user' ),
    'jewels' => getArrayValue( $req, 'jewels' )
);


if( $errors = invalidateParams( $params ) ){
    exit(json_encode(array('error'=>$errors)));
}
else{
    echo json_encode( buyJewels( $params ) );
}

function buyJewels( $params ){
    $db = new database( getDbcredentials() );
    $error = $db->buyJewels( $params[ 'username' ], $params[ 'jewels' ] );
    if( $error ){ 
        return array( 'error' => $error ); 
    }
    return array(
         'status' => 'success'
    );
}

function invalidateParams( $params ){
    $required = array( 'username', 'jewels' );
    $numeric = array( 'jewels' );
    $errorlist = findError( $params, $required, $numeric );
    //to should be comma-seperated list
    return $errorlist;
}
