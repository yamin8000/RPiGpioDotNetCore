<?php
/**
 * @author: Yamin Siahmargooei
 * @copyright Golestan University, School of Engineering
 * @year 2018
 * Supervisor: Mohammad Maghsoudloo
 * B.S. Project
 * IoT Cloud Research Center
 */

$response_object = new stdClass();
$response_object->time = date("Y/m/d h:i:sa");
$response_object->data = "no data";
echo json_encode($response_object);