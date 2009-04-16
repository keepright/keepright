<?php


$path_parts = pathinfo($_SERVER["SCRIPT_FILENAME"]);
$keepright_cfg=$path_parts['dirname'] . '/keepright.config';

if (isset($keepright_cfg) && isset($_POST['content']) && isset($_POST['save'])) {
	//echo "will save now";

	if (is_writeable($keepright_cfg)) {

		if (!$handle = fopen($keepright_cfg, 'w')) {

			echo "cannot open $keepright_cfg for writing\n";
			exit;
		} else {
			$content = stripslashes($_POST['content']);
			$content = strtr($content, array("\r\n" => "\n"));	// unixoid line endings, please!

			if (fwrite($handle, $content) === false) {

				echo "cannot write config file\n";
				exit;
			} else echo "file saved\n";
		}
	} else echo "config file $keepright_cfg is not writeable\n";
}


?>


<html><body>
<?php include('navigate.php'); ?>

<form method="post" action="configure.php">

<textarea name="content" rows="25" cols="90"><?php
    echo file_get_contents($keepright_cfg);
?></textarea>
<br><input type="submit" name="save" value="save"/>
</form>


</body></html>