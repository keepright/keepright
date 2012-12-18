<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>

<?php
require('webconfig.inc.php');
require('helpers.inc.php');


echo "<head><title>" . T_gettext('data consistency checks for OSM') . "</title>";
echo '<script type="text/javascript" src="keepright.js"></script></head><body>';
echo "<img src='keepright.png' alt='" . T_gettext('keep-right logo') . "'>";

printf(T_gettext("data consistency checks for %sOSM%s"),
"<a href='http://www.openstreetmap.org'>", "</a>");

echo '<form name="myform" method="get" action="' . $_SERVER['PHP_SELF'] . '">';
echo '<div style="position:absolute; top:70px; right:10px;">'; language_selector(); echo '</div>';
echo "</form><hr>";

echo T_gettext('These pages show checks that are run on a local excerpt database filled with OSM data.');

echo "<br><br><a href='report_map.php?zoom=14&lat=48.20808&lon=16.37221'>";
printf(T_gettext('Data Checks for Europe%s (Starting point Vienna, Austria)'), "</a>");

echo "<br><a href='report_map.php?zoom=14&lat=-33.87613&lon=151.17154'>";
printf(T_gettext('Data Checks for Australia%s (Starting point Sydney)'), "</a>");

echo "<br><a href='report_map.php?zoom=14&lat=30.039&lon=31.25345'>";
printf(T_gettext('Data Checks for Africa%s (Starting point Cairo, Egypt)'), "</a>");

echo "<br><a href='report_map.php?zoom=12&lat=46.79923&lon=-71.19432'>";
printf(T_gettext('Data Checks for Canada%s (Starting point Québec)'), "</a>");

echo "<br><a href='report_map.php?zoom=12&lat=39.95356&lon=-75.12364'>";
printf(T_gettext('Data Checks for USA%s (Starting point Philadelphia, PA)'), "</a>");

echo "<br><a href='report_map.php?zoom=11&lat=18.61093&lon=-69.9473'>";
printf(T_gettext('Data Checks for Central America%s (Starting point Santo Domingo, Republica Dominicana)'), "</a>");

echo "<br><a href='report_map.php?zoom=14&lat=-23.58791&lon=-46.65713'>";
printf(T_gettext('Data Checks for South America%s (Starting point São Paulo, Brazil)'), "</a>");

echo "<br><a href='report_map.php?zoom=14&lat=35.68051&lon=139.76404'>";
printf(T_gettext('Data Checks for Asia%s (Starting point Tokio, Japan)'), "</a>");


echo "<br><br>";
printf(T_gettext("Developers have a look at the %sSourceForge%s site providing svn access to the sources."), "<a href='http://sourceforge.net/projects/keepright'>", '</a>');
echo "<br>";
printf(T_gettext("If you want to export errors from KeepRight you will want to have a look at the %sinterfacing guide%s"), "<a href='interfacing.php'>", '</a>');


echo '<h3>' . T_gettext('logfile') . '</h3>';


$db1=mysqli_connect($db_host, $db_user, $db_pass, $db_name);
mysqli_query($db1, "SET SESSION wait_timeout=60");

// fetch announcements from db
announcements($db1, 0);

echo '<br><br>';
printf(T_gettext('For archeologists: %sOld log entries%s have moved.'), "<a href='logs.php'>", '</a>');


echo '<br><br><h4>' . T_gettext('a few words on the new comment feature') . '</h4>';
echo T_gettext("Please give a comment that helps me improve the check routines if you find a false-positive. Don't confuse the comment box with an editing feature. This is not Potlatch! You cannot add missing tags via KeepRight!") . '<br><br>';


echo T_gettext("Please note that in order to make the JOSM link work, JOSM must already be running when you hit the JOSM-link. Furthermore you have to enable the &apos;remote control&apos;-plugin in JOSMs options.");

echo '<h3>' . T_gettext('Currently the following checking procedures are implemented') . '</h3>';




$result=query("
	SELECT error_type, error_name, error_description
	FROM $error_types_name
	WHERE hidden=0 AND error_type=10*FLOOR(error_type/10)
	ORDER BY error_type
", $db1, false);
while ($row = mysqli_fetch_array($result, MYSQL_ASSOC)) {
	echo '<p><b>' . T_gettext($row['error_name']) . '</b><br>' . T_gettext($row['error_description']) . '</p>';
}

mysqli_free_result($result);
mysqli_close($db1);



echo T_gettext('If you find an error in my errors lists I would definitely like to hear about it!') . '<br>';

echo '<h3>' . T_gettext('Impressum') . '</h3>';
echo T_gettext("This work is done without commercial background, just for my personal pleasure. I would be very happy if it was helpful for the OSM Project.") . '<br><br>' .
T_gettext('If you like to contact me, my mailbox at the austrian server of gmx is labelled KeepRight.') . '<br>' . T_gettext('Please understand that I will not always be able to immediately respond to your mail on weekdays.');

?>
</body></html>