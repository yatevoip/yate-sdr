<?php
/**
 * main.php
 * This file is part of the Yate-BTS Project http://www.yatebts.com
 *
 * Copyright (C) 2014 Null Team
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

require_once("lib/lib_proj.php");
set_timezone();
require_once("ansql/set_debug.php");
require_once("ansql/lib.php");
require_once("ansql/lib_files.php");
require_once("lib/menu.php");
require_once("lib/lib_requests.php");

$module = NULL;
$method = NULL;

$dir = $level = "default";

$module = (!$module) ? getparam("module") : $module;
$action = getparam("action");
$method = getparam("method");

if (!$module) {
	if (!in_array($method, $management_requests))
		$module = "working_mode";
	else
		$module = "general";
}

if ($module == "bts_configuration") {
	// Parameters used in MODULE: BTS Configuration 
	$section = (isset($_SESSION["BTS"]["section"])) ? $_SESSION["BTS"]["section"] : 'Radio';
	$subsection = (isset($_SESSION["BTS"]["subsection"])) ? $_SESSION["BTS"]["subsection"]  :'gsm';
} else
	unset($_SESSION["BTS"]);

if ($module == "enb_configuration") {
	// Parameters used in MODULE: ENB Configuration 
	$section = (isset($_SESSION["ENB"]["section"])) ? $_SESSION["ENB"]["section"] : 'Radio';
	$subsection = (isset($_SESSION["ENB"]["subsection"])) ? $_SESSION["ENB"]["subsection"]  :'eNodeB';
} else
	unset($_SESSION["ENB"]);

if ($method == "manage")
        $method = $module;

$page   = (!getparam("page")) ? 0 : getparam("page");

$_SESSION["limit"] = (isset($_SESSION["limit"])) ? $_SESSION["limit"] : 20;
$limit             = (getparam("limit")) ? getparam("limit") : $_SESSION["limit"];
$_SESSION["limit"] = $limit;
$_SESSION["level"] = $level;

$_SESSION["main"] = "main.php";
if ($module == "subscribers") {
	save_page_info();
} elseif (isset($_SESSION["previous_page"])) {
	unset($_SESSION["previous_page"]);
}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd"> 
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
<head>
<title><?php print $proj_title; ?></title>
<link type="text/css" rel="stylesheet" href="css/main.css" />
<link rel="icon" type="image/ico" href="images/favicon.ico">
    <meta name="viewport" content="initial-scale=1.0, user-scalable=no" />
    <meta http-equiv="content-type" content="text/html; charset=UTF-8"/>
<script type="text/javascript" src="ansql/javascript.js"></script>
<script type="text/javascript" src="javascript.js"></script>
</head>
<body class="mainbody">
<?php   get_content(); ?>
</body>
</html>
