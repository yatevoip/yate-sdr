<?php
/**
 * Copyright (C) 2017 Null Team
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

/**
 * pages.php
 * Contains functions called using XMLHttpRequest object from javascript.js
 */ 
require_once("ansql/use_json_requests.php");
require_once("ansql/lib.php");
require_once("lib/lib_requests.php");

$method = getparam("method");

if (call_user_func($method) === FALSE)
	    Debug::trigger_report("critical","Method requested from pages was not found: ".$method);

function node_status_json()
{
	$res = node_status();
	print json_encode($res);
}
