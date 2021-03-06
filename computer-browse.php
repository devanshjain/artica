<?php
include_once(dirname(__FILE__).'/ressources/class.templates.inc');
include_once(dirname(__FILE__).'/ressources/class.tcpip.inc');
include_once(dirname(__FILE__).'/ressources/class.system.network.inc');
include_once(dirname(__FILE__).'/ressources/class.computers.inc');

if(posix_getuid()<>0){
	$users=new usersMenus();
	if((!$users->AsSambaAdministrator) OR (!$users->AsSystemAdministrator)){
		$error=$tpl->_ENGINE_parse_body("{ERROR_NO_PRIVS}");
		echo "alert('$error')";
		die();
	}
}


if($_GET["mode"]=="selection"){selection_js();exit;}
if(isset($_GET["browse-computers"])){index();exit;}

if(isset($_GET["browse-networks"])){networks();exit;}
if(isset($_GET["browse-networks-add"])){networks_add();exit;}

if(isset($_GET["network-scanner-execute"])){network_scanner_execute();exit;}
if(isset($_GET["computer-refresh"])){echo computer_list();exit;}
if(isset($_GET["calc-cdir-ip"])){echo network_calc();exit;}
if(isset($_GET["calc-cdir-ip-add"])){echo networks_add_save();exit;}
if(isset($_GET["NetworkDelete"])){networks_del();exit;}
if(isset($_GET["DeleteAllcomputers"])){computers_delete();exit;}
if(isset($_GET["Status"])){Status();exit;}
if(isset($_GET["view-scan-logs"])){events();exit;}
if(isset($_GET["NetWorksDisable"])){networks_disable();exit;}
if(isset($_GET["NetWorksEnable"])){networks_enable();exit;}

if(isset($_GET["artica-import-popup"])){artica_import_popup();exit;}
if(isset($_GET["artica-import-delete"])){artica_import_delete();exit;}

if(isset($_GET["artica-importlist-popup"])){artica_importlist_popup();exit;}


if(isset($_GET["artica_ip_addr"])){artica_import_save();exit;}
if(isset($_POST["popup_import_list"])){artica_importlist_perform();exit;}

if(isset($_GET["selection-computers"])){selection_popup();exit;}
if(isset($_GET["selection-list"])){selection_list();exit;}



if(posix_getuid()<>0){js();}






function networks_del(){
	$md5=$_GET["NetworkDelete"];
	
	$net=new networkscanner();
	print_r($net->networklist);
	while (list ($num, $maks) = each ($net->networklist)){
		$maks_md5=md5($maks);
		if($maks_md5==$md5){
			echo "OK $num-$maks";
			
			unset($net->networklist[$num]);
			$net->save();
			break;
		}
	}

	
}

function network_calc(){
	$ip_start=$_GET["calc-cdir-ip"];
	$mask=$_GET["calc-cdir-netmask"];
	$ip=new networking();
	echo $ip->route_shouldbe($ip_start,$mask);
	
}

function networks_add_save(){
	$net=new networkscanner();
	$netmask=$_GET["calc-cdir-ip-add"];
	if($netmask<>null){
		$net->networklist[]=$netmask;
		$net->save();
	}
	
}


function selection_js(){
	$users=new usersMenus();
	if(!$users->AsSambaAdministrator){die("alert('no privileges')");}
	
	$page=CurrentPageName();
	$tpl=new templates();
	$title=$tpl->_ENGINE_parse_body('{browse_computers}::{select}');
	$callback=$_GET["callback"];
	$html="
	function BrowseComputerSelection(){
		YahooLogWatcher(550,'$page?selection-computers=*&callback=$callback','$title');
	}
	
	BrowseComputerSelection();";
	
	
	echo $html;
	}
	
	
function selection_popup(){
	$page=CurrentPageName();
	$callback=$_GET["callback"];
	$html=Field_text("selection-search",$_GET["selection-computers"],"font-size:13px;padding:3px",null,null,null,false,"SelectionComputerPress(event)")."
	<div style='padding:3px;border:3px;height:300px;overflow:auto' id='selection-list'></div>
	
	
	
	<script>
	   function SelectionComputerPress(e){
	   		if(checkEnter(e)){SelectionComputer(document.getElementById('selection-search').value);}
		}
	
	
		function SelectionComputer(pattern){
			LoadAjax('selection-list','$page?selection-list='+pattern+'&callback=$callback');
		}
		
		SelectionComputer('*');
	</script>
	
	";
	
	
	
	$tpl=new templates();
	echo $tpl->_ENGINE_parse_body($html);	
	
}

