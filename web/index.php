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

echo "<br><br><a href='report.php'>" . T_gettext('Europe Data Checks as ugly list') . "</a><br><br>";

printf(T_gettext("Developers have a look at the %sSourceForge%s site providing svn access to the sources."), "<a href='http://apps.sourceforge.net/trac/keepright/'>", '</a>');


echo '<h3>' . T_gettext('logfile') . '</h3>';

echo "<h4>2010-06-05</h4>";
printf(T_gettext("To avoid routing errors like %sthis one%s I changed the check 'highways connected directly'. It will now complain about any connections of a motorway with a highway=service or highway=unclassified if it lacks an access=no or access=private tag or if it is not a service=parking_aisle.<br>I hope you agree that this inconvenience is necessary. Thank you, Nathan, for telling me about this!"), "<a target='_new' href='please_turn_left.png'>", '</a>');


echo '<br><br>';
echo "<h4>2010-05-24</h4>";
echo '<b>' . T_gettext("KeepRight becomes multilingual! - KeepRight wird mehrsprachig! - O KeepRight se tornou multilíngue!") . '</b>';

echo '<br>' . strtr(T_gettext(
"As a start KeepRight may be used in english, german and brazilian portugese. New translations are welcome! The gettext template file is $1right here$2. Special thanks go to Rodrigo for this great idea!<br><br>Zunächst gibt es KeepRight auf deutsch, englisch und brasilianischem Portugiesisch. Weitere Sprachen sind willkommen! Das gettext-Template gibt es $1hier$2. Vielen Dank an Rodrigo für diese großartige Idee!<br><br>Como início, o KeepRight pode ser usado em inglês, alemão e Portuguẽs Brasileiro. Novas traduções são bem vindas! O arquivo com o modelo para gettext está  $1bem aqui$2. Agradecimentos especiais para o usuário Rodrigo, por esta grande ideia!"),
array('$1'=>'<a href="locale/keepright.pot">', '$2'=>'</a>')
);

echo "<h4>2010-04-17</h4>";
printf(T_gettext("If you want to export errors from KeepRight you will want to have a look at the %sinterfacing guide%s"), "<a href='interfacing.php'>", '</a>');

echo "<h4>2010-04-05</h4>";
echo T_gettext("Getting rid of the evil db-parameter") . '<br>' .
T_gettext("You may have noticed that you couldn&apos;t pan around the whole world in KeepRight as you liked. There were invisible boundaries and you always had to take care about the db-parameter in the URLs to be appropriate for the current position.<br>This is over now! The db-parameter is gone. So have fun panning around the whole world and fixing errors easier than ever!");

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
	WHERE error_type=10*FLOOR(error_type/10)
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