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
	    echo "Missing $tshoot"
	    exit 1
	fi
	;;

esac
