<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
    <title>data consistency checks for OSM</title>
  </head>
  <body>

<img src="keepright.png" alt="keep-right logo">data consistency checks for <a href="http://www.openstreetmap.org">OSM</a><hr>

These pages show checks that are run on a local excerpt database filled with OSM data.
<br><br>
<!--
<div style="border:thin solid #800000; background-color:#80FF80;">Please welcome the first Keepright partner site providing error checks for <a href="http://keepright.x10hosting.com" target="_blank">Australia</a><br><br>Want to become a partner too? Want to have a look into the sources behind keepright? Have a look at the <a href="http://apps.sourceforge.net/trac/keepright/">sourceforge</a> site providing svn access, a mailing list and a trac ticket request system</div>-->
<a href="report_map.php?db=osm_EU&zoom=14&lat=48.20808&lon=16.37221">Data Checks for Europe</a> (Starting point Vienna, Austria)<br>
<a href="http://keepright.x10hosting.com" target="_blank">Australian Keepright partner site</a><br>

<a href="report_map.php?db=osm_XA&zoom=14&lat=30.039&lon=31.25345">Data Checks for Africa</a> (Starting point Cairo, Egypt)<br>
<a href="report_map.php?db=osm_XC&zoom=14&lat=-23.58791&lon=-46.65713">Data Checks for South America</a> (Starting point São Paulo, Brazil)<br>
<a href="report_map.php?db=osm_XD&zoom=14&lat=35.68051&lon=139.76404">Data Checks for Asia</a> (Starting point Tokio, Japan)<br>
<br>
<a href="report.php">Europe Data Checks as ugly list</a><br>

<p>North America is still missing in the list. Volunteers wanting to run the scripts on their powerful machine are welcome to join!</p>
<br>
Developers have a look at the <a href="http://apps.sourceforge.net/trac/keepright/">sourceforge</a> site providing svn access to the sources.

<h3>logfile</h3>
<h4>2009-08-13</h4>
After a long discussion about the layers check I just removed the &quot;not so obvious&quot; part of the check. 
from now on, this is (again) correct:
<pre>
way A, layer 0	|
 		|           way B, layer 1
		|
 		*------------------------------------
		|
		|

</pre>
Intersections of ways on different layers are still not OK, if way A and B share a common node and intersect at intermediate nodes:
<pre>
 way A, layer 0	|
 		|           way B, layer 1
		|
 	--------*------------------------------------
		|
		|
</pre>
sorry for the confusion and thanks for the discussion!

<br><br>
<h4>2009-08-03</h4>
I just published a minor update that cleans up the levels check and the motorways check:<br>
Not only highways but also landuse ways were included in the layers conflict check. This was not intended and is now fixed. Thank you, Norbert, for the hint!<br>Motorways that are continued by highway=trunk roads are not reported as error any more as this seems to be a common case. Thank you, Jean-Luc, for the hint!<br><br>

<h4>2009-08-01</h4>
There was some discussion going on about the layers check without a clear conclusion (at least for me). So please take the layers check as a warning, not as an error. It&apos;s just saying that there are ways on different layers that are connected. Please decide on your own, if that&apos;s OK.<br><br>
There is a new check that looks for motorways that are connected to other ways by accidant (this easily happens at bridges crossing motorways). Again, this check is not always right. Motorways that end somewhere may continue in a primary road.

<br><br>

<h4>2009-07-25</h4>
With today's update I introduce a new check that looks for ways that are connected but reside on different layers. There seems to be a difference between what the <a href="http://wiki.openstreetmap.org/wiki/Tunnel" target="_blank">wiki says</a> and what's <a href="http://www.openstreetmap.org/?zoom=18&lat=48.20244&lon=16.40252" target="_blank">common practice</a>.<br>
So I split the check in cases where ways intersect on in-between-nodes ("obviously wrong") and ways that start in a common node ("not so obvious").<br>
Thank you, Christian, for the idea to this check!

<br><br>

<h4>2009-07-21</h4>
There is a new link that will bring you immediately back to the error you're visiting at the moment. Find the link at the bottom of the error bubble. Thank you, Rejo, for the suggestion!
<br><br>

<h4>2009-07-17</h4>
An updated errors-table went online today! Planet dump was updated as of july 13th 2009.<br>
During the last three weeks some errors were not updated correctly because of my wrong use of <tt>wput</tt>. This is now resolved.


<br><br>For archeologists: <a href="logs.php">Old log entries</a> have moved.


<br><br>
<h4>a few words on the new comment feature</h4>
Please give a comment that helps me improve the check routines if you find a false-positive. Don't confuse the comment box with an editing feature. This is not potlatch! You cannot add missing tags via keepright!
<br><br>
Please note that in order to make the JOSM link work, JOSM must already be running when you hit the JOSM-link. Furthermore you have to enable the &apos;remote control&apos;-plugin in JOSMs options.

<h3>Currently the following checking procedures are implemented</h3>
<?php

require('webconfig.inc.php');
require('helpers.inc.php');

$db1=mysqli_connect($db_host, $db_user, $db_pass, $db_name);
mysqli_query($db1, "SET SESSION wait_timeout=60");

$result=query("
	SELECT error_type, error_name, error_description
	FROM $error_types_name 
	WHERE error_type=10*FLOOR(error_type/10)
	ORDER BY error_type
	", $db1, false);
while ($row = mysqli_fetch_array($result, MYSQL_ASSOC)) {
	echo '<p><b>' . $row['error_name'] . '</b><br>' . $row['error_description'] . '</p>';
}

mysqli_free_result($result);
mysqli_close($db1);
?>



If you find an error in my errors lists I would definitely like to hear about it!<br>


<h3>Impressum</h3>
This work is done without commercial background, just for my personal pleasure. I would be very happy if it was helpful for the OSM Project.<br><br>
If you like to contact me, my mailbox at the austrian server of gmx is labelled keepright.
Please understand that I will not always be able to immediately respond to your mail on weekdays.

</body>
</html>

