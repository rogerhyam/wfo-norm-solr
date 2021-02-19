<?php

require_once('config.php');
require_once('include/curl_functions.php');
require_once('include/AuthorTeam.php');

/*

This script will generate / update the lookup table for author abbreviations -> wikidata Q numbers (plus label, birth & death dates)

*/

echo "\nThis will update the Authors lookup table from Wikidata given a data dump file\n";

if(php_sapi_name() !== 'cli'){
    echo "Command line only!\n";
    exit;
}

$ops = getopt('f:');

// have we got a file
if(isset($ops['f']) && $ops['f']){
    $file_path = $ops['f']; 
}else{
    echo "!! You must set a path to the classification file with the -f option.\n";
    exit;
}

if(!file_exists($file_path)){
    echo "!! File doesn't exist: $file_path";
    exit;
}

if(is_dir($file_path)){
    echo "!! File is directory: $file_path";
    exit;
}

// count the lines
$total_lines = 0;
$fp = fopen($file_path,"r");
if($fp){
    while(!feof($fp)){
      $content = fgets($fp);
      if($content){
        $total_lines++;
      } 
    }
}
fclose($fp);
$total_lines--;
echo "Total lines: " . number_format($total_lines) . "\n";

// open the file
$file = fopen($file_path, "r");

if($file === FALSE){
    echo "Couldn't open file: $file_path\n";
    exit;
}

// assume longest line is 2,000 chars (currently longest is 480)
$fields = fgetcsv($file, 2000, "\t");

$field_index = array_search('scientificNameAuthorship', $fields);

if($field_index === false){
    echo "Can't find the 'scientificNameAuthorship' field in \n";
    print_r($fields);
    echo "Giving up\n";
    exit;
}

$line_count = 0;
$solr_docs = array();
$display_length = 0;

while($row = fgetcsv($file, 2000, "\t")){

    $authorTeam = new AuthorTeam($row[$field_index], true);
    
    $line_count++;

    // nice progress display
    echo str_repeat(chr(8), $display_length); // rewind
    echo str_repeat(" ", $display_length); // blank
    echo str_repeat(chr(8), $display_length); // rewind
    
    $percentage = round(($line_count/$total_lines)*100);
    $display = sprintf("%s |%s| %s", number_format($line_count), str_pad(str_repeat("=", $percentage).'>', 101, "-"), number_format($total_lines) );
    $display_length = strlen($display);
    
    echo $display;
}

// read the first line to get the column headers

// work out which column contains the author strings

// only look at that column


fclose($file);
echo "All done!\n\n";

