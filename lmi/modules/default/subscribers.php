<?php
/**
 * subscribers.php
 * This file is part of the Yate-SDR Project http://www.yatebts.com
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

function subscribers()
{
	global $method;

	$method = "list_subscribers";
	list_subscribers();
}

function list_subscribers()
{
	global $pysim_mode, $page, $method, $module;
	
	$method = "list_subscribers";
		
	box_note("Subscribers can be accepted based on two criteria: "."<a class='llink' href='".$_SESSION["main"]."?module=$module&method=regexp'>regular expression</a>"." that matches the IMSI or they must be inserted individually.", false);

	$subscribers = array();
	$res = get_subscribers();
	if ($res[0])
		$subscribers = $res[1];

	$all_subscribers = array();$i=0;
	foreach ($subscribers as $imsi=>$subscr) {
		$all_subscribers[$i] = $subscr;
		$all_subscribers[$i]["imsi"] = $imsi;
		$i++;
	}

	$formats = array("IMSI"=>"imsi","msisdn","short_number","ICCID"=>"iccid","ki","OP/OPC"=>"op","function_display_bit_field:Use OPC"=>"opc","IMSI Type"=>"imsi_type","function_display_bit_field:active"=>"active");
	if ($pysim_mode)
		$formats["function_write_subcriber_on_sim:"] = "imsi,ki";

	items_on_page();
	pages(get_count_subscribers());
	table($all_subscribers, $formats, "subscriber", "imsi", array("&method=edit_subscriber"=>"Edit","&method=delete_subscriber"=>"Delete"), array("&method=add_subscriber"=>"Add subscriber", "&method=edit_regexp"=>"Accept by REGEXP", "&method=export_subscribers_in_csv&page=$page"=>"Export subscribers", "&method=import_subscribers"=>"Import subscribers"));
}

function regexp()
{
	$regexp = get_regexp();
	if (isset($regexp[1]["regexp"]) && strlen($regexp[1]["regexp"])) {
		$regexp  = $regexp[1]["regexp"];
		$buttons = array("Modify", "Return to setting subscribers individually");
 	} else {
		$regexp  = " - ";
		$buttons = array("Modify");
	}


	box_note("If a regular expression is used, 2G/3G authentication cannot be used. For 2G/3G authentication, please set subscribers individually.");
	start_form();
	addHidden(null, array("method"=>"edit_regexp", "regexp"=>$regexp));
	$fields = array("regexp"=>array("value"=>$regexp, "display"=>"fixed"));
	editObject(null, $fields, "Regular expression based on which subscriber IMSI are accepted for registration", $buttons, null, true);
	end_form();
}

function online_subscribers()
{
	global $method, $limit, $page;

	$total = request_api(array(), "get_online_nipc_subscribers", "count", $method);

	box_note("This page displays the subscribers currently registered in the BTS.");

	items_on_page();
	pages($total);
	$online_subscribers = array();
	if ($total>0) {
		$online_subscribers = request_api(array("limit"=>$limit,"offset"=>$page), "get_online_nipc_subscribers", "subscribers", $method);
	}
	
	$formats = array("IMSI","MSISDN","REGISTERED","EXPIRES");
	table($online_subscribers, $formats, "online subscriber", "imsi");
}

function accepted_subscribers()
{
	global $method, $limit, $page;

	$total = request_api(array(), "get_accepted_nipc_subscribers", "count", $method);

	box_note("This page displays the subscribers seen and accepted by the BTS in the interval specified by TMSI expire(default 864000 seconds - 10 days) from BTS Configuration>System>YBTS.");

	items_on_page();
	pages($total);
	$online_subscribers = array();
	if ($total>0) {
		$online_subscribers = request_api(array("limit"=>$limit,"offset"=>$page), "get_accepted_nipc_subscribers", "subscribers", $method);
	}
	
	$formats = array("IMSI","MSISDN");
	table($online_subscribers, $formats, "online subscriber", "imsi");
}

function rejected_imsis()
{
	global $method, $limit, $page;

	$total = request_api(array(), "get_rejected_nipc_subscribers", "count", $method);

	box_note("This page displays rejected IMSIs since the last Yate restart.");

	items_on_page();
	pages($total);
	$rejected_subscribers = array();
	if ($total>0) {
		$rejected_subscribers = request_api(array("limit"=>$limit, "offset"=>$page), "get_rejected_nipc_subscribers", "imsis", $method);
	}

	$formats = array("IMSI","No attempts register"=>"NO");
	table($rejected_subscribers, $formats, "rejected IMSIs", "imsi");
}

function edit_regexp($error=null,$error_fields=array())
{
	if (getparam("Return_to_setting_subscribers_individually")=="Return to setting subscribers individually") {
		$res = request_api(array(";regexp"=>""), "set_nipc_system");
		return list_subscribers();
	}

	$regexp = getparam("regexp");
	if (!$regexp) {
		$regexp = get_regexp();
		if (isset($regexp[1]["regexp"]) && strlen($regexp[1]["regexp"]))
			$regexp = $regexp[1]["regexp"];
		else
			$regexp = null;
	} elseif ($regexp == " - ")
		$regexp = null;
	
	$response_fields = get_bts_node();

	$warning_data = array();
	if (isset($response_fields["security"]["auth.call"]))
		$warning_data[] = $response_fields["security"]["auth.call"];
	if (isset($response_fields["security"]["auth.sms"]))
		$warning_data[] = $response_fields["security"]["auth.sms"];
	if (isset($response_fields["security"]["auth.ussd"]))
		$warning_data[] = $response_fields["security"]["auth.ussd"];

	if (in_array("yes", $warning_data) || in_array("on", $warning_data))
		warning_note("You can't set mobile terminated authentication for calls, SMS, USSD when regular expression is used. Authentication requests will be ignored in this scenario.");
	
	box_note("If a regular expression is used, 2G/3G authentication cannot be used. For 2G/3G authentication, please set subscribers individually.");
	$fields = array(
		"regexp" => array("value"=> $regexp, "required"=>true, "comment"=>"Ex: ^001")
	);
	error_handle($error,$fields,$error_fields);
        start_form();
        addHidden("write_file");
        editObject(NULL,$fields,"Regular expression based on which subscriber IMSI are accepted for registration","Save");
        end_form();
}

function edit_regexp_write_file()
{
	$expressions = array();
	$res = get_regexp();
	if (!$res[0]) 
		errormess($res[1], "no");
	else {
		if (is_array($res[1]) && isset($res[1]["regexp"]))
			$expressions = array($res[1]["regexp"]);
		$cc = array();
		if (isset($res[1]["country_code"]))
			$cc["country_code"] = $res[1]["country_code"];
		if (isset($res[1]["smsc"]))
			$cc["smsc"] = $res[1]["smsc"];
	}
	$regexp = getparam("regexp");

	if (!$regexp) {
		$res = request_api(array(";regexp"=>""), "set_nipc_system");
		return notice("Cleared regular expression. All subscribers must be accepted individually.");
	}

	if (in_array($regexp, $expressions)) {
		notice("Finished setting regular expression.", "subscribers");
		return;
	}	

	$res = set_regexp($regexp);
	if (!$res[0])
		return edit_regexp($res[1]);

	notice("Finished setting regular expression", "subscribers");
}

function country_code_and_smsc()
{
	$res = get_cc_smsc();
	$country_code = "";
	$smsc = "";
	$gw_sos = "";
	$sms_text = "";
	if (is_array($res[1])) {
		if (isset($res[1]["country_code"]))
			$country_code = $res[1]["country_code"];
		if (isset($res[1]["smsc"]))
			$smsc         = $res[1]["smsc"];
		if (isset($res[1]["gw_sos"]))
			$gw_sos       = $res[1]["gw_sos"];
		if (isset($res[1]["sms_text"]))
			$sms_text     = $res[1]["sms_text"];
	}
	if (!strlen($sms_text))
		$sms_text = 'Your allocated phone no. is ${nr}. Thank you for installing YateBTS. Call David at david(${david_number})';

	if (!strlen($country_code)) 
		edit_country_code_and_smsc();
	else {
		$fields = array(
			"country_code"=>array("value"=>$country_code, "display"=>"fixed"),
			"smsc" => array("value"=>$smsc, "display"=>"fixed", "column_name"=>"SMSC"),
			"sms_text" => array("value"=>$sms_text, "display"=>"fixed", "column_name"=>"SMS text"),
			"gw_sos" => array("value"=>$gw_sos, "display"=>"fixed", "column_name"=>"Gateway SOS")
		);
		start_form();
		addHidden(null,array("method"=>"edit_country_code_and_smsc", "country_code"=>$country_code, "smsc"=>$smsc, "gw_sos"=>$gw_sos, "sms_text"=>$sms_text));
		editObject(null,$fields,"Country code and SMSC for the majority of your subscribers.",array("Modify"),null,true);
		end_form();
	}
}

function edit_country_code_and_smsc($error=null,$error_fields=array())
{
	global $method;

	$method = "edit_country_code_and_smsc";

	$country_code = getparam("country_code");
	$smsc = getparam("smsc");
	$gw_sos = getparam("gw_sos");
	$sms_text = getparam("sms_text");
	
	$fields = array(
		"country_code"=>array("value"=>$country_code, "compulsory"=>true, "comment"=>" Your Country code (where YateBTS is installed). Ex: 1 for US, 44 for UK"),
		"smsc"=>array("column_name"=>"SMSC", "value"=>$smsc, "compulsory"=>true, "comment"=>"A short message service center (SMSC) used to store, forward, convert and deliver SMS messages."),
		"sms_text" => array("column_name"=>"SMS text", "compulsory"=>true, "value"=>$sms_text, "comment"=>'Content of SMS message sent to users when first registering. In message ${nr} will be replaced with the allocated/set MSISDN and ${david_number} with 32843.<br/><br/>
You should have a maximum of 70 characters in the sms body after the numbers are replaced. Otherwise if UC2 is used, body of SMS will be truncated. <br/><br/>
Original text is: Your allocated phone no. is ${nr}. Thank you for installing YateBTS. Call David at david(${david_number})
		
		'),
		"gw_sos"=>array("column_name"=>"Gateway SOS", "value"=>$gw_sos, "comment"=>"Resource for the emergency calls gateway.<br/>
If not set any emergency calls will be delivered to the outbound gateway<br/>
It is also possible to specify a short or international number (possibly MSISDN)<br/>
Ex: gw_sos=sip/sip:sos@emergency.gw<br/>
Ex: gw_sos=111<br/>
Ex: gw_sos=+10744341111")
	);

	error_handle($error,$fields,$error_fields);
	start_form();
	addHidden("write_file");
	editObject(NULL,$fields,"Set Country Code and SMSC","Save");
	end_form();
}

function edit_country_code_and_smsc_write_file()
{
	$cc_file = array();
	$res = get_cc_smsc();

	if (!$res[0])
		errormess($res[1], "no");
	else
		$cc_file = $res[1];

	$cc_param       = getparam("country_code");
	$smsc_param     = getparam("smsc");
	$gw_sos_param   = getparam("gw_sos");
	$sms_text_param = getparam("sms_text");

	if ($gw_sos_param) {
		warning_note("In order to route emergency calls you also need to set RACH.AC to '0'(or another value as stated in GSM 04.08 10.5.2.29) in BTS Configuration>GSM>GSM advanced. <font class='error'>DON'T MODIFY \"Gateway SOS\" UNLESS YOU ARE A REAL OPERATOR</font>. You might catch real emergency calls than won't ever be answered.", false);
	}

	if (!$cc_param)
		return edit_country_code_and_smsc("Please set the country code!", array("country_code"));
	if (!ctype_digit(strval($cc_param)))
		return edit_country_code_and_smsc("Country Code invalid!", array("country_code"));
	if (!$smsc_param)
		return edit_country_code_and_smsc("Please set SMSC!", array("smsc"));
	if (!$sms_text_param) 
		return edit_country_code_and_smsc("Please set SMS text!", array("sms_text"));

	if (is_array($cc_file) && @$cc_file["country_code"]==$cc_param && @$cc_file["smsc"]==$smsc_param && @$cc_file["gw_sos"]==$gw_sos_param && @$cc_file["sms_text"]==$sms_text_param) {
		notice("Finished setting Country Code and SMSC. Nothing to update.", "country_code_and_smsc");
		return;
	}

	$general_params = array(
		"country_code" => $cc_param,
		"smsc"         => $smsc_param,
		"gw_sos"       => $gw_sos_param,	
		"sms_text"     => $sms_text_param
	);

	$res = set_cc_smsc($general_params);
	if (!$res[0])
		return edit_country_code_and_smsc($res[1]);
	notice("Finished writting Country Code and SMSC into subscribers.conf.", "country_code_and_smsc");
}

function get_cc_smsc()
{
	$content = request_api(array(), "get_nipc_system", "nipc_system");
	return array(true, $content);
}

function set_cc_smsc($params)
{
	$res = request_api($params, "set_nipc_system"); 

	return array(true);
}

function edit_subscriber($error=null,$error_fields=array())
{
	global $method;
	$method = "edit_subscriber";

	$imsi = getparam("imsi") ? getparam("imsi") : getparam("imsi_val");
	
	if ($imsi) {
		$subscriber = get_subscriber($imsi);
		if (!$subscriber[0]) {
			if (!$error)
				// if there is not error, print message that we didn't find subscriber
				// otherwise if was probably an error when adding one so this message is not relevant
				errormess($subscriber[1], "no");
			$subscriber = array();
		} if (isset($subscriber[1]))
			$subscriber = $subscriber[1];
		else
			$subscriber = array();
	} else
		$subscriber = array();

	$imsi_type = array("selected"=> "2G", "2G", "3G");

	if (get_param($subscriber,"imsi_type"))
		$imsi_type["selected"] = get_param($subscriber,"imsi_type");
	if (getparam("imsi_type"))
		$imsi_type["selected"] = getparam("imsi_type");
	
	$active = (in_array(get_param($subscriber,"active"), array("on","true","enabled","enable","1","yes"))) ? true : false;
	$opc = (in_array(get_param($subscriber,"opc"), array("on","true","enabled","enable","1","yes"))) ? true : false;
	
	$op = (get_param($subscriber,"op")) ? get_param($subscriber,"op") : ((getparam("imsi_type")=="3G") ? "00000000000000000000000000000000" : "");

	$fields = array(
		"imsi"   => array("value"=>$imsi, "required"=>true, "comment"=>"SIM card id", "column_name"=>"IMSI"),
		"msisdn" => array("value"=>get_param($subscriber,"msisdn"), "comment"=>"DID associated to this IMSI. When outside call is made, this number will be used as caller number.", "column_name"=>"MSISDN"),
		"short_number" => array("value" => get_param($subscriber,"short_number"),"comment"=>"Short number that can be used to call this subscriber."),
		"active" => array("value"=>$active, "display"=>"checkbox", "comment"=>"Only active subscribers are allowed to register."),
		"imsi_type" => array($imsi_type, "display"=>"select", "column_name"=>"IMSI Type", "required"=>true, "comment"=> "Type of SIM associated to the IMSI", "javascript" => 'onclick="show_hide_op();"'),
		"iccid" => array("value"=>get_param($subscriber,"iccid"),"column_name"=>"ICCID"),
		"ki" => array("value"=>get_param($subscriber,"ki"), "comment"=>"Card secret. You can use * to disable authentication for this subscriber.", "required"=>true),
		"op" => array("value"=>$op, "column_name"=>"OP/OPC", "triggered_by"=>"imsi_type", "comment"=>"Operator secret. Empty for 2G IMSIs.<br/>00000000000000000000000000000000 for 3G IMSIs."),
		"opc" => array("value"=>$opc, "column_name"=>"Use OPC", "display"=>"checkbox", "triggered_by"=>"imsi_type", "comment"=>"If checked then authentication algorithm will use value set in OP/OPC as OPC.")
	);
	
	if ($imsi && count($subscriber) && !in_array("imsi",$error_fields))
		$fields["imsi"]["display"] = "fixed";
	if ($imsi && $imsi_type["selected"] == "3G") {
		unset($fields["op"]["triggered_by"]);
		unset($fields["opc"]["triggered_by"]);
	}
	if (!count($subscriber))
		$imsi = NULL;

	error_handle($error,$fields,$error_fields);
	start_form();
	addHidden("write_file", array("imsi_val"=>$imsi,"opc"=>"off"));
	editObject(NULL,$fields,"Set subscriber","Save");
	end_form();
}

function edit_subscriber_write_file()
{
	$imsi = (getparam("imsi")) ? getparam("imsi") : getparam("imsi_val");

	$res = get_subscribers($imsi);

	if (!$res[0]) {
		//errormess($res[1], "no");
		$old_subscriber = array();
	} else {
		$old_subscriber = $res[1];
	}

	if (!$imsi)
		return edit_subscriber("Please set 'imsi'",array("imsi"));

	$subscriber = array("imsi"=>$imsi);

	$fields = array("msisdn"=>false, "short_number"=>false, "active"=>false, "ki"=>true, "op"=>false, "opc"=>false, "imsi_type"=>true, "iccid"=>false);
	foreach ($fields as $name=>$required) {
		$val = getparam($name);
		if ($required && !$val)
			return edit_subscriber("Field $name is required");
		if ($name!="active" && $name!="opc")
			$subscriber[$name] = $val;
		else
			$subscriber[$name] = ($val=="on") ? "on" : "off";

	}
	if ($subscriber["imsi_type"]=="2G") {
		$subscriber["op"]  = "";
		$subscriber["opc"] = false;
	}
	if (!strlen($subscriber["iccid"]))
		unset($subscriber["iccid"]);
	if (getparam("imsi_type")=="3G" && (getparam("op")==NULL || getparam("op")==""))
		$subscriber["op"] = "00000000000000000000000000000000";

	$res = validate_subscriber($subscriber);
	if (!$res[0])
		return edit_subscriber($res[1],$res[2]);

	if (getparam("imsi_val") && isset($old_subscriber["imsi"])) {
		$modified = false;
		//check if there are changes
		foreach ($fields as $name=>$required) {
			$val = getparam($name);
			if ($name=="active" || $name=="opc") {
				$val = ($val=="on") ? "on" : "off";
			}
			elseif ($name=='op' && getparam("imsi_type")=="2G") {
				continue;
			}
			
			if ((!isset($old_subscriber[$name]) && $val) || (isset($old_subscriber[$name]) && $old_subscriber[$name]!=$val)) {
				$modified = true;
			}
			if ($modified) {
				break;
			}
		}

		if (!$modified) {
			notice("Finished setting subscriber with IMSI $imsi. Nothing to update.", "subscribers");
			return;
		}
	}

	if (!getparam("imsi_val") && isset($old_subscriber[$imsi])) {
		return edit_subscriber("Subscriber with IMSI $imsi is already set.",array("imsi"));
	}

	$subscribers = array("subscribers" => array($imsi=>$subscriber));

	if (!getparam("imsi_val")) {
		unset($subscribers["subscribers"][$imsi]["imsi"]);
	}
	$res = set_subscribers($subscribers);
	if (!$res[0]) {
		return edit_subscriber($res[1]);
	}

	notice("Finished setting subscriber with IMSI $imsi.", "subscribers");
}

function delete_subscriber()
{
	ack_delete("subscriber", getparam("imsi"), NULL, "imsi", getparam("imsi"));
}

function delete_subscriber_database()
{
	$imsi = getparam("imsi");
	$res = request_api(array("imsi"=>$imsi), "delete_nipc_subscriber", null, "subscribers");
	notice("Finished removing subscriber with IMSI $imsi.", "subscribers");
}

function set_subscribers($subscribers)
{
	global $method;

	$res = request_api($subscribers, "set_nipc_subscribers", null, $method);
	return array(true);
}

function set_regexp($regexp)
{
	$res = request_api(array("regexp"=>$regexp), "set_nipc_system");
	return array(true);
}

function get_subscribers($imsi=false, $get_all=false)
{
	global $limit, $page;

	if ($imsi) {
		$res = request_api(array("imsi"=>$imsi), "get_nipc_subscribers","subscribers");
		return array(true, $res);
	} else {

		$total = getparam("total");
		if (!$total)
			$total = get_count_subscribers();
		if ($total>0) {
			if ($get_all) {
				$limit = $total;
				$page=0;
			}
			$res = request_api(array("limit"=>$limit, "offset"=>$page), "get_nipc_subscribers", "subscribers");
			return array(true, $res);
		}

		return array(true, array());
	}
}

function get_count_subscribers()
{
	$res = request_api(array(), "get_nipc_subscribers", "count");
	return $res;
}

function get_regexp()
{
	global $method;

	$res = request_api(array(), "get_nipc_system", "nipc_system", $method);
	return array(true, $res);
}

function get_subscriber($imsi)
{
	$subscribers = get_subscribers($imsi);

	if (!count($subscribers))
		return array(false, "Could not find subscriber with imsi $imsi");
	return array(true, $subscribers[1]);
}

function detect_pysim_installed()
{
	global $pysim_path;

	if (!have_pysim_prog())
		return array(false, "Please install pySIM and create file config.php to set the location for pySIM after instalation. E.g. \$pysim_path = \"/usr/bin\";");

	$pysim_prog_path = have_pysim_prog();
	$pysim_real_path = str_replace(array("/pySim-prog.py","\n"), "", $pysim_prog_path);

	if (!isset($pysim_path)) 
		$pysim_path = $pysim_real_path;

	if (!is_file($pysim_path.'/'.'pySim-prog.py')) 
		return array(false, "The path for pySIM set in configuration file is not correct. Please set in file config.php the right location for pySIM. This path was detected: \$pysim_path = \"$pysim_real_path\";");

	return array(true);
}

function manage_sims()
{
	global $pysim_csv, $pysim_mode;

	if (!$pysim_mode)
		return;

	$pysim_installed = detect_pysim_installed();

	if (!$pysim_installed[0]) {
		errornote($pysim_installed[1]);
		return;
	}
	
	$all_sim_written = read_csv();

	$formats = array("IMSI"=>"imsi", "ICCID"=>"iccid", "operator_name", "mobile_country_code", "mobile_network_code", "ki", "opc", "function_display_add_into_subscribers:"=>"imsi,ki");
?>
	<div>
		<a class="write_sim" href="main.php?module=subscribers&method=write_sim_form"><img title="SIM Programmer" src="images/sim_programmer.png" alt="SIM Programmer" /></a>
	</div>
<?php
	table($all_sim_written, $formats, "written SIM", "sim");
	if (file_exists($pysim_csv)) {
		?><div class="download_file"><a href="download.php" class="content">Download csv file with all written SIMs</a></div><?php
	}
}

function display_add_into_subscribers($imsi, $ki)
{
	global $module, $main;

	$subscribers = get_subscriber($imsi);

	if (!$subscribers[0]) {
		$link = $main."?module=$module&method=write_imsi_in_subscribers";
		$link .= "&imsi=".$imsi. "&ki=".$ki;	
		print "<a class=\"content\" href=\"$link\">Add in subscribers</a>";
	} else  
		print "";
}

function write_sim_form($error=null, $error_fields=array(), $generate_random_imsi = true, $insert_subscribers = true, $add_existing_subscriber=false, $params = array())
{
	global $upload_path, $sim_type, $method;

	$method = "write_sim_form";
	$pysim_installed = detect_pysim_installed();

	if (!$pysim_installed[0]) {
		errornote($pysim_installed[1]);
		return;
	} 

	$response_fields = get_bts_node();

	$res = get_cc_smsc();
	$cc = $smsc = "";
	if ($res[0] && is_array($res[1])) {
		$cc = $res[1]["country_code"];
		$smsc = $res[1]["smsc"];
	}

	$mcc = "001";
	$mnc = "01";
	$advanced_mcc = $advanced_mnc = $advanced_op = $advanced_smsc = false;
	if (isset($response_fields["gsm"]["Identity.MCC"])) {
		$mcc = $response_fields["gsm"]["Identity.MCC"];
		$advanced_mcc = true;
	}
	if (isset($response_fields["gsm"]["Identity.MNC"])) {
		$mnc = $response_fields["gsm"]["Identity.MNC"];
		$advanced_mnc = true;
	}
	if (isset($response_fields["gsm"]["Identity.ShortName"])) {
		$op = $response_fields["gsm"]["Identity.ShortName"];
		$advanced_op = true;
	}
	$params["smsc"] = get_smsc();

	if (!empty($params["smsc"]))
		$advanced_smsc = true;

	if (!$add_existing_subscriber) {
		box_note("There are two methods of writing the SIM cards, depending on the state of the \"Generate random IMSI\" field. If the field is selected, the SIM credentials are randomly generated. Otherwise, the data must be inserted manually. Please check that your SIM Card Reader is inserted into the USB port of your device. Before saving data, please insert a SIM card into the SIM Card Reader.");
	} else {
		if (test_existing_imsi_in_csv($params["imsi"]))
			box_note("This IMSI: ".$params["imsi"]." is already written on another SIM card.");
	}

	$type_card = get_card_types();
	$type_card["selected"] = $sim_type; 

	if (!$cc) 
		$fields["country_code"] = array("required" => true, "value"=>$cc, "comment" => "Your Country code (where YateBTS is installed). Ex: 1 for US, 44 for UK");

	if (!$add_existing_subscriber) {
		$fields["generate_random_imsi"] =  array("comment" => "Checked - if you want the parameter for the card to be generated randomly or uncheck - to insert your card values manually", "column_name"=>"Generate random IMSI", "javascript" => 'onclick="show_hide_cols()"', "display"=>"checkbox", "value"=>$generate_random_imsi);
		//show/hide fields when generate_random_imsi is unselected/selected
		$fields["imsi"] = array("required"=>true,"column_name"=>"IMSI", "comment" => "Insert IMSI to be written to the card. Ex.:001011641603116", "triggered_by"=>"generate_random_imsi");
		$fields["iccid"] = array("required"=>true,"column_name"=>"ICCID", "comment" => "Insert ICCID(Integrated Circuit Card Identifier) to be written to the card. Ex.: 8940001017992212557", "triggered_by"=>"generate_random_imsi");
		$fields["ki"] = array("required"=>true,"column_name"=>"Ki", "comment" => "Insert Ki to be written to the card. Ex.: 3b07f45b11d2003247e9ae6f13de7573", "triggered_by"=>"generate_random_imsi");
		$fields["opc"] = array("required"=>true,"column_name"=>"OPC", "comment" => "Insert OPC to be written to the card. Ex.: 6cb49bb6f99e97c3913924e7a1f32650", "triggered_by"=>"generate_random_imsi");

		if ($params["smsc"] == "") {
			$fields["smsc"] = array("required"=>true, "column_name"=>"SMSC", "comment"=>"Short message server center.", "advanced"=>true, "javascript"=>"onClick=advanced('sim')", "triggered_by"=>"generate_random_imsi");
		}
	} else {
		$fields["imsi"] = array("column_name"=>"IMSI", "value"=> $params["imsi"],"display"=>"fixed");
		$fields["iccid"] = array("required"=>true,"column_name"=>"ICCID", "value"=> $params["iccid"], "comment" => "Insert ICCID(Integrated Circuit Card Identifier) to be written to the card. Ex.: 8940001017992212557.");
		$fields["ki"] = array("display"=>"fixed", "column_name"=>"Ki", "value" => $params["ki"]);
		$fields["opc"] = array("required"=>true,"column_name"=>"OPC", "value"=> $params["opc"], "comment" => "Insert OPC to be written to the card. Ex.: 6cb49bb6f99e97c3913924e7a1f32650.");
		if ($params["smsc"] == "") {
			$fields["smsc"] = array("required"=>true, "column_name"=>"SMSC", "comment"=>"Short message server center.");
		}
	}
	if (!$add_existing_subscriber) {
		$fields["insert_subscribers"] = array("comment" => "Uncheck if you don't want SIM credentials to be written in subscribers.js.", "display"=>"checkbox", "value" => $insert_subscribers); 
	}
	//advanced fields if they are set in ybts.conf file
	$fields["operator_name"] = $advanced_op ? array("required" => true,"advanced"=> true, "value" =>$op, "comment" => "Set Operator name on SIM.") : array("required" => true, "comment" => "Set Operator name on SIM.");
	if ($cc)
		$fields["country_code"] = array("required" => true, "value"=>$cc, "comment" => "Your Country code (where YateBTS is installed). Ex: 1 for US, 44 for UK", "advanced"=> true);

	$fields["card_type"] = array($type_card,"advanced"=> true, "required"=>true, "display"=>"select", "column_name"=> "Card Type", "comment" =>" Select the card type for writing SIM credentials. The SIM cards that you received are \"GrcardSim\". For other card types, see the list of cards supported by PySim. It is not guaranteed that your card will be written, even if it is in that list."); 

	$fields["mobile_country_code"] = ($advanced_mcc) ? 
		array("required" => true,"advanced"=> true,"value" => $mcc,"comment" => "Set Mobile Country Code.","javascript"=>"onClick=advanced('sim')") :
		array("required" => true,"value" => $mcc, "comment" => "Set Mobile Country Code.");
	$fields["mobile_network_code"] = ($advanced_mnc) ? array("required" => true,"advanced"=> true, "value" => $mnc, "comment" => "Set Mobile Network Code.", "javascript"=>"onClick=advanced('sim')") :
		array("required" => true,"value" => $mnc, "comment" => "Set Mobile Network Code.");

	if (strlen($params["smsc"])>0) {
		$fields["smsc_adv"] = array("required"=>true, "column_name"=>"SMSC", "comment"=>"Short message server center.", "value"=>$params["smsc"], "advanced"=>true, "javascript"=>"onClick=advanced('sim')");
	}

	if ($generate_random_imsi != "on") {
		unset($fields["imsi"]["triggered_by"]);
		unset($fields["iccid"]["triggered_by"]);
		unset($fields["ki"]["triggered_by"]);
		unset($fields["opc"]["triggered_by"]);
		unset($fields["smsc"]["triggered_by"]);
	}

	error_handle($error,$fields,$error_fields);
	start_form(NULL,"post",false,"outbound");
	if ($add_existing_subscriber) 
		addHidden("to_pysim", array("generate_random_imsi"=>$generate_random_imsi, "add_existing_subscriber"=>$add_existing_subscriber, "imsi"=>$params["imsi"], "ki"=>$params["ki"]));
	else
		addHidden("to_pysim", array("generate_random_imsi"=>$generate_random_imsi));
	editObject(NULL,$fields,"Set SIM data for writting","Save");
	end_form();
}

function get_card_types()
{
	$type_card = array(
		array('card_type_id'=>'fakemagicsim', 'card_type'=>'FakeMagicSim'),
		array('card_type_id'=>'supersim', 'card_type'=>'SuperSim', ),
		array('card_type_id'=>'magicsim', 'card_type'=>'MagicSim'),
		array('card_type_id'=>'grcardsim','card_type'=>'GrcardSim'),
		array('card_type_id'=>'sysmosim-gr1', 'card_type'=>'Sysmocom SysmoSIM-GR1'),
		array('card_type_id'=>'sysmoSIM-GR2', 'card_type'=>'Sysmocom SysmoSIM-GR2' ), 
		array('card_type_id'=>'sysmoUSIM-GR1', 'card_type'=>'Sysmocom SysmoUSIM-GR1'),
		array('card_type'=>'auto','card_type_id'=>'auto')//autodetection is implemented in PySim/cards.py only for classes: FakeMagicSim, SuperSim, MagicSim the other types of card will fail (at this time 2014-04-16)
	);
	return $type_card;
}

function write_sim_form_to_pysim()
{
	global $upload_path, $method;

	$error = "";
	$params = array("operator_name","country_code","mobile_country_code","mobile_network_code", "card_type");
	foreach ($params as $key => $param) {
		if (!getparam($param)) {
			$error .= "This parameter '". ucfirst(str_replace("_"," ",$param)). "' cannot be empty!<br/>\n";
			$error_fields[] = $param;
		} elseif ($param != "operator_name" && $param != "card_type" && !ctype_digit(strval(getparam($param)))) {
			$error .= "Invalid integer value for parameter '". ucfirst(str_replace("_"," ",$param)). "': ". getparam($param). ".<br/> \n";
			$error_fields[] = $param;
		} elseif ($param == "mobile_country_code" && (strlen(getparam($param))>4 || getparam($param) <= 0 || getparam($param) >= 999)) {
			$error .= "Mobile Country Code value must be between 0 and 999. <br/>\n";
			$error_fields[] = $param;
		} elseif ($param == "mobile_network_code" && (strlen(getparam($param))>4 || getparam($param) <= 0 || getparam($param) >= 999)) {
			$error .= "Mobile Network Code value must be between 0 and 999. <br/>\n";
			$error_fields[] = $param;	
		} else		
			$data[$param] = getparam($param);
	}

	$change_command = false;	

	if (getparam("generate_random_imsi") != "on" || getparam("add_existing_subscriber"))
	       	$change_command = true;

	if ($change_command) {
		//validation on fields

		$data["smsc"] = getparam("smsc")!=NULL ? getparam("smsc") : getparam("smsc_adv");
		if ($data["smsc"] == "" && !ctype_digit(strval($data["smsc"]))) {
		         $error .= "SMSC must be digits only!<br/>\n";
			 $error_fields[] = $param;
		}

		if (getparam("add_existing_subscriber")){ 
			$params = array("iccid", "opc");
			$data["imsi"] = getparam("imsi");
			$data["ki"] = getparam("ki");
		} else	
			$params = array("imsi", "iccid", "ki", "opc");
		foreach ($params as $key => $param) {
			if (!getparam($param)) {
				$error .= "This parameter '".strtoupper($param). "' cannot be empty!<br/>\n";
				$error_fields[] = $param;
			} elseif ($param == "imsi") {
				$imsi = getparam($param);
			        if (!ctype_digit(strval($imsi)) || ( strlen($imsi) != 15 && strlen($imsi) != 14))
					$error .= "IMSI: $imsi is not valid, must contain 14 or 15 digits. <br/>\n";
				if (test_existing_imsi_in_csv($imsi))
					$error .= "This IMSI: $imsi is already written on another SIM card.<br/>\n";
				$error_fields[] = $param;
			} elseif (($param == "opc" || $param == "ki") && !preg_match('/^[0-9a-fA-F]{32}$/i', getparam($param))) {
				$name = $param == "opc" ? "OPC" : "Ki";
				 $error .= $name . ": ".getparam($param)." needs to be 128 bits, in hex format.<br/>\n";
				 $error_fields[] = $param;
			} elseif ($param == "iccid" && !ctype_digit(strval(getparam($param))) && strlen(getparam($param)) != 19) {
				$error .= "ICCID: ". getparam($param) ." must contain 19 digits!<br/>\n";
				$error_fields[] = $param;
			}	
			$data[$param] = getparam($param);
		}
	}
	if (!strlen($error))
		$output = execute_pysim($data, $change_command);
	else {
		if (getparam("add_existing_subscriber"))
			return write_sim_form($error, $error_fields, getparam("generate_random_imsi"),getparam("insert_subscribers"),true, $data);
		else
			return write_sim_form($error, $error_fields, getparam("generate_random_imsi"),getparam("insert_subscribers"));
	}

	if ($output)
		print "<pre>".$output."</pre>";

	//for successfully written SIM cards, write tha last one into subscribers file 
	if (substr(trim($output),-6,6) == "Done !" && getparam("insert_subscribers") == "on") {
		$all_sim_written = read_csv();
		write_generated_imsi_to_file($all_sim_written[count($all_sim_written)-1]);
	}

	if (getparam("add_existing_subscriber"))
		list_subscribers();
	else
		manage_sims();
}

function write_imsi_in_subscribers()
{
	$imsi = getparam("imsi");
	$ki = getparam("ki");

	if (!$imsi && !$ki)
		return;

	$subscribers = array("imsi"=> $imsi , "ki"=> $ki);
	write_generated_imsi_to_file($subscribers);

	manage_sims();
}

function write_generated_imsi_to_file($subscribers)
{
	$res = get_subscriber($subscribers["imsi"]);
	if ($res[0])
		return;

	unset($subscribers["iccid"], $subscribers["operator_name"],$subscribers["country_code"],$subscribers["mobile_country_code"],$subscribers["mobile_network_code"]);
	$res = get_subscribers();
	if ($res[0])
		$new = $res[1];	

	$new[$subscribers["imsi"]] = array(/*"imsi"=>$subscribers["imsi"],*/"msisdn"=> "","short_number"=>"","active"=>"off","ki"=>$subscribers["ki"],"op"=>"","imsi_type"=>"2G");
	$res = set_subscribers($new);

	if (!$res[0])
		return errormess($res[1],"no");
}

