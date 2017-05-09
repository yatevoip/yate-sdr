<?php

function status()
{
	$res = make_request(array(),"query_stats");

	if ($res["code"]!="0") {
		errormess("API:[". $res["code"]."] ".$res["message"], "no");
		return;
	}
	display_query_stats($res, "Yate-SDR");
}
