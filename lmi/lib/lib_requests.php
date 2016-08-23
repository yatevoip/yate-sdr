<?php
require_once("ansql/use_json_requests.php");

/**
 * Clasify type of error from cron/api
 */
function get_type_error($code)
{
	$start_code = substr($code,0,1);
	if ($start_code=="4")
		return "fatal_usage";
	elseif ($start_code=="5")
		return "fatal";
	elseif ($start_code=="2")
		return "retry_once";
	else
		return "retry";
}

/**
 * Beautify error category to be displayed in the interface
 */
function beautify_type_error($type)
{
	switch ($type) {
		case "retry":
			return "Retriable";
		case "retry_once":
			return "Retriable once";
		case "fatal_usage":
			return "Fatal/need correction";
		case "fatal":
			return "Fatal/need maintenance";
		default:
			return ucwords($type);
	}
}

function build_request_url_for_api(&$out,&$request)
{
	global $server_name,$request_protocol,$node;

	if (!isset($request_protocol))
		$request_protocol = "http";

	$out = array("request"=>$request,"node"=>"sdr","params"=>$out);
	$url = "$request_protocol://$server_name/api.php";
	return $url;
}

function request_api($out, $request=null, $response_field=null, $err_cb=null)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"api request");

	global $method, $action, $accept_loop;

	$res = make_request($out,$request);
	if ($res["code"]<0) {
		// library generated error. Port this to project error standard
		$res["code"] = $res["code"] * -300;
	}

	$error = false;
	if ($res["code"]!="0") {
		errormess("[".$res["code"]."] ".$res["message"],"no");
		$error = true;
	} elseif ($response_field && !isset($res[$response_field])) {
		errormess("Could not retrieve $response_field from api response.","no");
		//$errormess = $res["message"];
		$error = true;
	}
	if ($error) {
		if ($err_cb) {
			if ($err_cb==$method && !$action && !isset($accept_loop))
				print errormess("Loop prevention: didn't make callback to $err_cb.","no");
			else
				$err_cb(false);
		}
		if ($res["code"] && get_type_error($res["code"]) == "fatal") 
			Debug::trigger_report('api_usage', "Could not retrieve $response_field from api response. res=".print_r($res,true));
		print "</td></tr></table></body></html>";
		exit();
	}

	unset($res["code"]);
	unset($res["message"]);
	if ($response_field)
		return $res[$response_field];
	elseif (count($res))
		return $res;
	return true;
}

?>
