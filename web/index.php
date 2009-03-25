<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
    <title>data consistency checks for OSM</title>
  </head>
  <body>

<img src="keepright.png" alt="keep-right logo">data consistency checks for <a href="http://www.openstreetmap.org">OSM</a><hr>

This pages show checks that are run on a local excerpt database filled with OSM data. Checks are run on a database containing just the Europe part of the planet file for performance and memory reasons.
<br><br>
I'm proud to offer you two ways of accessing check results
<ul>
	<li><a href="report.php">Europe Data Checks as ugly list</a></li>
	<li><a href="report_map.php">Europe Data Checks painted on the map</a></li>
</ul>
By default you will be put into the center of Vienna.<br>

<h4>a few words on the new comment feature</h4>
Please give a comment that helps me improve the check routines if you find a false-positive. Don't confuse the comment box with an editing feature. This is not potlatch! You cannot add missing tags via keepright!

<h3>logfile</h3>
<h4>2009-03-21</h4>
An updated errors-table went online today! Planet dump was updated as of march 17th 2009.<br><br>

<h4>2009-03-14</h4>
An updated errors-table went online today! Planet dump was updated as of march 10th 2009.<br><br>

<h4>2009-03-10</h4>
Checks "missing tags" and "highway without ref tag" were redefined and relaxed to better fit real life: "missing tags" won't complain on multipolygon ways tagged as "role inner". "highway without ref tag" does now accept motorways as correctly tagged if any of ref, nat_ref or int_ref tags exist. This change removes 11.686 errors for "missing tags" and 97.054 for "highway without ref tag". Thank you, Ulf, Dermot and Jean-Luc!
<br><br>

<h4>2009-03-07</h4>
An updated errors-table went online today! Planet dump was updated as of march 2nd 2009. Due to an error in the scripts many errors were invisible up to now. Thank you, Hans, for the hint! <br><br>

<h4>2009-02-28</h4>
An updated errors-table went online today! Planet dump was updated as of feb. 22th 2009.<br><br>

<h4>2009-02-21</h4>
An updated errors-table went online today! Planet dump was updated as of feb. 17th 2009.<br><br>
Furthermore a completely rewritten user interface is in place now. This work evolved in cooperation with Hans, who provided valuable input and beta testing feedback. Thank you!<br>
For those who want to find out how much better the new interface is: You can still use the <a href="report_map1.php">old version</a>, but you should not.<br><br>
You may now mark errors as 'temporarily ignorable' if you just corrected the error and don't want other users to loose time with this issue. These errors will be removed from the map if they are indeed closed, otherwise they will jump back to open state during the next update.<br>If you find a false positive, please mark it as 'permanently ignorable' and please give a short comment that helps me improve my check routines!

<br><br>For archeologists: <a href="logs.php">Old log entries</a> have moved.


<h3>Currently the following checking procedures are implemented</h3>
<?php

require('webconfig.inc.php');
require('helpers.inc.php');

$db1=mysqli_connect($db_host, $db_user, $db_pass, $db_name);

$result=query("SELECT error_type, error_name, error_description FROM error_types ORDER BY error_type", $db1, false);
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