function execute_pysim($params, $command_manually=false)
{
	global $pysim_path, $pysim_csv;

	if (!isset($_SESSION["card_num"]))
		$_SESSION["card_num"] = 0;
	$random_string = generateToken(5);
	/**
	 * usage: Run pySIM with some minimal set params:
	 * ./pySim-prog.py -n 26C3 -c 49 -x 262 -y 42 -z <random_string_of_choice> -j <card_num>
	 *
	 *   With <random_string_of_choice> and <card_num>, the soft will generate
	 *   'predictable' IMSI and ICCID, so make sure you choose them so as not to
	 *   conflict with anyone. (for eg. your name as <random_string_of_choice> and
	 *   0 1 2 ... for <card num>).
	 */

	$pysim_installed = detect_pysim_installed();

	if (!$pysim_installed[0]) {
		errornote($pysim_installed[1]);
		return;
	}

	$params_sim_data = "-e -n ".$params["operator_name"]." -c ".$params["country_code"]." -x ".$params["mobile_country_code"]." -y ".$params["mobile_network_code"]." -z $random_string  -j ". $_SESSION["card_num"];

	$command = 'stdbuf -o0 ' . $pysim_path.'/'.'pySim-prog.py -p 0 -t '. $params["card_type"]." ".$params_sim_data. " --write-csv ".$pysim_csv ;

	/**
	 * Or if command manually is set then run pySIM with every parameter specified manually:
	 * E.g.:  ./pySim-prog.py -n 26C3 -c 49 -x 262 -y 42 -i <IMSI> -s <ICCID>
	 */ 
	if ($command_manually)
		$command = 'stdbuf -o0 ' . $pysim_path.'/'.'pySim-prog.py -e -p 0 -t '. $params["card_type"]. " -n ".$params["operator_name"]."  --write-csv ".$pysim_csv." -i ". $params["imsi"]." -s ".$params["iccid"]. " -o ". $params["opc"]." -k ". $params["ki"]." -c ".$params["country_code"]." -x ".$params["mobile_country_code"]." -y ".$params["mobile_network_code"];

	if (isset($params["smsc"]))
		$command .= " -m ".$params["smsc"];

	$descriptorspec = array(
		0 => array("pipe","r"),// stdin is a pipe that the child will read from
		1 => array("pipe","w"),//stdout
		2 => array("pipe","w") //stderr
	) ;

	// define current working directory where files would be stored
	// open process and pass it an argument
	$process = proc_open($command, $descriptorspec, $pipes);

	if (is_resource($process)) {
		// $pipes now looks like this:
		// 0 => writeable handle connected to child stdin
		// 1 => readable handle connected to child stdout
		// Any error output will be appended to $upload_path."pysim-error.log"
		$output = false;
		do {
			$read = array($pipes[1]);$write=array();$except=array();
			if (!stream_select($read, $write ,$except,3)) {
				//if card was not inserted in the Reader or the time expired
				fclose($pipes[1]);
				proc_terminate($process);
				proc_close($process);
				print "Card was not found in SIM card reader... Terminating program...";
				return;
			}
			$return_message = fread($pipes[1], 1024);
			$output .= $return_message; 
		} while(!empty($return_message));
	}

	if ($err = stream_get_contents($pipes[2])) {
		$split_errs = explode("\n", rtrim($err));
		$output .= "<div style=\"display:none;\" id=\"pysim_err\">";
		$i = 1;
		foreach ($split_errs as $key => $split_err) {
			if ($i == count($split_errs))
				break;
			$output .= $split_err."\n";
			$i++;
		}

		$output .= "\n</div>";
		$output .= $split_errs[count($split_errs)-1];
		if (preg_match("/Exception AttributeError: \"'PcscSimLink' object has no attribute '_con/", $output))
			$output .= "\nPlease connect you SIM card reader to your device.\n"; 
		$output .= "\n<br/><div id=\"err_pysim\">For full pySim traceback <div id=\"err_link_pysim\" class=\"error_link\" onclick=\"show_hide_pysim_traceback('pysim_err')\" style=\"cursor:pointer;\">click here</div></div>";
	} 

	proc_close($process);

	$_SESSION["card_num"] += 1;
	return $output;
}

