<?php
/**
 * bts_configuration.php
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

require_once("ybts/ybts_menu.php");
require_once("ybts/lib_ybts.php");

global $node;

$node = "satsite";

function bts_configuration()
{
	global $section, $subsection;

	$res = test_default_config();
	if (!$res[0]) {//permission errors
		errormess($res[1], "no");
		return;
	}

?>
<table class="page" cellspacing="0" cellpadding="0">
<tr>
    <td class="menu" colspan="2"><?php ybts_menu();?></td>
<tr>
    <td class="content_form"><?php create_form_ybts_section($section, $subsection); ?></td>
    <td class="content_info"><?php description_ybts_section(); ?></td>
</tr>
<tr><td class="page_space" colspan="2"> &nbsp;</td></tr>
</table>
<?php
}

function bts_configuration_database()
{
	global $section, $subsection, $module;

	$structure = get_fields_structure_from_menu();
	$errors_found = false;
	$warnings = "";
	$fields = array();
	foreach ($structure as $m_section => $data) {
		foreach($data as $key => $m_subsection) {
			Debug::xdebug($module,"Subsection $m_subsection");
			$res = validate_fields_ybts($m_section, $m_subsection);
			if (!$res[0]) { 
				$errors_found = true;
				$section = $m_section;
				$subsection = $m_subsection;
				$_SESSION["section"] = $m_section;
				$_SESSION["subsection"] = $m_subsection;
				break;
			} else {
				$fields = array_merge($fields, $res["request_fields"]);  
			}
			if (isset($res["warning"])) {
				$warnings .= $res["warning"];
			}
		}
		if ($errors_found)
		       	break;
	}

	if (!$errors_found) {
		$ybts_fields_modified = get_status_fields($structure);
		if ($ybts_fields_modified) {

			//if no errors encountered on validate data fields then send API request

			$c0 = $fields['gsm']['Radio.C0'];
			$c0 = explode("-",$c0);
			$c0 = $c0[1];
			$fields['gsm']['Radio.C0'] = $c0;

			$fields['gprs_roaming']['nnsf_bits'] = $fields['gprs_roaming']['gprs_nnsf_bits'];
			unset($fields['gprs_roaming']['gprs_nnsf_bits']);


			$network_map = $fields['gprs_roaming']['network_map'];
			$network_map = explode("\r\n",$network_map);
			unset($fields['gprs_roaming']['network_map']);
			foreach ($network_map as $assoc) {
				$assoc = explode("=",$assoc);
				if (count($assoc)!=2)
					continue;
				$fields['gprs_roaming'][$assoc[0]] = trim($assoc[1]);
			}

			$fields = array("ybts"=>$fields);
			$res = make_request($fields, "set_bts_node");

			if (!isset($res["code"]) || $res["code"]!=0) {

				// find subsection where error was detected so it can be opened
				$pos_section = strrpos($res["message"],"'",-15);
				$subsection = substr($res["message"],$pos_section+1);
				$subsection = substr($subsection,0,strpos($subsection,"'"));

				$section = find_section($subsection);
				if (!$section) {
					$section = "GSM";
					$subsection = "gsm";
				}

				$_SESSION["subsection"] = $subsection;
				$_SESSION["section"] = $section;

				$fields_sect_error = true;
				$error = $res["message"];
			} else {
				$fields_sect_error = false;
				$error = NULL;
				unset($_SESSION["section"], $_SESSION["subsection"]);
				
				/*$res = set_codecs_ysipchan(getparam("mode"));
				if (!$res[0]) {
					errormess($res[1]);
				}*/
			}

		}
	}

?>
<table class="page" cellspacing="0" cellpadding="0">
<tr>
    <td class="menu" colspan="2"><?php ybts_menu();?>
<tr> 
	<td class="content_form"><?php 
	if ($errors_found) {
		if (strlen($warnings))
			message("Warning! ".$warnings, "no");
		create_form_ybts_section($section, $subsection, true, $res["error"], $res["error_fields"]);
	}
	else {
		if (!$ybts_fields_modified) {
			print "<div id=\"notice_$subsection\">";
                        message("Finish editing sections. Nothing to update in ybts.conf file.", "no");
                        print "</div>";
			create_form_ybts_section($section, $subsection);
	?></td>
    	<td class="content_info"><?php description_ybts_section(); ?></td>
</tr>
<tr><td class="page_space" colspan="2"> &nbsp;</td></tr>
</table>
<?php
			return;
		}

		if (isset($res["code"]) && $res["code"]=="0") {
			print "<div id=\"notice_$subsection\">";
			message("Finished configuring BTS.", "no");
			print "</div>";


		}

		if (strlen($warnings))
			message("Warning! ".$warnings,"no");
;
		create_form_ybts_section($section, $subsection, $fields_sect_error, $error);
 }
?></td>
    <td class="content_info"><?php description_ybts_section(); ?></td>
</tr>
<tr><td class="page_space" colspan="2"> &nbsp;</td></tr>
</table>
<?php
}

function find_section($subsection)
{
	$menu = get_menu_structure();
	$subsection = str_replace("_", " ",strtolower($subsection));

	foreach ($menu as $section=>$subsections) {
		foreach ($subsections as $menu_subsection)
			if ($subsection == strtolower($menu_subsection))
				return $section;
	}

	return;
}

function set_codecs_ysipchan($mode)
{
	global $yate_conf_dir;

	$filename = $yate_conf_dir. "ysipchan.conf";
	if (is_file($yate_conf_dir. "ysipchan.conf"))
		$file = new ConfFile($filename,true,true);
	else
		$file = new ConfFile($filename,false,true);

	if ($mode=="nib") {
		$file->structure["codecs"] = array();
		$file->structure["codecs"]["default"] = "enable";
		$mess = "default=enable";
	} else {
		$file->structure["codecs"]["default"] = "disable";
		$file->structure["codecs"]["gsm"] = "enable";
		$mess = "<br/>default=disable<br/>gsm=enable<br/>";
	}

	$file->save();
	if (!$file->status())
		return array(false, "Could not set $mess"." in [codecs] section in ysipchan.conf: ".$file->getError());
	return array(true);
}

?>
