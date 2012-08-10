<?php
include('db.inc.php');
include('lib.inc.php');
$req = $_GET;
$params = array(
	'gameid' => getArrayValue( $req, 'game' )
);

$info = array();
if( $errors = invalidateParams( $params ) ){
    exit(json_encode(array('error'=>$errors)));
}
else{
	$db = new database( getDbcredentials() );
    $info[ 'gamestatus' ] = $db->getGameStatus( $params[ 'gameid' ] );
}
exit( json_encode( $info ) );

function invalidateParams( $params ){
    $required = array( 'gameid' );
    $numeric = array( 'gameid' );
    return findError( $params, $required, $numeric );
}
