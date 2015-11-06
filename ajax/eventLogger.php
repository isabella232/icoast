<?php

require_once('../includes/globalFunctions.php');
$dbConnectionFile = DB_file_location();
require_once($dbConnectionFile);

$eventDataError = array();
$eventData = array();

if (isset($_POST['eventType'])) {
    $setTypeResult = setType($_POST['eventType'], "integer");
    if ($setTypeResult &&
            ($_POST['eventType'] === 1 || $_POST['eventType'] === 2 || $_POST['eventType'] === 3 || $_POST['eventType'] === 4)) {
        $eventData['event_type'] = $_POST['eventType'];
    } else {
        $eventDataError[] = "Invalid Event Type in supplied Event Logger data: {$_POST['eventType']}";
    }
} else {
    $eventDataError[] = "Missing Event Type field in supplied Event Logger data.";
}


if (isset($_POST['eventText'])) {
    if (strlen($_POST['eventText']) > 0) {
        $eventData['event_text'] = $_POST['eventText'];
    } else {
        $eventDataError[] = "Empty Event Text field in supplied Event Logger data.";
    }
} else {
    $eventDataError[] = "Missing Event Text field in supplied Event Logger data.";
}

if (isset($_POST['eventSummary'])) {
    if (strlen($_POST['eventSummary']) > 0) {
        $eventData['event_summary'] = $_POST['eventSummary'];
    } else {
        $eventDataError[] = "Empty Event Summary field in supplied Event Logger data.";
    }
} else {
    $eventDataError[] = "Missing Event Summary field in supplied Event Logger data.";
}


if (isset($_POST['userId'])) {
    $setTypeResult = setType($_POST['eventType'], "integer");
    if ($setTypeResult && $_POST['userId'] >= 0) {
        $eventData['user_id'] = $_POST['userId'];
    } else {
        $eventDataError[] = "Invalid User Id field in supplied Event Logger data: {$_POST['userId']}";
    }
}

if (isset($_POST['url'])) {
    $eventData['source_url'] = htmlspecialchars($_POST['url']);
}

if (isset($_POST['queryString'])) {
    $eventData['query_string'] = htmlspecialchars($_POST['queryString']);
}

if (isset($_POST['postData'])) {
    $eventData['post_data'] = htmlspecialchars($_POST['postData']);
}

if (isset($_POST['sourceScript'])) {
    $eventData['source_script'] = htmlspecialchars($_POST['sourceScript']);
}

if (isset($_POST['sourceFunction'])) {
    $eventData['source_function'] = htmlspecialchars($_POST['sourceFunction']);
}

if (isset($_POST['clientAgent'])) {
    $eventData['client_agent'] = htmlspecialchars($_POST['clientAgent']);
}

if (isset($_POST['eventCode'])) {
    $setTypeResult = setType($_POST['eventCode'], "integer");
    if ($setTypeResult && $_POST['eventCode'] > 0) {
        $eventData['event_code'] = htmlspecialchars($_POST['eventCode']);
    } else {
        $eventDataError[] = "Invalid Event Code field in supplied Event Logger data: {$_POST['eventCode']}";
    }
}

if (count($eventDataError = 0) && count($eventData) > 0) {
    $columnString = '';
    $valueString = '';
    $eventLogParams = array();
    foreach ($eventData as $column => $value) {
        $columnString .= ', ' . $column;
        $valueString .= ', :' . $column;
    }
    $eventLogQuery = "INSERT into event_log (event_time$columnString) VALUES (NOW()$valueString)";;
    run_prepared_query($DBH, $eventLogQuery, $eventData);
} else {
    //Call to self required to log error with error logging!
}