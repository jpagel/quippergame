<?php
var_dump(gridmail());

function testemail(){
    $to = 'jfpagel@gmail.com';
    $subject = 'test email from quippergame';
    $body = date( 'Ymd H:i:s' ) . "\n\nJust testing.\nLove, the quippergame API";
    $headers = "From: api@quipper.com\r\n" .
                "Reply-To: jfpagel@fasstmail.fm\r\n" .
                "X-Mailer: PHP/" . phpversion();
    return mail( $to, $subject, $body, $headers );
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