function selection_list(){
	if($_GET["selection-list"]=='*'){$_GET["selection-list"]=null;}
	if($_GET["selection-list"]==null){$tofind="*";}else{$tofind="*{$_GET["selection-list"]}*";}
	$filter_search="(&(objectClass=ArticaComputerInfos)(|(cn=$tofind)(ComputerIP=$tofind)(uid=$tofind))(gecos=computer))";
	
$ldap=new clladp();
$attrs=array("uid","ComputerIP","ComputerOS","ComputerMachineType","ComputerMacAddress");
$dn="$ldap->suffix";
$hash=$ldap->Ldap_search($dn,$filter_search,$attrs,20);


$html="<table>";

for($i=0;$i<$hash["count"];$i++){
	$realuid=$hash[$i]["uid"][0];
	$hash[$i]["uid"][0]=str_replace('$','',$hash[$i]["uid"][0]);
	$js_show=MEMBER_JS($realuid,1);
	if($_GET["callback"]<>null){$js_selection="{$_GET["callback"]}('$realuid');";}
	

	
	$ip=$hash[$i][strtolower("ComputerIP")][0];
	$os=$hash[$i][strtolower("ComputerOS")][0];
	$type=$hash[$i][strtolower("ComputerMachineType")][0];
	$mac=$hash[$i][strtolower("ComputerMacAddress")][0];
	$name=$hash[$i]["uid"][0];
	if(strlen($name)>25){$name=substr($name,0,23)."...";}
	
	
	if($os=="Unknown"){if($type<>"Unknown"){$os=$type;}}
	
	if(strlen($os)>20){$os=texttooltip(substr($os,0,17).'...',$os,null,null,1);}
	if(strlen($ip)>20){$ip=texttooltip(substr($ip,0,17).'...',$ip,null,null,1);}
	
	$img="<img src='img/base.gif'>";
	
	$roolover=CellRollOver($js_selection,"{select}");
	
	if(!IsPhysicalAddress($mac)){
		$img=imgtootltip("status_warning.gif","{WARNING_MAC_ADDRESS_CORRUPT}");
		$roolover=CellRollOver($js_show,"{edit}<hr>{WARNING_MAC_ADDRESS_CORRUPT}");
		$js_selection=null;
	}
	
	$html=$html . 
	"<tr $roolover>
	<td width=1% $roolover>$img</td>
	<td nowrap><strong>$name</strong></td>
	<td ><strong>$ip</strong></td>
	<td><strong>$mac</strong></td>
	$js_add
	</tr>
	";
	}
$html=$html . "</table>";
$tpl=new templates();
echo  $tpl->_ENGINE_parse_body($html);
	
}



