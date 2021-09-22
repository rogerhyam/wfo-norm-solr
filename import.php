<?php

require_once('config.php');
require_once('include/curl_functions.php');
require_once('include/solr_functions.php');
require_once('include/AuthorTeam.php');

/* useful curl commands to clean things up

 curl -X POST -H 'Content-Type: application/json' 'http://localhost:8983/solr/wfo/update' --data-binary '{"delete":{"query":"snapshot_version_s:2021-09"} }' --user wfo:****
 curl -X POST -H 'Content-Type: application/json' 'http://localhost:8983/solr/wfo/update' --data-binary '{"commit":{} }' --user wfo:****

*/

echo "\nThis will import a new taxonomic backbone file\n";

if(php_sapi_name() !== 'cli'){
    echo "Command line only!\n";
    exit;
}

$ops = getopt('f:v:');

// have we got a version
if(isset($ops['v']) && $ops['v']){
    $version = $ops['v']; 
}else{
    echo "You must set a version for this import with the -v option. \n";
    exit;
}

// have we got a file
if(isset($ops['f']) && $ops['f']){
    $file_path = $ops['f']; 
}else{
    echo "You must set a path to the classification file with the -f option.\n";
    exit;
}

if(!file_exists($file_path)){
    echo "File doesn't exist: $file_path";
    exit;
}

if(is_dir($file_path)){
    echo "File is directory: $file_path";
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
}

// get the column titles
// assume longest line is 2,000 chars (currently longest is 480)
$fields = fgetcsv($file, 2000, "\t");
echo "Total fields: " . number_format(count($fields)) . "\n";

// go for it to work through lines
$line_count = 0;
$solr_docs = array();
$display_length = 0;

while($row = fgetcsv($file, 2000, "\t")){

    // integrity check - there should be as many columns as there are fields
    if(count($fields) != count($row)){
        print_r($row);
        exit;
    }

    $solr_doc = array();

    // we tag each record with the version
    $solr_doc['snapshot_version_s'] = $version;
    $solr_doc['solr_import_dt'] = date('Y-m-d\Th:i:s\Z');

    // work through the fields
    for ($i=0; $i < count($fields); $i++) { 
        
        // each field is stored as a _s at a minimum
        $field = $fields[$i];
        $val = trim($row[$i]);

        // we need to fix capitalization in the taxonomicStatus_s field 
        // because it has become unreliable.
        if($field == 'taxonomicStatus'){
            $val = ucfirst( strtolower( trim($val) ) );
        }

        $solr_doc[$field . '_s'] = $val;

        // the id of the solr record ID is the WFO id plus the version
        if($field == 'taxonID'){
            $solr_doc['id'] = $val . '-' . $version;
        }


        // created and modified can be stored as date types
        if($field == 'created'|| $field == 'modified'){
            if($val){
                $solr_doc[$field . '_dt'] = $val . 'T00:00:00Z';
            }
        }

    }

    // make our own combined name field for lookups and store it lower case
    $full_name =  '<i>' . $solr_doc['scientificName_s'] . '</i>';
    if(isset($solr_doc['scientificNameAuthorship_s'])) $full_name = $full_name . ' ' . $solr_doc['scientificNameAuthorship_s'];
    if(isset($solr_doc['family_s']) && $solr_doc['family_s'] != $solr_doc['scientificName_s'] ) $full_name = $full_name . ' [' . $solr_doc['family_s'] .']';
    $solr_doc['full_name_s_lower'] = strip_tags($full_name);
    $solr_doc['full_name_s'] = $full_name;

    // authors get special treatment
    $authorTeamString = $row[array_search('scientificNameAuthorship', $fields)];
    $authorTeam = new AuthorTeam($authorTeamString);
    $solr_doc['scientificNameAuthorship_html_s'] = $authorTeam->getHtmlAuthors();
    $solr_doc['scientificNameAuthorship_labels_ss'] = $authorTeam->getAuthorLabels();
    $solr_doc['scientificNameAuthorship_ids_ss'] = $authorTeam->getAuthorIds();

    // rank as lowercase for stats
    $solr_doc['taxonRank_lower_s'] = strtolower($row[array_search('taxonRank', $fields)]);

    // we save the name as a path so we can search on it.
    // get get species and subspecific taxa
    $path = "";
    $parts = explode(' ', $row[array_search('scientificName', $fields)]);
    if(count($parts) == 4) unset($parts[2]); // remove the rank
    $path = "/" . implode('/', $parts);
    // no naughty chars in paths. Just letters and forward slash
    $path = preg_replace('/[^A-Za-z\/]/', '', $path);
    // lowercase it so we can use it for searching easily
    $path = strtolower($path);
    $solr_doc['name_descendent_path'] = $path;
    $solr_doc['name_ancestor_path'] = $path;

    $solr_docs[] = $solr_doc;

    $line_count++;

    // every ten thousand we send them off to solr
    if(count($solr_docs) >= 10000 || $line_count >= $total_lines){
        //echo "$line_count: \t Sending to SOLR\n";
        $response = solr_add_docs($solr_docs);
        $response = json_decode($response);
        if(isset($response->error)){
            print_r($response->error);
        }
        solr_commit();
        $solr_docs = array();


        // nice progress display
        echo str_repeat(chr(8), $display_length); // rewind
        echo str_repeat(" ", $display_length); // blank
        echo str_repeat(chr(8), $display_length); // rewind
        
        $percentage = round(($line_count/$total_lines)*100);
        $display = sprintf("%s |%s| %s", number_format($line_count), str_pad(str_repeat("=", $percentage).'>', 101, "-"), number_format($total_lines) );
        $display_length = strlen($display);
        
        echo $display;

    }

}

fclose($file);
solr_commit();
echo "\nImport done!\n";
