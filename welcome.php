<?php

$formats = \EasyRdf\Format::getFormats();

?>
<html>
<head>

<style>
    body{
        font-family: Sans-Serif;
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
    #content{
        margin: auto;
        max-width: 60em;
        border: 1px solid gray;
        padding: 2em;
    }
    .alert{
        background-color: yellow;
        color: red;
        border: solid 1px red;
        padding: 0.5em;
    }
    .aside{
        background-color: #eee;
        color: black;
        border: solid 1px gray;
        padding: 0.5em;
    }
    h3{
        color: gray;
    }
</style>

</head>
<body>
<div id="content">
<h1>WFO Machine Readable Plant List</h1>

<p>
    This service provides a machine readable version of the taxonomic backbone of the <a href="http://www.worldfloraonline.org/">World Flora Online</a>.
    It is intended for technical users. This page is the only human readable part of it.
    If you are not interested in consuming raw data through a web service you probably want to go to <a href="http://www.worldfloraonline.org/">the main website of the World Flora Online</a>.    
</p>

<p>
    There are two ways to interact with the data: through semantic web compatible URIs or via the GraphQL query language. Both approaches use the same basic data model.
    Objects and properties are defined by URIs in the Semantic Web interface and in the GraphQL documentation.
</p>

<p  class="alert" >
    <strong>Status:</strong> This service is still under development and should not be considered stable. Please enjoy exploring it. If you are interested in using it 
    in a production system please contact Roger Hyam <a href="rhyam@rbge.org.uk">rhyam@rbge.org.uk</a> and register your interest.
    We will then be able to freeze features that are needed by live systems. We'd also welcome any feedback you might have.
</p>

<h2>Data Model</h2>

<h3>Overview</h3>

<p>
    For each major update of the classification in the main WFO database a snapshot of the taxonomic backbone (names and their statuses) is taken and added to this service. 
    The data available here therefore represents multiple classifications of the plant kingdom showing how our understanding has changed through time.
</p>
<p>
    In order to represent multiple classifications in a single dataset it is necessary to adopt the TaxonConcept model which differentiates between taxa (TaxonConcepts)
    which vary between classifications and names (TaxonNames) which do not, but which may play different roles in different classifications.
</p>
<p class="aside">
    <strong>Taxon name/concept background: </strong>
    A good analogy for those unfamiliar with the TaxonConcept model is that of polygons and points within a geospatial model.
    A classification divides a plane into contiguous map of nested polygons (like counties, regions, countries, continents).
    These are the taxa.
    The names are points on the plane.
    The name used for a polygon is the oldest point that occurs within it.
    Other names that fall in that polygon are referred to as synonyms.
    Different taxonomic classifications are like the different maps of the same plane with different polygons but with points that are the same on all maps.
    Polygons on two maps might have the same calculated name but different boundaries and different synonyms.
    It is therefore necessary to refer to taxa in different classifications using unique identifiers rather than their calculated names.
</p>

<h3>Identifiers</h3>

<p>
All TaxonConcepts and TaxonNames are identified with URIs which resolve according to semantic web best practices (see below).
These identifiers are also used in the GraphQL accessible data. They are intended to be persistent and can be stored.
</p>
<p>
<strong>TaxonNames</strong> identifiers take the form <a href="<?php echo get_uri('wfo-0001048237') ?>" ><?php echo get_uri('wfo-0001048237') ?></a>. The final part of the URI is the same as the identifier 
used in the live web pages for the current version of the WFO. There is a one to one relationship between names, as created under the International Code for Botanical Nomenclature,
and these identifiers.
</p>

<p>
<strong>TaxonConcepts</strong> identifiers take the form <a href="<?php echo get_uri('wfo-0001048237-2019-05') ?>" ><?php echo get_uri('wfo-0001048237-2019-05') ?></a>. The final part of the URI is a name identifier 
qualified by a classification version. The version format is the year followed by the two digit month. 
</p>

