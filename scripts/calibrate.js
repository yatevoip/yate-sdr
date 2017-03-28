/**
 * calibrate.js
 * This file is part of the YATE Project http://YATE.null.ro
 *
 * Radio calibration management module
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

#require "lib_str_util.js"

Engine.debugName("calibrate");
Message.trackName("calibrate");

module = "";                             // Radio module name
product = "";                            // Product name (used in debug)
productRunning = undefined;              // Module (product) is not idle (not started)
productStartLine = "";                   // Product start command line
productStopLine = "";                    // Product stop command line
calToutId = undefined;                   // Postponed calibration timeout ID
device = null;                           // Known (tracked) device
calibration = null;                      // Calibration process data and functions
lastCalOk = null;                        // Last succesfull calibration result
lastCalFailed = null;                    // Last failed calibration
engineStop = 0;                          // engine.stop counter
calParamsSkip = ["updated"];             // Config params to skip when returning calibration data
// Configurable params
calibration_file = "";                   // Calibration file
auto_calibration = true;                 // Enable auto calibration
freqoffs_calibration = true;             // Enable frequency offset calibration

cmds = [
    {
	name: "start",
	params: "[force={yes|NO}] [save={YES|no}] [frequency= samplerate= filter=]",
	info: "Start calibration",
	desc: "",
    },
    {
	name: "stop",
	params: "",
	info: "Stop calibration",
	desc: "",
    },
];

// Retrieve command description string. Build it if not already done
function cmdDesc(c)
{
    if (!c.desc) {
	c.desc = "  calibrate " + c.name;
	if (c.params)
	    c.desc += " " + c.params;
	c.desc += "\r\n" + c.info + "\r\n";
    }
    return c.desc;
}

// Build an object from line parameters (param=value ....)
function parseParams(line)
{
    if (!line)
	return null;
    var p = {};
    while (line) {
	var m = line.match(/^(.* )?([^= ]+)=([^=]*)$/);
	if (!m) {
	    p[line] = "";
	    break;
	}
	var s = m[3];
	p[m[2]] = s.trim();
	line = m[1];
    }
    return p;
}

function durationSec(ms)
{
    return (ms / 1000) + "." + (ms % 1000) + "sec";
}

// Build calibration section name for board + application combination
function appSectionName(serial,obj)
{
    return serial + "_" + obj.frequency + "_" + obj.samplerate + "_" + obj.filter;
}

// Copy section parameters to destination
function copySection(dest,sect,skipParams)
{
    var keys = sect.keys();
    for (var i = 0; i < keys.length; i++) {
	var k = keys[i];
	if (skipParams)
	    if (skipParams.indexOf(k) >= 0)
		continue;
	dest[k] = sect.getValue(k);
    }
}

// Copy 'src' to 'dest'
function copyProps(dest,src,destPrefix)
{
    if (!(dest && src))
	return;
    if (destPrefix) {
	for (var p in src)
	    dest[destPrefix + p] = src[p];
    }
    else {
	for (var p in src)
	    dest[p] = src[p];
    }
}

// Build prop=obj[prop] string
function props2str(obj,sep)
{
    if (!obj)
	return "";
    if (!sep)
	sep = ",";
    var s;
    for (var p in obj)
	s += sep + p + "=" + obj[p];
    return s.substr(sep.length);
}

// calToutId timeout handler
function onCalToutId()
{
    Calibration.check(Calibration.EvTimer);
    calToutId = undefined;
}

// (re)set calToutId timer
function setCalTimeout(interval)
{
    if (undefined != calToutId) {
	Engine.clearTimeout(calToutId);
	calToutId = undefined;
    }
    if (interval)
	calToutId = Engine.setTimeout(onCalToutId,interval);
}

// Set product running state. Return true if changed
function setProductRunning(state)
{
    if (!module) {
	productRunning = undefined;
	return;
    }
    var old = productRunning;
    if (undefined !== state) {
	switch (module) {
	    case "ybts":
		productRunning = ("Idle" !== state);
		break;
	    case "enb":
		productRunning = ("Started" === state);
		break;
	    default:
		Engine.debug(Engine.DebugStub,
		    "Unable to detect " + product + " running state (module=" + module + ")");
		return false;
	}
    }
    else
	productRunning = false;
    if (productRunning === old)
	return false;
    if (debug) {
	var s = "";
	if (!productRunning)
	    s = "not ";
	Engine.debug(Engine.DebugNote,product + " is " + s + "running");
    }
    return true;
}

// Force product running state update
function updateProductRunning(first)
{
    if (!module) {
	setProductRunning();
	return;
    }
    var m = new Message("engine.command");
    m.module = Engine.debugName();
    m.line = module + " status";
    var state;
    if (m.dispatch()) {
	var s = "" + m.retValue();
	var m = s.match(/^.*[,;]*state=([[:alpha:]]*)[ ,;]?.*$/);
	if (m)
	    state = m[1];
	else
	    Engine.debug(Engine.DebugWarn,"Failed to detect " + product +
		" running state module='" + module + "' status: '" + s + "'");
    }
    else if (!first)
	// Don't warn on first load: the module may not be loaded anyway
	Engine.debug(Engine.DebugWarn,"Failed to detect " + product +
	    " running state module='" + module + "'. Module not loaded?");
    setProductRunning(state);
}

// Save data in config file
function saveCalConfig(section,props,what,updated,newSect)
{
    if (!props)
	return true;
    if (!section) {
	Engine.debug(Engine.DebugNote,"Failed to save configuration for " + what +
	    ": missing section name (serial?)");
	return false;
    }
    var cfg = new ConfigFile(calibration_file,false);
    if (newSect)
	cfg.clearSection(section);
    var sect = cfg.getSection(section,true);
    if (updated)
	sect.setValue("updated",updated);
    else
	sect.clearKey("updated");
    for (var p in props)
	sect.setValue(p,props[p]);
    if (cfg.save()) {
	if (debug)
	    Engine.debug(Engine.DebugInfo,"Saved " + what + " section='" + section + "'");
	return true;
    }
    Engine.debug(Engine.DebugNote,"Failed to save configuration for " + what +
	" in section='" + section + "'");
    return false;
}


//
// Calibration processing object
//
function Calibration(source,params,first,dontSave)
{
    this.source = source;
    this.createTime = Date.now();
    this.first = first;
    this.dontSave = dontSave;
    this.loop = 0;
    this.params = params;
    this.info = props2str(params," ");
    this.productStart = undefined;       // Start product when terminated
    this.calAutoDisabled = false;
    this.init();
};
Calibration.prototype = new Object;
// State
Calibration.Idle = 0;                    // Initial (idle) state
Calibration.Postponed = 1;               // Postponed, waiting for timer
Calibration.WaitRadioStop = 2;           // Waiting for radio to stop
Calibration.WaitCalibrate = 3;           // Waiting for calibration to terminate
Calibration.stateName = ["Idle", "Postponed", "WaitRadioStop", "WaitCalibrate"];
// Notification events
Calibration.EvTimer = 0;                 // Timer
Calibration.EvProductStateChanged = 1;   // Product state changed
Calibration.EvCalTerminated = 2;         // Calibration terminated
Calibration.EvChanNotify = 3;            // chan.notify handler

// (re)init calibration process
Calibration.prototype.init = function()
{
    this.loop++;
    this.endTime = 0;
    this.state = Calibration.Postponed;
    this.waitRadioStopIndex = 0;
    this.notifyId = undefined;           // Notify ID for radio test worker
    this.calParams = null;               // Calibration parameters (result)
    if (this.loop === 1) {
	this.startTime = this.createTime;
	var extra;
	if (this.source.name) {
	    var s = this.source.name;
	    for (var p in this.source) {
		if ("name" !== p)
		    s += " " + p + "='" + this.source[p] + "'";
	    }
	    extra = " requested by '" + s + "'";
	}
	Engine.debug(Engine.DebugCall,"Postponing calibration for " + this.info + extra);
	setCalTimeout(1);
    }
    else {
	this.startTime = Date.now();
	var interval = this.loop * 500;
	if (interval > 60000)
	    interval = 60000;
	Engine.debug(Engine.DebugCall,"Restarting (#" + this.loop + ") calibration for " +
	    this.info + " in " + interval + "ms");
	setCalTimeout(interval);
    }
};

// Calibration ended: update data, log debug messages
// Disable auto calibration on specific failures
Calibration.prototype.ended = function(error)
{
    if (this.endTime)
	return;
    this.endTime = Date.now();
    // Duplicate some info: remember it
    var last = {};
    copyProps(last,this.source,"source_");
    last.start_time = this.startTime;
    last.end_time = this.endTime;
    copyProps(last,this.params);
    if (!error)
	copyProps(last,this.calParams,"cal_");
    else
	last.error = error;
    var duration = durationSec(this.endTime - this.startTime);
    if (!error) {
	Engine.debug(Engine.DebugCall,"Calibration ended duration=" + duration);
	lastCalOk = last;
	saveCalConfig("last_calibration",last,null,null,true);
	if (this.calAutoDisabled) {
	    Engine.debug(Engine.DebugNote,"Re-enable automatic calibration");
	    auto_calibration = true;
	    this.calAutoDisabled = false;
	}
    }
    else {
	Engine.debug(Engine.DebugCall,"Calibration ended (duration=" + duration + ") error: " + error);
	lastCalFailed = last;
	saveCalConfig("last_failed_calibration",last,null,null,true);
	// Disable auto calibration in some odd situations
	if (auto_calibration &&
	    (this.state === Calibration.Postponed ||        // Failed to stop radio module
	    this.state === Calibration.WaitRadioStop)) {    // Failed to start calibration
	    Engine.alarm("system",Engine.DebugWarn,error +
		": disabling automatic calibration, waiting for reload or subsequent success");
	    auto_calibration = false;
	    this.calAutoDisabled = true;
	}
    }
    // TODO: save to log file ?
};

// Process calibration
Calibration.prototype.process = function(event,error,params)
{
    //Engine.debug(Engine.DebugAll,"Calibration.process() event=" + event + " error=" + error);
    if (event === Calibration.EvProductStateChanged) {
	if (productRunning && this.state !== Calibration.Postponed) {
	    // This should not happen:
	    // product changed state from idle to non idle while we are operating
	    Engine.debug(Engine.DebugWarn,product + " started in calibration state " +
		Calibration.stateName[this.state]);
	    return true;
	}
    }
    if (this.state === Calibration.Postponed) {
	// Update current state. Remember to restart if already started
	if (undefined === this.productStart) {
	    updateProductRunning();
	    this.productStart = productRunning;
	}
	// Stop the product
	if (!Calibration.productControl(false)) {
	    this.ended("Failed to stop product (radio) module");
	    return false;
	}
	this.state = Calibration.WaitRadioStop;
    }
    if (this.state === Calibration.WaitRadioStop) {
	// Calibration is waiting for product to become idle
	if (productRunning) {
	    this.waitRadioStopIndex++;
	    var level;
	    if (2 === this.waitRadioStopIndex)
		level = Engine.DebugAll;
	    else if (4 === this.waitRadioStopIndex)
		level = Engine.DebugNote;
	    else if (301 <= this.waitRadioStopIndex) {
		// TODO: What if not stopped after some long period?
		//       - Restart yate (we don't know the current start)
		//       - Continue waiting
		//       - Alarm
		//       - Stop the process of waiting without any additional operation
		var delta = (this.waitRadioStopIndex - 301) % 300;
		if (0 === delta)
		    Engine.debug(Engine.DebugWarn,
			"Postponed calibration for " + this.info + ": radio not stopped for " +
			((this.waitRadioStopIndex - 1) * 200) + "ms");
	    }
	    if (level && debug)
		Engine.debug(level,"Postponed calibration for " + this.info + ": waiting for radio to stop");
	    // Restart timeout
	    setCalTimeout(200);
	    return true;
	}
	setCalTimeout();
	if (this.calibrationControl(true)) {
	    this.state = Calibration.WaitCalibrate;
	    return true;
	}
	this.ended("Failed to start calibration for " + this.info);
    }
    if (this.state === Calibration.WaitCalibrate) {
	// In this state we are waiting for
	// - radio calibration termination
	// - radiotest termination
	if (event === Calibration.EvCalTerminated) {
	    if (!this.endTime) {
		if (error)
		    error = "Radio calibration failed error=" + error;
		else
		    this.calParams = params;
		this.ended(error);
		// Wait for radiotest worker to terminate
		if (this.notifyId) {
		    if (debug)
			Engine.debug(Engine.DebugAll,"Calibration ended: waiting for radio test to end");
		    return true;
		}
	    }
	}
	else if (event === Calibration.EvChanNotify) {
	    if (!params)
		return true;
	    if (debug)
		Engine.debug(Engine.DebugAll,"radiotest notification state='" + msg.state + "'");
	    if ("stop" !== params.state)
		return true;
	    this.notifyId = undefined;
	    if (!this.endTime) {
		// Wait for calibration result on success
		if (!error)
		    return true;
		this.ended("Radio test worker failed error=" + error);
	    }
	}
	else
	    return true;
    }
    // Restart ?
    if (this.first && !this.calParams) {
	// Make sure calibrate is stopped
	this.calibrationControl(false);
	this.init();
	return true;
    }
    this.stop();
    // Signal termination
    return false;
};

// Calibration control: stop it!
Calibration.prototype.stop = function(cancelled,reason)
{
    // Make sure calibrate is stopped
    this.calibrationControl(false);
    var extra;
    if (module)
	extra = ". " + product + " start: " + yesNo(this.productStart);
    var oper = "terminated";
    if (cancelled) {
	oper = "cancelled";
	if (reason)
	    extra = " (" + reason + ")" + extra;
    }
    Engine.debug(Engine.DebugCall,"Calibration process " + oper + extra);
    // Restart application
    if (this.productStart)
	Calibration.productControl(true);
};

// Calibration control: start or stop calibration
Calibration.prototype.calibrationControl = function(on)
{
    var m = new Message("chan.control");
    m.module = Engine.debugName();
    m.component = "radiotest";
    if (!on) {
	delete this.notifyId;
	m.operation = "stop";
	m.dispatch();
	return true;
    }
    this.notifyId = "calibrate_" + Date.now();
    m.operation = "start";
    // Additional start parmeters
    // Radio test layer config
    m.setParam("name","calibrate");
    m.override = true;
    m.notify = this.notifyId;
    m.init_only = true;
    // Radio interface init
    if (this.params) {
	for (var p in this.params) {
	    if ("frequency" === p) {
		m["radio_txfrequency"] = this.params[p];
		m["radio_rxfrequency"] = this.params[p];
	    }
	    else
		m["radio_" + p] = this.params[p];
	}
    }
    m["radio_calibrate"] = true;
    // Allow start parameters to be overridden from config
    var cfg = new ConfigFile(Engine.configFile("calibrate"),false);
    var sect = cfg.getSection("calibrate_params");
    if (sect)
	copySection(m,sect);
    if (m.dispatch()) {
	Engine.debug(Engine.DebugCall,"Calibration for " + this.info + " started");
	return true;
    }
    return false;
};

// Start stop radio (product) module
Calibration.productControl = function(on)
{
    if (!module)
	return true;
    var m = new Message("engine.command");
    m.module = Engine.debugName();
    if (on)
	m.line = radioStartLine;
    else
	m.line = radioStopLine;
    var notify = on || productRunning;
    if (on)
	var oper = "start";
    else
	var oper = "stop";
    if (m.dispatch()) {
	if (debug && notify)
	    Engine.debug(Engine.DebugAll,"Calibration: " + product + " '" + oper + "' succeeded");
	updateProductRunning();
    }
    else if (notify) {
	Engine.alarm("system",Engine.DebugWarn,"Failed to " + oper + " " + product);
	return false;
    }
    return true;
};

Calibration.check = function(event,error,params)
{
    if (!calibration)
	return;
    if (event === Calibration.EvChanNotify) {
	if (!calibration.notifyId)
	    return;
	if (calibration.notifyId !== params.id)
	    return;
	error = params.error;
    }
    if (calibration.process(event,error,params))
	return;
    delete calibration;
    setCalTimeout();
};


// Handle radio start notification
// Set calibration parameters
// Start (delayed) calibration if not found in config
function onRadioStart(msg)
{
    var ifc = msg.interface;
    if (!ifc) {
	Engine.debug(Engine.DebugNote,"Ignoring radio start notification: interface id is missing");
	return false;
    }
    var serial = msg.serial;
    if (!serial) {
	Engine.debug(Engine.DebugNote,"Ignoring radio start notification interface=" +
	    ifc + ": serial is missing");
	return false;
    }
    delete calibration;
    device = {id:ifc, serial:serial};
    if (debug)
	Engine.debug(Engine.DebugAll,"Tracking radio interface '" + ifc + "'");
    var p = {
	frequency: msg.tx_frequency,
	samplerate: msg.tx_samplerate,
	filter: msg.tx_filter,
    };
    var cfg = new ConfigFile(calibration_file,false);
    if (p.frequency && p.samplerate && p.filter) {
	// Save last configured params
	saveCalConfig(module,p,"last radio configuration",msg.name(),true);
	var tmp = appSectionName(serial,p);
	var sect = cfg.getSection(tmp);
	if (sect || !auto_calibration) {
	    // Application + board specific calibration
	    if (sect) {
		copySection(msg,sect,calParamsSkip);
		if (debug)
		    Engine.debug(Engine.DebugInfo,"Returning calibration params interface=" +
			ifc + " serial=" + serial + " " + props2str(p," "));
	    }
	    else if (debug)
		Engine.debug(Engine.DebugInfo,"Skipping calibration for interface '" +
		    ifc + "': disabled");
	}
	else {
	    // Postpone calibration
	    var src = {name:"device poweron"};
	    calibration = new Calibration(src,p,true);
	}
    }
    else if (auto_calibration)
	Engine.debug(Engine.DebugNote,"Failed to setup calibration data interface=" +
	    ifc + " (serial=" + serial + " " + props2str(p," ") + "): missing param(s)");
    // Retrieve board specific calibration section
    var boardCfg = cfg.getSection(device.serial);
    if (boardCfg)
	copySection(msg,boardCfg,calParamsSkip);
    if (freqoffs_calibration) {
	// Start frequency offset calibration
	if (device.id.startsWith("bladerf/")) {
	    if (debug)
		Engine.debug(Engine.DebugInfo,
		    "Starting frequency offset calibration for '" + device.id + "'");
	    var m = new Message("chan.control");
	    m.module = Engine.debugName();
	    m.component = device.id;
	    m.operation = "freqcalstart";
	    m.enqueue();
	}
	else
	    Engine.debug(Engine.DebugNote,
		"Failed to start frequency offset calibration for '" + device.id +
		"': not implemented");
    }
    return true;
}

// Handle module update message
function onModuleUpdate(msg)
{
    switch (msg.module) {
	case null:
	case undefined:
	    return false;
	case "bladerf":
	    break;
	default:
	    if (module && msg.module === module) {
		if (setProductRunning(msg.state))
		    Calibration.check(Calibration.EvProductStateChanged);
	    }
	    return false;
    }
    switch (msg.status) {
	case null:
	case undefined:
	    if (undefined !== msg.RadioFrequencyOffset) {
		var p = {RadioFrequencyOffset: msg.RadioFrequencyOffset};
		saveCalConfig(msg.serial,p,"RadioFrequencyOffset",msg.name());
	    }
	    break;
	case "start":
	    return onRadioStart(msg);
	case "stop":
	    if (device) {
		if (msg.interface === device.id) {
		    if (debug)
			Engine.debug(Engine.DebugAll,
			    "Removed tracked radio interface '" + msg.interface + "'");
		    device = null;
		}
	    }
	    break;
	case "calibrated":
	    var error = msg.error;
	    var calParams;
	    if (!error) {
		calParams = {};
		for (var param in msg)
		    if (param.startsWith("cal_"))
			calParams[param.substr(4)] = msg[param];
		// Save result if not disabled
		if (!(calibration && calibration.dontSave)) {
		    var sect = appSectionName(msg.serial,msg);
		    saveCalConfig(sect,calParams,"calibration data",msg.name(),true);
		}
	    }
	    Calibration.check(Calibration.EvCalTerminated,error,calParams);
	    break;
    }
    return false;
}

// Handle chan.notify
function onChanNotify(msg)
{
    Calibration.check(Calibration.EvChanNotify,undefined,msg);
    return false;
}

// Handle calibrate start command
function onCalibrateStart(msg,params)
{
    var code;
    var error;
    while (true) {
	var cmdP;
	var p;
	if (params) {
	    if (params.frequency || params.samplerate || params.filter) {
		cmdP = {};
		p = cmdP;
		p.frequency = params.frequency;
		p.samplerate = params.samplerate;
		p.filter = params.filter;
	    }
	}
	if (!cmdP) {
	    if (!module) {
		code = 402;
		error = "Missing calibration parameters";
		break;
	    }
	    var cfg = new ConfigFile(calibration_file,false);
	    var sect = cfg.getSection(module);
	    if (!sect) {
		code = 201;
		error = "Last start parameters not found in config";
		break;
	    }
	    p = {};
	    p.frequency = sect.getValue("frequency");
	    p.samplerate = sect.getValue("samplerate");
	    p.filter = sect.getValue("filter");
	}
	for (var prop in p) {
	    var tmp = parseInt(p[prop]);
	    if (!isNaN(tmp)) {
		p[prop] = tmp;
		continue;
	    }
	    if (isPresent(p[prop])) {
		code = 401;
		error = "Invalid '" + prop + "' value '" + p[prop] + "'";
	    }
	    else {
		code = 402;
		error = "Missing '" + prop + "'";
	    }
	    if (!cmdP)
		error += " parameter";
	    else
		error += " in last start section config";
	    break;
	}
	if (code)
	    break;
	if (calibration && !parseBool(params.force)) {
	    code = 405;
	    error = "Calibration already running";
	    break;
	}
	var old = calibration;
	if (old) {
	    Engine.debug(Engine.DebugCall,"Replacing calibration: " + msg.name());
	    old.calibrationControl(false);
	}
	var source = {};
	if (msg.module)
	    source.name = msg.module;
	else
	    source.name = msg.name();
	if (msg.cmd_address)
	    source.cmd_address = msg.cmd_address;
	if (msg.received)
	    source.received = msg.received;
	calibration = new Calibration(source,p,false,!parseBool(params.save));
	// Remember old calibration product start flag: restart it
	if (old)
	    calibration.productStart = old.productStart;
	var result = "Calibration postponed";
	if (old)
	    result += " (replaced)";
	msg.retValue(result + "\r\n");
	return true;
    }
    msg.error = code;
    msg.reason = error;
    Engine.debug(Engine.DebugNote,"Failed to start calibration: " + error);
    return false;
}

// Handle calibrate stop command
function onCalibrateStop(msg)
{
    var str;
    if (calibration) {
	calibration.stop(true,"command received");
	delete calibration;
	str = "Calibration cancelled";
    }
    else
	str = "Calibration is not running";
    if (!msg.result)
	str += "\r\n";
    msg.retValue(str);
    return true;
}

// Handle commands
function onCommand(msg)
{
    if (!msg.line) {
	var part = msg.partword;
	switch (msg.partline) {
	    case undefined:
	    case "":
	    case "help":
	    case "reload":
	    case "debug":
	    case "status":
	    case "status overview":
		oneCompletion(msg,"calibrate",part);
		break;
	    case "calibrate":
	    case "help calibrate":
		for (var c of cmds)
		    oneCompletion(msg,c.name,part);
		break;
	}
	return false;
    }
    if (msg.line.startsWith("calibrate ")) {
	var line = msg.line.substr(10);
	switch (line) {
	    case "start":
		return onCalibrateStart(msg);
	    case /^start .*/:
		return onCalibrateStart(msg,parseParams(line.substr(6)));
	    case "stop":
		return onCalibrateStop(msg);
	}
	return false;
    }
    return false;
}

