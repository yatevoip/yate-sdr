#!/usr/bin

# sdr_asroot.sh
# This file is part of the YATE Project http://YATE.null.ro
#
# Yet Another Telephony Engine - a fully featured software PBX and IVR
# Copyright (C) 2018 Null Team
#
# This software is distributed under multiple licenses;
# see the COPYING file in the main directory for licensing
# information for this specific distribution.
#
# This use of this software may be subject to additional restrictions.
# See the LEGAL file in the main directory for details.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.

# Helper script for Management API root access

if [ "X$2" != "Xsdr" ]; then
	echo "Invalid node name '$2', expecting 'sdr'" >&2
	exit 1
fi

case "X$1" in

    Xtroubleshoot)
	tshoot="/usr/src/tools/basic-tshoot-lmi"
	if [ -s "$tshoot" -a -O "$tshoot" ]; then
	    exec "$tshoot"
	    exit 0
	else
	    echo "Missing troubleshooting script $tshoot"
	    exit 1
	fi
	;;
    Xntpd_status)
	check_status_lib="/usr/lib/yate-bash-ip.sh"
	if [ -s "$check_status_lib" ]; then
	    . "/usr/lib/yate-bash-ip.sh"
	    check_ntp
	    if [ $ntp_service_value -eq 1 ]
	    then
		echo "Chrony Service is running"
		if (( $(echo "$avg_skew < 2" |bc -l) )); then
		    echo "Skew is in the acceptable range"
		else
		    echo "Skew value is Not in the accepatable range"
		fi
		else
		    echo "X Chrony Service is Not running"
		fi
		exit 0
	else
	    echo "Missing libraries that check ntpd status"
	    exit 1
	fi
	;;

    Xopenvpn_usage)
	check_status_lib="/usr/lib/yate-bash-openvpn.sh"
	if [ -s "$check_status_lib" ]; then
	    . "/usr/lib/yate-bash-openvpn.sh"
	    is_hc_in_use && echo "yes" || echo "no"
	    exit 0
	else
	    echo "Missing libraries that check openvpn usage"
	    exit 1
	fi
	;;

    Xopenvpn_status)
	check_status_lib="/usr/lib/yate-bash-openvpn.sh"
	if [ -s "$check_status_lib" ]; then
	    . "/usr/lib/yate-bash-openvpn.sh"
	    check_openvpn_service_status && echo "Running" || echo "Stopped"
	    exit 0
	else
	    echo "Missing libraries that check openvpn status"
	    exit 1
	fi
	;;

esac
