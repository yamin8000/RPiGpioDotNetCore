<?php
/**
 * @author: Yamin Siahmargooei
 * @copyright Golestan University, School of Engineering
 * @year 2018
 * Supervisor: Mohammad Maghsoudloo
 * B.S. Project
 * IoT Cloud Research Center
 */

//TODO encrypting json data with SHA or at very least implementing some sort of token for security

date_default_timezone_set("Asia/Tehran");
//method of request (post or get)
$method = $_SERVER['REQUEST_METHOD'];
/**
 * query string that user can send with url
 * like iot.com/service.php?results=100
 */
$query_string = $_SERVER['QUERY_STRING'];
//raw json data in string format
$post_data = file_get_contents('php://input');
//decoding json to associative array
$post_json_array = json_decode($post_data, true);

switch ($method) {
    case 'GET':
        handle_get_request();
        break;
    case 'POST':
        handle_post_request();
        break;
}


/**
 * http get request handler
 * mobile(android) or raspberry pi get requests
 */
function handle_get_request()
{
    global $query_string;
    read_db($query_string);
}

/**
 * http post request handler
 * which are commands sent from mobile(android) or data from raspberry pi
 */
function handle_post_request()
{
    /*
     * adding raw post data(possibly json) to database
     *
     * json format example:
     * {
     * 'client':'mobile',
     * 'data':'off'
     * }
     *
     * or
     *
     * {"client":"raspberry pi","data":"100"}
     */
    global $post_json_array;
    add_to_db($post_json_array['client'], $post_json_array['data']);
}

/**
 * @param $client string, request
 * sender client type (mobile, raspberry pi, web browser)
 * @param $data string, command sent from mobile or liquid level sent from raspberry pi
 */
function add_to_db($client, $data)
{
    global $post_json_array;
    $table_rows_count = get_table_rows_count();
    $current_time = get_current_time();
    /*
     * this only happens only one time when database is empty
     * status of electric valve is null by default
     * valve is connected to raspberry pi so logically we can query initial status from pi
     * from different point of view we can dismiss initial status which is irrelevant
     * simply we don't care about initial status
     * because valve status means something only when mobile send a command to turn it off or on
     */
    $electric_valve_status = "";
    $liquid_level = "";
    require 'connect_db.php';

    if ($table_rows_count != 0) {
        /*
         * if table is not empty then probably mobile sent commands or raspberry pi sent data
         * either way we need to know status of electric valve which can be {unknown, on, off}
         * if raspberry pi sent data (liquid level) status of electric valve remains unchanged and we can fetch it from last record of database
         * if mobile sent data (command) status of electric valve changes and now it's equal to mobile command
         */
        $last_row_select_query = "SELECT * FROM (SELECT * FROM pi_data_liquid_level ORDER BY id DESC) AS sorted LIMIT 1";
        $result = mysqli_query($db_connection, $last_row_select_query);
        switch ($client) {
            case 'mobile':
                //TODO check if command is in correct format, if not return 'unknown'
                $electric_valve_status = $data;
                $liquid_level = mysqli_fetch_assoc($result)['liquid_level'];
                break;
            case 'raspberry pi':
                //TODO check if liquid level is numeric
                $liquid_level = $data;
                $electric_valve_status = mysqli_fetch_assoc($result)['valve_status'];
                break;
            default:
                //TODO display error page or json error
                die("Error");
                break;
        }
    } else {
        /*
         * well, this means table is empty and there is no data from mobile or raspberry pi from before
         * however if code reached here either of them sent data right now
         * only course of action here is adding either raspberry pi or mobile data to database and 'unknown' value for another one
         * anyway when raspberry pi sees 'unknown' this means no action
         * and mobile cannot decide any action because there's no data
         * this section of code possibly happen only one time after database creation
         */

        switch ($client) {
            case 'mobile':
                //TODO check if command is in correct format, if not return 'unknown'
                $electric_valve_status = $data;
                $liquid_level = 'unknown';
                break;
            case 'raspberry pi':
                //TODO check if liquid level is numeric
                $liquid_level = $data;
                $electric_valve_status = 'unknown';
                break;
        }
        //$electric_valve_status = "unknown";
        //$liquid_level = "unknown";
    }

    //now we changed liquid level and electric valve status based on different scenarios then we can add data to database
    $insert_query = "INSERT INTO pi_data_liquid_level (time, liquid_level, valve_status) VALUES ('$current_time', '$liquid_level', '$electric_valve_status')";
    // TODO sending proper response in case of success or error
    if (mysqli_query($db_connection, $insert_query)) {
        $response = array('time' => get_current_time(), 'client' => $post_json_array['client'], 'status' => 'ok');
    } else {
        $response = array('time' => get_current_time(), 'client' => $post_json_array['client'], 'status' => 'error', 'error' => mysqli_error($db_connection));
    }
    echo json_encode($response);

    mysqli_close($db_connection);
}

/**
 * by default this method read last 10 records of raspberry pi data from database
 * and sending records as json array to user
 * alternatively user can limit number of request using query string
 * @param $search_limit number of records user wants to see
 * @return bool|mysqli_result result of sql query
 */
function read_db($search_limit)
{
    $json_array = array();

    require 'connect_db.php';

    //TODO fixing this -> when there's no query string, $search_limit = '' or null? probably equals to '' because code works
    if ($search_limit == '') {
        $search_limit = 10;
    } else {
        //TODO fix
    }
    if (is_numeric($search_limit)) {
        //reading from database
        $sql_query = "SELECT * FROM (SELECT * FROM pi_data_liquid_level ORDER BY id DESC) AS sorted LIMIT " . $search_limit;
    } else {
        die("todo fix");
    }

    $result = mysqli_query($db_connection, $sql_query);

    /*
     * fetching records row by row from sql results and adding it to a associative array for creating json
     */
    if (mysqli_num_rows($result) > 0) {
        // output data of each row
        while ($row = mysqli_fetch_assoc($result)) {
            //creating associative array and pushing records to array
            array_push($json_array, array('id' => $row['id'], 'time' => $row['time'], 'liquid_level' => $row['liquid_level'], 'valve_status' => $row['valve_status']));
        }
        //encoding array to json and printing it to user
        $json_string = json_encode($json_array, JSON_PRETTY_PRINT);
        echo $json_string;
    } else {
        include 'no_data.php';
    }

    mysqli_close($db_connection);
    return $result;
}

/**
 * function for finding out if database is empty or not
 */
function get_table_rows_count()
{
    require 'connect_db.php';
    $sql = "SELECT * FROM pi_data_liquid_level";
    $result = mysqli_query($db_connection, $sql);
    return mysqli_num_rows($result);
}

/**
 * @return false|string, current date and time in desired format for adding to database
 */
function get_current_time()
{
    date_default_timezone_set("Asia/Tehran");
    return date("Y/m/d H:i:sa", time());
}
