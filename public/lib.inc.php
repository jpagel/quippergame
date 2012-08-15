<?php

define( 'COINS_BONUS_FIRST', 300 );
define( 'COINS_BONUS_SECOND', 200 );
define( 'COINS_BONUS_THIRD', 100 );
define( 'COINS_BONUS_NOFPLAYERS', 100 );
define( 'MAX_PLAYERS_PER_GAME', 10 );
define( 'HISTORICAL_GAME_LIMIT', 3 );

date_default_timezone_set('Europe/London');

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

function createNewGame( $params, $debug=false ){
	$db = new database( getDbcredentials() );
    $gameid = $db->startGame( $params );
    $tolist = array();
    if( $gameid ){
        $gameinfo = array( 'gameid' => $gameid );
        if( $to = getArrayValue( $params, 'to' ) ){
            //send invitations to the to list
            $tolist = explode( ',', $to );
        }
        else{
            //no invitees ... choose 10 random invitees
            $fromid = $db->getUserIdFromUserName( $params[ "user" ] );
            $tolist = $db->chooseTeammates( $gameid, $params[ 'category' ], $params[ 'level' ], $fromid, false );
            if( $debug ){
                $gameinfo[ 'tolist' ] = $tolist;
            }
        }
        if( count($tolist) ){
            $errorlist = array();
            $from = $params[ 'user' ];
            foreach( $tolist as $to ){
                $toid = $db->getUserIdFromUserName( $to );
                if( $error = inviteSingle( $gameid, $from, $toid, 0, $db ) ){
                    $errorlist[] = $error;
                }
            }
        }
        else{
            //no invitees
        }
        if( $errorlist ){
            $gameinfo[ 'error' ] = $errorlist;
        }
        return $gameinfo;
    }
    else{ 
        return array( 'error' => 'no game created' );
    }
}

function findError( $params, $required=array(), $numeric=array(), $commaseparatednumbers=array(), $expectedsize=false ){
    $errorlist = array();
    foreach( $params as $key=>$value ){
        if( in_array( $key, $required ) ){
            if( !trim($value) ){
                $errorlist[] = "missing param: $key";
            }
        }
        if( in_array( $key, $numeric ) ){
            if( trim( $value ) && !is_numeric( $value ) ){
                $errorlist[] = "should be numeric value: $key:$value";
            }
        }
        if( in_array( $key, $commaseparatednumbers ) ){
            $valuelist = explode( ',', $value );
            if( false !== $expectedsize ){
                if( $expectedsize != count( $valuelist ) ){
                    $errorlist[] = "wrong number of comma-separated values (expected $expectedsize)";
                }
            }
            foreach( $valuelist as $v ){
                if( trim( $v ) ){
                    if( !is_numeric( $v ) ){
                        $errorlist[] = "comma-separated value $v should be numeric";
                    }
                }
            }
        }
    }
    return $errorlist;
}
function inviteSingle( $gameid, $from, $to, $friend, $db=false ){
    //get $to's device id and 
    if( !$db ){
	    $db = new database( getDbcredentials() );
    }
    $deviceid = $db->getDeviceIdForUserId( $to );
    $error = false;
    $displayname = $db->getDisplayNameFromUsername( $from );
    if( $deviceid ){
        //send push notification
        $msg = "$displayname has sent you a new challenge";
        sendIosNotification( $deviceid, $msg );
        //add to invite table
        if( $db->insertInvitation( $gameid, $from, $to, $friend ) ){
            //ok
        }
        else{
            $error = $db->getError();
        }
    }
    else{
        $error = "no device id found for user $to";
    }
    return $error;
}
function enquote( $value ){
	if( is_numeric( $value ) ){
		return $value;
	}
	return "'" . addslashes( $value ) . "'";
}
function getArrayValue( $array, $key, $default=false ){
	if( in_array( $key, array_keys( $array ) ) ){
		return $array[ $key ];
	}
	return $default;
}
function sendIosNotification( $deviceToken, $message ){
return true;
	$passphrase = 'quiz24';
	
	$ctx = stream_context_create();
	stream_context_set_option($ctx, 'ssl', 'local_cert', 'ck.pem');
	stream_context_set_option($ctx, 'ssl', 'passphrase', $passphrase);
	
	// Open a connection to the APNS server
	$fp = stream_socket_client(
		'ssl://gateway.sandbox.push.apple.com:2195', $err,
		$errstr, 60, STREAM_CLIENT_CONNECT|STREAM_CLIENT_PERSISTENT, $ctx);
	
	if (!$fp)
		exit("Failed to connect: $err $errstr" . PHP_EOL);
	
	//echo 'Connected to APNS' . PHP_EOL;
	
	// Create the payload body
	$body['aps'] = array(
		'alert' => $message,
		'sound' => 'default'
		);
	
	// Encode the payload as JSON
	$payload = json_encode($body);
	
	// Build the binary notification
	$msg = chr(0) . pack('n', 32) . pack('H*', $deviceToken) . pack('n', strlen($payload)) . $payload;
	
	
	// Send it to the server
	$result = fwrite($fp, $msg, strlen($msg));
	
	if (!$result)
		$status = 'Message not delivered' . PHP_EOL;
	else
		$status = 'Message successfully delivered' . PHP_EOL;
	
	// Close the connection to the server
	fclose($fp);
	return $status;
}
