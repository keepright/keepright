<?php
require('webconfig.inc.php');
require('helpers.inc.php');
?><!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
    <title><?php echo T_gettext('interfacing KeepRight'); ?></title>
    <script type="text/javascript" src="keepright.js"></script>
  </head>
  <body>

<a href="/"><img border="0" src="keepright.png" alt="keep-right logo"></a><?php printf(T_gettext("data consistency checks for %sOSM%s"),
"<a href='http://www.openstreetmap.org'>", "</a>"); ?><hr>

<?php 
echo '<form name="myform" method="get" action="' . $_SERVER['PHP_SELF'] . '">';
echo '<div style="position:absolute; top:70px; right:10px;">'; language_selector(); echo '</div>';
echo "</form>";

echo '<h2>' . T_gettext('Interfacing with KeepRight') . '</h2>';

echo T_gettext('You may use KeepRight&apos;s results in a number of ways:');

echo "
<ul>
	<li>"; printf(T_gettext('by means of the %smap web page%s'), '<a href="report_map.php">', '</a>'); echo "</li>
	<li>"; printf(T_gettext('exporting %sGPX waypoints%s'), '<a href="#GPX">', '</a>') ; echo "</li>
	<li>"; printf(T_gettext('exporting new errors as %sRSS feed%s'), '<a href="#RSS">', '</a>'); echo "</li>
	<li>"; printf(T_gettext('getting the whole errors %sdump file%s'), '<a href="#DUMP">', '</a>'); echo "</li>
</ul>
";

echo "
<a name='GPX'><h3>" . T_gettext('exporting GPX waypoints') . "</h3>
<b>" . T_gettext('Purpose') . "</b><br>
" . T_gettext('Exporting a section of the map into a GPX-styled list of waypoints for use with GPS units') . "

<br><br><b>" . T_gettext('URL format') . "</b><br>
http://keepright.ipax.at/export.php?format=gpx&amp;ch=20,30,311,312&amp;left=-82.39&amp;bottom=30&amp;right=-82.1&amp;top=30.269<br>
" . T_gettext('You can specify a list of error types you want to have in the file as well as a bounding box on the map. This export will return up to 100 waypoints. This limit could be increased, in case there is demand.') . "
<br><br>" . T_gettext('There is a link on the lower left corner of the map-page pointing to the GPX service that always includes the current error type selection and view from the map.');

echo "
<a name='RSS'><h3>" . T_gettext('exporting new errors as RSS feed') . "</h3>
<b>" . T_gettext('Purpose') . "</b><br>
" . T_gettext('Watching a section of the map for newly found errors') . "

<br><br><b>" . T_gettext('URL format') . "</b><br>
http://keepright.ipax.at/export.php?format=rss&amp;ch=20,30,311,312&amp;left=-82.39&amp;bottom=30&amp;right=-82.1&amp;top=30.269

" . T_gettext('The URL format is the same as for GPX exports, just the format parameter is different. The RSS feed will include error entries that were first found within the last three weeks.') . "
<br><br>" . T_gettext('There is a link on the lower left corner of the map-page pointing to the RSS service that always includes the current error type selection and view from the map.');


echo "
<a name='DUMP'><h3>" . T_gettext('getting the whole dump-file') . "</h3>
<b>" . T_gettext('Purpose') . "</b><br>
" . T_gettext('Doing something completely different with 10 millions of errors...') . "

<br><br><b>" . T_gettext('URL format') . "</b><br>
<a href='http://keepright.ipax.at/keepright_errors.txt.bz2'>http://keepright.ipax.at/keepright_errors.txt.bz2</a>

" . T_gettext('This tab-separated file contains all errors currently open for the whole planet (currently >200MB). It is being updated daily.');

echo "
<br><br><b>" . T_gettext('Table layout') . "</b><br>
<ul>
<li>" . T_gettext('schema') . "</li>
";
printf(T_gettext('The schema is an identifier naming a region on the planet. According to the %splanet splitting map%s the planet is split in rectangular parts to get roughly equally sized dump files. Consider the schema as a prefix for the error_id.'), '<a href="planet.jpg">', '</a>');
echo "
<li>" . T_gettext('error_id') . "</li>" . T_gettext('A number identifying errors, starting from 1 for each schema. An error_id is worth nothing if you don&apos;t know the schema!') . "
<li>" . T_gettext('error_type') . "</li>" . T_gettext('numeric representation of the type of error. Error types are assigned in blocks of 10s (20, 30, 40...). They correspond with the name of the script file doing the checking.') . "<br>
" . T_gettext('Error types may be sub-typed (281, 282 etc.). Subtyped error checking routines test for different aspects related to a single topic (in the example 280 means &quot;boundaries&quot;, 281 means &quot;missing name[ for boundaries]&quot; and 282 means &quot;missing admin level[ for boundaries]&quot;). Subtyped error types are rendered as groups that may be collapsed on the web site.');

