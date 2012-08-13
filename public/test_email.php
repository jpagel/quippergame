<?php
var_dump(testemail());

function testemail(){
    $to = 'jfpagel@gmail.com';
    $subject = 'test email from quippergame';
    $body = date( 'Ymd H:i:s' ) . "\n\nJust testing.\nLove, the quippergame API";
    $headers = "From: api@quipper.com\r\n" .
                "Reply-To: jfpagel@fasstmail.fm\r\n" .
                "X-Mailer: PHP/" . phpversion();
    return mail( $to, $subject, $body, $headers );
}
