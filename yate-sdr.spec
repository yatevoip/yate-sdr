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

%define Suggests() %(LANG=C LC_MESSAGES=C rpm --help | fgrep -q ' --suggests ' && echo "Suggests:" || echo "##")
%define Recommends() %(LANG=C LC_MESSAGES=C rpm --help | fgrep -q ' --recommends ' && echo "Recommends:" || echo "##")
%{!?dist:%define dist %{?distsuffix:%distsuffix%{?product_version}}}
%{!?systemd:%define systemd %(test -x /usr/bin/systemd && echo 1 || echo 0)}
%{!?_unitdir:%define _unitdir /usr/lib/systemd/system}
%{!?tarname:%define tarname %{name}-%{version}-%{buildnum}}
%define bin /usr/bin
%define buildnum 1

Summary:	Yate Software Defined Radio
Name:		yate-sdr
Version:	1.2
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
Requires:	yate >= 6.1.0
Requires:	yate-scripts
Requires:	yate-common >= 1.7
Requires:	php-curl
%{Recommends}	netkit-telnet
%{Recommends}	tcpdump
%{Recommends}	iptraf-ng
%{Recommends}	conntrack-tools
# These are for older packaging versions like Mandriva
%{Suggests}	iptraf
%{Suggests}	conntrack


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
%{bin}/pre-yate-sdr
%config(noreplace) %{_sysconfdir}/logrotate.d/%{name}
%{_datadir}/yate/api/*
%{_datadir}/yate/scripts/*
/var/www/html/lmi
%dir %{_sysconfdir}/yate/sdr
%{_sysconfdir}/yate/sdr/*.conf.sample
%{_sysconfdir}/yate/sdr/*.conf
#%config(noreplace) %{_sysconfdir}/yate/sdr/*.conf
%config(noreplace) %{_sysconfdir}/yate/sdr/enb_bands.csv
%{_sysconfdir}/yate/sdr/enb_bands.csv

%post
mkdir -p /var/log/lmi /var/lib/lmi/upload
chown -R apache.apache /var/log/lmi /var/lib/lmi
%if "%{systemd}" != "0"
/usr/bin/systemctl daemon-reload
%endif
if [ "X$1" = "X1" ]; then
    %{_datadir}/yate/scripts/rpm_restore.sh %{name}
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

# Adding log entry when the rpm is installed
date_v=$(date '+%Y-%m-%d-%H:%M')
echo $date_v "  Installed Yate SDR" %{buildnum}%{?revision}_%(svnversion | cut -d ":" -f2 | cut -d "M" -f1)_svn%{?dist} >> /var/log/yate-rpms.log


# Edit the enb_bands.csv file according to the hardware if SKU exist
sku=$(grep -r "SKU" /etc/sysconfig/oem)
if [[ ! -z $sku ]]
then
        model=$(grep -r "SKU" /etc/sysconfig/oem | cut -d "-" -f 2)
        model=${model::-3}
        if [ $model == 'SATSITE' ]; then
                band=$(echo $sku | cut -d "-" -f 3 | cut -c2-)
                only_band=$(awk '{if(NR=='"$band"') print $0}' %{_sysconfdir}/yate/sdr/enb_bands.csv)
                echo $only_band > %{_sysconfdir}/yate/sdr/enb_bands.csv

        fi
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
date_v=$(date '+%Y-%m-%d-%H:%M')
echo $date_v "  Uninstalled Yate SDR" %{buildnum}%{?revision}_%(svnversion | cut -d ":" -f2 | cut -d "M" -f1)_svn%{?dist} >> /var/log/yate-rpms.log


%triggerin -- yate
if [ "X$2" = "X2" ]; then
%if "%{systemd}" != "0"
    /usr/bin/systemctl condrestart %{name}.service
%else
    /sbin/service %{name} condrestart
%endif
fi


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
mkdir -p %{buildroot}%{bin}
ln -sf yate %{buildroot}%{_bindir}/%{name}
cp -p scripts/* %{buildroot}%{_datadir}/yate/scripts/
cp -p logrotate %{buildroot}%{_sysconfdir}/logrotate.d/%{name}
cp -p conf/* %{buildroot}%{_sysconfdir}/yate/sdr/
cp -p api/* %{buildroot}%{_datadir}/yate/api/
cp -rp lmi/* %{buildroot}/var/www/html/lmi/
cp -p src/pre-yate-sdr %{buildroot}%{bin}/
echo '<?php $version = "%{version}-%{release}" ?>' > %{buildroot}/var/www/html/lmi/version.php
echo '<?php global $sdr_version; $sdr_version = "%{version}-%{release}"; ?>' > %{buildroot}%{_datadir}/yate/api/sdr_version.php
mkdir -p %{buildroot}%{_sysconfdir}/yate/sdr
#ln -sf  /usr/share/yate/enb/enb_bands.csv %{buildroot}%{_sysconfdir}/yate/sdr/enb_bands.csv
cp -p conf/enb_bands.csv %{_sysconfdir}/yate/sdr/enb_bands.csv


%clean
rm -rf %{buildroot}


%changelog
* Thu Dec 19 2019 Nour Shukri <nour.shukri@legba.ro>
- Added the script pre-yate-sdr to run before the service starts and set Nice value to negative ten

* Thu Oct 3 2019 Nour Shukri <nour.shukri@legba.ro>
- Added enb_bands file & the code to customize it for SS
- Removed the noreplace for the config files with conf extension installed in sdr directory

* Tue Sep 3 2019 Nour Shukri <nour.shukri@legba.ro>
- Added Yate RPMs log file

* Thu Apr 11 2019 Nour Shukri <nour.shukri@legba.ro>
- Added enb_bands link

* Sun Jan  8 2017 Paul Chitescu <paulc@null.ro>
- Created specfile
