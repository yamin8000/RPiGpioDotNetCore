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

/**
 * method for handling http get requests
 * mobile(android) or web get requests
 */
function handle_get_request()
{
    global $query_string;
    read_db($query_string);
}

/**
 * method for handling http post requests
 * which are commands sent from mobile(android)
 */
function handle_post_request()
{
    add_to_db(file_get_contents('php://input'));
}

/**
 * method for adding command received from android mobile to database
 * @param $command string sent from android mobile
 */
function add_to_db($command)
{
    include 'connect_db.php';
    /**
     * adding raw command text, current server time and status of command
     * status can be either served or unserved
     * TODO fix status
     * currently status is unused!
     */
    $sql_query = "insert into commands (time, command, status)
    values (now(), '$command', 'unserved')";

    // TODO sending proper response in case of success or error
    if (mysqli_query($db_connection, $sql_query)) {
        echo "New command added successfully";
    } else {
        echo "Error: " . $sql_query . "<br>" . mysqli_error($db_connection);
    }

    mysqli_close($db_connection);
}

/**
 * method for reading last 10 records of raspberry pi data from database
 * and sending records as json array to user
 * alternatively user can limit number of request using query string
 * @param $search_limit number of records user wants to see
 * @return bool|mysqli_result result of sql query
 */
function read_db($search_limit)
{
    $response_json_array = array();

    include 'connect_db.php';

    if ($search_limit == '') {
        $search_limit = 10;
    }
    if (is_numeric($search_limit))
        $sql_query = "SELECT * FROM (SELECT * FROM pi_data ORDER BY id DESC) AS sorted LIMIT " . $search_limit;
    
    $result = mysqli_query($db_connection, $sql_query);

    if (mysqli_num_rows($result) > 0) {
        // output data of each row
        while ($row = mysqli_fetch_assoc($result)) {
            array_push($response_json_array, array("id" => $row["id"], "time" => $row["time"], "pins" => $row["pins"]));
        }
        $json_string = json_encode($response_json_array, JSON_PRETTY_PRINT);
        echo $json_string;
    } else {
        include 'no_data.php';
    }

    mysqli_close($db_connection);
    return $result;
}
