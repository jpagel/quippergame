<?php
include('lib.inc.php');
var_dump(grid2mail());

function testemail(){
    $to = 'jfpagel@gmail.com';
    $subject = 'test email from quippergame';
    $body = date( 'Ymd H:i:s' ) . "\n\nJust testing.\nLove, the quippergame API";
    $headers = "From: api@quipper.com\r\n" .
                "Reply-To: jfpagel@fasstmail.fm\r\n" .
                "X-Mailer: PHP/" . phpversion();
    return mail( $to, $subject, $body, $headers );
}

function grid2mail(){
$url = 'http://sendgrid.com/';
$user = 'jpagel';
$pass = 'f1ippers'; 
 
    $to = 'jfpagel@gmail.com';
    $subject = 'test email from quippergame';
    $body = date( 'Ymd H:i:s' ) . "\n\nJust testing.\nLove, the quippergame API";
$params = array(
    'api_user'  => $user,
    'api_key'   => $pass,
    'to'        => $to,
    'subject'   => $subject,
    'html'      => $body,
    'text'      => $body,
    'from'      => 'api@quipper.com'
  );
 
 
$request =  $url.'api/mail.send.json';
 
// Generate curl request
$session = curl_init($request);
// Tell curl to use HTTP POST
curl_setopt ($session, CURLOPT_POST, true);
// Tell curl that this is the body of the POST
curl_setopt ($session, CURLOPT_POSTFIELDS, $params);
// Tell curl not to return headers, but do return the response
curl_setopt($session, CURLOPT_HEADER, false);
curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
 
// obtain response
$response = curl_exec($session);
curl_close($session);
 
// print everything out
print_r($response);
}

function gridmail(){
    
    $to = 'jfpagel@gmail.com';
    $toname = 'jojoiii';
    $subject = 'test email from quippergame';
    $body = date( 'Ymd H:i:s' ) . "\n\nJust testing.\nLove, the quippergame API";
    $headers = "From: api@quipper.com\r\n" .
                "Reply-To: jfpagel@fasstmail.fm\r\n" .
                "X-Mailer: PHP/" . phpversion();
    
    $url = "https://sendgrid.com/api/mail.send.xml?api_user=jonathan@politicalcompass.org&api_key=secureSecret&to=$to&toname=$toname&subject=$subject&text=$body";
    $ch = curl_init("http://www.example.com/");
    $success = curl_exec( $ch );
    fclose( $ch );
    return $success;
}
