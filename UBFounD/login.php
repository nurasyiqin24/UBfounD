<?php

// Section or login pages. Make sure you have existing account!
session_start();

//Only allow login form submissions
if($_SERVER["REQUEST_METHOD"] !== "POST"){
    header ("Location: login.html");
    exit;

}


//Connect to existing MYSQL Pages 

$conn





?>