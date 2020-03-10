<?php

/* sdr.php
 * This file is part of the YATE Project http://YATE.null.ro
 *
 * JSON API handler for Yate based Software Defined Radio products
 *
 * Yet Another Telephony Engine - a fully featured software PBX and IVR
 * Copyright (C) 2015-2017 Null Team
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

global $sdr_version;
global $bts_version;
global $enb_version;

@include_once("sdr_version.php");
@include_once("bts_version.php");
@include_once("enb_version.php");

if (!isset($sdr_version))
    $sdr_version = "unknown";
if (!isset($bts_version))
    $bts_version = $sdr_version;
if (!isset($enb_version))
    $enb_version = $sdr_version;

function getFreqError()
{
    $res = shell_exec("(LANG=C /usr/bin/chronyc -n tracking || LANG=C /usr/sbin/ntpdc -n -c sysinfo)"
	. " 2>/dev/null | /usr/bin/sed -n 's/^\\(Residual\\|stability\\).*: *\\([^ ].\\+\\)/\\2/p'");
    if (null !== $res)
	$res = trim($res);
    return ("" != $res && null !== $res)
	? buildSuccess("freq_error",$res)
	: buildError(501,"Cannot retrieve system frequency error.");
}

function sdrHandler($request,$json,$recv,$node)
{
    global $sdr_version;
    global $bts_version;
    global $enb_version;

    if (("sdr" != $node) && (null !== $node) && ("" != $node))
	    return null;

    switch ($request) {
	case "get_node_type":
	    //return array("type" => "sdr", "version" => $sdr_version);
	    $sdr_nodes = array(
			"bts" => array("type" => "config_bts", "version" => $bts_version),
			"enb" => array("type" => "config_enb", "version" => $enb_version)
		);

	    $node_types = array();
	    foreach ($sdr_nodes as $nodename=>$info) {
		$node_response = yateRequest(1049,$info["type"],$request,getParam($json,"params"),$recv,5,false);
		if (!isset($node_response["code"]) || !isset($node_response["node"]["sdr_mode"]) || $node_response["code"] != 0)
			continue;
		else
			$node_types[] = array("type"=>$nodename, "version" => $info["version"], "sdr_mode"=>$node_response["node"]["sdr_mode"]);
	    }
	    $node_types[] = array("type"=>"sdr", "version" => $sdr_version);
	    return $node_types;
	case "get_version":
	    return buildSuccess("version",$sdr_version);
	case "get_freq_error":
	    return getFreqError();
	case "set_bts_node":
	case "get_bts_node":
	case "set_nipc_subscribers":
	case "get_nipc_subscribers":
	case "delete_nipc_subscriber":
	case "set_nipc_system":
	case "get_nipc_system":
	case "get_online_nipc_subscribers":
	case "get_accepted_nipc_subscribers":
	case "get_rejected_nipc_subscribers":
	case "set_nipc_outbound":
	case "get_nipc_outbound":
	case "set_nipc_cdrfile":
	case "get_nipc_cdrfile":
	    if ("sdr" != $node)
		return null;
	    return yateRequest(1049,"config_bts",$request,getParam($json,"params"),$recv);
	case "set_enb_node":
	case "get_enb_node":
	case "get_bands":
	    if ("sdr" != $node)
	        return null;
	    return yateRequest(1049,"config_enb",$request,getParam($json,"params"),$recv);
	case "set_sdr_mode":
	    // try find sending it to bts_config, if no answer, try sending it to enb_config
	    $res = yateRequest(1049,"config_bts",$request,getParam($json,"params"),$recv,5,false);
	    if ($res["code"] != 200)
		return $res;
	    return yateRequest(1049,"config_enb",$request,getParam($json,"params"),$recv);
	case "query_stats":
	case "get_loggers":
	case "get_logging":
	case "set_logging":
	    if ("sdr" != $node)
	        return null;
	    // fall through
	case "get_available_modes":
	case "calibrate_start":
	case "calibrate_poll":
	    // No need to check node: calibration will be handled if loaded
	    return yateRequest(1049,"control",$request,getParam($json,"params"),$recv);
	case "get_node_status":
	    if ("sdr" != $node)
	        return null;
	    $res = yateRequest(1049,"control",$request,getParam($json,"params"),$recv);
	    if ("unknown" != $bts_version)
		$res["bts_version"] = $bts_version;
	    if ("unknown" != $enb_version)
		$res["enb_version"] = $enb_version;
	    return $res;
	case "troubleshoot":
	    exec("sudo /var/www/html/api_asroot.sh troubleshoot sdr",$res,$out);
	    $err_msg = implode("\n", $res);
	    if ($out!==0) {
		return buildError(501, $err_msg);
	    }
	    return array("code"=>"0", "response"=>$res);
        case "get_ntpd_status":
	    exec("sudo /var/www/html/api_asroot.sh ntpd_status sdr",$res,$out);
	    if ($out!==0) {
		$err_msg = implode("\n", $res);
 		return buildError(501,$err_msg);
	    }
	    $service = implode("\n",$res);
	    if ($res[0] == "Chrony Service is running") {
		$operational = true;
		$state = "Running";
		if ($res[1] == "Skew is in the acceptable range") {
		    $level = "NOTE";
		} else {
		    $level = "MILD";
 		}
	    } else {
		$operational = false;
		$level = "WARN";
		$state = "Stopped";
	    }
	    return array("code"=>"0","status"=>array("operational"=>$operational,"level"=>$level,"state"=>$state,"service"=>$service));
	case "get_openvpn_status":
	    exec("sudo /var/www/html/api_asroot.sh openvpn_status sdr",$res,$out);
	    if ($out!==0) {
		$err_msg = implode("\n", $res) ;
		return buildError(501,$err_msg);
	    }
	    if ($res[0] == "Running") {
		$state = "Running";
		$service = "Service is running";
		$operational = true;
		$level = "NOTE";
	    } else {
		$state = "Stopped";
		$service = "Service is not running";
		$operational = false;
		$level = "WARN";
	    }
	    exec("sudo /var/www/html/api_asroot.sh openvpn_usage sdr",$res2,$out2);
	    if ($out2!==0) {
		$err_msg = implode("\n", $res2);
		return buildError(501,$err_msg);
	    }
	    if ($state == "Stopped") {
		if ($res2[0] == "no") {
		    $service = "Service is not used";
		    $state = "Not used";
		    $operational = true;
		    $level = "MILD";
		}
	    }
	    return array("code"=>"0","status"=>array("operational"=>$operational,"level"=>$level,"state"=>$state,"service"=>$service));
    }
    return null;
}

return addHandler(sdrHandler);

/* vi: set ts=8 sw=4 sts=4 noet: */
?>
