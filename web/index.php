<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
    <title>data consistency checks for OSM</title>
  </head>
  <body>

<img src="keepright.png" alt="keep-right logo">data consistency checks for <a href="http://www.openstreetmap.org">OSM</a><hr>

This pages show checks that are run on a local excerpt database filled with OSM data. Checks are run on a database containing just the Europe part of the planet file for performance and memory reasons.
<br><br>

<div style="border:thin solid #800000; background-color:#80FF80;">Please welcome the first Keepright partner site providing error checks for <a href="http://keepright.x10hosting.com" target="_blank">Australia</a><br><br>Want to become a partner too? Want to have a look into the sources behind keepright? Have a look at the <a href="http://apps.sourceforge.net/trac/keepright/">sourceforge</a> site providing svn access, a mailing list and a trac ticket request system</div>

<ul>
	<li><a href="report.php">Europe Data Checks as ugly list</a></li>
	<li><a href="report_map.php">Europe Data Checks painted on the map</a></li>
	<li><a href="http://keepright.x10hosting.com" target="_blank">Australian Keepright partner site</a></li>
</ul>
By default you will be put into the center of Vienna for the European version.<br>

<h4>a few words on the new comment feature</h4>
Please give a comment that helps me improve the check routines if you find a false-positive. Don't confuse the comment box with an editing feature. This is not potlatch! You cannot add missing tags via keepright!

<h3>logfile</h3>
<h4>2009-04-18</h4>
An updated errors-table went online today! Planet dump was updated as of april 14th 2009.<br><br>
The first keepright partner site is now online! Please visit <a href="http://keepright.x10hosting.com" target="_blank">keepright Australia</a>

<h4>2009-04-10</h4>
An updated errors-table went online today! Planet dump was updated as of april 7th 2009.<br><br>

<h4>2009-04-06</h4>
Just added an updated version of "dead-ended one-ways" errors. The check now includes motorways and motorway_links, which are regarded as one-way streets implicitly. Second there is a new part that searches for colliding one-way streets (one-ways pointing to a single node that cannot be left or from a single node that cannot be reached). Thank you, Ossi, for that hint!

<br><br>For archeologists: <a href="logs.php">Old log entries</a> have moved.


<h3>Currently the following checking procedures are implemented</h3>
<?php

require('webconfig.inc.php');
require('helpers.inc.php');

$db1=mysqli_connect($db_host, $db_user, $db_pass, $db_name);

$result=query("SELECT error_type, error_name, error_description FROM $error_types_name ORDER BY error_type", $db1, false);
while ($row = mysqli_fetch_array($result, MYSQL_ASSOC)) {
	echo '<p><b>' . $row['error_name'] . '</b><br>' . $row['error_description'] . '</p>';
}

mysqli_free_result($result);
mysqli_close($db1);
?>


<!-- <h3>Source files</h3>

If you like to try running data consistency checks by yourself you may <a href="keepright.tar.gz">download the source files</a>. Please refer to the README file for detailed installation instructions.<br><br> -->

If you find an error in my errors lists I would definitely like to hear about it!<br>


<h3>Impressum</h3>
This work is done without commercial background, just for my personal pleasure. I would be very happy if it was helpful for the OSM Project.<br><br>
If you like to contact me, my mailbox at the austrian server of gmx is labelled keepright.
Please understand that I will not always be able to immediately respond to your mail on weekdays.

</body>
</html>

