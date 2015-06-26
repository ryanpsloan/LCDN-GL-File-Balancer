<?php
session_start();
session_unset();
session_destroy();
header("Location: index.php");
/**
 * Author: Ryan Sloan
 * ryan@paydayinc.com
 * This page acts to unset the session and clear all data out to prepare for a new upload
 *
 */
?>