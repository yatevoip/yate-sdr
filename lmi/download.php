<?php
require_once("ansql/lib.php");
require_once("defaults.php");
require_once("lib/lib_requests.php");
global $pysim_csv, $yate_conf_dir;

if (getparam("file")) {
	$filename = getparam("file");
	$path_file = $yate_conf_dir .$filename;
	$fp = @fopen($path_file,'rb');
} else {
	$method = getparam("method");

	if ($method == "config") {
		$operation = "get_node_config";
		$file = "yateSDR_config.tgz";
		$content_type = "application/octet-stream";
		$request_params = array();

		$res = make_request($request_params, $operation);

		if ($res && is_array($res)) {
			errormess("[API: ".$res["code"]."]".$res["message"]);
			exit();
		}

		header("Cache-Control:  maxage=1");
		header("Pragma: public");
		header("Content-type:$content_type");
		header("Content-Disposition:attachment;filename=$file");
		header("Content-Description: PHP Generated Data");
		header("Content-Transfer-Encoding: binary");
		header('Content-Length' . filesize());
		ob_clean();
		print $res;
		exit;

	} else {							                

		$fp = @fopen($pysim_csv, 'rb');
		$filename = str_replace($yate_conf_dir, "", $pysim_csv);
	}
}
header('Content-Type: "application/octet-stream"');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header("Content-Transfer-Encoding: binary");
header('Expires: 0');
header('Pragma: no-cache');
if (getparam("file"))
	header("Content-Length: ".filesize($yate_conf_dir .getparam("file")));
else
	header("Content-Length: ".filesize($pysim_csv));

fpassthru($fp);
fclose($fp);
?>
