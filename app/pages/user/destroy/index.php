<?php
if(!isset($_SESSION)) {
    session_start();
}

$_SESSION = null;

session_destroy();

echo "User destroyed";