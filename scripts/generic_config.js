/**
 * generic_config.js
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

//#pragma trace "cachegrind.out.generic_config"

function GenericConfig(file, section_names, overwrite)
{
    if (overwrite == undefined)
	overwrite = true;

    this.error = new Object;

    if (file != undefined)
	this.file = file;
    if (section_names != undefined)
	this.sections = section_names;

    this.overwrite = overwrite;
}

GenericConfig.prototype = new Object;

// Load and clear config file if overwrite was true
GenericConfig.prototype.prepareConfig = function(params)
{
    this.conf = prepareConf(this.file,true);

    // clear ybts sections
    var sections = this.conf.sections();
    for (var sect of sections) {
	if (this.overwrite)
	    this.conf.clearSection(sect);
	else {
	    this.current_config = this.getConfig();
	}
    }
};

// Call validations and set configurations
GenericConfig.prototype.setConfig = function()
{
    var section_name, section, section_params, param_name, param_value;

    if (!this.overwrite) {
	// keep existing section and values
	for (section_name in this.current_config) {
	    section = this.conf.getSection(section_name,true);
	    section_params = this.current_config[section_name];
	    for (param_name in section_params) 
		section.setValue(param_name, section_params[param_name]);
	}
	// received configurations will be put over this ones
    }

    //return error if any required section is missing
    for (section_name_req in this.params_required) {
	if (!params[section_name_req]) {
	    this.error.reason = "Missing required section: '"+ section_name_req +"'.";
	    this.error.error = 402;
	    return false;
	}
    }

    for (var i = 0; i < this.sections.length; i++) {
	var section_name = this.sections[i];

	var section_params = params[section_name];
	if (!section_params)
	    continue;

	var section = this.conf.getSection(section_name,true);
	for (param_name in section_params) {
	    var param_value = section_params[param_name];
	    
	    if (!this.validateConfig(section_name, param_name, param_value)) 
		return false;
	    if (this.skip_empty_params[section_name][param_name])
		continue;

	    section.setValue(param_name, param_value);
	}
    }
    return true;
};

// Validate new configurations
GenericConfig.prototype.validateConfig = function(section_name, param_name, param_value)
{
    var validations = this.validations[section_name][param_name];

    var required = this.params_required[section_name];
    if (required!=undefined) {
	for (var i=0; i<required.length; i++) {
	    var param_desc = required[i];
	    if (isParamMissing(this.error, param_desc, params.gsm[required[i]],section_name))
		return false;
	}
    }

    if (param_value=="") {
	if (validations) {
	    if (!inArray(param_name, this.params_allowed_empty)) {
		this.error.reason = "Field "+param_name+" can't be empty in section '"+ section_name +"'."; 
		this.error.error = 402;
		return false;
	    }
	} else {
	    if (!this.skip_empty_params[section_name]) 
		this.skip_empty_params[section_name] = new Object();

	    this.skip_empty_params[section_name][param_name] = true;
	    return true;

	}
    }	

    if (validations["minimum"] != undefined)
	if (!checkFieldValidity(this.error, section_name, param_name, param_value, validations["minimum"], validations["maximum"]))
	    return false;

    if (validations["regex"] != undefined)
	if (!checkFieldValidity(this.error, section_name, param_name, param_value, undefined, undefined, validations["regex"]))
	    return false;

    if (validations["select"] != undefined)
	if (!checkValidSelect(this.error, param_name, param_value, validations["select"], section_name))
	    return false;

    if (validations["callback"] != undefined) {
	var callback = validations["callback"];
	if ("object" == typeof callback) {
	    if (!callback(this.error, param_name, param_value, section_name))
		return false;
	}
    }

    return true;
};

// Save config files
GenericConfig.prototype.saveConfig = function()
{
    if (!checkUnlocked(this.error,[this.conf]))
	return false;
    if (!saveConf(this.error,this.conf))
	return false;
    return true;
};

// Get configuration from file
GenericConfig.prototype.getConfig = function()
{
    var c, sections, keys, key, section, res;

    c = new ConfigFile(Engine.configFile(this.file));
    c.load("Could not load "+this.name);
    sections = c.sections();

    res = {};
    for (var section_name in sections) {
	res[section_name] = {};
	section = sections[section_name];
	keys = section.keys();
	for (key of keys) {
	    res[section_name][key] = section.getValue(key);
	}
    }

    return res;
};

API.on_get_generic_file = function(configObj,params)
{
    if (debug)
	dumpObj("configObj",configObj);
    return configObj.getConfig();
};

// Generoc function to handle set requests
API.on_set_generic_file = function(configObj,params,msg,setNode)
{
    if (debug)
	dumpObj("configObj",configObj);

    if (setNode && !params) {
	configObj.prepareConfig(msg);
	if (!configObj.saveConfig())
	    return configObj.error;
	return { name: configObj.name };
    }

    if (!checkJson(configObj.error,params,msg.json))
	return configObj.error;

    configObj.prepareConfig(msg);

    if (!configObj.setConfig(msg))
	return configObj.error;

    // Save config
    if (!configObj.saveConfig())
	return configObj.error;

    Engine.debug(Engine.DebugAll, "Saved config");
    return { name: configObj.name };
};

// Prepare a config file:
// Load, clear, set updated info
function prepareConf(name,msg,clear)
{
    var c = new ConfigFile(Engine.configFile(name));
    var l = c.getBoolValue("general","locked");
    if (false !== clear)
	c.clearSection();
    c.setValue("general","updated",msg.received);
    c.setValue("general","locked",l);
    return c;
};

function saveConf(error,conf)
{
    if (conf.getBoolValue("general","locked"))
	return setStorageError(error,"Locked config file '" + conf.name() + "'",false);
    if (conf.save())
	return true;
    return setStorageError(error,"Failed to save config file '" + conf.name() + "'",false);
}

function checkUnlocked(error,confs)
{
    for (var c of confs) {
	if (c.getBoolValue("general","locked"))
	    return setStorageError(this.error,"One or more of the config files is locked",false);
    }
    return true;
}

function dumpObj(prefix,obj)
{
    var dump = Engine.dump_r(obj);
    Engine.output(prefix + ":\r\n-----\r\n" + dump + "\r\n-----");
}
/* vi: set ts=8 sw=4 sts=4 noet: */
