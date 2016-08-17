<?php
/**
 * enb_configuration.php
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

require_once("ansql/sdr_config/enb_tabbed_settings.php");
require_once("lib/lib_requests.php");

global $node;

$node = "satsite";

function enb_configuration()
{
	global $section, $subsection;

	$form = new EnbTabbedSettings();
	$form->displayTabbedSettings();
}

function enb_configuration_database()
{
	$form = new EnbTabbedSettings();
	$res = $form->applyFormResults();
}
?>
