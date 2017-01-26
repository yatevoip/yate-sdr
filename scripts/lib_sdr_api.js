/**
 * lib_sdr_api.js
 * This file is part of the YATE Project http://YATE.null.ro
 *
 * Common SDR API library
 *
 * Yet Another Telephony Engine - a fully featured software PBX and IVR
 * Copyright (C) 2014-2017 Null Team
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

// Check JSON parse result
// Return true on success, false otherwise (set error)
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

// Build and return API error
function apiError(error,reason)
{
    if (!error || isNaN(error))
	error = 400;
    reason = "" + reason;
    if (!reason)
	reason = "Unknown.";
    else if (!reason.endsWith("."))
	reason += ".";
    return {error:error, reason:reason};
}

// Build error for invalid parameter value
function apiErrorInvalid(param,value,extra)
{
    var s = "Invalid '" + param + "' value";
    if (value)
	s += " '" + value + "'";
    if (extra)
	s += ": " + extra;
    return apiError(401,s);
}

// Callback used when handler for api.request message is installed
function onApiRequest(msg)
{
    var func = API["on_" + msg.operation];
    if ("function" != typeof func.apply) {
	if (debug)
	    Engine.output("Undefined function '" + func + "' in onApiRequest");
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

/* vi: set ts=8 sw=4 sts=4 noet: */
