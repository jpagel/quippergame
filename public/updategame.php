<?php
include('db.inc.php');
include('lib.inc.php');
$req = $_GET;
$params = array(
	'user' => getArrayValue( $req, 'user' ),
	'gameid' => getArrayValue( $req, 'game' ),
	'score' => getArrayValue( $req, 'score' ),
    'answers' => getArrayValue( $req, 'answers' )
);

if( $errors = invalidateParams( $params ) ){
    exit(json_encode(array('error'=>$errors)));
}
else{
    echo json_encode( updateGame( $params ) );
}


function invalidateParams( $params ){
    $required = array( 'user', 'gameid', 'score', 'answers' );
    $numeric = array( 'gameid', 'score' );
    $csn = array( 'answers' );
    return findError( $params, $required, $numeric, $csn, 5 );
}
