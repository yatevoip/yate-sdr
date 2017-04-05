<?php
/**
 * subscribers.php
 * This file is part of the Yate-SDR Project http://www.yatebts.com
 *
 * Copyright (C) 2014 - 2016 Null Team
 *
 * This software is distributed under multiple licenses;
 * see the COPYING file in the main directory for licensing
 * information for this specific distribution.
 *
 * This use of this software may be subject to additional restrictions.
 * See the LEGAL file in the main directory for details.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */


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
	global $server_name,$request_protocol;

	if (!isset($request_protocol))
		$request_protocol = "http";

	$out = array("request"=>$request,"node"=>"sdr","params"=>$out);
	$url = "$request_protocol://$server_name/api.php";
	return $url;
}

function request_api($out, $request=null, $response_field=null, $err_cb=null)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"api request");

	global $method, $action, $accept_loop, $parse_errors, $func_build_request_url, $retry_after_restart;

	$res = make_request($out,$request);
	if ($res["code"]<0) {
		// library generated error. Port this to project error standard
		$res["code"] = $res["code"] * -300;
	}
        
        $url = $func_build_request_url($out,$request);

	$error = false;
	if ($res["code"]!="0") {
                write_error($request, $out, "", "", $url, $res);
		
		// This error happens if Yate was just restarted and user switched to a new tab
		// If this happens wait 5 seconds then try again before returning error to use
		$retriable = "Cannot connect to Yate on port";
		if (!isset($retry_after_restart) && $res["code"]=="200" && substr($res["message"],0,strlen($retriable))==$retriable) {
			sleep(5);
			$retry_after_restart = true;
			return request_api($out, $request, $response_field, $err_cb);
		} elseif (isset($retry_after_restart)) {
			unset($retry_after_restart);
		}
		
		errormess("[API: ".$res["code"]."] ".$res["message"]. " Full response in $parse_errors.","no");
		$error = true;
	} elseif ($response_field && !isset($res[$response_field])) {
		write_error($request, $out, "", "", $url, $res);
		errormess("Could not retrieve $response_field from api response.". " Full response in $parse_errors.","no");
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
