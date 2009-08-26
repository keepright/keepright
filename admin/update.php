<html><body>
<?php include('navigate.php'); ?>

<h3>update source code</h3>
<?php
	if (isset($_POST['svn_up'])) {
		echo '<pre>';
		system("sudo -u $ADMIN_USERNAME /usr/bin/svn up .. --non-interactive 2>&1 | tee -a permalog");
		echo '</pre>';
	}
?>
<form name="svn_up" action="update.php" method="post">
	<input type="submit" name="svn_up" value="&gt; svn up">
</form>



<h3>update database and run checks</h3>
<?php

	if (isset($_POST['updateDB']) && isset($_POST['isocode']) && strlen($_POST['isocode'])<=4) {
		echo '<pre>';
		echo system("sudo -u $ADMIN_USERNAME " . dirname($_SERVER['SCRIPT_FILENAME']) . "/updateDB.sh " . $_POST['isocode'] . '  2>&1 | tee log | tee -a permalog &');
		echo '</pre>';
	}
?>
<form name="updateDB" action="update.php" method="post">
	<input type="submit" name="updateDB" value="&gt; updateDB.sh">
	<input type="text" name="isocode" size="4" value="EU">
</form>

</body></html>