<?php
/**
 * menu.php
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

include ("structure.php");

global $module, $method, $support, $level, $do_not_load, $iframe, $working_mode;

function get_login_form()
{
	global $login, $link;
?>
	<div class="login-div">
	<form action="index.php" method="post" name="login" id="login">
	    <fieldset class="login" border="1">
	        <legend class="login">Login</legend>
<?php
	if ($login)
		print $login;
	else
		print "<p>&nbsp;</p>";
?>
		<p class="wellcome_to">Welcome!</p>
		<p align="right"><label>Username:&nbsp;</label><input type="text" name="username" id="username" size="19"/></p>
		<p align="right"><label>Password:&nbsp;</label><input type="password" name="password" id="password" size="19" /></p>
		<p align="right"><input type="submit" value="Send" class="submit"/></p>
		<div align="center">
<?php
	/*      $sigur = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'];
	 *      $s1 = $sigur ? "Cripted SSL" : "Uncripted";
	 *      $s2 = $sigur ? "deactivate" : "secure";
	 *      $l = $sigur ? "http://" : "https://";
	 *      $l .= $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
	 *      print "<b>$s1</b> <a  class=\"signup\" href=\"$l\">$s2</a>";*/
?>
	        </div>
	    </fieldset>
	</form>
	</div>
<?php
}

function get_content()
{
	global $module,$dir,$support,$iframe,$function_called,$module_db_identifier,$default_server,$default_ip;
	global $method;
	global $proj_title;
	global $dump_request_params;
?>
	<table class="container" cellspacing="0" cellpadding="0">
		<tr>
			<td class="holdlogo">
				<div class="left_upperbanner"><img src="images/yatesdr_lmi_logo.png" title="NIB Logo" /></div>
			</td>

			<td class="holdstatus">
<?php
			display_node_status();
?>
			</td>
			<td class="holdlinks">

				<div class="right_upperbanner">Welcome</div>
				<div class="download_config">
					<a class="llink" href="download.php?method=config&module=<?php print $module;?>">Download configuration</a>
					 <a class="llink" href="main.php?method=force_calibration">Force calibration</a>
				</div>
				<div class="error_reporting">
				    <?php Debug::button_trigger_report(); ?>
				</div>
				<div class="version">Version: <?php print get_version();?></div>
		</td></tr>
<!--		<tr>
			<td class="upperbanner">
				<div class="upperbanner">
				Welcome	<font class="bluefont"> 
<?php
	/*if(isset($_SESSION["real_user"]))
		print $_SESSION["real_user"]. ". You are currently logged in as ";
	print $_SESSION["username"];
?>
					</font>
					!&nbsp;&nbsp;<a class="uplink" href="index.php">Logout</a>&nbsp;&nbsp;
<?php
	if(isset($_SESSION["real_user"]))
		print '<a class="uplink" href="main.php?method=stop_impersonate">Return&nbsp;to&nbsp;your&nbsp;account</a>';
?>&nbsp;&nbsp;<?php
	print set_contexts();*/
?>
				</div>
			</td>
			<td class="version">/* print get_version();*/</td>
		</tr>-->
	</table>
	<div class="position"> <br/> </div>
	<table class="firstmenu" cellpadding="0" cellspacing="0">
		<tr>
			<?php menu(); ?>
		</tr>
	</table>
	<?php submenu();?>
	<table class="holdcontent" cellspacing="0" cellpadding="0">
        <?php
        if (isset($dump_request_params) && $dump_request_params) {
                print "<tr id='dumped_request' style='display:none;'><td class=\"holdcontent\">";
                print_r ($_REQUEST);
                print "</td></tr>";
        }
        ?>
		<tr>
		<td class="holdcontent <?php print $method;?>">
	<?php
	if (in_array($method,array("form_bug_report","send_bug_report","clear_triggered_error"))) {
		call_user_func('Debug::'.$method);
		if ($method=="send_bug_report") 
			message("Thank you for submitting bug report.");
		elseif ($method=="clear_triggered_error")
			load_page($_SESSION["main"]."?module=$module");
		
	} elseif ($method == "download_config_error") {
		errormess(getparam("errormess"));
	} else {

	$load = ($module == "HOME") ? "home" : $module;
	if ($module) {
		if (is_file("modules/$dir/$load.php"))
			include("modules/$dir/$load.php");

		$call = get_default_function();
		if (!isset($function_called) || !$function_called && function_exists($call) ) {
			$call();
		}
	}
	}?>
			</td>
		</tr>
	</table>
<?php
}