function js(){
	
	$users=new usersMenus();
	if(!$users->AsSambaAdministrator){die("alert('no privileges')");}
	$page=CurrentPageName();
	$tpl=new templates();
	$title=$tpl->_ENGINE_parse_body('{browse_computers}');
	$networks=$tpl->_ENGINE_parse_body('{edit_networks}');
	$delete_all_computers_warn=$tpl->javascript_parse_text('{delete_all_computers_warn}');
	$import_artica_computers=$tpl->_ENGINE_parse_body('{import_artica_computers}');
	$prefix=str_replace('.','_',$page);
	$prefix=str_replace('-','',$prefix);
	
	$start="browse_computers_start();";
	if(isset($_GET["in-front-ajax"])){$start="browse_computers_start_infront();";}
	$html="
	var rule_mem='';
	var {$prefix}timeout=0;
	var {$prefix}timerID  = null;
	var {$prefix}tant=0;
	var {$prefix}reste=0;	
	
	function browse_computers_start(){
		YahooLogWatcher(750,'$page?browse-computers=yes&mode={$_GET["mode"]}&value={$_GET["value"]}&callback={$_GET["callback"]}','$title');
		{$prefix}demarre();
	
	}
	
	function browse_computers_start_infront(){
	   $('#BodyContent').load('$page?browse-computers=yes&mode={$_GET["mode"]}&value={$_GET["value"]}&callback={$_GET["callback"]}&show-title=yes');
	   {$prefix}demarre();
	
	}	
	
	function {$prefix}demarre(){
		if(!YahooLogWatcherOpen()){return false;}
			{$prefix}tant = {$prefix}tant+1;
	
			if ({$prefix}tant <10 ) {                           
				{$prefix}timerID = setTimeout(\"{$prefix}demarre()\",1000);
		      } else {
						if(!YahooSetupControlOpen()){return false;}
						{$prefix}tant = 0;
						{$prefix}ChargeLogs();
						{$prefix}demarre();
		   }
	}
	
var x_{$prefix}ChargeLogs  = function (obj) {
		document.getElementById('progress-computers').innerHTML=obj.responseText;
	}	

	
	function {$prefix}ChargeLogs(){
		var XHR = new XHRConnection();
		XHR.appendData('Status','yes');
		XHR.sendAndLoad('$page', 'GET',x_{$prefix}ChargeLogs);  
	}
	
	
var x_ScanNetwork      = function (obj) {var tempvalue=obj.responseText;if(tempvalue.length>0){alert(tempvalue);}}

var x_AddNetworkPerform= function (obj) {YahooWin3Hide();ViewNetwork();}

var x_ClacNetmaskcdir  = function (obj) {
		document.getElementById('netmaskcdir').value=obj.responseText;
	}
	
	function ViewNetwork(){
		YahooWin2(550,'$page?browse-networks=yes','$networks');
	}
	
	function AddNetwork(){
		YahooWin3(450,'$page?browse-networks-add=yes','$networks');
	}
	
	function ViewComputerScanLogs(){
		YahooWin3(550,'$page?view-scan-logs=yes','$networks');
	}
	
	function NetworkDelete(md){
		var XHR = new XHRConnection();
		XHR.appendData('NetworkDelete',md);
		document.getElementById('networks').innerHTML='<div style=\"width:100%\"><center style=\"margin:20px;padding:20px\"><img src=\"img/wait_verybig.gif\"></center></div>';
		XHR.sendAndLoad('$page', 'GET',x_AddNetworkPerform);  	
	
	}
	
	function ClacNetmaskcdir(){
		var XHR = new XHRConnection();
		XHR.appendData('calc-cdir-ip',document.getElementById('ip_addr').value);
		XHR.appendData('calc-cdir-netmask',document.getElementById('netmask').value);
		XHR.sendAndLoad('$page', 'GET',x_ClacNetmaskcdir);        
	}
	
	
	function AddNetworkPerform(){
		var XHR = new XHRConnection();
		var cdir=document.getElementById('netmaskcdir').value;
		if(cdir.length>0){
			XHR.appendData('calc-cdir-ip-add',document.getElementById('netmaskcdir').value);
		 	document.getElementById('networks_add').innerHTML='<div style=\"width:100%\"><center style=\"margin:20px;padding:20px\"><img src=\"img/wait_verybig.gif\"></center></div>';
			XHR.sendAndLoad('$page', 'GET',x_AddNetworkPerform); 
		}  
	
	}
	
	
	function ScanNetwork(){
		var XHR = new XHRConnection();
		XHR.appendData('network-scanner-execute','yes');
        XHR.sendAndLoad('$page', 'GET',x_ScanNetwork);        
	
	}
	
	function BrowsComputersRefresh(){
		var mode='';
		var val='';
		if(document.getElementById('mode')){mode=document.getElementById('mode').value;}
		if(document.getElementById('value')){val=document.getElementById('value').value;}
		if(document.getElementById('callback')){callback=document.getElementById('callback').value;}
		LoadAjax('computerlist','$page?computer-refresh=yes&tofind='+document.getElementById('query_computer').value+'&mode={$_GET["mode"]}&{$_GET["value"]}&callback={$_GET["callback"]}');
	
	}
	
	var x_AddComputerToDansGuardian= function (obj) {
		var mid='ip_group_rule_list_'+rule_mem;
		LoadAjax(mid,'dansguardian.index.php?ip-group_list-rule='+rule_mem);
	}

	var x_DeleteAllComputers= function (obj) {
		var results=obj.responseText;
		alert(results);
		BrowsComputersRefresh();
	}
	
	function AddComputerToDansGuardian(uid,rule){
		var XHR = new XHRConnection();
		rule_mem=rule;
		XHR.appendData('AddComputerToDansGuardian',uid);
		XHR.appendData('AddComputerToDansGuardianRule',rule);
		XHR.sendAndLoad('dansguardian.index.php', 'GET',x_AddComputerToDansGuardian);
	}
	
	function BrowseComputerCheckRefresh(e){
		if(checkEnter(e)){BrowsComputersRefresh();}
	}
	function BrowseComputerFind(){BrowsComputersRefresh();}
	
	
	function DeleteAllComputers(){
		if(confirm('$delete_all_computers_warn')){
			var XHR = new XHRConnection();
			XHR.appendData('DeleteAllcomputers','yes');
			document.getElementById('computerlist').innerHTML='<center><img src=img/wait_verybig.gif></center>';
			XHR.sendAndLoad('$page', 'GET',x_DeleteAllComputers);  
		}
	}
	
var x_NetWorksDisable= function (obj) {
		ViewNetwork();
	}	
	
	function NetWorksDisable(mask){
			var XHR = new XHRConnection();
			XHR.appendData('NetWorksDisable',mask);
		 	document.getElementById('networks').innerHTML='<div style=\"width:100%\"><center style=\"margin:20px;padding:20px\"><img src=\"img/wait_verybig.gif\"></center></div>';
			XHR.sendAndLoad('$page', 'GET',x_NetWorksDisable); 
	} 
	
	function NetWorksEnable(mask){
			var XHR = new XHRConnection();
			XHR.appendData('NetWorksEnable',mask);
		 	document.getElementById('networks').innerHTML='<div style=\"width:100%\"><center style=\"margin:20px;padding:20px\"><img src=\"img/wait_verybig.gif\"></center></div>';
			XHR.sendAndLoad('$page', 'GET',x_NetWorksDisable); 
	} 	
	
	
	function ImportComputers(ip){
		YahooWin3('450','$page?artica-import-popup=yes&ip='+ip,'$import_artica_computers');
	
	}
	
	function ImportListComputers(){
		YahooWin3('450','$page?artica-importlist-popup=yes','$import_artica_computers');
	
	}

var x_ImportListComputersPerform= function (obj) {
		var results=obj.responseText;
		alert(results);
		YahooWin3Hide();
		BrowsComputersRefresh();
	}		
	
	function ImportListComputersPerform(){
			var XHR = new XHRConnection();
			XHR.appendData('popup_import_list',document.getElementById('popup_import_list').value);
		 	document.getElementById('popup_import_div').innerHTML='<div style=\"width:100%\"><center style=\"margin:20px;padding:20px\"><img src=\"img/wait_verybig.gif\"></center></div>';
			XHR.sendAndLoad('$page', 'POST',x_ImportListComputersPerform); 
	}
	
	
  	
		
	var x_SaveImportComputers= function (obj) {
			YahooWin3Hide();
			ViewNetwork();
		}	
		
	
	function SaveImportComputers(){
				var XHR = new XHRConnection();
				XHR.appendData('artica_ip_addr',document.getElementById('artica_ip_addr').value);
				XHR.appendData('port',document.getElementById('port').value);
				XHR.appendData('artica_user',document.getElementById('artica_user').value);
				XHR.appendData('password',document.getElementById('password').value);
			 	document.getElementById('import_artica_computers').innerHTML='<div style=\"width:100%\"><center style=\"margin:20px;padding:20px\"><img src=\"img/wait_verybig.gif\"></center></div>';
				XHR.sendAndLoad('$page', 'GET',x_SaveImportComputers); 
		} 	
	
	function DeleteImportComputers(ip){
				var XHR = new XHRConnection();
				XHR.appendData('artica-import-delete',ip);
				XHR.sendAndLoad('$page', 'GET',x_SaveImportComputers); 
	}
$start
	";
	
	
	echo $html;
}


function index(){
	$tpl=new templates();
	if(isset($_GET["show-title"])){
		$title=$tpl->_ENGINE_parse_body('{browse_computers}')."::";
	}
	
	if($_GET["mode"]=="dansguardian-ip-group"){
		$title_add=" - {APP_DANSGUARDIAN}";
	}
	
	if($_GET["mode"]=="selection"){
		$title_add=" - {select}";
	}
	
	
	
	$menus_right=menus_right();
	$list=computer_list();
	$html="
	<div style='float:right'>" . imgtootltip('32-refresh.png','{refresh}','BrowsComputersRefresh()')."</div>
	<H3 style='border-bottom:1px solid #005447'>$title$title_add</H3>
	<table style='width:100%'>
	<tr>
		<td valign='top' width='90%'>
			<div style='width:100%;height:350px;overflow:auto' id='computerlist'>" . $list."</div>
			<div style='text-align:right;padding:4px;width:100%'><hr>" .button('{delete_all}',"DeleteAllComputers()")."</div>
		</td>
		<td valign='top'>
			$menus_right
		</td>
	</tr>
	</table>
	<div id='progress-computers'></div>";
	
	
	
	
	echo $tpl->_ENGINE_parse_body($html);	
}

function computers_delete(){
	$filter_search="(&(objectClass=ArticaComputerInfos)(|(cn=*)(ComputerIP=*)(uid=*))(gecos=computer))";
	$ldap=new clladp();
	$attrs=array("uid","dn","ComputerOS");
	$dn="$ldap->suffix";
	$hash=$ldap->Ldap_search($dn,$filter_search,$attrs);
	for($i=0;$i<$hash["count"];$i++){
		if($hash["$i"]["uid"][0]==null){continue;}
		$count=$count+1;
		$computer=new computers($hash["$i"]["uid"][0]);
		$computer->DeleteComputer();
	}
	
	$tpl=new templates();
	echo $tpl->javascript_parse_text("{success}: $count {computers}");
	
}

function computer_list(){
	if($_GET["tofind"]=='*'){$_GET["tofind"]=null;}
	if($_GET["tofind"]==null){$tofind="*";}else{$tofind="*{$_GET["tofind"]}*";}
	$filter_search="(&(objectClass=ArticaComputerInfos)(|(cn=$tofind)(ComputerIP=$tofind)(uid=$tofind))(gecos=computer))";
	
$ldap=new clladp();
$attrs=array("uid","ComputerIP","ComputerOS","ComputerMachineType");
$dn="$ldap->suffix";
$hash=$ldap->Ldap_search($dn,$filter_search,$attrs,20);

$PowerDNS="<td width=1%><h3>&nbsp;|&nbsp;</h3></td><td><h3>". texttooltip('{APP_PDNS}','{APP_PDNS_TEXT}',"javascript:Loadjs('pdns.php')")."</h3></td>";

if($_GET["mode"]=="selection"){$PowerDNS=null;}

$html="

<input type='hidden' id='mode' value='{$_GET["mode"]}' name='mode'>
<input type='hidden' id='value' value='{$_GET["value"]}' name='value'>
<input type='hidden' id='callback' value='{$_GET["callback"]}' name='callback'>
<table style='width:100%'><tr>
<td width=1% nowrap><H3>{$hash["count"]} {computers}</H3></td>$PowerDNS</tr></table>
<table style='width:100%'>";



for($i=0;$i<$hash["count"];$i++){
	$realuid=$hash[$i]["uid"][0];
	$hash[$i]["uid"][0]=str_replace('$','',$hash[$i]["uid"][0]);
	$js=MEMBER_JS($realuid,1);
	
	if($_GET["mode"]=="dansguardian-ip-group"){
			$js_add="<td width=1%>" . imgtootltip('add-18.gif',"{add_computer}","AddComputerToDansGuardian('$realuid','{$_GET["value"]}')")."</td>";
	}
	
	if($_GET["mode"]=="selection"){
		$js="{$_GET["callback"]}('$realuid');";
	}
	

	$roolover=CellRollOver($js);
	$ip=$hash[$i][strtolower("ComputerIP")][0];
	$os=$hash[$i][strtolower("ComputerOS")][0];
	$type=$hash[$i][strtolower("ComputerMachineType")][0];
	$name=$hash[$i]["uid"][0];
	if(strlen($name)>25){$name=substr($name,0,23)."...";}
	
	
	if($os=="Unknown"){
		if($type<>"Unknown"){
			$os=$type;
		}
	}
	
	if(strlen($os)>20){$os=texttooltip(substr($os,0,17).'...',$os,null,null,1);}
	if(strlen($ip)>20){$ip=texttooltip(substr($ip,0,17).'...',$ip,null,null,1);}
	
	
	
	$html=$html . 
	"<tr>
	<td width=1% $roolover><img src='img/base.gif'></td>
	<td $roolover nowrap><strong>$name</strong></td>
	<td $roolover><strong>$ip</strong></td>
	<td $roolover><strong>$os</strong></td>
	$js_add
	</tr>
	";
	}
$html=$html . "</table>";
$tpl=new templates();
return  $tpl->_ENGINE_parse_body($html);		
	
}


function menus_right(){
	$findcomputer=Paragraphe("64-samba-find.png","{scan_your_network}",'{scan_your_network_text}',"javascript:ScanNetwork()","scan_your_network",210);
	$networs=Paragraphe("64-win-nic-loupe.png","{edit_networks}",'{edit_networks_text}',"javascript:ViewNetwork()","edit_networks",210);
	$add_computer_js="javascript:YahooUser(670,'domains.edit.user.php?userid=newcomputer$&ajaxmode=yes','windows: New computer');";
	$add_computer=Paragraphe("64-add-computer.png","{ADD_COMPUTER}","{ADD_COMPUTER_TEXT}",$add_computer_js);
	
	if($_GET["mode"]=="selection"){
		$networs=null;
		$findcomputer=null;
	}
	
	$users=new usersMenus();
	if(!$users->nmap_installed){
		$findcomputer=Paragraphe("64-samba-find-grey.png","{scan_your_network}",'{scan_your_network_text}',"","scan_your_network",210);
	}
	
	$html=
	"<table>
	<tr>
	<td>
		<table style='width:100%' class='table_form'>
		<tr>
		<td class=legend>{query}:</td>
		<td>" . Field_text('query_computer',null,'width:120xp',null,"BrowseComputerFind()",false,false,'BrowseComputerCheckRefresh(event)')."</td>
		</tr>
		<tr>
	
		</table>
		$findcomputer$networs$add_computer
	</td>
	</tr>
	</table>";
	
	$tpl=new templates();
	return $tpl->_ENGINE_parse_body($html);		
}

function events(){
	$tpl=new templates();
	
	echo $tpl->_ENGINE_parse_body("<H1>{events}</H1>");
	
	if(!is_file("ressources/logs/nmap.log")){
		echo $tpl->_ENGINE_parse_body("{error_no_datas}");
		return;
	}
	
	$datas=@file_get_contents("ressources/logs/nmap.log");
	$tpl=explode("\n",$datas);
	if(!is_array($tpl)){
		echo $tpl->_ENGINE_parse_body("{error_no_datas}");
		return;
	}
	
rsort($tpl);
	
while (list ($num, $line) = each ($tpl)){
		if(trim($line)==null){continue;}
		$html=$html . "<div><code style='font-size:10px'>$line</code></div>";
}		

$html="<div style='width:100%;height:230px;overflow:auto'>$html</div>";
$html=RoundedLightWhite($html);
	echo $html;
	
}

function networks(){
	
	$networsplus=Paragraphe("64-win-nic-plus.png","{add_network}",'{add_network_text}',"javascript:AddNetwork()","add_network",210);
	$importArtica=Paragraphe("64-samba-get.png","{import_artica_computers}",'{import_artica_computers_text}',"javascript:ImportComputers('')","import_artica_computers",210);
	$importList=Paragraphe("64-samba-get.png","{import_artica_computers}",'{import_artica_computers_list_text}',"javascript:ImportListComputers()","import_artica_computers_list_text",210);
	
	
	$articas=artica_import_list();
	$nets=networkslist(1);
	$html="<H1>{edit_networks}</H1>
	<div id='networks'>
	<table style='width:100%'>
	<tr>
		<td valign='top' width=70%>
			<div style='width:100%;height:200px;overflow:auto'>
				" . RoundedLightWhite("<div id='netlist'>$nets</div>")."
			</div>
			<br>
			<div style='width:100%;height:200px;overflow:auto'>
				" . RoundedLightWhite("<div id='articas'>$articas</div>")."
			</div>	
					
			
			
		</td>
		<td valign='top'>
		$networsplus
		$importArtica
		$importList
		</td>
	</tr>
	</table>
	</div>";
	
	$tpl=new templates();
	echo $tpl->_ENGINE_parse_body($html);
	}
	
	
function networks_disable(){
	$net=new networkscanner();
	$net->disable_net($_GET["NetWorksDisable"]);
}
function networks_enable(){
	$net=new networkscanner();
	$net->enable_net($_GET["NetWorksEnable"]);	
	
}
	
function networks_add(){
	$html="<H1>{add_network}</H1>
	<div id='networks_add'>
		" . RoundedLightWhite("
		<table style='width:100%'>
			<tr>
				<td valign='top' class=legend>{ip_address}:</td>
				<td valign='top'>".Field_text("ip_addr",null,'width:120px',null,'ClacNetmaskcdir()',null,false,"ClacNetmaskcdir()")."</td>
				<td valign='top' class=legend>{netmask}:</td>
				<td valign='top'>".Field_text("netmask","255.255.255.0",'width:120px',null,'ClacNetmaskcdir()',null,false,"ClacNetmaskcdir()")."</td>				
			</tr>
			
			
			<tr>
				<td valign='top' colspan=4 align='right' style='padding-right:13px'>
				". Field_text('netmaskcdir',null,'width:120px')."
				</td>
				
				
			</tr>
			<TR>
				<td colspan=4 align='right'>
					<hr>
					<input type='button' OnClick=\"javascript:AddNetworkPerform();\" value='{add}&nbsp;&raquo;'>
				</td>
			</tr>
		</table>")."</div>";
		
		
	
	
	$tpl=new templates();
	echo $tpl->_ENGINE_parse_body($html);	
}

function artica_import_delete(){
	$sock=new sockets();
	$ini=new Bs_IniHandler();
	$ini->loadString($sock->GET_INFO("ComputersImportArtica"));	
	unset($ini->_params[$_GET["artica-import-delete"]]);
	$sock->SaveConfigFile($ini->toString(),"ComputersImportArtica");
}
	


function artica_import_popup(){
	
	//cyrus.murder.php
	$sock=new sockets();
	$ini=new Bs_IniHandler();
	$ini->loadString($sock->GET_INFO("ComputersImportArtica"));	
	$array=$ini->_params[$_GET["ip"]];
	$html="<H1>{import_artica_computers}</H1>
	<p class=caption>{import_artica_computers_explain}</p>
	<div id='import_artica_computers'>
		" . RoundedLightWhite("
		<table style='width:100%'>
			<tr>
				<td valign='top' class=legend>{REMOTE_ARTICA_SERVER}:</td>
				<td valign='top'>".Field_text("artica_ip_addr",$array["artica_ip_addr"],'width:120px',null)."</td>
			</tr>
			<tr>
				<td valign='top' class=legend>{REMOTE_ARTICA_SERVER_PORT}:</td>
				<td valign='top'>".Field_text("port",$array["port"],'width:120px',null)."</td>			
			</tr>
			<tr>
				<td valign='top' class=legend>{username}:</td>
				<td valign='top'>".Field_text("artica_user",$array["artica_user"],'width:120px',null)."</td>
			</tr>
			<tr>				
				<td valign='top' class=legend>{password}:</td>
				<td valign='top'>".Field_password("password",$array["password"],'width:120px',null)."</td>			
			</tr>			
			<TR>
				<td colspan=2 align='right'>
					<hr>
					<input type='button' OnClick=\"javascript:SaveImportComputers();\" value='{edit}&nbsp;&raquo;'>
				</td>
			</tr>
		</table>")."</div>";
		
		
	
	
	$tpl=new templates();
	echo $tpl->_ENGINE_parse_body($html);		
	
}

function artica_import_list(){
	$sock=new sockets();
	$ini=new Bs_IniHandler();
	$ini->loadString($sock->GET_INFO("ComputersImportArtica"));
	if(!is_array($ini->_params)){return null;}
	$html="
	
	<div style='font-size:13px;padding-bottom:5px;font-weight:bold'>{import_artica_computers}</div>
	<table>";
	while (list ($ip, $array) = each ($ini->_params)){
		if(trim($ip)==null){continue;}
		
		$delete=imgtootltip('ed_delete.gif','{delete}',"DeleteImportComputers('$ip')");
		$js="ImportComputers('$ip')";
		
		$html=$html . "
		<tr " . CellRollOver($js).">
			<td width=1%><img src='img/fw_bold.gif'></td>
			<td><strong style='font-size:13px'>$ip:{$array["port"]}</td>
			<td><strong style='font-size:13px'>{$array["artica_user"]}</td>
			<td>$delete</td>
		</tr>
		
		";}
			
	$tpl=new templates();
	return $tpl->_ENGINE_parse_body("$html</table>");
	echo $tpl->_ENGINE_parse_body($html);
	
	
	
}


function artica_import_save(){
	$sock=new sockets();
	$ini=new Bs_IniHandler();
	$ini->loadString($sock->GET_INFO("ComputersImportArtica"));
	
	while (list ($num, $line) = each ($_GET)){
		$ini->_params[$_GET["artica_ip_addr"]][$num]=$line;
	}
	
	$sock->SaveConfigFile($ini->toString(),"ComputersImportArtica");
	$sock->getFrameWork("cmd.php?computers-import-nets=yes");
	
}

function artica_importlist_popup(){
	$html="<p style='font-size:13px'>{computer_popup_import_explain}</p>
	<div id='popup_import_div'>
	<textarea id='popup_import_list' style='width:99%;height:450px;overflow:auto'></textarea>
	<div style='text-align:right'>
		<hr>
			". button("{import}","ImportListComputersPerform()")."
	</div>
	</div>";
	
	$tpl=new templates();
	echo $tpl->_ENGINE_parse_body($html);	
}


function artica_importlist_perform(){
	$datas=$_POST["popup_import_list"];
	$sock=new sockets();
	$sock->SaveConfigFile($datas,"ComputerListToImport");	
	$sock->getFrameWork("cmd.php?browse-computers-import-list=yes");
	$tpl=new templates();
	echo $tpl->javascript_parse_text("{importation_background_text}");
	

}

	
function networkslist($noecho=1){
	
	$net=new networkscanner();
	if(!is_array($net->networklist)){return null;}
	$html="<table style='width:100%'>";
	
while (list ($num, $maks) = each ($net->networklist)){
		if(trim($maks)==null){continue;}
		$hash[$maks]=$maks;
}	
	
	while (list ($num, $maks) = each ($hash)){
		if(trim($maks)==null){continue;}
		
		$delete=imgtootltip('ed_delete.gif','{delete}',"NetworkDelete('" . md5($num)."')");
		if($net->DefaultNetworkList[$maks]){
			if(!$net->Networks_disabled[$maks]){
				$style=null;
				$delete="{default}&nbsp;" .texttooltip("{enabled}","{disable}","NetWorksDisable('$maks');",null,0,'font-size:12px;color:black');
			}else{
				
				$style=";text-decoration:line-through";
				$delete="{default}&nbsp;" .texttooltip("{disabled}","{enable}","NetWorksEnable('$maks');",null,0,'font-size:12px;color:red');
			}
			
		}
		
		$html=$html . "
		<tr " . CellRollOver().">
			<td width=1%><img src='img/fw_bold.gif'></td>
			<td><strong style='font-size:13px$style' nowrap>$maks</td>
			<td nowrap>$delete</td>
		</tr>
		
		";}
			
	$tpl=new templates();
	if($noecho==1){return $tpl->_ENGINE_parse_body("$html</table>");}
	echo $tpl->_ENGINE_parse_body($html);			
	
	
}




function network_scanner_execute(){
	$tpl=new templates();
	$net=new networkscanner();
	$net->save();
	$sock=new sockets();
	$sock->getFrameWork("cmd.php?LaunchNetworkScanner=yes");
	$box=$tpl->_ENGINE_parse_body('{network_scanner_execute_background}');
	
	$ini=new Bs_IniHandler('ressources/logs/nmap.progress.ini');
	$ini->set('NMAP','pourc','10');
	$ini->set('NMAP','text','{scheduled}');	
	$ini->saveFile('ressources/logs/nmap.progress.ini');
	
	echo $box;
	
}

function Status(){
	$ini=new Bs_IniHandler('ressources/logs/nmap.progress.ini');
	$pourc=$ini->get('NMAP','pourc');
	$text=$ini->get('NMAP','text');
	if($pourc==null){$pourc=0;}
	if($pourc==0){$text="{sleeping}";}
	if($pourc==100){$text="{success}";}
	$color="#5DD13D";	
	$tpl=new templates();
$html="
<table style='width:100%'>
<tr>
<td valign='top'>
	<p class=caption>$text...</p>
	<div style='width:100%;background-color:white;padding-left:0px;border:1px solid $color'>
		<div id='progression_computers'>
			<div style='width:{$pourc}%;text-align:center;color:white;padding-top:3px;padding-bottom:3px;background-color:$color'>
				<strong style='color:#BCF3D6;font-size:12px;font-weight:bold'>{$pourc}%</strong></center>
			</div>
		</div>
	</div>
</td>
<td valign=middle width=1%><div style='background-color:white;padding:5px'>" . imgtootltip('loupe-32.png','{events}',"ViewComputerScanLogs()")."</div></td>
</tr>
</table>
";	
				
echo $tpl->_ENGINE_parse_body($html);				
				
}


class networkscanner{
	var $networklist=array();
	var $DefaultNetworkList=array();
	var $Networks_disabled=array();
	
	
	function networkscanner(){
		$sock=new sockets();
		$datas=$sock->GET_INFO('NetworkScannerMasks');
		$tbl=explode("\n",$datas);
		
		$disabled=$sock->GET_INFO('NetworkScannerMasksDisabled');
		
		
		while (list ($num, $maks) = each ($tbl) ){
		if(trim($maks)==null){continue;}
			$arr[trim($maks)]=trim($maks);
		}
		
	if(is_array($arr)){
			while (list ($num, $net) = each ($arr)){
				$this->networklist[]=$net;
			}
		}

	
	$tbl=explode("\n",$disabled);	
	if(is_array($tbl)){
		while (list ($num, $maks) = each ($tbl) ){
			if(trim($maks)==null){continue;}
			$this->Networks_disabled[$maks]=true;
		}
	}
		
		
		$this->builddefault();
		
	}
	
	function disable_net($net){
		$sock=new sockets();
		$disabled=$sock->GET_INFO('NetworkScannerMasksDisabled');
		$disabled=$disabled."\n".$net;
		$sock=new sockets();
		$sock->SaveConfigFile($disabled,"NetworkScannerMasksDisabled");
	}
	
	function enable_net($net){
		$sock=new sockets();
		$disabled=$sock->GET_INFO('NetworkScannerMasksDisabled');
		$tbl=explode("\n",$disabled);	
		if(is_array($tbl)){
			while (list ($num, $maks) = each ($tbl) ){
				if(trim($maks)==null){continue;}
				$Networks_disabled[$maks]=$maks;
			}
		}

		unset($Networks_disabled[$net]);
		if(is_array($Networks_disabled)){
			while (list ($num, $maks) = each ($Networks_disabled) ){
				if(trim($maks)==null){continue;}
				$conf=$conf.$maks."\n";
			}
		}
		
		$sock->SaveConfigFile($conf,"NetworkScannerMasksDisabled");
		
		
		
	}
	
	
	function save(){
		if(is_array($this->networklist)){
			reset($this->networklist);
			while (list ($num, $maks) = each ($this->networklist)){
				if(trim($maks)==null){continue;}
				$arr[trim($maks)]=trim($maks);
				}
			}
		
		if(is_array($arr)){
			
			while (list ($num, $net) = each ($arr)){
				$conf=$conf . "$net\n";
			}
		}
		echo $conf;
		$sock=new sockets();
		$sock->SaveConfigFile($conf,"NetworkScannerMasks");
		$sock->DeleteCache();
		
	}
	
	function builddefault(){
		
		$net=new networking();
		$cip=new IP();
		while (list ($num, $ip) = each ($net->array_TCP)){
			if(preg_match('#([0-9]+)\.([0-9]+)\.([0-9]+)\.([0-9]+)#',$ip,$re)){
				$ip_start="{$re[1]}.{$re[2]}.{$re[3]}.0";
				$ip_end="{$re[1]}.{$re[2]}.{$re[3]}.255";
				$cdir=$cip->ip2cidr($ip_start,$ip_end);
				if(trim($cdir)<>null){
					$this->DefaultNetworkList[trim($cdir)]=true;
					$this->networklist[]=$cdir;
				}
			}
			
		}
		
		
		
	}
	
	
		
	
}




?>