<?php
require( 'lib.inc.php' );
require( 'db.inc.php' );

$req = $_GET;
$n = getArrayValue($req, 'n');
var_dump( main( $n ) );

function main( $n=20 ){

	$db = new database( getDbcredentials() );
    $msglist = array();
    //register 20 users
    $u = $n;
    $msglist = registerUsers( $db, $u, $msglist );
    
    //create 3 games per user
    $gpu = 3;
    $msglist = createGames( $db, $gpu, $n, $msglist );
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
