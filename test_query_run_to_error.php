<?php

// get a test query and display it

// php -d memory_limit=2G test_query_run_to_error.php  

$end_point = "http://localhost:2000/gql";

$json = json_decode(file_get_contents('tests/query_log_fixed.json'));

echo "\nNumber of queries: " . count($json);

for ($i=8767; $i < count($json); $i++) {
    echo "\n$i\t";

    $query = $json[$i][0];
    $good_response = $json[$i][0];

    $query_string = $query->query;

    // make all the taxon ids into ids for 2022-12
    $query_string = preg_replace('/(wfo-[0-9]{10})-[0-9]{4}-[0-9]{2}/', "$1-2022-12", $query_string);

   // echo $query_string;

    // Create a new cURL resource
    $ch = curl_init($end_point);

    // Setup request to send json via POST
    $payload = json_encode(array("query" => $query_string));

    // Attach encoded JSON string to the POST fields
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

    // Set the content type to application/json
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));

    // Return response instead of outputting
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Execute the POST request
    $result = curl_exec($ch);

    // Close cURL resource
    curl_close($ch);

    if(stripos($result, 'error') && stripos($result, '[message] => Syntax Error:') === false){

        $result = json_decode($result);

        if(isset($result->errors) && count($result->errors) == 1 && stripos($result->errors[0]->message, 'Syntax Error') !== false){
            echo "Syntax Error";
            continue;
        }


        // stop 
        print_r($result);
        echo "\n-------- Query ----------\n";
        echo $query_string;
        exit;

    }else{
        echo "OK";
    }

}