// $length - the length of the random string
// returns random string with mixed numbers, upperletters and lowerletters
function generateToken($length)
{
	//0-9 numbers,10-35 upperletters, 36-61 lowerletters
	$str = "";
	for ($i=0; $i<$length; $i++) {
		$c = mt_rand(0,61);
		if ($c >= 36) {
			$str .= chr($c+61);
			continue;
		}
		if ($c >= 10) {
			$str .= chr($c+55);
			continue;
		}
		$str .= chr($c+48);
	}
	return $str;
}

function read_csv()
{
	global $upload_path;

	$sim_data = array();
	$filename = $upload_path.'sim_data.csv';
	if (!file_exists($filename))
		return $sim_data;

	$formats = array("operator_name", "iccid", "mobile_country_code", "mobile_network_code", "imsi", "smsp", "ki", "opc");
	$csv = new CsvFile($filename,$formats, array(), false);
	if ($csv->getError()) {
		box_note($csv->getError());
		return $sim_data;
	}

	$sim_data = $csv->file_content;
	return $sim_data;
}

function test_existing_imsi_in_csv($imsi)
{
	$sim_data = read_csv();

	$sim_imsis = array();

	for ($i=0; $i<count($sim_data); $i++){ 
		if (isset($sim_data[$i]["imsi"]))
			$sim_imsis[] = $sim_data[$i]["imsi"];
	}

	if (in_array($imsi, $sim_imsis))
		return true;

	return false;
}