echo "
<li>" . T_gettext('error_name') . "</li>" . T_gettext('textual representation (short name) of the type of error. On sub-typed error types you may want to prepend the error_name that belongs to the main number to make the name complete.') . "
<li>" . T_gettext('object_type') . "</li>" . T_gettext('one of node/way/relation') . "
<li>" . T_gettext('object_id') . "</li>" . T_gettext('an OSM node_id, way_id or relation_id') . "
<li>" . T_gettext('state') . "</li>" . T_gettext('one of new, reopened, ignore_temporarily, ignore. You won&apos;t see any &apos;cleared&apos; errors because the dump contains active errors only. Temporarily ignored errors are issues fixed by a user who really hopes to have fixed it. Temporarily ignored errors will jump back in the &apos;new&apos; state with the next update if the error isn&apos;t really fixed. Ignored errors are issues that are simply false positives and should never come back just because KeepRight is wrong and this exception cannot be included in the ruleset.') . "<br>
" . T_gettext('Errors that were once cleared and come back at some point in time later are put in the <em>reopened</em> state. Please note that this may happen due to runtime-errors in the scripts. So you may just consider <em>new</em> and <em>reopened</em> the same.'); 

echo "
<li>" . T_gettext('msgid') . "</li>" . T_gettext('This is the scaffold for the error description where placeholders ("$i") stand in place of the actual values inserted by the concrete error instance. You may put this scaffold inside a GNU gettext() function to have it translated. GNU gettext requires  a .po file that holds original and translated strings. You may use existing .po files from here: ') . "<a href='locale/de.po'>de</a> <a href='locale/pt_BR.po'>pt_BR</a><br>" . T_gettext('find the GNU gettext template file here:') . " <a href='locale/keepright.pot'>keepright.pot</a>
<li>txt1 ... txt5</li>" . T_gettext('These bits of text are the contents that have to be inserted in the error message after translation. txt1 will replace $1, etc.') . "
<li>" . T_gettext('first_occurrence') . "</li>" . T_gettext('Timestamp (MESZ) of when this error was found the first time') . "
<li>" . T_gettext('last_checked') . "</li>" . T_gettext('Timestamp (MESZ) of last time this error was (re-)checked by the scripts') . "
<li>" . T_gettext('object_timestamp') . "</li>" . T_gettext('Timestamp of the object that was used when checking, as found in the official planet file.') . "
<li>" . T_gettext('user_name') . "</li>" . T_gettext('User name of the user that last edited the given object.') . "
<li>" . T_gettext('lat') . "</li>
<li>" . T_gettext('lon') . "</li>" . T_gettext('Location on the planet. Coordinates are given in the same projection as found in the official planet file. Please note that numbers are displayed as int values. To convert back to real lon/lat you have to divide by 10^7') . "
<li>" . T_gettext('comment') . "</li>" . T_gettext('User-comment (if any)') . "
<li>" . T_gettext('comment_timestamp') . "</li>" . T_gettext('Timestamp (MESZ) of when the comment was given (if any)') . "
</ul>
";

echo '<br><br><b>' . T_gettext('loading the errors table') . '</b><br><br>';


echo T_gettext('This is the schema definition for use with MySQL databases:') . "
<pre>
CREATE TABLE IF NOT EXISTS `keepright_errors` (
  `schema` varchar(6) NOT NULL default '',
  `error_id` int(11) NOT NULL,
  `error_type` int(11) NOT NULL,
  `error_name` varchar(100) NOT NULL,
  `object_type` enum('node','way','relation') NOT NULL,
  `object_id` bigint(64) NOT NULL,
  `state` enum('new','reopened','ignore_temporarily','ignore') NOT NULL,
  `first_occurrence` datetime NOT NULL,
  `last_checked` datetime NOT NULL,
  `object_timestamp` datetime NOT NULL,
  `user_name` text NOT NULL,
  `lat` int(11) NOT NULL,
  `lon` int(11) NOT NULL,
  `comment` text,
  `comment_timestamp` datetime,
  `msgid` text,
  `txt1` text,
  `txt2` text,
  `txt3` text,
  `txt4` text,
  `txt5` text
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
</pre>

<pre>
mysql --local-infile --password --user=root --execute \"LOAD DATA LOCAL INFILE 'keepright_errors.txt' INTO TABLE keepright_errors CHARACTER SET utf8 IGNORE 1 LINES;\" osm_EU 
</pre>
<p>";

echo T_gettext('Please note that <em>schema</em> is a reserved word in MySQL, so you always have to quote it like this: `schema`') . "</p>

<p>" . T_gettext('There are two primary keys in this table: a natural one and an artificial one:') . "<br>

" . T_gettext('The natural primary key consists of error_type, object_type, object_id, lat, lon. That means one type of error may be found on multiple spots belonging to one single object (eg. self-intersections of ways).') . "</br>

" . T_gettext('The artificial primary key consists of schema and error_id. It is used just for simplicity of referencing individual error instances and it is completely redundant.') . "</p>
<br><br><b>" .

T_gettext('querying node counts') . '</b><br><p>';

echo T_gettext('As a waste-product the scripts create a file that contains the numer of nodes per square degree found in the planet file. Resolution is 0.1 degrees. You may download the file here:');
echo " <a href='http://keepright.ipax.at/nodecount.txt.bz2'>http://keepright.ipax.at/nodecount.txt.bz2</a><br>";

echo T_gettext('This dump file can be useful for statistics if you want to calculate an `errors per node` measure') . "</p>

</body>
</html>";
?>