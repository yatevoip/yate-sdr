/**
 * sdr_config.js
 * This file is part of the YATE Project http://YATE.null.ro
 *
 * Yet Another Telephony Engine - a fully featured software PBX and IVR
 * Copyright (C) 2014-2015 Null Team
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

//#pragma cache "true"
//#pragma compile "sdr_config.jsc"
//#pragma trace "cachegrind.out.sdr_config"

#require "generic_config.js"

//  Satsite file configuration object
function SatSiteConfig()
{
    GenericConfig.apply(this);
    this.file = "satsite";
    this.sections = ["basic", "site-equipment", "shutdown"];
    this.overwrite = false;
};
SatSiteConfig.prototype = GenericConfig.prototype;

// Get configurations from satsite.conf
API.on_get_satsite_node = function(params,msg)
{
    var satsite = new SatSiteConfig;
    return API.on_get_generic_file(satsite,msg);
};

// Configure satsite.conf related parameters
API.on_set_satsite_node = function(params,msg,setNode)
{
    var satsite = new SatSiteConfig;
    return API.on_set_generic_file(satsite,params,msg,setNode);
};

// Ybladerf file configuration object
function YBladeRfConfig()
{
    GenericConfig.apply(this);
    this.file = "ybladerf";
    this.sections = ["general", "libusb", "filedump", "test_name"];
    this.overwrite = false;
}
YBladeRfConfig.prototype = GenericConfig.prototype;

// Get configurations from ybladerfconf
API.on_get_ybladerf_node = function(params,msg)
{
    var ybladerf = new YBladeRfConfig;
    return API.on_get_generic_file(ybladerf,msg);
};

// Configure ybladerf.conf related parameters
API.on_set_ybladerf_node = function(params,msg,setNode)
{
    var ybladerf = new YBladeRfConfig;
    return API.on_set_generic_file(ybladerf,params,msg,setNode);
};

function checkJson(error,params,json)
{
    if (params)
    	return true;
    if (json) {
    	error.reason = "Invalid JSON content.";
    	error.error = 401;
    }
    else {
    	error.reason = "Missing all parameters.";
    	error.error = 402;
    }
    return false;
}

// Implement basic get request
API.on_get_node = function(params,msg)
{
    var func;
    var have_settings;
    var error;

    json = {};
    for (var conf of confs) {
    	func = API["on_get_"+conf+"_node"];
	if ("function" != typeof func.apply) 
    	    continue;

    	json[conf] = func(params);
    	have_settings = true;
    }
    return { name: "node", object: json };
};

// Configure node params
API.on_set_node = function(params,msg)
{
    if (debug)
	dumpObj("on_set_node (params)",msg.json);

    var error = new Object;
    if (!checkJson(error,params,msg.json))
	return error;

    var confParams;
    var func;
    var error;
    var have_settings = false;

    for (var conf of confs) {
	if (debug)
	    Engine.debug(Engine.DebugAll, "Searching config params for file " + conf);
	confParams = params[conf];
	if (!confParams)
	    continue;
	func = API["on_set_"+conf+"_node"];
	if ("function" != typeof func.apply) {
	    var mess_reason = "Missing support for configuring " + conf;
	    return { reason: mess_reason, error: 201 };
	}
	error = func(confParams,msg,true);
	if (error.reason) {
	    error.reason = conf + ": " + error.reason;
	    return error;
	}
	have_settings = true;
    }

    if (!have_settings)
	return { reason: "Missing configuration parameters.", error: 402 };

    if (parseBool(params.restart)) {
	Engine.output("Equipment restart on node config: " + msg.received);
	Engine.restart();
    }
    return { name: "node", object: conf_node };
};

// Callback used when handler for api.request message is installed
function onApiRequest(msg)
{
    var func = API["on_" + msg.operation];
    if ("function" != typeof func.apply)
	return false;
    var res = func(JSON.parse(msg.json),msg);
    if (!res)
	return false;
    if ("" != res.reason) {
	msg.retValue("-");
	msg.error = res.error;
	msg.reason = res.reason;
	if (debug)
	    Engine.debug(Engine.DebugNote,
		msg.operation + " failed: " + res.error + " '" + res.reason + "'");
    }
    else {
	msg.retValue(res.name);
	msg.json = JSON.stringify(res.object);
	if (debug)
	    Engine.debug(Engine.DebugInfo,msg.operation + " succeeded");
    }
    return true;
}

// Callback used when handler for engine.init message is installed
function onReload(msg)
{
    if (msg.plugin && ("api_config" != msg.plugin))
	return false;
    loadCfg();
    return !!msg.plugin;
}

function loadCfg(first)
{
    config.load(true);
    if (first) {
	var tmp = config.getValue("debug");
	if ("" != tmp)
	    Engine.setDebug(tmp);
	debug = Engine.debugEnabled();
    }
}
/* vi: set ts=8 sw=4 sts=4 noet: */
