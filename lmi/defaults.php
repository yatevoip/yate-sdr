<?php
/**
 * defaults.php
 * This file is part of the Yate-BTS Project http://www.yatebts.com
 *
 * Copyright (C) 2014-2017 Null Team
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

$proj_title = "YateLMI - Local Management Interface";

$limit = 20;  //max number of objects to display on page

$func_build_request_url = "build_request_url_for_api";
$func_handle_headers = "handle_api_header";
# yate-sdr cdr logs file
$yate_cdr = "/var/log/yate-sdr-cdr.csv";

$dirs = array("/etc/yate/sdr/", "/usr/local/etc/yate/");
foreach ($dirs as $pos_dir) {
	if (is_dir($pos_dir)) 
		$yate_conf_dir = $pos_dir;
	if (is_readable($pos_dir) && is_writable($pos_dir))
		break;
}

$yate_ip = "127.0.0.1";
$server_name = $yate_ip;

$default_ip = "tcp://".$yate_ip;
$default_port = '5037';

// openvpn interface name
$openvpn_interface = "tun0";

# log dirs
$parse_errors = "/var/log/lmi/parse_errors.txt";
$logs_in = array("/var/log/lmi/ansql_logs.txt");

// where to upload files
$upload_path = "/var/lib/lmi/upload/";

#working mode is ignored if set to true
$devel_mode = false;

#true to enable writing SIMs using PySIM
$pysim_mode = false;

# the file used by PySim to write the SIM credentials 
if (isset($yate_conf_dir))
	$pysim_csv = $yate_conf_dir . "sim_data.csv";

# type of card SIM used by PySIM. Types allowed: fakemagicsim, supersim, magicsim, grcardsim, sysmosim-gr1, sysmosim-gr1, sysmoSIM-GR2, sysmoUSIM-GR1 or try auto 
$sim_type = "sysmoSIM-GR2";

$debug_notify = array("mail" => array("supportmmi@null.ro"));

// don't run htmlentites on all fields set in $_POST,$_GET,$_REQUEST. If missing or true, htmlentites will be run on all fields when lib.php is included
$htmlentities_onall = false;

// list of methods that should work even when Yate is stopped
$management_requests = array("manage_license", "form_bug_report", "view_log", "config", "restart_node", "license_update", "license_receipt" );
?>