function get_params_subscriber_from_pysim_csv($imsi)
{
	if (!test_existing_imsi_in_csv($imsi))
		return array();

	$params = array();
	$sim_data = read_csv();

	for ($i=0; $i<count($sim_data); $i++)
		if ($sim_data[$i]["imsi"]== $imsi)
			return $sim_data[$i];
}

function write_subcriber_on_sim($imsi, $ki)
{
	return '<a href="main.php?module=subscribers&method=write_subscriber_form&imsi='.$imsi.'&ki='.$ki.'"><img src="images/sim_programmer.png" /></a>';
}

function write_subscriber_form()
{
	$iccid_required = get_iccid_required_params();
	$params = array("imsi" => getparam("imsi"), "ki"=>getparam("ki"));
	$sim_data = get_params_subscriber_from_pysim_csv($params["imsi"]);
	if (count($sim_data)) 
		$params = $sim_data;
	$params["smsc"] = get_smsc();

	if (!isset($params["iccid"]))
		$params["iccid"] = get_iccid_random($iccid_required["cc"], $iccid_required["mcc"], $iccid_required["mnc"]);
	if (!isset($params["opc"]))
		$params["opc"] = get_opc_random();

	write_sim_form($error=null,$error_fields=array(), "on", "off", true, $params);
}

