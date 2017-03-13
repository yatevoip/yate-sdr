# yate-sdr.spec
# This file is part of the YATE Project http://YATE.null.ro
#
# Yet Another Telephony Engine - a fully featured software PBX and IVR
# Copyright (C) 2014-2015 Null Team
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

%{!?dist:%define dist %{?distsuffix:%distsuffix%{?product_version}}}
%{!?systemd:%define systemd %(test -x /usr/bin/systemd && echo 1 || echo 0)}
%{!?_unitdir:%define _unitdir /usr/lib/systemd/system}
%{!?tarname:%define tarname %{name}-%{version}-%{buildnum}}

%define buildnum 1

Summary:	Yate Software Defined Radio
Name:		yate-sdr
Version:	0.1
Release:	%{buildnum}%{?revision}%{?dist}
License:	GPL
Vendor:		Null Team Impex SRL
Packager:	Paul Chitescu <paulc@null.ro>
Source:		%{tarname}.tar.gz
Group:		Applications/Communication
BuildArch:	noarch
BuildRoot:	%{_tmppath}/%{tarname}-root
%if "%{systemd}" != "0"
Requires:	/usr/bin/systemctl
%else
Requires:	/sbin/chkconfig
Requires:	/sbin/service
%endif
Requires:	logrotate
Requires:	yate
Requires:	yate-scripts
Requires:	yate-common
Recommends:	netkit-telnet


%description
The Software Defined Radio holds resources common to all Yate based radio products.


%files
%defattr(-,root,root)
%{_bindir}/%{name}
%if "%{systemd}" != "0"
%{_unitdir}/%{name}.service
%else
%{_initrddir}/%{name}
%endif
%config(noreplace) %{_sysconfdir}/logrotate.d/%{name}
%{_datadir}/yate/api/*
%{_datadir}/yate/scripts/*
/var/www/html/lmi
%dir %{_sysconfdir}/yate/sdr
%{_sysconfdir}/yate/sdr/*.conf.sample
%defattr(600,root,root)
%config(noreplace) %{_sysconfdir}/yate/sdr/*.conf


%post
mkdir -p /var/log/lmi /var/lib/lmi/upload
chown -R apache.apache /var/log/lmi /var/lib/lmi
%if "%{systemd}" != "0"
/usr/bin/systemctl daemon-reload
%endif
if [ "X$1" = "X1" ]; then
%if "%{systemd}" != "0"
    /usr/bin/systemctl enable %{name}.service
    /usr/bin/systemctl restart %{name}.service
%else
    /sbin/chkconfig %{name} on
    /sbin/service %{name} restart
%endif
else
%if "%{systemd}" != "0"
    /usr/bin/systemctl condrestart %{name}.service
%else
    /sbin/service %{name} condrestart
%endif
fi


%preun
if [ "X$1" = "X0" ]; then
%if "%{systemd}" != "0"
    /usr/bin/systemctl stop %{name}.service
%else
    /sbin/service %{name} stop
%endif
fi


%postun
%if "%{systemd}" != "0"
/usr/bin/systemctl daemon-reload
%endif


%prep
%setup -q -n %{name}


# older rpmbuild uses these macro basic regexps
%define _requires_exceptions pear
# newer rpmbuild needs these global extended regexps
%global __requires_exclude pear


%build


%install
%if "%{systemd}" != "0"
mkdir -p %{buildroot}%{_unitdir}
cp -p %{name}.service %{buildroot}%{_unitdir}/
%else
mkdir -p %{buildroot}%{_initrddir}
cp -p %{name}.init %{buildroot}%{_initrddir}/%{name}
%endif
mkdir -p %{buildroot}%{_bindir}
mkdir -p %{buildroot}%{_datadir}/yate/scripts
mkdir -p %{buildroot}%{_datadir}/yate/api
mkdir -p %{buildroot}%{_datadir}/yate/data
mkdir -p %{buildroot}%{_sysconfdir}/logrotate.d
mkdir -p %{buildroot}%{_sysconfdir}/yate/sdr
mkdir -p %{buildroot}/var/www/html/lmi
ln -sf yate %{buildroot}%{_bindir}/%{name}
cp -p scripts/* %{buildroot}%{_datadir}/yate/scripts/
cp -p logrotate %{buildroot}%{_sysconfdir}/logrotate.d/%{name}
cp -p conf/* %{buildroot}%{_sysconfdir}/yate/sdr/
cp -p api/* %{buildroot}%{_datadir}/yate/api/
cp -rp lmi/* %{buildroot}/var/www/html/lmi/
echo '<?php $version = "%{version}-%{release}" ?>' > %{buildroot}/var/www/html/lmi/version.php
echo '<?php global $sdr_version; $sdr_version = "%{version}-%{release}"; ?>' > %{buildroot}%{_datadir}/yate/api/sdr_version.php


%clean
rm -rf %{buildroot}


%changelog
* Sun Jan  8 2017 Paul Chitescu <paulc@null.ro>
- Created specfile
