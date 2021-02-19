<?php

require_once('config.php');
require_once('include/AuthorTeam.php');
require_once('include/solr_functions.php');


$wfo_id = @$_GET['wfo_id'];
if(!$wfo_id){
    echo "You must pass a wfo_id parameter for the taxon e.g. wfo-0000615910-2019-05";
    exit;
}

echo "<h1>$wfo_id</h1>";

$doc = solr_get_doc_by_id($wfo_id);

$authorTeam = new AuthorTeam($doc->scientificNameAuthorship_s);

echo "<h2>{$doc->scientificNameAuthorship_s}</h2>";

echo "<p>" . $authorTeam->getHtmlAuthors() . "</p>";

echo "<p><textarea cols=\"100\" rows=\"10\">" . $authorTeam->getHtmlAuthors() . "</textarea></p>";

?>
<h2>Examples</h2>
<ul>
    <li><a href="/lookup_authors_test.php?wfo_id=wfo-0000622726-2019-05">wfo-0000622726-2019-05</a></li>
    <li><a href="/lookup_authors_test.php?wfo_id=wfo-0000677592-2019-05">wfo-0000677592-2019-05</a></li>
    <li><a href="/lookup_authors_test.php?wfo_id=wfo-0000522033-2019-05">wfo-0000522033-2019-05</a></li>
    <li><a href="/lookup_authors_test.php?wfo_id=wfo-0000602537-2019-05">wfo-0000602537-2019-05</a></li>
    <li><a href="/lookup_authors_test.php?wfo_id=wfo-0000655786-2019-05">wfo-0000655786-2019-05</a></li>
    <li><a href="/lookup_authors_test.php?wfo_id=wfo-0000795894-2019-05">wfo-0000795894-2019-05</a></li>
    <li><a href="/lookup_authors_test.php?wfo_id=wfo-0000434584-2019-05">wfo-0000434584-2019-05</a></li>    
</ul>

<?php
echo "<h2>Authors</h2>";
echo "<pre>";
var_dump($authorTeam->authors);
echo "</pre>";

echo "<h2>Full Solr Doc</h2>";
echo "<pre>";
var_dump($doc);
echo "</pre>";

?>
