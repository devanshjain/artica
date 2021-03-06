<?php
if(posix_getuid()<>0){die("Cannot be used in web server mode\n\n");}
include_once(dirname(__FILE__).'/ressources/class.templates.inc');
include_once(dirname(__FILE__).'/ressources/class.ini.inc');
include_once(dirname(__FILE__).'/ressources/class.main_cf.inc');
include_once(dirname(__FILE__).'/ressources/class.amavis.inc');
include_once(dirname(__FILE__).'/ressources/class.samba.inc');
include_once(dirname(__FILE__).'/ressources/class.squid.inc');

$main=new main_cf();
$main->save_conf();
$main->save_conf_to_server(1);

$amavis=new amavis();
$amavis->Save();
$amavis->SaveToServer();

$samba=new samba();
$samba->SaveToLdap();

$squid=new squidbee();
$squid->SaveToLdap();
$squid->SaveToServer();

system('/etc/init.d/artica-postfix restart postfix');
system('/etc/init.d/artica-postfix restart squid');
system('/etc/init.d/artica-postfix restart samba');
system('/usr/share/artica-postfix/bin/artica-install --cyrus-checkconfig');
system('/etc/init.d/artica-postfix restart imap');

?>