// Handle calibrate status enquiry command
function onStatus(msg)
{
    var status = {};
    if (calibration) {
	status.state = Calibration.stateName[calibration.state];
	status.age = durationSec(Date.now() - calibration.createTime);
    }
    else
	status.state = Calibration.stateName[Calibration.Idle];
    var str;
    if (!msg.json) {
	str = "module=calibrate;";
	str += props2str(status);
    }
    if (parseBool(msg.details,true) && (lastCalOk || lastCalFailed)) {
	if (msg.json) {
	    status.last_calibration_ok = lastCalOk;
	    status.last_calibration_failed = lastCalFailed;
	}
	else {
	    var tmp = props2str(lastCalOk);
	    if (tmp)
		str += ";last_calibration_ok=," + tmp;
	    var tmp = props2str(lastCalFailed);
	    if (tmp)
		str += ";last_failed_calibration=," + tmp;
	}
    }
    if (msg.json)
	str = JSON.stringify(status);
    else
	str += "\r\n";
    msg.retValue(str);
    return true;
}

function onHelp(msg)
{
    var s = "";
    var retOk = false;
    switch (msg.line) {
	case null:
	case undefined:
	case "":
	case "calibrate":
	    for (var c of cmds)
		s += cmdDesc(c);
	    retOk = ("calibrate" === msg.line);
	    break;
	case /^calibrate .*/:
	    var tmp = msg.line.substr(9);
	    if (tmp) {
		var idx = cmds.indexOf(tmp,0,"name");
		if (idx >= 0) {
		    s += cmdDesc(cmds[idx]);
		    retOk = true;
		}
		break;
	    }
	    for (var c of cmds)
		s += cmdDesc(c);
	    retOk = true;
	    break;
    }
    if (s) {
	var tmp = msg.retValue();
	msg.retValue(tmp + s);
    }
    return retOk;
}