function get_bts_node()
{
	$response_fields = request_api(array(), "get_bts_node", "node");
	if (!isset($response_fields["ybts"])) {
		Debug::xdebug("subscribers", "Could not retrieve ybts fields when editing a regexp.");
		return null;
	}
	return $response_fields["ybts"];
}

function get_iccid_required_params()
{
	$res = get_cc_smsc();
	if ($res[0] && is_array($res[1])) 
		$cc = $res[1]["country_code"];

	$res = get_bts_node();

	$mcc = "001";
	$mnc = "01";

	if (isset($res["gsm"]["Identity.MCC"]))
		$mcc = $res["gsm"]["Identity.MCC"];
	if (isset($res["gsm"]["Identity.MNC"]))
		$mnc = $res["gsm"]["Identity.MNC"];

	$params = array("cc"=>$cc, "mcc"=>$mcc, "mnc"=>$mnc);

	return $params;
}

function get_smsc()
{
	$smsc = "";
	
	$res = get_cc_smsc();
	if (is_array($res[1])) 
		$smsc = $res[1]["smsc"];

	return $smsc;
}

function import_subscribers($error=null,$error_fields=array())
{
	global $method;

	$method = "import_subscribers";
	$fields = array(
		"insert_file_location" => array("display"=>"file", "file_example"=>"import_example.csv"),
		"note!" => array("value"=>"File type must be .csv.", "display"=>"fixed")
	);

	error_handle($error,$fields,$error_fields);
	start_form(NULL,"post",true);
	addHidden("from_csv");
	editObject(NULL,$fields,"Import subscribers from .csv file", "Upload");
	end_form();
}

