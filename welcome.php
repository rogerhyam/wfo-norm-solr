<?php

$formats = \EasyRdf\Format::getFormats();

?>
<html>
<head>

<style>
    body{
        padding: 1em;
        width: 80%;
    }
    table{
        border-spacing: 0;
        border-collapse: collapse;
    }
    td{
        padding: 0.3em;
    }
    th{
        text-align: left;
        background-color: gray;
        color: white;
    }
</style>

</head>
<body>
<h1>WFO Machine Readable Plant List</h1>

<p>This service provides a machine readable version of the taxonomic backbone of the World Flora Online.</p>

<h2>Semantic Web Resources</h2>

<h3>Supported formats</h3>

<p>Data can be returned in the <?php echo count($formats) ?> formats listed in the table below. </p>
<p>If a request does not include the format as the last part of the URI then the following will occur.
The Accepts HTTP header of the request will be examined for a recognised mime type string.
If one is found then a 303 redirect will be issued to a URL for a resource in that format.
If no format is found then the user will be assumed to be a human not a machine and will be redirected 
to the web page for that taxon on the main WFO site.</p>
<table>
<tr>
    <th>Name</th>
    <th>Recommended Mime Type</th>
    <th>Recognised Mime Types</th>
    <th>Example</th>

</tr>
<?php

foreach($formats as $format_name => $format){
    echo "<tr>";
    echo "<td>$format_name</td>";
    echo "<td>". $format->getDefaultMimeType() . "</td>";
    echo "<td>" . implode( ', ', array_keys($format->getMimeTypes()) ) . "</td>";
    echo "<td><a href=\"/wfo-4000000718/$format_name\">/wfo-4000000718/$format_name</a></td>"; 
    echo "</tr>";

}

?>
</table>

<h2>GraphQL Interface</h2>

<p>There is also a GraphQL Interface to the data .... </p>

<h2>Data Model</h2>

<a href="terms/png"><img src="terms/png" style="width: 50%"/></a>


</body>