<?php
/**
 * @author: Yamin Siahmargooei
 * @copyright Golestan University, School of Engineering
 * @year 2018
 * Supervisor: Mohammad Maghsoudloo
 * B.S. Project
 * IoT Cloud Research Center
 */

date_default_timezone_set("Asia/Tehran");
$method = $_SERVER['REQUEST_METHOD'];
$query_string = $_SERVER['QUERY_STRING'];

switch ($method) {
    case 'GET':
        handle_get_request();
        break;
    case 'POST':
        handle_post_request();
        break;
    case 'DELETE':
        //Here Handle DELETE Request
        break;
    case 'PUT':
        //Here Handle PUT Request
        break;
}

function handle_get_request()
{
    read_db();
}

function handle_post_request()
{
    add_to_db(file_get_contents('php://input'));
}

function add_to_db($pins_json)
{
    $current_time = get_current_time();
    include 'connect_db.php';
    $sql = "INSERT INTO pi_data (time, pins) VALUES ('$current_time', '$pins_json')";

    if (mysqli_query($db_connection, $sql)) {
        echo "New record created successfully";
    } else {
        echo "Error: " . $sql . "<br>" . mysqli_error($db_connection);
    }

    mysqli_close($db_connection);
}

function read_db()
{
    $json_array = array();

    include 'connect_db.php';
    $sql = "SELECT * FROM commands";
    $result = mysqli_query($db_connection, $sql);

    if (mysqli_num_rows($result) > 0) {
        // output data of each row
        while ($row = mysqli_fetch_assoc($result)) {
            array_push($json_array, array('id' => $row['id'], 'time' => $row['time'], 'command' => $row['command'], 'status' => $row['status']));
        }
    } else {
        include 'no_data.php';
    }


    $json_string = json_encode($json_array, JSON_PRETTY_PRINT);
    echo $json_string;

    mysqli_close($db_connection);
    return $result;
}

function get_current_time()
{
    date_default_timezone_set("Asia/Tehran");
    return date("Y/m/d H:i:sa", time());
}