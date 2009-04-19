<html>
<!-- 
<head>
	<meta http-equiv="refresh" content="5; URL=logs.php">
</head>
-->
<body>
<?php include('navigate.php'); ?>

<pre>
	<?php

	if (file_exists('log'))
		echo file_get_contents('log');
	else
		echo "no log file present";

	?>
</pre>

</body></html>