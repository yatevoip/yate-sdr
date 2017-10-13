<?php

function status()
{
	$request_params = array();
	if (getparam("additional_details")=="on")
		$request_params["details"] = true;

	$res = make_request($request_params,"query_stats");

	print "<div class='status_option'>";
	start_form();
	print "<input type='hidden' name='module' value='status' />";
	print "Additional details:";
        print "<input type='checkbox' name='additional_details' ";
	if (getparam("additional_details")=="on")
		print " checked";
	print "/>";
	print "&nbsp;&nbsp;&nbsp;<input type='submit' value='Refresh' />";
	end_form();
	print "</div>";

	if ($res["code"]!="0") {
		errormess("API:[". $res["code"]."] ".$res["message"], "no");
		return;
	}
	display_query_stats($res, "Yate-SDR");
}