function import_subscribers_from_csv()
{
	global $upload_path;

	$res = is_valid_upload_path();
	if (!$res[0])
		return errormess($res[1]);

	$filename = basename($_FILES["insert_file_location"]["name"]);
	$ext = strtolower(substr($filename,-4));
	if ($ext != ".csv")
		return import_subscribers("File format must be .csv", array("insert_file_location"));

	$real_name = time().".csv";
	$file = "$upload_path/$real_name";
	if (!move_uploaded_file($_FILES["insert_file_location"]['tmp_name'],$file))
		return import_subscribers("Could not upload file.", array("insert_file_location"));

	$new_subscribers = get_subscribers_from_uploaded_csv($file);

	if (!$new_subscribers[0])
		return import_subscribers($new_subscribers[1], array("insert_file_location"));

	$res = analize_subscribers_data($new_subscribers[1], $new_subscribers[2]);
	$new_subscribers = $new_subscribers[1];

	if (isset($res["override"]) || isset($res["skip"])) {
		return display_intermediate_step($res);
	}

	//if no errors found in the file 
	//insert subscribers into subscribers.conf
	finish_importing_subscribers($new_subscribers);
}

function validate_subscriber($fields)
{
	if (isset($fields["imsi"])) {
		$imsi = $fields["imsi"];
		if (!preg_match("/^[0-9]{14,15}$/", $imsi))
			return array(false, "The IMSI: '".$imsi."' must contain only 14 or 15 digits.", array("imsi"));
	}

	if (strlen($fields["msisdn"])) {
		if (strlen($fields["msisdn"])<7) 
			return array(false, "The MSISDN: '".$fields["msisdn"]."' must have at least 7 digits.", array("msisdn"));
		if (!ctype_digit(strval($fields["msisdn"])))
			return array(false, "The MSISDN: '".$fields["msisdn"]."' must contain only digits.", array("msisdn"));
		if (preg_match("/^0/", $fields["msisdn"]))
			return array(false, "The MSISDN: '".$fields["msisdn"]."' can't start with 0.", array("msisdn"));
	}

	$short_number = $fields["short_number"];
	if (strlen($short_number)) {
		if (!ctype_digit(strval($short_number)))
			return array(false, "The Short number: '".$short_number."' must be numeric.", array("short_number"));
		if (strlen($short_number)<3)
			return array(false, "The Short number: '".$short_number."' must have at least 3 digits.",array("short_number"));
	}

	$active_allowed = array("on","off","true","false","enabled","disabled","enable","disable","1","0","yes","no");
	if ($fields["ki"]!="*" && !preg_match('/^[0-9a-fA-F]{32}$/i', $fields["ki"]))
		return array(false,"Invalid KI:'".$fields["ki"]."'. KI needs to be 128 bits, in hex format.", array("ki"));
	if (!isset($fields["imsi_type"]) || ($fields["imsi_type"]!="2G" && $fields["imsi_type"]!="3G"))
		return array(false, "Imsi type invalid: '".$fields["imsi_type"]."'. Only 2G or 3G allowed.", array("imsi_type"));
	if (!isset($fields["active"]) || !in_array($fields["active"],$active_allowed))
		return array(false, "Active field invalid: '". $fields["active"]."'. Only bool value allowed.", array("active"));
	if (isset($fields["iccid"]) && strlen($fields["iccid"])) {
		if (strlen($fields["iccid"]) > 20)
			return array(false, "Invalid ICCID: '". $fields["iccid"] ."' can't have more than 20 characters.", array("iccid"));
	}

	return array(true);
}