// Handle the reload command
function onReload(msg)
{
    if (msg.plugin && ("calibrate" != msg.plugin))
	return false;
    initialize();
    return !!msg.plugin;
}

// Handle debugging commands
function onDebug(msg)
{
    Engine.setDebug(msg.line);
    debug = Engine.debugEnabled();
    msg.retValue("Radio Calibration debug " + Engine.debugEnabled() + " level " +
	Engine.debugLevel() + "\r\n");
    return true;
}

function onEngineStop(msg)
{
    engineStop++;
    if (calibration) {
	// Engine is exiting: forget about calibration
	// This will avoid a lot of incorrect debug messages
	Engine.debug(Engine.DebugCall,"Engine is stopping: removing running calibration");
	delete calibration;
    }
}

// Script is beeing unloaded
function onUnload()
{
    if (calibration)
	calibration.stop(true,"module unloaded");
    Engine.output("Unloading module Radio Calibration");
}

function initialize()
{
    Engine.output("Initializing module Radio Calibration");
    var cfg = new ConfigFile(Engine.configFile("calibrate"));
    var gen = cfg.getSection("general",true);
    if (!module) {
	var level = Engine.DebugInfo;
	var mode = gen.getValue("mode");
	switch (mode) {
	    case "enb":
		module = "enb";
		product = "ENB";
		break;
	    case "bts":
		module = "ybts";
		product = "BTS";
		break;
	    case "-":
		level = Engine.DebugNote;
		module = "";
		product = "CALIBRATION-ONLY";
		break;
	    default:
		Engine.debug(Engine.DebugConf,"Mode '" + mode + "' is missing or invalid");
		return;
	}
	if (module) {
	    radioStartLine = gen.getValue("radio_start_line");
	    if (!radioStartLine)
		radioStartLine = module + " start";
	    radioStopLine = gen.getValue("radio_stop_line");
	    if (!radioStopLine)
		radioStopLine = module + " stop";
	}
	calibration_file = gen.getValue("calibration_file");
	if (!calibration_file)
	    calibration_file = "${configpath}/radio_calibration.conf";
	calibration_file = Engine.replaceParams(calibration_file,Engine.runParams());
	// Load last calibration
	var cfgCal = new ConfigFile(calibration_file,false);
	var calSect = cfgCal.getSection("last_calibration");
	if (calSect) {
	    lastCalOk = {};
	    copySection(lastCalOk,calSect);
	}
	calSect = cfgCal.getSection("last_failed_calibration");
	if (calSect) {
	    lastCalFailed = {};
	    copySection(lastCalFailed,calSect);
	}
	//
	var tmp = gen.getValue("debug");
	if (tmp != "")
	    Engine.setDebug(tmp);
	debug = Engine.debugEnabled();
	Message.install(onModuleUpdate,"module.update",90);
	Message.install(onCommand,"engine.command",120);
	Message.install(onHelp,"engine.help",150);
	Message.install(onStatus,"engine.status",100,"module","calibrate");
	Message.install(onDebug,"engine.debug",150,"module","calibrate");
	Message.install(onEngineStop,"engine.stop",10);
	Message.install(onChanNotify,"chan.notify",120,"module","radiotest");
	Engine.debug(level,"Starting in mode=" + mode + " (module=" + module + " product=" + product +
	    ") calibration_file='" + calibration_file + "'");
	updateProductRunning(true);
    }
    auto_calibration = gen.getBoolValue("auto_calibration",true);
    freqoffs_calibration = gen.getBoolValue("freqoffs_calibration",true);
}

initialize();
Message.install(onReload,"engine.init",120);

/* vi: set ts=8 sw=4 sts=4 noet: */
