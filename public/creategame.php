<?php
include('db.inc.php');
include('lib.inc.php');
$req = $_GET;
$params = array(
	'user' => getArrayValue( $req, 'user' ),
	'level' => getArrayValue( $req, 'difficulty' ),
	'category' => getArrayValue( $req, 'category' ),
	'target' => getArrayValue( $req, 'target' ),
    'to' => getArrayValue( $req, 'to' ),
    'quids' => getArrayValue( $req, 'quids' )
);

if( $errors = invalidateParams( $params ) ){
    exit(json_encode(array('error'=>$errors)));
}
else{
    echo json_encode( createNewGame( $params ) );
}

function invalidateParams( $params ){
    $required = array( 'user', 'level', 'category', 'quids', 'target' );
    $numeric = array( 'level', 'category', 'target' );
    $commaseparatednumbers = array( 'quids' );
    $expectedsize = 5;
    return findError( $params, $required, $numeric, $commaseparatednumbers, $expectedsize );
}