function get_subscribers_from_uploaded_csv($file)
{
	$new_subscribers = array();
	$res = array();
	$formats = array("imsi","msisdn", "iccid", "algorithm", "imsi_type", "active", "cs_active", "ps_active", "lte_active", "ims_active", "short_number", "op", "opc", "ki", "pin", "puk", "pin2", "puk2", "adm1", "ota");

	$csv = new CsvFile($file,$formats);

	if (!count($csv->file_content) && $csv->getError())
		return array(false, $csv->getError());

	$skip_params = array("algorithm","cs_active", "ps_active", "lte_active", "ims_active", "pin", "puk", "pin2", "puk2", "adm1", "ota");
	$imsis = array();
	foreach ($csv->file_content as $key=>$subs_data) {
		if (isset($subs_data["imsi"]) && strlen($subs_data["imsi"])) {
			$new_subscribers[$subs_data["imsi"]] = $csv->file_content[$key];
			unset($new_subscribers[$subs_data["imsi"]]["imsi"]);
			
			foreach ($skip_params as $k=>$skip_param) { 
				unset($new_subscribers[$subs_data["imsi"]][$skip_param]);
			}
			$imsis[] = $subs_data["imsi"];
		} else {
			$res["skip"][] = "Skipped line: ".make_readable_line($subs_data). ". Error: IMSI is required.";
		}
	}
	$imsis = array_count_values($imsis);
	find_duplicate($new_subscribers, $imsis, "imsi", $res);

	return array(true,$new_subscribers,$res);
}

// Old implementation to overwrite the imsis when importing a csv file
function overwrite_imsi_form($error=null, $error_fields=array())
{
	global $method;

	$method="overwrite_imsi";
	$imsi_duplicated = array();
	if (isset($_SESSION["imsi_duplicate"]))
		$imsi_duplicate = $_SESSION["imsi_duplicate"];

	$i=0;
	foreach ($imsi_duplicate as $key => $imsi) {
		$fields["imsi".$i] = array("display"=>"fixed", "value"=>$imsi, "column_name"=>"IMSI");
		$i++;
	}
	$fields["note!"] = array("value"=>"The IMSI found in csv file are the same as the ones in subscribers.conf but with different values.", "display"=>"fixed");

	error_handle($error,$fields,$error_fields);
	start_form();
	addHidden("in_file");
	editObject(NULL,$fields, "Overwrite existing subscribers", "Overwrite IMSIs");
	end_form();
}

function overwrite_imsi_in_file()
{
	if (isset($_SESSION["new_subs"]))
		$merge_subs = $_SESSION["new_subs"];

	if (isset($_SESSION["keep_general"]))
		$keep_general = $_SESSION["keep_general"];

	if (!count($merge_subs))
		return overwrite_imsi_form("No subscribers found to overwrite.");

	$res = set_subscribers($merge_subs);
	if (!$res[0]) 
	 	return overwrite_imsi_form("The subscribers were not overwritten. Error: ". $res[1]);

	unset($_SESSION["new_subs"],$_SESSION["keep_general"],$_SESSION["imsi_duplicate"]);
	$res = restart_yate();
	if ($res[0] && isset($res[1])) //yate is not running
		notice("Finished overwritting subscribers. " .$res[1], "list_subscribers");
	elseif (!$res[0]) //errors on socket connection
		notice("Finished overwritting subscribers. For changes to take effect please restart yate or reload just nipc.js from telnet with command: \"javascript reload nipc\".Please note that after this you will lose existing registrations.", "list_subscribers");
	else //yate was restarted
		notice("Finished overwritting subscribers.", "list_subscribers");
}

function export_subscribers_in_csv()
{
	global $upload_path, $pysim_mode;

	$res = is_valid_upload_path();
	if (!$res[0])
		return errormess($res[1]);

	$subscribers = get_subscribers();
	if (!$subscribers[0]) {
		box_note("No subscribers to export.");
		return;
	}
	$smsc = get_smsc();
//	$formats = array("IMSI", "Msisdn", "Short_number", "Active", "Ki", "OP", "IMSI_Type", "ICCID", "SMSC", "OPC");
	$formats = array("imsi","msisdn", "iccid", "algorithm", "imsi_type", "active", "cs_active", "ps_active", "lte_active", "ims_active", "short_number", "op", "opc", "ki", "pin", "puk", "pin2", "puk2", "adm1", "ota");
	if ($pysim_mode) {
		$formats[] = "iccid";
		$formats[] = "smsc";
		$formats[] = "opc";
	}
	$i=0;
	$arr = array();
	foreach ($subscribers[1] as $imsi=>$params) {
		//array_push($params,$imsi);
		if ($pysim_mode) {	
			$params_pysim = get_params_subscriber_from_pysim_csv($imsi);
			if (count($params_pysim)) {
				$params["iccid"] = $params_pysim["iccid"];
				$params["opc"] = trim($params_pysim["opc"]);
				$params["op"] = trim($params_pysim["op"]);
			}
		}
		$params["imsi"] = $imsi;
		if (!isset($params["op"]))
			$params["op"] = "";
		if (!isset($params["algorithm"]))
			$params["algorithm"] = ($params["imsi_type"] == "3G") ? "milenage" : "comp128-1";
		if (!isset($params["ps_active"]))
			$params["ps_active"] = "1";
		if (!isset($params["cs_active"]))
			$params["ps_active"] = "1";
		if (!isset($params["lte_active"]))
			$params["lte_active"] = "1";
		if (!isset($params["ims_active"]))
			$params["ims_active"] = "1";
		if (in_array($params["active"],array("on","true","enabled","enable","1","yes")))
			$params["active"] = "1";
		else
			$params["active"] = "0";
		if (!isset($params["opc"]))
			$params["opc"] = "0";
		elseif (in_array($params["opc"],array("on","true","enabled","enable","1","yes")))
			$params["opc"] = "1";
		else
			$params["opc"] = "0";
		$arr[] = $params;
	}

	$filename = "list_subscribers.csv";

	$csv = new CsvFile($upload_path.$filename, $formats, $arr, false, false, ",", false);

	if (!$csv->status())
		return notice($csv->getError(),"list_subscribers");

	$csv->write();

	notice("Content was exported. <a href=\"download.php?file=$filename\">Download</a>", "list_subscribers", true, false);
}

