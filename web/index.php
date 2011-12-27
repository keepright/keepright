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
echo '<br>';

echo "
<a href='http://keepright.x10hosting.com' target='_blank'>" . T_gettext('Australian KeepRight partner site') . "</a><br>";

echo "<a href='report_map.php?zoom=14&lat=30.039&lon=31.25345'>";
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


printf(T_gettext("Developers have a look at the %sSourceForge%s site providing svn access to the sources."), "<a href='http://apps.sourceforge.net/trac/keepright/'>", '</a>');
echo "<br>";
printf(T_gettext("If you want to export errors from KeepRight you will want to have a look at the %sinterfacing guide%s"), "<a href='interfacing.php'>", '</a>');


echo '<h3>' . T_gettext('logfile') . '</h3>';

echo "<h4>2011-06-06</h4>";
echo T_gettext(
	"Currently there are some regions empty (no markers visible). " .
	"I'm working on the problem and try to restore all error markers " .
	"as soon as possible. It will take two days until everything will be fixed"
) . '<br>';


echo "<h4>2010-11-29</h4>";
echo T_gettext(
	"You'll notice some improvements on two checks and one completely new one " .
	"coming up during the next days:<br> <em>faintly connected roundabouts</em> " .
	"is a new sub-check for the roundabout check that will complain about " .
	"roundabouts with less than three connections to other roads. This need not " .
	"be an error, but at least it is questionable what faintly connected " .
	"roundabouts are built for<br> <em>language unknown</em> is a new warning-" .
	"type check that tries to improve localization by complaining about name tags " .
	"where the language code cannot be deferred from other tags. Thank you, Ed, " .
	"for the idea!<br> <em>motorways connected directly</em>-check had many false " .
	"positives at rest areas (these are service highways that intentionally don't " .
	"have an access restriction). I eliminated these using a heuristic method: " .
	"service roads are OK if they lead to a parking area, a fuel station, a " .
	"toilet or a restaurant. BTW: highway=services is something completely " .
	"different and should not be used on ways. Thank you, Johan, for making me " .
	"think about the problem again!"
) . '<br>';


echo "<h4>2010-06-20</h4>";
printf(T_gettext("There are a few more languages in the select box now. You can help translating KeepRight on the %slaunchpad site%s. Thanks to all who helped!"), "<a target='_new' href='https://translations.launchpad.net/keepright'>", '</a>');

echo "<h4>2010-06-08</h4>";
printf(T_gettext("To avoid routing errors like %sthis one%s I changed the check 'highways connected directly'. It will now complain about any connections of a motorway with a highway=service or highway=unclassified if it lacks an access=no or access=private tag or if it is not a service=parking_aisle.<br>I hope you agree that this inconvenience is necessary. Thank you, Nathan, for telling me about this!"), "<a target='_new' href='please_turn_left.png'>", '</a>');



echo '<br><br>';
printf(T_gettext('For archeologists: %sOld log entries%s have moved.'), "<a href='logs.php'>", '</a>');


echo '<br><br><h4>' . T_gettext('a few words on the new comment feature') . '</h4>';
echo T_gettext("Please give a comment that helps me improve the check routines if you find a false-positive. Don't confuse the comment box with an editing feature. This is not Potlatch! You cannot add missing tags via KeepRight!") . '<br><br>';


echo T_gettext("Please note that in order to make the JOSM link work, JOSM must already be running when you hit the JOSM-link. Furthermore you have to enable the &apos;remote control&apos;-plugin in JOSMs options.");

echo '<h3>' . T_gettext('Currently the following checking procedures are implemented') . '</h3>';




$db1=mysqli_connect($db_host, $db_user, $db_pass, $db_name);
mysqli_query($db1, "SET SESSION wait_timeout=60");

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