<?php
/**
 * @author: Yamin Siahmargooei
 * @copyright Golestan University, School of Engineering
 * @year 2018
 * Supervisor: Mohammad Maghsoudloo
 * B.S. Project
 * IoT Cloud Research Center
 */

// MySql Default username password
$server_name = "localhost";
$username = "root";
$password = "";

$db_name = "bsp_rpi";

// Create connection
$db_connection = mysqli_connect($server_name, $username, $password, $db_name);
// Check connection
if (!$db_connection) {
    die("Connection failed: " . mysqli_connect_error());
}