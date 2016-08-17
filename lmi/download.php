<?php
require_once("ansql/lib.php");
require_once("defaults.php");
global $pysim_csv, $upload_path;

if (getparam("file")) {
	$filename = getparam("file");
	$path_file = $upload_path .$filename;
	$fp = @fopen($path_file,'rb');
} else {
	$fp = @fopen($pysim_csv, 'rb');
	$filename = str_replace($upload_path, "", $pysim_csv);
}
header('Content-Type: "application/octet-stream"');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header("Content-Transfer-Encoding: binary");
header('Expires: 0');
header('Pragma: no-cache');
if (getparam("file"))
	header("Content-Length: ".filesize($upload_path .getparam("file")));
else
	header("Content-Length: ".filesize($pysim_csv));

fpassthru($fp);
fclose($fp);
?>