function menu()
{
	global $level,$support, $module, $working_mode, $sdr_mode, $devel_mode;

	$names = array();
	if ($handle = opendir("modules/$level/")) {
		while (false !== ($file = readdir($handle))) {
			if (substr($file,-4) != ".php")
				continue;
			if (stripos($file,".php") === false)
				continue;
			if($file == "home.php" || $file=="subscribers.php")
				continue;
			$names[] = preg_replace('/.php/i','',$file);
		}
		closedir($handle);
	}
	sort($names);
	if(is_file("modules/$level/home.php"))
		$names = array_merge(array("home"), $names);
	if(is_file("modules/$level/subscribers.php"))
		$names = array_merge(array("subscribers"), $names);

	$all_modules = $names;

        $working_mode = get_working_mode();
        $sdr_mode = $working_mode;
        
	// instead of loading all files from modules/default directory, define menu based on working_mode
	$modules_per_mode = array(
		"" => array("working_mode"),
		"not configured" => array("working_mode"),
		"nib" => array("working_mode", "subscribers", "bts_configuration", "outbound", "call_logs"),
		"roaming" => array("working_mode", "bts_configuration"),
		"dataroam" => array("working_mode", "bts_configuration"),
		"enb" => array("working_mode", "enb_configuration"),
	);

	if (!$devel_mode) {

		if (!isset($modules_per_mode[$working_mode])) 
			return errormess("Invalid working mode '".$working_mode."'", "no");

		$names = $modules_per_mode[$working_mode];
	}

	//array with the structure name (files) and the new name that will be displayed
	$change_structure_names = array("bts_configuration" => "BTS_Configuration", "enb_configuration" => "ENB_Configuration");
	$i = 0;
	foreach ($names as $name) {
		if (dont_load($name) || $name == "verify_settings")
			continue;
		if ($i)
			print "<td class=\"separator\">&nbsp;</td>";

		if ($name == $module) {
			print "<td class=\"firstmenu_selected\">";
			print '<div  class="linkselected" onclick="location.href=\'main.php?module='.$name.'\'">';
		} else {
			print "<td class=\"firstmenu\">";
			print '<div class="link" onclick="location.href=\'main.php?module='.$name.'\'">';
		}
		if (isset($change_structure_names[$name]))
			$name = $change_structure_names[$name];
		print str_replace(" ","&nbsp;",ucwords(str_replace("_"," ",$name))).'</div>';

		print "</td>";
		$i++;
	}

	foreach ($all_modules as $name) {
		if (in_array($name,$names))
			continue;
		print "<td class=\"separator\">&nbsp;</td>";
		if (isset($change_structure_names[$name]))
			$name = $change_structure_names[$name];
		$name = str_replace(" ","&nbsp;",ucwords(str_replace("_"," ",$name)));
		print "<td class=\"menu_unavailable\"><div class='link_unavailable'>".$name."</div></td>";
	}
	print("<td class=\"fillspace\">&nbsp;</td>");
}

function dont_load($name)
{
	global $do_not_load;

	if (!is_array($do_not_load))
		return false;

	for($i=0; $i<count($do_not_load); $i++) {
		if ($do_not_load[$i] == $name)
			return true;
	}

	return false;
}

