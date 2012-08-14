<?php
require( 'lib.inc.php' );
require( 'db.inc.php' );

var_dump( main() );

function main(){

	$db = new database( getDbcredentials() );
    $msglist = array();
    //register 20 users
    $u = 20;
    //$msglist = registerUsers( $db, $u, $msglist );
    
    //create 5 games per user
    $gpu = 5;
    $msglist = createGames( $db, $gpu );
    return $msglist;
}

function createGames( $db, $gpu, $msglist=array() ){
    $sql = "SELECT username FROM user";
    $userlist = $db->fetchColumn( $sql );
    $newgamecount = 0;
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
            $info = createNewGame( $params );
            if( is_numeric( getArrayValue( $info, 'gameid' ) ) ){
                $newgamecount++;
            }
        }
    }
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
