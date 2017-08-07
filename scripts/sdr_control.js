/**
 * sdr_control.js
 * This file is part of the YATE Project http://YATE.null.ro
 *
 * SDR control API
 *
 * Yet Another Telephony Engine - a fully featured software PBX and IVR
 * Copyright (C) 2017 Null Team
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
//#pragma compile "sdr_control.jsc"
//#pragma trace "cachegrind.out.sdr_control"

#require "lib_sdr_api.js"
#require "lib_status.js"

Engine.debugName("api_control");
Message.trackName("api_control");

debug = true;

// Retrieve product status
API.on_get_node_status = function(params,msg)
{
    var bts = false;
    var enb = false;
    var cal = false;
    var m = new Message("engine.status");
    m.module = "calibrate";
    if (m.dispatch(true)) {
	m = m.retValue();
	cal = (0 > m.indexOf("state=Idle"));
    }
    m = new Message("engine.status");
    m.module = "enb";
    if (m.dispatch(true)) {
	m = m.retValue();
	enb = (0 <= m.indexOf("state=Started"));
    }
    m = new Message("engine.command");
    m.line = "ybts status";
    if (m.dispatch(true)) {
	m = m.retValue();
	bts = (0 <= m.indexOf("state=RadioUp"));
    }
    var obj = { operational:true, level:"NOTE" };
    if (cal) {
	obj.operational = false;
	if (bts || enb) {
	    obj.state = "Conflict";
	    obj.level = "WARN";
	}
	else {
	    obj.state = "Calibrating";
	    obj.level = "MILD";
	}
    }
    else if (bts) {
	if (enb)
	    obj.state = "Run BTS+ENB";
	else
	    obj.state = "Running BTS";
    }
    else if (enb)
	obj.state = "Running ENB";
    else {
	obj.operational = false;
	obj.state = "Stopped";
	obj.level = "WARN";
    }
    return { name:"status", object:obj };
};

// Retrieve (sub)product stats
API.on_query_stats = function(params,msg)
{
    var stats = retrieveStats(["bladerf","ybts","mbts","gsmtrx","ys1ap","gtp","sip","yrtp","cdrbuild"]);
    if (!stats)
	return { error:200, reason:"Internal retrieval error." };
    mergeStats(stats,["ybts conn","ybts ue","enb all"]);
    return { name:"stats", object:stats };
};

// Start radio board calibration
API.on_calibrate_start = function(params,msg)
{
    var m = new Message("engine.command");
    m.module = "api.request";
    m.received = msg.received;
    m.line = "calibrate start";
    if (m.dispatch()) {
	var s = m.retValue();
	if (s.endsWith("\r\n"))
	    s = s.substr(0,s.length - 2);
	return {name:"calibrate_start", object:{message:s}};
    }
    if (!isPresent(m.error))
	return apiError(201,"Calibrate module not loaded");
    var error = parseInt(m.error);
    if (isNaN(error))
	return apiError(400,m.error);
    return apiError(error,m.reason);
};

// Poll radio board calibration status
API.on_calibrate_poll = function(params,msg)
{
    var details = true;
    if (params) {
	if (isPresent(params.details)) {
	    details = parseBool(params.details,null);
	    if (null === details)
		return apiErrorInvalid("details",params.details,"not a boolean");
	}
    }
    var m = new Message("engine.status");
    m.module = "calibrate";
    m.details = details;
    m.received = msg.received;
    m.json = true;
    if (!m.dispatch())
	return apiError(201,"Calibrate module not loaded");
    var obj = JSON.parse(m.retValue());
    if (obj)
	return {name:"calibrate_status", object:obj};
    return apiError(400,"Status parse failed");
};

// Find out what scripts are candidates for working mode
API.on_get_available_modes = function(params,msg)
{
    var files = {"nib":"nib", "roaming":"roaming", "dataroam":"dataroam", "enb":"enb-main"};
    var path = Engine.runParams().sharedpath;
    if (path)
	path += "/scripts";
    var conf = new ConfigFile(Engine.configFile("javascript"));
    path = conf.getValue("general","scripts_dir",path);
    path = Engine.replaceParams(path,Engine.runParams());
    var avail = [ ];
    for (var m in files) {
	var f = path + "/" + files[m];
	if (File.exists(f + ".js") || File.exists(f + ".jsc"))
	    avail.push(m);
    }
    return {name:"modes", object:avail};
};

// Handle reload operation
function onReload(msg)
{
    if (msg.plugin && ("api_control" != msg.plugin))
	return false;
    initialize();
    return !!msg.plugin;
}

// Handle rmanager commands
function onCommand(msg)
{
    if (!msg.line) {
	switch (msg.partline) {
	    case "reload":
	    case "debug":
		oneCompletion(msg,"api_control",msg.partword);
		break;
	}
    }
    return false;
}

// Handle debugging commands
function onDebug(msg)
{
    Engine.setDebug(msg.line);
    debug = Engine.debugEnabled();
    msg.retValue("api_control debug " + Engine.debugEnabled() + " level " +
	Engine.debugLevel() + "\r\n");
    return true;
}

function initialize(first)
{
    Engine.output("Initializing module API Control");
//    var cfg = new ConfigFile(Engine.configFile("?????????"),false);
//    var sect = cfg.getSection("?????????",true);
    if (first) {
	Engine.debug(Engine.DebugStub,"Please implement config file handling");
//	Engine.setDebug(sect.getValue("debug"));
//	debug = sect.getBoolValue("debug",Engine.debugEnabled());
    }
}

initialize(true);
Message.install(onReload,"engine.init",120);
Message.install(onCommand,"engine.command",120);
Message.install(onDebug,"engine.debug",150,"module","api_control");
Message.install(onApiRequest,"api.request",90,"type","control");

/* vi: set ts=8 sw=4 sts=4 noet: */