function submenu()
{
	global $module,$dir,$struct,$method,$support,$block;

	if(!isset($struct[$dir.'_'.$module]))
		return;
	$i = 0;
	$max = 10;
	print '<table class="secondmenu">
		<tr>';
	print '<td class="padd">&nbsp;</td>';
	if(!$method) {
		if(in_array("manage", $struct["$dir"."_".$module]))
			$method = "manage";
		elseif(in_array($module, $struct["$dir"."_".$module]))
			$method = $module;
		elseif (isset($struct["$dir"."_".$module][0]))
			$method = $struct["$dir"."_".$module][0];
		else {
			$method = $module;
		}
	}

	$change_structure_names = array("country_code_and_smsc"=>"Country&nbsp;Code&nbsp;and&nbsp;SMSC");

	foreach($struct["$dir"."_".$module] as $option) {
		/*      $res = submenu_check($dir,$module,$option);
		 *                      if(!$res)
		 *                                              continue;*/
		if($i % $max == 0 && $i){
			print("<td class=\"fillfree\">&nbsp;</td>");
			print '</tr><tr>';
		}
		$printed = false;
		if(isset($block["$dir"."_".$module]))
			if(in_array($option, $block["$dir"."_".$module])) {
				print("<td class=\"option\"><a class=\"secondlinkinactive\">");
				$printed = true;
			}
		if($method == $option && !$printed)
			print("<td class=\"option\"><a class=\"secondlinkselected\" href=\"main.php?module=$module&method=$option\">");//.strtoupper($option)."</a></td>");
		elseif(!$printed)
			print("<td class=\"option\"><a class=\"secondlink\" href=\"main.php?module=$module&method=$option\">");//.strtoupper($option)."</a></td>");

		if (isset($change_structure_names[$option]))
			print $change_structure_names[$option];
		else
			print str_replace(" ","&nbsp;",ucwords(str_replace("_"," ",$option)));
		print("</a></td><td class=\"option_separator\"><div></div></td>");
		$i++;
	}
	print("<td class=\"fillfree\" colspan=\"$max\">&nbsp;</td>");
	print "</tr></table>";
}

function get_version()
{
	if (is_file("version.php")) {
		include ("version.php");
		return $version;
	} elseif (is_file("../version.php")) {
		include ("../version.php");
		return $version;
	} else {
		$rev = "";
		exec("svn info 2>/dev/null | sed -n 's,^Revision: *,,p'",$rev);
		if (!is_array($rev) || !isset($rev[0]))
			return "Could not detect version";
		else
			return "svn rev. ".$rev[0];
	}
}

function force_calibration()
{
	print "Are you sure you want to force calibration? Yate will be automatically restarted after calibration is finished.";
	print "<br/><br/>";

	$link = "main.php?method=force_calibration&action=database";
	print '<a class="llink" href="'.htmlentities($link).'">Yes</a>';

	print '&nbsp;&nbsp;&nbsp;&nbsp;';

	$link = $_SESSION["main"].'?';
	if (isset($_SESSION["previous_page"])) {
		foreach ($_SESSION["previous_page"] as $param=>$value)
			$link .= "$param=$value&";
	}
	print '<a class="llink" href="'.htmlentities($link).'">No</a>';
}

function force_calibration_database()
{
	$res = request_api(array(), "calibrate_start");
	notice("Finished forcing calibration.", "working_mode");
}

function display_node_status()
{
	$res = node_status();
	print "<table class='node_status' cellpadding='0' cellspacing='0'>";
	print "<tr><td class='node_status_upper' colspan='3'></td><td></td></tr>";
	print "<tr><td class='node_status_lower' colspan='3'></td><td></td></tr>";
	print "<tr><td class='node_status'>";
	print "Status";

	print "</td>";
	print "<td class='node_line'>";
	print "<img id='sdr_line' alt='Status line' src='images/node_status_line.png' />";;
	print "</td>";
	print "<td class='node_state_".$res["color"]."' id='sdr_state'>";
	print "<img id='sdr_bullet' alt='State bullet' src='images/node_state_".$res["color"].".png' />";
	print $res["state"];
	print "</td>";
	print "<td class='node_ask' id='node_link'>";
	if ($res["color"]=='green')
		print "<a class='llink' href='main.php?method=show_node_details&module=none'>Details</a>";
	print "</td>";
//	print "<td class='node_ask' onclick='show_hide(\"sdr_desc\");'>";
//	print "<img alt='State description' src='images/state_question_mark.png' />";
//	print "</td>";
	print "</tr>";
	print "</table>";
	
//	print "<div class='node_desc' id='sdr_desc' style='display:none;'> Node status.";
	
//	if ($res["details"])
//		print "<a class='llink' href='main.php?method=show_node_details&module=none'>Details</a>";
//	print "</div>";
}
?>
