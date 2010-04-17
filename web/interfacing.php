<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
    <title>interfacing keepRight</title>
  </head>
  <body>

<a href="/"><img border="0" src="keepright.png" alt="keep-right logo"></a>data consistency checks for <a href="http://www.openstreetmap.org">OSM</a><hr>

<h2>Interfacing with KeepRight</h2>

You may use KeepRight&apos;s results in a number of ways:

<ul>
	<li>by means of the <a href="report_map.php">map web page</a></li>
	<li>exporting <a href="#GPX">GPX waypoints</a></li>
	<li>exporting new errors as <a href="#RSS">RSS feed</a></li>
	<li>getting the whole errors <a href="#DUMP">dump file</a></li>
</ul>

<a name="GPX"><h3>exporting GPX waypoints</h3>
<b>Purpose</b><br>
Exporting a section of the map into a GPX-styled list of waypoints for use with GPS units

<br><br><b>URL format</b><br>
http://keepright.ipax.at/export.php?format=gpx&amp;ch=20,30,311,312&amp;left=-82.39&amp;bottom=30&amp;right=-82.1&amp;top=30.269<br>
You can specify a list of error types you want to have in the file as well as a bounding box on the map. This export will return up to 100 waypoints. This limit could be increased, in case there is demand.
<br><br>There is a link on the lower left corner of the map-page pointing to the GPX service that always includes the current error type selection and view from the map.


<a name="RSS"><h3>exporting new errors as RSS feed</h3>
<b>Purpose</b><br>
Watching a section of the map for newly found errors

<br><br><b>URL format</b><br>
http://keepright.ipax.at/export.php?format=rss&amp;ch=20,30,311,312&amp;left=-82.39&amp;bottom=30&amp;right=-82.1&amp;top=30.269

The URL format is the same as for GPX exports, just the format parameter is different. The RSS feed will include error entries that were first found within the last three weeks.
<br><br>There is a link on the lower left corner of the map-page pointing to the RSS service that always includes the current error type selection and view from the map.


<a name="DUMP"><h3>getting the whole dump-file</h3>
<b>Purpose</b><br>
Doing something completely different with 10 Millions of errors...

<br><br><b>URL format</b><br>
<a href="http://keepright.ipax.at/keepright_errors.txt.bz2">http://keepright.ipax.at/keepright_errors.txt.bz2</a>

This tab-separated file contains all errors currently open for the whole planet (currently >200MB). It is being updated daily.

<br><br><b>Table layout</b><br>
<ul>
<li>schema</li>
The schema is an identifier naming a region on the planet. According to the <a href="planet.jpg">planet splitting map</a> the planet is split in rectangular parts to get roughly equally sized dump files. Consider the schema as a prefix for the error_id.
<li>error_id</li>A number identifying errors, starting from 1 for each schema. An error_id is worth nothing if you don&apos;t know the schema!
<li>error_type</li>numeric representation of the type of error. Error types are assigned in blocks of 10s (20, 30, 40...). They correspond with the name of the script file doing the checking.<br>
Error types may be sub-typed (281, 282 etc.). Subtyped error checking routines test for different aspects related to a single topic (in the example 280 means &quot;boundaries&quot;,  281 means &quot;missing name[ for boundaries]&quot; and 282 means &quot;missing admin level[ for boundaries]&quot;). Subtyped error types are rendered as groups that may be collapsed on the web site.

<li>error_name</li>textual representation (short name) of the type of error. On sub-typed error types you may want to prepend the error_name that belongs to the main number to make the name complete.
<li>object_type</li>one of node/way/relation
<li>object_id</li>an OSM node_id, way_id or relation_id
<li>state</li>one of new, reopened, ignore_temporarily, ignore. You won&apos;t see any &apos;cleared&apos; errors because the dump contains active errors only. Temporarily ignored errors are issues fixed by a user who really hopes to have fixed it. Temporarily ignored errors will jump back in the &apos;new&apos; state with the next update if the error isn&apos;t really fixed. Ignored errors are issues that are simply false positives and should never come back just because KeepRight is wrong and this exception cannot be included in the ruleset.<br>
Errors that were once cleared and come back at some point in time later are put in the <em>reopened</em> state. Please note that this may happen due to runtime-errors in the scripts. So you may just consider <em>new</em> and <em>reopened</em> the same.
<li>description</li>The verbose error message that comes out of the checking routine
<li>first_occurrence</li>Timestamp (MESZ) of when this error was found the first time
<li>last_checked</li>Timestamp (MESZ) of last time this error was (re-)checked by the scripts
<li>object_timestamp</li>Timestamp of the object that was used when checking, as found in the official planet file.
<li>lat</li>
<li>lon</li>Location on the planet. Coordinates are given in the same projection as found in the official planet file.
<li>comment</li>User-comment (if any)
<li>comment_timestamp</li>Timestamp (MESZ) of when the comment was given (if any)
</ul>


<br><br><b>loading the errors table</b><br><br>


This is the schema definition for use with MySQL databases:
<pre>
CREATE TABLE IF NOT EXISTS `keepright_errors` (
  `schema` varchar(6) NOT NULL,
  `error_id` int(11) NOT NULL,
  `error_type` int(11) NOT NULL,
  `error_name` varchar(100) NOT NULL,
  `object_type` enum('node','way','relation') NOT NULL,
  `object_id` bigint(64) NOT NULL,
  `state` enum('new','reopened','ignore_temporarily','ignore') NOT NULL,
  `description` text NOT NULL,
  `first_occurrence` datetime NOT NULL,
  `last_checked` datetime NOT NULL,
  `object_timestamp` datetime NOT NULL,
  `lat` int(11) NOT NULL,
  `lon` int(11) NOT NULL,
  `comment` text,
  `comment_timestamp` datetime
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

LOAD DATA LOCAL INFILE 'keepright_errors.txt' INTO TABLE keepright_errors IGNORE 1 LINES;
</pre>

<p>Please note that <em>schema</em> is a reserved word in MySQL, so you always have to quote it like this: `schema`</p>

<p>There are two primary keys in this table: a natural one and an artificial one:<br>

The natural primary key consists of error_type, object_type, object_id, lat, lon. That means one type of error may be found on multiple spots belonging to one single object (eg. self-intersections of ways).</br>

The artificial primary key consists of schema and error_id. It is used just for simplicity of referencing individual error instances and it is completely redundant.</p>


</body>
</html>

