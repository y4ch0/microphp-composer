<?php
if(!isset($_SESSION)) {
    session_start();
}

$_SESSION["user"]["id"] = 6772356;
$_SESSION["user"]["name"] = "admin";
$_SESSION["user"]["role"] = "Admin";

echo "User created";