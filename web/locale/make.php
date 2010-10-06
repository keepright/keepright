<?php



$items = glob('*.po');

foreach ($items as $filename) {
	//echo $filename . ' ' ;
	$path_parts = pathinfo($filename);

	$dir = $path_parts['filename'] . '/LC_MESSAGES';

	$cmd = "msgfmt -o $dir/keepright.mo $filename";
	echo "$cmd\n";

	if (!is_dir($dir)) mkdir($dir, 0777, true);
	system($cmd);

}




?>