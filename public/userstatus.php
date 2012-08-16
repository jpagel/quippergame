<?php
include('db.inc.php');
include('lib.inc.php');
$req = $_GET;
$params = array(
	'user' => getArrayValue( $req, 'user' ),
);

if( $errors = invalidateParams( $params ) ){
    exit(json_encode(array('error'=>$errors)));
}
else{
    echo json_encode( getUserStatus( $params ) );
}

function getUserStatus( $params ){
	$db = new database( getDbcredentials() );
    $userid = $db->getUserIdFromUserName( $params[ 'user' ] );
    //get invitations
    //get gamesessions
    if( $userid ){
        list( $invitationlist, $gamesessionlist, $finishedlist ) = $db->getUserStatusScreen( $userid );
        foreach( $finishedlist as &$game ){
            $game[ 'gamelength' ] = convertSecondsToHms( $game[ 'gamelengthSeconds' ] );
            unset( $game[ 'gamelengthSeconds' ] );
        }
        return array(
            'invitations' => $invitationlist,
            'gamesessions' => $gamesessionlist,
            'finished' => $finishedlist
        );
    }
}

function invalidateParams( $params ){
    $required = array( 'user' );
    $numeric = array();
    return findError( $params, $required, $numeric );
}
