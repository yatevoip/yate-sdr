<?php
require_once("lib/lib_requests.php");

function working_mode($editable=false)
{
	global $module;
	// retrieve working mode using API request

	$sdr_mode = get_working_mode();
	
	if (!isset($_SESSION["sdr_mode"]) || !strlen($_SESSION["sdr_mode"]) ||  $_SESSION["sdr_mode"]=="not configured") {
		br(2);
		note("It is required to choose a working mode before doing any other configuration.");
		br(2);
	}

	if (!isset($_SESSION["available_sdr_modes"])) {
		errormess("Incomplete installation! Please install al least one of the packages for nipc/roaming/dataroam/enb before trying to use the information. Check equipment api_logs.php to verify response.","no");
		return;
	} else {
		$available_modes = $_SESSION["available_sdr_modes"];
	}

	// working modes and their description
	$working_modes = array(
		"GSM nipc"      => array("mode_type"=>"nipc", "description"=>"GSM Network in a PC"),
		"GSM roaming"   => array("mode_type"=>"roaming", "description"=>"GSM BTS connected to YateUCN/HostedCore for voice/sms services"),
		"GSM dataroam"  => array("mode_type"=>"dataroam", "description"=>"GSM BTS connected to YateUCN/HostedCore for voice/sms/data services"),
		"LTE enb"       => array("mode_type"=>"enb", "description"=>"LTE eNodeB connected to a MME/HostedCore")
	);

	if (!$editable) {
		$th_name = "Status";
		$submit = "<input type='button' onclick='location.href=\"main.php?module=$module&method=modify_working_mode\";' value='Modify' />";
	} else {
		$th_name = "Enabled";
		$submit = "<input type='submit' value='Save' />";
	}

	start_form();
	addHidden("database");
	print "<table class='workingmode'>";
	print "<tr>";
	print "<th>$th_name</th>";
	print "<th>Mode</th>";
	print "<th>Description</th>";
	print "</tr>";
	foreach ($working_modes as $mode=>$mode_def) {
		print "<tr>";
		if (!in_array($mode_def["mode_type"],$available_modes))
			print "<td>unavailable</td>";
		elseif (stripos($mode, $sdr_mode)!==false) {
			if (!$editable)
				print "<td class='enabled_working_mode'>enabled</td>";
			else
				print "<td><input type='radio' name='working_mode' value='$mode' CHECKED/>";
		} else {
			if (!$editable)
				print "<td>disabled</td>";
			else
				print "<td><input type='radio' name='working_mode' value='$mode' />";
		}
		print "<td>$mode</td>";
		print "<td>".$mode_def["description"]."</td>";
		print "</tr>";
	}

	print "<tr><td colspan='3'>$submit</td></tr>";
	print "</table>";
	end_form();
}

function modify_working_mode()
{
	working_mode(true);
}

function modify_working_mode_database()
{
	// this should not happen but if it does, just return to main page
	if (!isset($_SESSION["node_types"]))
		return working_mode();

	$node_types = $_SESSION["node_types"];

	$available_nodes = array();
	foreach ($node_types as $node_type)
		$available_nodes[] = $node_type["type"];  // ex: bts, enb

	$working_mode = getparam("working_mode");
	$working_modes = array(
		"GSM nipc"      => "bts",
		"GSM roaming"   => "bts",
		"GSM dataroam"  => "bts",
		"LTE enb"       => "enb"
	);

	if (!isset($working_modes[$working_mode])) {
		errormess ("Invalid working mode $working_mode","no");
		return working_mode();
	}
	$node_type = $working_modes[$working_mode];
	if (!in_array($node_type, $available_nodes)) {
		errormess ("Unavailable node $node_type","no");
		return working_mode();
	}

	$mode = explode(" ",$working_mode);
	$mode = $mode[count($mode)-1];
	$request_fields = array("sdr_mode"=>$mode);

	// set new working mode
	$res = make_request($request_fields, "set_sdr_mode");

	if (!isset($res["code"]) || $res["code"]!=0) {
		errormess("Could not update working mode: "."[API: ".$res["code"]."] ".$res["message"],"no");
	} else {
		notice("Working mode was updated. Please wait a few seconds for Yate to finish restarting.", "no");
		$_SESSION["sdr_mode"] = $mode;
		print "<meta http-equiv=\"REFRESH\" content=\"5;url=".$_SESSION["main"]."\">";
	}
	
	working_mode();
}

?>
