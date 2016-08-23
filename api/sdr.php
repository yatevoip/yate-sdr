<?php

/* sdr.php
 * This file is part of the YATE Project http://YATE.null.ro
 *
 * JSON API handler for SatSite
 *
 * Yet Another Telephony Engine - a fully featured software PBX and IVR
 * Copyright (C) 2015-2016 Null Team
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

@include_once("sdr_version.php");

if (!isset($sdr_version))
    $sdr_version = "unknown";

function sdrHandler($request,$json,$recv,$node)
{
    global $sdr_version;

    if (("sdr" != $node) && (null !== $node) && ("" != $node))
	    return null;

    switch ($request) {
	case "get_node_type":
	    //return array("type" => "sdr", "version" => $sdr_version);
	    $sdr_nodes = array(
			"bts" => "config_bts", 
			"enb" => "config_enb"
		);

	    $node_types = array();
	    foreach ($sdr_nodes as $nodename=>$node_script) {
		$node_response = yateRequest(1051,$node_script,$request,getParam($json,"params"),$recv,5,false);
		if (!isset($node_response["code"]) || !isset($node_response["node"]["sdr_mode"]) || $node_response["code"] != 0)
			continue;
		else
			$node_types[] = array("type"=>$nodename, "version" => $sdr_version, "sdr_mode"=>$node_response["node"]["sdr_mode"]);
	    }
	    if (!count($node_types))
		return array();

	    return $node_types;
	case "get_version":
	    return buildSuccess("version",$sdr_version);
	case "set_bts_node":
	case "get_bts_node":
	case "set_nib_subscribers":
	case "get_nib_subscribers":
	case "delete_nib_subscriber":
	case "set_nib_system":
	case "get_nib_system":
	case "get_online_nib_subscribers":
	case "get_rejected_nib_subscribers":
	case "set_nib_outbound":
	case "get_nib_outbound":
	case "set_nib_cdrfile":
	case "get_nib_cdrfile":
	    if ("sdr" != $node)
		return null;
	    return yateRequest(1051,"config_bts",$request,getParam($json,"params"),$recv);
	case "set_enb_node":
	case "get_enb_node":
	    if ("sdr" != $node)
	        return null;	    
	    return yateRequest(1051,"config_enb",$request,getParam($json,"params"),$recv);
	case "set_sdr_mode":
	    // try find sending it to bts_config, if no answer, try sending it to enb_config
	    $res = yateRequest(1051,"config_bts",$request,getParam($json,"params"),$recv,5,false);
	    if ($res["code"] != 200)
		return $res;	    
	    return yateRequest(1051,"config_enb",$request,getParam($json,"params"),$recv);
    }
    return null;
}

return addHandler(sdrHandler);

/* vi: set ts=8 sw=4 sts=4 noet: */
?>
