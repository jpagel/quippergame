<?php
require( 'lib.inc.php' );
require( 'db.inc.php' );

$req = $_GET;

$t0 = microtime(true);
$n = getArrayValue($req, 'n');
var_dump( main( $n ) );
//var_dump( testpush( $n ) );
$tfinal = microtime(true);

echo formatTimeReport( $t0, $tfinal );

function testpush(){
    $msg = date( 'Y-m-d H:i:s' ) . ' test message';
    $outlist = array( $msg );
    for( $i=0; $i<10; $i++ ){
        $device = '178893bc87866f0f44d5d7bb4c3e5b84c0c3fa31b45e7e397a683f820919d89e';
        sendIosNotification( $device, $msg );
        $nondevice = '123';
        sendIosNotification( $nondevice, $msg );
    }
    return $outlist;
}

function formatTimeReport( $t0, $tfinal ){
    $seconds = $tfinal - $t0;
    return "\nTime taken: $seconds seconds\n";
}

function main( $n=20 ){

	$db = new database( getDbcredentials() );
    $msglist = array();
    //register 20 users
    $u = $n;
    //$msglist = registerUsers( $db, $u, $msglist );
    
    //create 3 games per user
    $gpu = 3;
    $msglist = createGames( $db, $gpu, $n, $msglist );

    $msglist = joinSomeGames( $db, $gpu, $msglist );
    $msglist = answerSomeQuizzes( $db, $gpu, $msglist );

    return $msglist;
}

function generateRandomAnswers( $n=5 ){
    $alist = array();
    for( $i=0; $i<$n; $i++ ){
        $alist[] = mt_rand( 0, 5 );
    }
    return implode( ',', $alist );
}

function answerSomeQuizzes( $db, $gpu, $msglist ){
    $sql = "SELECT * FROM gamesession ORDER BY game_id DESC LIMIT $gpu";
    $sessionlist = $db->fetchAll( $sql );
    $count = 0;
    foreach( $sessionlist as $row ){
        $params = array(
            'userid' => $row[ 'user_id' ],
            'gameid' => $row[ 'game_id' ],
            'score' => mt_rand( 0,500 ),
            'answers' => generateRandomAnswers(5)
        );
        $info = updateGame( $params );
        if( !$info[ 'error' ] ){
            $count++;
        }
    }
    $s = ( 1 == $count ) ? '' : 's' ;
    $msglist[] = "updated $count game$s";
    return $msglist;
}

function joinSomeGames( $db, $gpu, $msglist=array() ){
    //find some open invitations
    $sql = "SELECT * FROM invitation LIMIT $gpu";
    $invitationlist = $db->fetchAll( $sql );
    $addedcount = 0;
    foreach($invitationlist as $row){
        //var_dump( "adding " . $row[ 'to_id' ] . " to game " . $row[ 'game_id' ] );
        $error = $db->addUserIdToGame( $row[ 'game_id' ], $row[ 'to_id' ] );
        if( !$error ){
            $addedcount++;
        }
    }
    $s = ( 1 == $addedcount ) ? '' : 's' ;
    $msglist[] = "added $addedcount users to games";
    return $msglist;
}

function createGames( $db, $gpu, $n=10, $msglist=array() ){
    $sql = "SELECT username FROM user ORDER BY id DESC LIMIT $n";
    $userlist = $db->fetchColumn( $sql );
    $newgamecount = 0;
    $inviteecount = 0;
    foreach($userlist as $username ){
        //start $gpu games
        for( $i=0; $i<$gpu; $i++ ){
            $params = array(
                'user' => $username,
                'level' => mt_rand( 1, 3 ),
                'category' => mt_rand( 1, 9 ),
                'target' => mt_rand( 20, 150 ),
                'quids' => mkRandomQuids()
            );
            $info = createNewGame( $params, true );
            if( is_numeric( getArrayValue( $info, 'gameid' ) ) ){
                $newgamecount++;
                if( $inviteelist = getArrayValue( $info, 'tolist' ) ){
                    $invitees = count( $inviteelist );
                    $inviteecount += $invitees;
                }
            }
        }
    }
    $s = ( 1 == $inviteecount ) ? '':'s';
    $msglist[] = "$inviteecount invitation$s sent";
    $s = ( 1 == $newgamecount ) ? '' : 's' ;
    $msglist[] = "$newgamecount new game$s created";
    return $msglist;
}

function mkRandomQuids( $n=5 ){
    $quidlist = array();
    for( $i=0; $i<$n; $i++ ){
        $quidlist[] = mt_rand(1, 12000);
    }
    return implode( ',', $quidlist );
}

function registerUsers( $db, $n, $msglist=array() ){
    $registercount = 0;
    for( $i=0; $i<$n; $i++ ){
        $username = mkRandomString(5) . '@' . mkRandomString(7) . '.com';
        $token = mt_rand() . mt_rand();
        $displayname = getRandomDisplayname();
        $params = array(
	        'displayname' => $displayname,
	        'token' => $token,
	        'username' => $username
        );
        $registerinfo = register( $params );
        if( 'success' == $registerinfo[ 'status' ] ){
            $registercount++;
        }
    }
    $s = ( 1 == $registercount ) ? '' : 's' ;
    $msglist[] = "Registered $registercount user$s";
    
    return $msglist;
}

function getRandomDisplayname(){
    $seednamelist = array(
        'Trumpet', 'Alice', 'DrFoster', "Mr Big", "Jo DiMageo", "Porlock", 'Grand Dragon '
    );
    $seedname = $seednamelist[ array_rand( $seednamelist ) ];
    return $seedname . mt_rand(1, 10000);
}

function mkRandomString( $length ){
    $rnd = md5( microtime() );
    $max = strlen( $rnd );
    $start = mt_rand(0, $max - $length );
    return substr( $rnd, $start, $length );
}
