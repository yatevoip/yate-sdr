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
	$meth = getparam("meth");
	if (getparam("extra") == "true") {
                $extra = array("bts_version", "enb_version");
        }
        
        $res = (isset($extra)) ? node_status(null, null, $extra) : node_status(null, $meth); 
        
        if (isset($extra)) {
                $_SESSION["version"] = array();
                if (isset($res["version"]))
                        $_SESSION["version"]["sdr"] = $res["version"];
                if (isset($res["bts_version"]))
                        $_SESSION["version"]["bts"] = $res["bts_version"];
                if (isset($res["enb_version"]))
                        $_SESSION["version"]["enb"] = $res["enb_version"];
        }
        print json_encode($res);
}

function get_selected_band()
{
	$radio_band = getparam("radio_band");
	$_SESSION["Radio.Band"] = $radio_band;
	$band_options = prepare_gsm_field_radio_c0();
	print '<option value="">Not selected</option>';
	foreach ($band_options as $k=>$option) {
		$value = $option["Radio.C0_id"];
		$style = "";
		if ($value=="__disabled")
			$style = "disabled=\"disabled\"";

		print '<option '.$style.' value="'.$value.'">'.$option["Radio.C0"].'</option>';
	}
}

function get_version()
{
	if (!empty($_SESSION["version"])) {
		// return version of APIs instead of that of interface
		
		$sdr_mode = get_working_mode();
		if (isset($sdr_mode)) {
			if ($_SESSION["sdr_mode"]=="enb" && isset($_SESSION["version"]["enb"])) {
				print "ENB ".$_SESSION["version"]["enb"];
                        } elseif (isset($_SESSION["version"]["bts"])) {
				print "BTS ".$_SESSION["version"]["bts"];
                        }
		} else {
			// display version of all installed components
			$components = array("enb", "bts", "sdr");
			$version = "";
			foreach($components as $comp) {
				$version .= strtoupper($comp)." ". $_SESSION["version"][$comp]."<br/>";
			}
			print $version;
		}
	} elseif (is_file("version.php")) {
		include ("version.php");
		print $version;
	} elseif (is_file("../version.php")) {
		include ("../version.php");
		print $version;
	} else {
		$rev = "";
		exec("svn info 2>/dev/null | sed -n 's,^Revision: *,,p'",$rev);
		if (!is_array($rev) || !isset($rev[0]))
			print "Could not detect version";
		else
			print "svn rev. ".$rev[0];
	}

}