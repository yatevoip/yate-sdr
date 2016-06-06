<?php

/* satsite.php
 * This file is part of the YATE Project http://YATE.null.ro
 *
 * JSON API handler for SatSite
 *
 * Yet Another Telephony Engine - a fully featured software PBX and IVR
 * Copyright (C) 2014-2015 Null Team
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

global $satsite_version;

@include_once("satsite_version.php");

if (!isset($satsite_version))
    $satsite_version = "unknown";

function satsiteHandler($request,$json,$recv,$node)
{
    global $satsite_version;

    if (("satsite" != $node) && (null !== $node) && ("" != $node))
	return null;
    switch ($request) {
	case "get_node_type":
	    return array("type" => "satsite", "version" => $satsite_version);
	case "get_version":
	    return buildSuccess("version",$satsite_version);
	case "set_bts_node":
	case "get_bts_node":
	    if ("satsite" != $node)
		return null;
	    return yateRequest(1051,"config_bts",$request,getParam($json,"params"),$recv);
	case "set_enb_node":
	case "get_enb_node":
	    return yateRequest(1051,"config_enb",$request,getParam($json,"params"),$recv);
    }
    return null;
}

return addHandler(satsiteHandler);

/* vi: set ts=8 sw=4 sts=4 noet: */
?>
