<?php
/**
 * defaults.php
 * This file is part of the Yate-BTS Project http://www.yatebts.com
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

$proj_title = "YateSDR Local Management Interface";

$func_build_request_url = "build_request_url_for_api";
# yate cdr logs file
$yate_cdr = "/var/log/yate-cdr.csv";

$upload_path = "/var/log/lmi/";

# log dirs
$parse_errors = "/var/log/lmi/parse_errors.txt";
$logs_in = array("/var/log/lmi/ansql_logs.txt");

#working mode is ignored if set to true
$devel_mode = false;

#true to enable writing SIMs using PySIM
$pysim_mode = false;

# the file used by PySim to write the SIM credentials 
$pysim_csv = $upload_path . "sim_data.csv";

# type of card SIM used by PySIM. Types allowed: fakemagicsim, supersim, magicsim, grcardsim, sysmosim-gr1, sysmosim-gr1, sysmoSIM-GR2, sysmoUSIM-GR1 or try auto 
$sim_type = "sysmoSIM-GR2";
?>
