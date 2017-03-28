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
#require "lib_sdr_api.js"

debug = false;
//  Satsite file configuration object
function SatSiteConfig()
{
    GenericConfig.apply(this);
    this.file = "sdr";
    this.sections = ["basic", "site_equipment", "shutdown"];
    this.overwrite = true;
};
SatSiteConfig.prototype = new GenericConfig;

SatSiteConfig.prototype.validations = {
    "basic": {
	"location"        : {"callback": checkValidGeoLocation},
	"antennaDirection": {"callback": checkValidFloat},
	"antennaBeamwidth": {"minimum": 1, "maximum": 360},
	"reportingPeriod" : {"callback": checkValidInteger}
    },
    "shutdown": {
	"maxVswr":                 {"callback": checkValidFloat},
	"amplifierShutdownTemp":   {"mininum": 1, "maximum": 85},
	"amplifierRestartTemp":    {"mininum": 1, "maximum": 85},
	"powerSupplyShutdownTemp": {"mininum": 1, "maximum": 85},
	"powerSupplyRestartTemp":  {"mininum": 1, "maximum": 85},
	"softwareShutdownTemp":    {"mininum": 1, "maximum": 100},
	"softwareRestartTemp":     {"mininum": 1, "maximum": 85}
    } 
};

// Get configurations from satsite.conf
API.on_get_sdr_node = function(params,msg)
{
    var satsite = new SatSiteConfig;
    return API.on_get_generic_file(satsite,msg);
};

// Configure satsite.conf related parameters
API.on_set_sdr_node = function(params,msg,setNode)
{
    var satsite = new SatSiteConfig;
    return API.on_set_generic_file(satsite,params,msg,setNode);
};

// Ybladerf file configuration object
function YBladeRfConfig()
{
    GenericConfig.apply(this);
    this.file = "ybladerf";
    this.sections = ["general", "libusb", "filedump"];
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

API.on_get_node_type = function(params,msg)
{
    if (!confs)
	return { error: 201, reason: "Devel/configuration error: no config files defined."};

    // read sdr-jscript.conf general to find routing script to see if sdr works as enb or as bts 
    var sdr_jscript = new ConfigFile(Engine.configFile("sdr-jscript"));
    var sdr_mode = sdr_jscript.getValue("general","routing","not configured");
    if (sdr_mode == "welcome.js")
	sdr_mode = "bts";
    else if (sdr_mode == "enb-rrc.js")
	sdr_mode = "enb";
    else
	sdr_mode = "not configured";

    if (sdr_mode == "bts") {
	// read mode from ybts.conf
	var ybts_conf = new ConfigFile(Engine.configFile("ybts"));
	sdr_mode = ybts_conf.getValue("ybts","mode","not configured");
    }

    if (confs.indexOf("ybts") >= 0)
	return { name: "node", object: { "type": "bts", "sdr_mode": sdr_mode }};
    else if (confs.indexOf("yateenb") >= 0)
	return { name: "node", object: { "type": "enb", "sdr_mode": sdr_mode }};

    return { error: 201, reason: "Missing both ybts as yateenb in confs."};
};

API.on_set_sdr_mode = function(params,msg)
{
    if (!confs)
	return { error: 201, reason: "Devel/configuration error: no config files defined."};
    if (!params.sdr_mode)
	return { error: 402, reason: "Missing sdr_mode in request" };

    var error = new Object;
    var bts_modes = ["nib", "roaming", "dataroam"];

    var sdr_jscript = prepareConf("sdr-jscript",msg.received,false);
    var ybts_conf   = prepareConf("ybts",msg.received,false);
    var enb_conf    = prepareConf("yateenb",msg.received,false);
    var gtp_conf    = prepareConf("gtp",msg.received,false);
    var cal_conf    = prepareConf("calibrate",msg.received,false);

    if (params.sdr_mode == "enb") {
	sdr_jscript.setValue("general", "routing", "enb-rrc.js");
	ybts_conf.setValue("ybts","autostart",false);
	enb_conf.setValue("general","autostart",true);
	cal_conf.setValue("general","mode","enb");
	gtp_conf.setValue("ran_u","enabled",true);
	gtp_conf.setValue("ran_c","enabled",false);

    } else if (bts_modes.indexOf(params.sdr_mode) >= 0) {
	sdr_jscript.setValue("general", "routing", "welcome.js");
	ybts_conf.setValue("ybts","mode",params.sdr_mode);
	ybts_conf.setValue("ybts","autostart",true);
	enb_conf.setValue("general","autostart",false);
	cal_conf.setValue("general","mode","bts");

	if (params.sdr_mode == "nib" || params.sdr_mode == "roaming") {
	    gtp_conf.setValue("ran_u","enabled",false);
	    gtp_conf.setValue("ran_c","enabled",false);
	} else {
	    gtp_conf.setValue("ran_u","enabled",true);
	    gtp_conf.setValue("ran_c","enabled",true);
	}
	
    } else {
	error.error = 201;
	error.reason = "Invalid sdr_mode '" + params.sdr_mode +  "'.";
	return error;
    }
    
    if (!saveConf(error,sdr_jscript))
	return error;
    if (!saveConf(error,ybts_conf))
	return error;
    if (!saveConf(error,enb_conf))
	return error;
    if (!saveConf(error,gtp_conf))
	return error;

    Engine.output("Restart on node config: " + msg.received);
    Engine.restart();
    return {};
};

// Implement basic get request
API.on_get_node = function(params,msg)
{
    var have_settings;

    if (debug)
	dumpObj("on_get_node, json",msg.json);

    json = {};
    for (var conf of confs) {
	var func = API["on_get_"+conf+"_node"];
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
   var config = new ConfigFile(Engine.configFile("sdr"));

   if (first) {
	var tmp = config.getBoolValue("general","debug");
	if ("" != tmp)
	    Engine.setDebug(tmp);
	debug = Engine.debugEnabled();
    }
}
/* vi: set ts=8 sw=4 sts=4 noet: */
