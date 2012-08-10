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

function register($params){
	$errorlist = array();
	$optionalParams = array( 'displayname' );
	foreach( $params as $key=>$value ){
		if( !in_array( $key, $optionalParams ) ){
			if( !$value ){
				$errorlist[] = "missing param: $key";
			}
		}
	}
	if( count( $errorlist ) ){
		return json_encode( 
			array(
				'error' => implode( ':', $errorlist )
			) );
	}
	
	//does this userid exist already?
	$db = new database( getDbcredentials() );
	$deviceexistsalready = $db->valueExists( 'user_device', 'device_id', $params[ 'token' ] );
    $doregistration = true;
	if($deviceexistsalready){
/*
		return json_encode(
			array(
				'error' => "device " . $params[ 'token' ] . " already exists"
			)
		);
*/
        //does the device belong to this user?
        if( $deviceinfo = $db->getDeviceIdForUsername( $params[ 'username' ], $params[ 'token' ] ) ){
            //ok ... this user already registered with this device
		    return array(
				    'status' => 'success'
		    );
        }
        else{
            //this device already registered to a different user
            //register new user
        }
	}
	if( $doregistration ){
		//add user to db
		$userid = $db->insertUser( $params );
        if( $userid ){
		    return array(
				    'status' => 'success'
		    );
        }
        else{
		    return array(
				    'status' => 'failure',
		    );
        }
	}
}
