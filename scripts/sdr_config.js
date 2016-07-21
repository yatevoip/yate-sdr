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
SatSiteConfig.prototype = new GenericConfig;

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
YBladeRfConfig.prototype = new GenericConfig;

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

API.on_get_node_type = function(params,msg)
{
    if (!confs)
	return { error: 201, reason: "Devel/configuration error: no config files defined."};

    // read satsite.conf general section to see if satsite works as enb or as bts
    var satsite_conf = new ConfigFile(Engine.configFile("satsite"));
    var sdr_mode = satsite_conf.getValue("general","sdr_mode","not configured"); 

    if (sdr_mode == "bts") {
	// read mode from ybts.conf
	var ybts_conf = new ConfigFile(Engine.configFile("ybts"));
	sdr_mode = ybts_conf.getValue("ybts","mode","not configured");
    }

    if (confs.indexOf("ybts") >= 0)
	return { name: "node", object: { "type": "bts", "sdr_mode": sdr_mode }};
    else if (confs.indexOf("openenb") >= 0)
	return { name: "node", object: { "type": "enb", "sdr_mode": sdr_mode }};

    return { error: 201, reason: "Missing both ybts as openenb in confs."};
};

API.on_set_sdr_mode = function(params,msg)
{
    if (!confs)
	return { error: 201, reason: "Devel/configuration error: no config files defined."};
    if (!params.sdr_mode)
	return { error: 402, reason: "Missing sdr_mode in request" };

    var error = new Object;
    var bts_modes = ["nib", "roaming", "dataroam"];
    var satsite_conf = new ConfigFile(Engine.configFile("satsite"));

    if (params.sdr_mode == "enb") {
	satsite_conf.setValue("general", "sdr_mode", "enb");
    } else if (bts_modes.indexOf(params.sdr_mode) >= 0) {
	satsite_conf.setValue("general", "sdr_mode", "bts");

	var ybts_conf = new ConfigFile(Engine.configFile("ybts"));
	ybts_conf.setValue("ybts","mode",params.sdr_mode);
	if (!saveConf(error,ybts_conf))
	    return error;
    } else {
	error.error = 201;
    	error.reason = "Invalid sdr_mode '" + params.sdr_mode +  "'.";	
    	return error;
    }
    
    if (!saveConf(error,satsite_conf))
	return error;

    return {};
};

// Implement basic get request
API.on_get_node = function(params,msg)
{
    var func;
    var have_settings;
    var error;

    if (debug)
	dumpObj("on_get_node, json",msg.json);

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
	dumpObj("on_set_node, json",msg.json);

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
    return { name: "node" };
};

// Callback used when handler for api.request message is installed
function onApiRequest(msg)
{
    var func = API["on_" + msg.operation];
    if ("function" != typeof func.apply) {
	if (debug)
	    Engine.output("Undefined function " + func + " in onApiRequest");
	return false;
    }
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
