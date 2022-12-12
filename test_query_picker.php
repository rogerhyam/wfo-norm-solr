<?php

// get a test query and display it

// php -d memory_limit=2G test_query.php 

$json = json_decode(file_get_contents('tests/query_log_fixed.json'));

echo "\nNumber of queries: " . count($json);

for ($i=0; $i < 1000; $i++) {
    $line = readline("\n\nAny key for next query $i 'q' to quit: ");
    echo $line;
    if($line == 'q') break;
    $query_response = $json[$i];
    echo "\n------ Query ----\n";
    echo $query_response[0]->query;
    //echo "\n------ Response ----\n";
    //echo json_encode($query_response[1], JSON_PRETTY_PRINT);
}