<p>
Note that although the format of identifiers is described here (because it is useful for understanding and debugging) you should not construct them programmatically
but treat them as opaque strings. An example of how this can go wrong is that not every name has an associated taxon in every classification.
If a TaxonName's role within a classification is as a synonym there is no associated taxon in that classification.
The name <a href="<?php echo get_uri('wfo-0000615907') ?>" ><?php echo get_uri('wfo-0000615907') ?></a> (<i>Comandra elliptica</i> Raf.)
Is a synonym in the classification 2019-05.
If you were to create the taxon URI 
<a style="color: red" href="<?php echo get_uri('wfo-0000615907-2019-05') ?>" ><?php echo get_uri('wfo-0000615907-2019-05') ?></a> by tagging the version id on the end
and try to retrieve data you would currently be redirect to the taxon 
<a href="<?php echo get_uri('wfo-0000615918-2019-05') ?>" ><?php echo get_uri('wfo-0000615918-2019-05') ?></a> (<i>Comandra umbellata</i> (L.) Nutt.)
in which <i>Comandra elliptica</i> Raf. is a synonym.
This behaviour is technically wrong and may change to returning a HTTP 400 Bad Request or something else in future.
If you were to use the created URI in a web browser you would be redirected to the synonyms page in the website.
Dereferencing the name identifier would have provided a list of its usages in different classifications and is the correct approach.
</p>
<h3>Properties</h3>
<p>
The diagram below shows the property relationships in the data model. Further documentation on these can be found either by dereferencing the URIs of the terms in the RDF responses 
or by looking at the GraphQL documentation using an IDE. The second way might be useful even if you intend to only use the Semantic Web API.
</p>

<p></p>

<a href="terms/png"><img src="terms/png" style="width: 100%"/></a>

<h2>GraphQL Interface</h2>

<p>The <a href="https://graphql.org/">GraphQL</a> endpoint is <a href="https://wfo-list.rbge.info/gql">https://wfo-list.rbge.info/gql</a>.</p>

<p>
    There are many resources on the web about use of GraphQL. It enables self documenting APIs and all the objects and properties available here have been documented. 
    The use of a GraphQL client or IDE are recommended e.g. the GraphiQL plugin for Google Chrome.
</p>

<h2>Semantic Web Resources</h2>

<p>The URI identifiers for TaxonConcepts and TaxonNames follow Semantic Web best practice in implementing content negotiation.</p>

<p>
    Calling a URI will always result in an HTTP 303 "See Other" redirect to an appropriately formatted source of data about that resource.
    Where the client is sent depends on the content of the Accept header in the request.
    When data of a recognized mimetype isn't contained within the header or when an HTML mimetype is found 
    the client will be redirected to an appropriate page within the WFO website. This is to ensure human users are always sent to something
    appropriate and not confused by the data services offered here.
</p>

<p>
    If a recognized mimetype is found then the client is redirect to a data resource of that mimetype.
    By convention the URI of that data resource will be the original URI with a slash followed by the name of the data type appended.
    This behaviour makes developing and debugging easy but should not be relied upon in code as it may change in the future.
    Production systems should always resolve the identifier URI and follow the supplied redirect.
</p>

<h3>Supported formats</h3>

<p>
    Data can be returned in the <?php echo count($formats) ?> formats listed in the table below.
    These include graphical representations of the data.
</p>
<table>
<tr>
    <th>Name</th>
    <th>Recommended Mime Type</th>
    <th>Recognized Mime Types</th>
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

<p>An example graph for a TaxonConcept</p>
<a href="/wfo-4000000718-2019-05/svg"><img src="/wfo-4000000718-2019-05/svg" style="width: 100%"/></a>

<p>An example graph for a TaxonName</p>
<a href="/wfo-4000000718/svg"><img src="/wfo-4000000718/svg" style="width: 100%"/></a>


</div>

</body>