<?php

define( 'COINS_BONUS_FIRST', 300 );
define( 'COINS_BONUS_SECOND', 200 );
define( 'COINS_BONUS_THIRD', 100 );
define( 'COINS_BONUS_NOFPLAYERS', 100 );
define( 'MAX_PLAYERS_PER_GAME', 10 );
define( 'HISTORICAL_GAME_LIMIT', 3 );
define( 'LOGIN_RESET_TIME_HOURS', 6 );
define( 'PERIOD_GAME_CREATION_LIMIT', 4 );

date_default_timezone_set('Europe/London');

function updateGame( $params ){
    //param user (username)
    //param gameid
    //param score
    //param answers
	$db = new database( getDbcredentials() );
    if( $userid = getArrayValue( $params, 'userid' ) ){
        $params[ 'user' ] = $db->getUserNameFromUserId( $userid );
    }
    $gameover = false;
    if( $db->gameHasReachedTarget( $params[ 'gameid' ] ) ){
        $gameover = true;
    }
    elseif( $db->insertGameHistory( $params ) ){
        if( $db->gameHasReachedTarget( $params[ 'gameid' ] ) ){
            $gameover = true;
            $info = finishGame( $db, $params[ 'gameid' ] );
        }
        $info = array(
            'status' => 'success'
        );
    }
    else{
        $info = array(
            'error' => $db->getError()
        );
    }
    $info[ 'gamestatus' ] = $db->getGameStatus( $params[ 'gameid' ] );
    if( $gameover ){
        $info[ 'gameover' ] = 1;
    }
    else{
        $info[ 'gameover' ] = 0;
    }
    
    return $info;
}

function finishGame( $db, $gameid ){
    $errorlist = $db->finishGame( $gameid );
    if( count( $errorlist ) ){
        return array( 'error' => $errorlist );
    }
    return array( 'status' => 'success', 'message' => "game $gameid has reached its target" );
}

function addUserToGame( $params ){
    //param user (username)
    //param gameid
	$db = new database( getDbcredentials() );
    if( !$userid = getArrayValue( $params, 'userid' ) ){
        $userid = $db->getUserIdFromUserName( $params[ 'user' ] );
    }
    if( $error = $db->addUserIdToGame( $params[ 'gameid' ] , $userid ) ){
        $status = 'failure';
    }
    else{
        $status = 'success';
        $gameinfo = $db->getGameInfo( $params[ 'gameid' ] );
        $error = '';
    }
    $info = array( 'status' => $status );
    if( $error ){
        $info[ 'error' ] = $error;
    }
    else{
        $info[ 'questionids' ] = $gameinfo[ 'questionids' ];
    }
    return $info;
}

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

function userIdIsNotAllowedToCreateGame( $db, $userid ){
    $sql = "SELECT (UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(lastactivity)) FROM user WHERE id = $userid";
    $lastactivityago = $db->fetchSingleValueSql( $sql );    //seconds
    $resettimeSeconds = LOGIN_RESET_TIME_HOURS*3600;
    //is it more than 6 hours ago?
    if( ( $resettimeSeconds ) < $lastactivityago ){
        //OK!
        return false;
    }
    $n = $db->countGamesCreatedSince( $userid, 24 );
    if( 4 > $n ){
        //OK!
        return false;
    }
    else{
        //$waittimeinfo = convertSecondsToHms( $waittimeSeconds );
        return 'Game creation limit reached.';
    }
}

function createNewGame( $params, $debug=false ){
	$db = new database( getDbcredentials() );
    $db->log( "creating new game" );
    $creatorid = $db->getUserIdFromUserName( $params[ "user" ] );
    $fromid = $creatorid;
    if( $error = userIdIsNotAllowedToCreateGame( $db, $creatorid ) ){
        //jfp @todo put this back for production
        //return array( 'error' => $error );
    }
    $params[ 'creatorid' ] = $creatorid;
    $gameid = $db->startGame( $params );
    $tolist = array();
    if( $gameid ){
        $gameinfo = array( 'gameid' => $gameid );
        if( $to = getArrayValue( $params, 'to' ) ){
            //send invitations to the to list
            $tolist = explode( ',', $to );
            $toisidsalready = false;
            $friends = 1;
        }
        else{
            //no invitees ... choose 10 random invitees
            $tolist = $db->chooseTeammates( $gameid, $params[ 'category' ], $params[ 'level' ], $fromid );
            $toisidsalready = true;
            $friends = 0;
            if( $debug ){
                $gameinfo[ 'tolist' ] = $tolist;
            }
        }
        if( count($tolist) ){
            $errorlist = array();
            $from = $params[ 'user' ];
            foreach( $tolist as $to ){
                if( !$toisidsalready ){
                    $toid = $db->getUserIdFromUserName( $to );
                }
                else{
                    $toid = $to;
                }
                if( $toid ){
                    if( $error = inviteSingle( $gameid, $from, $toid, $friends, $db ) ){
                        $errorlist[] = $error;
                    }
                }
            }
            if( $diff = MAX_PLAYERS_PER_GAME - count( $tolist ) ){
                //invite $diff strangers to make up the numbers
                $tolistextra = $db->chooseTeammates( $gameid, $params[ 'category' ], $params[ 'level' ], $fromid, $diff, $tolist );
                foreach( $tolistextra as $toid ){
                    if( !in_array( $toid, $tolist ) ){
                        if( $error = inviteSingle( $gameid, $from, $toid, 0, $db ) ){
                            $errorlist[] = $error;
                        }
                    }
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
    $db->log( "Inviting $to to game $gameid from $from" );
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

function convertSecondsToHms( $totalseconds ){
    $completeMinutes = floor($totalseconds / 60);
    $remainingSeconds = $totalseconds - 60 * $completeMinutes;
    $completeHours = floor( $completeMinutes / 60 );
    $remainingMinutes = $remainingMinutes = $completeMinutes - 60 * $completeHours;
    $timeinfo = array(
        'hours' => $completeHours,
        'minutes' => $remainingMinutes,
        'seconds' => $remainingSeconds
    );
    return $timeinfo;
}

function sendIosNotification( $deviceToken, $message, $cert='../ck.pem', $enqueue=true ){
    if( $enqueue ){
        $db = new database( getDbcredentials() );
        $db->insertPush( $deviceToken, $message );
        return true;
    }
	$passphrase = 'quiz24';
	
	$ctx = stream_context_create();
	stream_context_set_option($ctx, 'ssl', 'local_cert', $cert);
	stream_context_set_option($ctx, 'ssl', 'passphrase', $passphrase);
	
	// Open a connection to the APNS server
	$fp = stream_socket_client(
		'ssl://gateway.sandbox.push.apple.com:2195', $err,
		$errstr, 60, STREAM_CLIENT_CONNECT|STREAM_CLIENT_PERSISTENT, $ctx);
    //stream_set_blocking( $fp, 0 );
	
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

function sendInvitations( $params ){
    //param from (username)
    //param to (comma-separated user_ids)
    //gameid
    extract( $params );
    $tolist = explode( ',', $to );
    $errorlist = array();
    $db = new database( getDbcredentials() );
    foreach( $tolist as $toid ){
        if( $error = inviteSingle( $gameid, $from, $toid, 1, $db ) ){
            $errorlist[] = $error;
        }
    }
    if( count( $errorlist ) ){
        return array(
            'error' => $errorlist
        );
    }
    else{
        return array(
            'category' => $db->getCategoryNameForGame( $gameid ),
            'status' => 'success'
        );
    }
}

