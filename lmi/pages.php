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
require_once "ansql/use_json_requests.php";
require_once "ansql/lib.php";
require_once "lib/lib_requests.php";
require_once "ansql/sdr_config/create_radio_band_select_array.php";

session_start();
$method = getparam("method");

if (call_user_func($method) === FALSE)
	    Debug::trigger_report("critical","Method requested from pages was not found: ".$method);

function node_status_json()
{
	$res = node_status();
	print json_encode($res);
}

function get_selected_band()
{
	$radio_band = getparam("radio_band");
	$_SESSION["Radio.Band"] = $radio_band;
	$band_options = prepare_gsm_field_radio_c0();
	foreach ($band_options as $k=>$option) {
		$value = $option["Radio.C0_id"];
		$style = "";
		if ($value=="__disabled")
			$style = "disabled=\"disabled\"";

		print '<option '.$style.' value="'.$value.'">'.$option["Radio.C0"].'</option>';
	}
}
