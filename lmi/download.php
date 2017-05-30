<?php
/**
 * download.php 
 * Makes api request for configs/logs and allow user to download file
 *
 * Copyright (C) 2017 Null Team
 */

require_once("ansql/lib.php");
require_once("defaults.php");
require_once("lib/lib_requests.php");
global $pysim_csv, $upload_path;

if (getparam("file")) {
	$filename = getparam("file");
	$path_file = $upload_path .$filename;
	$fp = @fopen($path_file,'rb');
} else {
	$method = getparam("method");

	if ($method == "config" || $method == "logs") {
		if ($method == "config") {
			$operation = "get_node_config";
			$file = "yateSDR_config.tgz";
			$content_type = "application/octet-stream";
			$request_params = array();
		} else {
			$operation = "get_node_logs";
			$file = "yateSDR_log.txt";
			$content_type = "text/plain";
			$request_params = array();
			if (getparam("level"))
				$request_params["level"] = getparam("level");
			if (getparam("lines"))
				$request_params["lines"] = getparam("lines");
		}

		$res = make_request($request_params, $operation);

		if ($res && is_array($res)) {

			$module = getparam("module");
			$err = "[API: ".$res["code"]."] " . $res["message"];
			Header("Location: main.php?module=".$module."&method=download_config_error&errormess=".$err);	
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
		$filename = str_replace($upload_path, "", $pysim_csv);
	}
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