function analize_subscribers_data($new_subscribers, &$res)
{
	$subs = get_subscribers(false,true);
	if ($subs[0])
		$old_subscribers = $subs[1];

	$msisdns = $short_numbers = $iccids = array();
	foreach ($new_subscribers as $imsi=>$subs) {
		if (strlen($subs['msisdn']))
			$msisdns[] = $subs['msisdn'];
		if (strlen($subs['short_number']))
			$short_numbers[] = $subs['short_number'];
		if (isset($subs["iccid"]) && strlen($subs["iccid"]))
			$iccids[] = $subs["iccid"];
		$valid_subs = validate_subscriber($subs);
		if (!$valid_subs[0]) {
			$res["skip"][] = " Skipped IMSI: ".$imsi.". Error: ".$valid_subs[1];
			unset($new_subscribers[$imsi]);
		}

		if (!preg_match("/^[0-9]{14,15}$/", $imsi)) {
			$res["skip"][] = "Skipped IMSI: ".$imsi.". Error: The IMSI must contain only 14 or 15 digits.";
			unset($new_subscribers[$imsi]);
		}
	}

	if (count($msisdns)) {
		$duplicate = array_count_values($msisdns);
		find_duplicate($new_subscribers,$duplicate,'msisdn',$res);
	}

	if (count($short_numbers)) {
		$duplicate = array_count_values($short_numbers);
		find_duplicate($new_subscribers,$duplicate,'short_number',$res);
	}

	if (count($iccids)) {
		$duplicate = array_count_values($iccids);
		find_duplicate($new_subscribers,$duplicate,'iccid',$res);
	}

	if (!count($old_subscribers)) {
		return array( "skip"            => (isset($res["skip"])) ? $res["skip"] : array(), 
					  "override"        => (isset($res["override"])) ? $res["override"] : array(),
					  "new_subscribers" => $new_subscribers);
	}

	$old_imsis = array();
	foreach ($old_subscribers as $imsi=>$old_subs) {
		$old_imsis[] = $imsi;
	}

	foreach ($new_subscribers as $imsi=>$subs) {

		if (in_array($imsi,$old_imsis)) {
			$old_msisdn = isset($old_subscribers[$imsi]["msisdn"]) ? $old_subscribers[$imsi]["msisdn"] : "";
			$old_sn = isset($old_subscribers[$imsi]["short_number"]) ? $old_subscribers[$imsi]["short_number"] : "";
			$old_op = isset($old_subscribers[$imsi]["op"]) ? $old_subscribers[$imsi]["op"] : "";
			$old_opc = isset($old_subscribers[$imsi]["opc"]) ? $old_subscribers[$imsi]["opc"] : "0";
			$old_iccid = isset($old_subscribers[$imsi]["iccid"]) ? $old_subscribers[$imsi]["iccid"] : "";
			if ($subs["msisdn"] != $old_msisdn) {
				$res["override"][] = "Found existing IMSI: ".$imsi." but with a different MSISDN. This will be overridden." ;
			} elseif ($subs["short_number"] != $old_sn) {
				$res["override"][] = "Found existing IMSI: ".$imsi." but with a different Short number. This will be overridden.";
			} elseif ($subs["imsi_type"] != $old_subscribers[$imsi]["imsi_type"]) {
				$res["override"][] = "Found existing IMSI: ".$imsi." but with a different Imsi_type. This will be overridden.";
			} elseif ($subs["ki"] != $old_subscribers[$imsi]["ki"]) {
				 $res["override"][] = "Found existing IMSI: ".$imsi." but with a different KI. This will be overriden.";
			} elseif ($subs["op"] != $old_op) {
				 $res["override"][] = "Found existing IMSI: ".$imsi." but with a different OP. This will be overridden.";
			} elseif (exist_bool_difference($subs["opc"], $old_opc)) {
				 $res["override"][] = "Found existing IMSI: ".$imsi." but with a different OPC. This will be overridden.";
			} elseif (exist_bool_difference($subs["active"], $old_subscribers[$imsi]["active"])) {
				 $res["override"][] = "Found existing IMSI: ".$imsi." but with a different Active. This will be overridden.";
			} elseif (isset($subs["iccid"]) && $old_iccid != $subs["iccid"]) {
				 $res["override"][] = "Found existing IMSI: ".$imsi." but with a different ICCID. This will be overridden.";
			} else {
				$res["skip"][] = " Skipped IMSI: ".$imsi.". No change.";
				unset($new_subscribers[$imsi]);
				continue;
			}
			$new_subscribers[$imsi]["imsi"] = $imsi; // so that this imsi will be edited not added in API
		}
	}

	return array (
	   	"skip"            => (isset($res["skip"])) ? $res["skip"] : array(), 
	    "override"        => (isset($res["override"])) ? $res["override"] : array(),
		"new_subscribers" => $new_subscribers
	);
}

function find_duplicate(&$new_subscribers, $dup_arr, $dup_type, &$res)
{
	$duplicates = array();
	foreach ($dup_arr as $elem=>$dup_no) {
		if ($dup_no>1) { //found duplicated element
			$duplicates[] = $elem;
		}
	}

	if (count($duplicates)) {
		foreach($new_subscribers as $imsi=>$subs) {
			foreach ($duplicates as $k=>$duplicate) {
				if ((isset($subs[$dup_type]) && $subs[$dup_type] == $duplicate) || ($dup_type=="imsi" && $imsi == $duplicate)) {
					$res["skip"][] = "Skipped IMSI: ".$imsi. ". Found ".$dup_type." duplicated.";
					unset($new_subscribers[$imsi]);
				} 
			}
		}
	}
}

function display_intermediate_step($res,$error="",$error_fields=array())
{
	global $method;

	$method = "display_intermediate_step";
	$_SESSION["new_subscribers"] = $res["new_subscribers"];
	$fields = array();
	$i = 0;
	if (isset($res["override"])) {
		foreach ($res["override"] as $k=>$mess) {
			$fields["imsi".($i+$k)] = array("display"=>"message", "value"=>$mess."<br/>", "column_name"=>"");
		}
		$i = count($res["override"])+1;
	}

	foreach ($res["skip"] as $k=>$mess) {
		$fields["imsi".($i+$k)] = array("display"=>"message", "value"=>$mess."<br/>", "column_name"=>"");
	}
	$custom_submit = '<div class="custom_submit"><input type="submit" name="submit_action" value="Continue" /> <input type="submit" name="submit_action" value="Cancel" /> <input type="submit" name="submit_action" value="Re-upload" /></div>';
	$fields["custom_submit"] = array("display"=>"custom_submit", "value"=>$custom_submit, "column_name"=>"");

	error_handle($error,$fields,$error_fields);
	start_form();
	addHidden("actions");
	editObject(NULL,$fields,"Analyze subscribers data from imported file","no",null,true);
	end_form();
}	

function display_intermediate_step_actions()
{
	if (getparam("submit_action") == "Cancel")
		return list_subscribers();
	elseif (getparam("submit_action") == "Re-upload")
		return import_subscribers();

	finish_importing_subscribers($_SESSION["new_subscribers"]);
}

function finish_importing_subscribers($new_subscribers)
{
    //insert subscribers into subscribers.conf
	$imported = 0;
	foreach ($new_subscribers as $imsi=>$data) {
		if ($data["imsi_type"] == "2G") {
			$new_subscribers[$imsi]["op"] = "";
		} elseif ($data["imsi_type"] == "3G" &&  $data["op"] == "") {
			$new_subscribers[$imsi]["op"] = "00000000000000000000000000000000";
		}

		if ($data["opc"] == "1") {
			$data["opc"] = true;
		} else {
			$data["opc"] = false;
		}

		if ($data["active"] == "1") {
			$data["active"] = true;
		} else {
			$data["active"] = false;
		}

		if (!strlen($data["iccid"]))
			unset($data["iccid"]);

		$fields = array("subscribers"=>array($imsi=>$data));
		$res = make_request($fields,"set_nipc_subscribers");
		if ($res["code"]!="0") {
			errormess("Error when importing subscriber with IMSI ".$imsi.". Error [API: ".$res["code"]."] ". $res["message"] ,"no");
			message("Fix error and reupload file.","no");
			break;
		} else {
			$imported += 1;
		}
	}
	notice("Finished importing subscribers. Imported ".$imported." subscribers.", "list_subscribers");
}
?>
