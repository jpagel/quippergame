<?php
include('db.inc.php');
include('lib.inc.php');

$req = $_GET;

$params = array(
	'displayname' => getArrayValue( $req, 'displayname' ),
	'token' => getArrayValue( $req, 'token' ),
	'username' => getArrayValue( $req, 'user' )
);

echo json_encode( register($params) );

