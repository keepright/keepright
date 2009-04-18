<html><body>
<?php include('navigate.php'); ?>

<h3>update source code</h3>
<?php
	if (isset($_POST['svn_up'])) {
		echo '<pre>';
		system('/usr/bin/svn up 2>&1 | tee -a log');
		echo '</pre>';
	}
?>
<form name="svn_up" target="update.php" method="post">
	<input type="submit" name="svn_up" value="&gt; svn up">
</form>



<h3>update database and run checks</h3>
<?php

	if (isset($_POST['updateDB']) && isset($_POST['isocode']) && strlen($_POST['isocode'])==2) {
		echo '<pre>';
		system('updateDB.sh ' . $_POST['isocode'] . ' 2>&1 | tee -a log');
		echo '</pre>update started. Please view the logs for results.';
	}
?>
<form name="updateDB" target="update.php" method="post">
	<input type="submit" name="updateDB" value="&gt; updateDB.sh">
	<input type="text" name="isocode" size="2" value="EU">
</form>

</body></html>