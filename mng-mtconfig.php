<?php
/*
 *********************************************************************************************************
 * daloRADIUS - RADIUS Web Platform
 * Copyright (C) 2020 - Christoph Haas <christoph.h44z@gmail.com> All Rights Reserved.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 *********************************************************************************************************
 *
 * Authors:	Christoph Haas <christoph.h44z@gmail.com>
 *
 *********************************************************************************************************
 */

$logAction = "";
$logDebugSQL = "";    // initialize variable

include("library/checklogin.php");
$operator = $_SESSION['operator_user'];

include('library/check_operator_perm.php');

// set session's page variable
$_SESSION['PREV_LIST_PAGE'] = $_SERVER['REQUEST_URI'];


//setting values for the order by and order type variables
isset($_REQUEST['orderBy']) ? $orderBy = $_REQUEST['orderBy'] : $orderBy = "id";
isset($_REQUEST['orderType']) ? $orderType = $_REQUEST['orderType'] : $orderType = "asc";

include_once('library/config_read.php');
$log = "visited page: ";
$logQuery = "performed query for listing of records on page: ";


if (isset($_REQUEST['username'])) {
	$username = trim($_REQUEST['username']);
} else {
	$username = "";
}

if (isset($_REQUEST['routerboard'])) {
	$routerboard = trim($_REQUEST['routerboard']);
} else {
	$routerboard = "sxt";
}

if (isset($_REQUEST['ipaddr'])) {
	$ipaddr = trim($_REQUEST['ipaddr']);
} else {
	$ipaddr = "10.0.0.138/24";
}

function cidrToRange($cidr) {
	$range = array();
	$cidr = explode('/', $cidr);
	$range[0] = long2ip((ip2long($cidr[0])) & ((-1 << (32 - (int)$cidr[1])))); // net
	$range[1] = long2ip((ip2long($range[0])) + pow(2, (32 - (int)$cidr[1])) - 1); // broadcast
	$range[2] = $cidr[1];
	$range[3] = $cidr[0];
	return $range;
}

$ipcidr = cidrToRange($ipaddr);
$netaddr = $ipcidr[0];
$broadcast = $ipcidr[1];
$cidr = $ipcidr[2];
$host = $ipcidr[3];

$dhcp_range = 50;
$dhcp_start = long2ip(ip2long($netaddr) + 10); // start at 10 (192.168.0.10)
$dhcp_end = long2ip(ip2long($dhcp_start) + $dhcp_range);

include 'library/opendb.php';

/* an sql query to retrieve the password for the username to use in the quick link for the user test connectivity */
$sql = "SELECT Value FROM " . $configValues['CONFIG_DB_TBL_RADCHECK'] . " WHERE UserName='" .
	$dbSocket->escapeSimple($username) . "' AND Attribute like '%Password'";
$res = $dbSocket->query($sql);
$logDebugSQL .= $sql . "\n";

$row = $res->fetchRow();
$user_password = $row[0];

/* fill-in all the user info details */
$sql = "SELECT firstname, lastname, enode_ssid, enode_wpa FROM " .
	$configValues['CONFIG_DB_TBL_DALOUSERINFO'] .
	" WHERE UserName='" .
	$dbSocket->escapeSimple($username) . "'";
$res = $dbSocket->query($sql);
$logDebugSQL .= $sql . "\n";

$row = $res->fetchRow();

$ui_firstname = $row[0];
$ui_lastname = $row[1];
$ui_enode = $row[2];
$ui_enode_key = $row[3];

$mtname = strtolower($ui_firstname . $ui_lastname);

$rb_config = array();

$rb_config["rb951b"] = '
/interface bridge</br>
add name=bridgeLAN</br>
/interface ethernet</br>
set [ find default-name=ether5 ] comment="WAN - POE"</br>
/interface wireless security-profiles</br>
set [ find default=yes ] supplicant-identity=MikroTik</br>
add authentication-types=wpa-psk,wpa2-psk eap-methods="" mode=dynamic-keys \</br>
    name=HOME supplicant-identity="" wpa-pre-shared-key=' . strtoupper(substr(base64_encode($user_password), 0, 8)) . ' wpa2-pre-shared-key=' . strtoupper(substr(base64_encode($user_password), 0, 8)) . '</br>
/interface wireless</br>
set [ find default-name=wlan1 ] band=2ghz-b/g/n country=austria disabled=no \</br>
    frequency=auto frequency-mode=regulatory-domain mode=ap-bridge \</br>
    security-profile=HOME ssid=Stubainet' . strtoupper(substr(base64_encode($username), 0, 4)) . ' </br>
/interface bridge port</br>
add bridge=bridgeLAN interface=ether1</br>
add bridge=bridgeLAN interface=ether2</br>
add bridge=bridgeLAN interface=ether3</br>
add bridge=bridgeLAN interface=ether4</br>
add bridge=bridgeLAN interface=ether5</br>
add bridge=bridgeLAN interface=wlan1</br>
/ip dhcp-client</br>
add default-route-distance=0 dhcp-options=hostname,clientid disabled=no \</br>
    interface=bridgeLAN</br>
/ip service</br>
set telnet disabled=yes</br>
set ftp disabled=yes</br>
/system clock</br>
set time-zone-autodetect=no time-zone-name=Europe/Vienna</br>
/system identity</br>
set name=RB951_' . $mtname . '</br>
/system ntp client</br>
set enabled=yes primary-ntp=83.218.179.254</br>
/</br>
password old-password="" new-password="' . getenv('MT_PASS') . '" confirm-new-password="' . getenv('MT_PASS') . '"</br>
</br>';

$rb_config["sxt"] = '
/interface wireless security-profiles</br>
add authentication-types=wpa2-psk eap-methods=passthrough management-protection=allowed mode=dynamic-keys name=' . $ui_enode . ' supplicant-identity="" wpa2-pre-shared-key=' . $ui_enode_key . '</br>
/interface wireless</br>
set 0 band=5ghz-a/n country=austria disabled=no frequency-mode=regulatory-domain rx-chains=0,1 tx-chains=0,1 nv2-preshared-key=' . $ui_enode_key . ' nv2-security=enabled radio-name=' . $mtname . ' security-profile=' . $ui_enode . ' ssid=' . $ui_enode . ' wireless-protocol=nv2-nstreme-802.11</br>
/interface pppoe-client</br>
add add-default-route=yes allow=mschap1,mschap2 disabled=no interface=wlan1 name=pppoe-out1 password=' . $user_password . ' user=' . $username . '</br>
/ip pool</br>
add name=dhcp_pool1 ranges=' . $dhcp_start . '-' . $dhcp_end . '</br>
/ip dhcp-server</br>
add address-pool=dhcp_pool1 disabled=no interface=ether1 name=dhcp1</br>
/ip address</br>
add address=' . $ipaddr . ' interface=ether1</br>
/ip dhcp-client</br>
add default-route-distance=0 disabled=no interface=wlan1</br>
/ip dhcp-server network</br>
add address='. $netaddr . '/' . $cidr . ' dns-server=83.218.160.1,83.218.160.2 gateway=' . $host . '</br>
/ip firewall nat</br>
add action=masquerade chain=srcnat dst-address=0.0.0.0/0 src-address='. $netaddr . '/' . $cidr . '</br>
/ip dns set servers=83.218.160.1,83.218.160.2</br>
/ip service</br>
set telnet disabled=yes</br>
set ftp disabled=yes</br>
set ssh port=7722 disabled=no</br>
/ip upnp</br>
set enabled=yes</br>
/system clock</br>
set time-zone-name=Europe/Vienna</br>
/system identity</br>
set name=SXT_' . $mtname . '</br>
/system ntp client</br>
set enabled=yes primary-ntp=10.90.1.254</br>
/</br>
password old-password="" new-password="' . getenv('MT_PASS') . '" confirm-new-password="' . getenv('MT_PASS') . '"</br>
/ipv6 address</br>
add from-pool=poolSnetV6 interface=ether1</br>
/ipv6 dhcp-client</br>
add add-default-route=yes interface=pppoe-out1 pool-name=poolSnetV6</br>
</br>';

$rb_config["rb411"] = '
/interface wireless security-profiles</br>
add authentication-types=wpa2-psk eap-methods=passthrough management-protection=allowed mode=dynamic-keys name=' . $ui_enode . ' supplicant-identity="" wpa2-pre-shared-key=' . $ui_enode_key . '</br>
/interface wireless</br>
set 0 band=5ghz-a/n country=austria disabled=no frequency-mode=regulatory-domain rx-chains=0,1 tx-chains=0,1 nv2-preshared-key=' . $ui_enode_key . ' nv2-security=enabled radio-name=' . $mtname . ' security-profile=' . $ui_enode . ' ssid=' . $ui_enode . ' wireless-protocol=nv2-nstreme-802.11</br>
/interface pppoe-client</br>
add add-default-route=yes allow=mschap1,mschap2 disabled=no interface=wlan1 name=pppoe-out1 password=' . $user_password . ' user=' . $username . '</br>
/ip pool</br>
add name=dhcp_pool1 ranges=' . $dhcp_start . '-' . $dhcp_end . '</br>
/ip dhcp-server</br>
add address-pool=dhcp_pool1 disabled=no interface=ether1 name=dhcp1</br>
/ip address</br>
add address=' . $ipaddr . ' interface=ether1</br>
/ip dhcp-client</br>
add default-route-distance=0 disabled=no interface=wlan1</br>
/ip dhcp-server network</br>
add address='. $netaddr . '/' . $cidr . ' dns-server=83.218.160.1,83.218.160.2 gateway=' . $host . '</br>
/ip firewall nat</br>
add action=masquerade chain=srcnat dst-address=0.0.0.0/0 src-address='. $netaddr . '/' . $cidr . '</br>
/ip dns set servers=83.218.160.1,83.218.160.2</br>
/ip service</br>
set telnet disabled=yes</br>
set ftp disabled=yes</br>
set ssh port=7722 disabled=no</br>
/ip upnp</br>
set enabled=yes</br>
/system clock</br>
set time-zone-name=Europe/Vienna</br>
/system identity</br>
set name=RB411_' . $mtname . '</br>
/system ntp client</br>
set enabled=yes primary-ntp=10.90.1.254</br>
/</br>
password old-password="" new-password="' . getenv('MT_PASS') . '" confirm-new-password="' . getenv('MT_PASS') . '"</br>
</br>';
$rb_config["rb411ar"] = '
/interface wireless security-profiles</br>
add authentication-types=wpa2-psk eap-methods=passthrough management-protection=allowed mode=dynamic-keys name=' . $ui_enode . ' supplicant-identity="" wpa2-pre-shared-key=' . $ui_enode_key . '</br>
add authentication-types=wpa-psk,wpa2-psk eap-methods=passthrough group-ciphers=tkip management-protection=allowed mode=dynamic-keys name=home supplicant-identity="" unicast-ciphers=tkip wpa-pre-shared-key=' . strtoupper(substr(base64_encode($user_password), 0, 8)) . ' wpa2-pre-shared-key=' . strtoupper(substr(base64_encode($user_password), 0, 8)) . '</br>
/interface wireless</br>
set 0 band=2ghz-onlyg country=austria disabled=no frequency-mode=regulatory-domain mode=ap-bridge scan-list=default security-profile=home ssid=Stubainet' . strtoupper(substr(base64_encode($username), 0, 4)) . '</br>
set 1 band=5ghz-a/n country=austria disabled=no frequency-mode=regulatory-domain rx-chains=0,1 tx-chains=0,1 nv2-preshared-key=' . $ui_enode_key . ' nv2-security=enabled radio-name=' . $mtname . ' security-profile=' . $ui_enode . ' ssid=' . $ui_enode . ' wireless-protocol=nv2-nstreme-802.11</br>
/interface pppoe-client</br>
add add-default-route=yes allow=mschap1,mschap2 disabled=no interface=wlan2 name=pppoe-out1 password=' . $user_password . ' user=' . $username . '</br>
/interface bridge</br>
add name=bridgeLAN</br>
/interface bridge port</br>
add bridge=bridgeLAN interface=wlan1</br>
add bridge=bridgeLAN interface=ether</br>
/ip pool</br>
add name=dhcp_pool1 ranges=' . $dhcp_start . '-' . $dhcp_end . '</br>
/ip dhcp-server</br>
add address-pool=dhcp_pool1 disabled=no interface=bridgeLAN name=dhcp1</br>
/ip address</br>
add address=' . $ipaddr . ' interface=bridgeLAN</br>
/ip dhcp-client</br>
add default-route-distance=0 disabled=no interface=wlan2</br>
/ip dhcp-server network</br>
add address='. $netaddr . '/' . $cidr . ' dns-server=83.218.160.1,83.218.160.2 gateway=' . $host . '</br>
/ip firewall nat</br>
add action=masquerade chain=srcnat dst-address=0.0.0.0/0 src-address='. $netaddr . '/' . $cidr . '</br>
/ip dns set servers=83.218.160.1,83.218.160.2</br>
/ip service</br>
set telnet disabled=yes</br>
set ftp disabled=yes</br>
set ssh port=7722 disabled=no</br>
/ip upnp</br>
set enabled=yes</br>
/system clock</br>
set time-zone-name=Europe/Vienna</br>
/system identity</br>
set name=RB411AR_' . $mtname . '</br>
/system ntp client</br>
set enabled=yes primary-ntp=10.90.1.254</br>
/</br>
password old-password="" new-password="' . getenv('MT_PASS') . '" confirm-new-password="' . getenv('MT_PASS') . '"</br>
</br>';
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
<head>
<script src="library/javascript/pages_common.js" type="text/javascript"></script>
<script src="library/javascript/rounded-corners.js" type="text/javascript"></script>
<script src="library/javascript/form-field-tooltip.js" type="text/javascript"></script>

<script type="text/javascript" src="library/javascript/ajax.js"></script>
<script type="text/javascript" src="library/javascript/ajaxGeneric.js"></script>

<title>daloRADIUS</title>
<meta http-equiv="content-type" content="text/html; charset=utf-8" />
<link rel="stylesheet" href="css/1.css" type="text/css" media="screen,projection" />
<link rel="stylesheet" href="css/form-field-tooltip.css" type="text/css" media="screen,projection" />
<link rel="stylesheet" type="text/css" href="library/js_date/datechooser.css">
<!--[if lte IE 6.5]>
<link rel="stylesheet" type="text/css" href="library/js_date/select-free.css"/>
<![endif]-->
</head>

<?php
include("menu-mng-users.php");
?>

<div id="contentnorightbar">

	<h2 id="Intro"><a href="#">Mikrotik Config :: <?php if (isset($username)) {
				echo $username;
			} ?>
			<h144>+</h144>
		</a></h2>
	<form method="post" action="mng-mtconfig.php?username=<?php echo $username;?>" name="mtconfig">
		<table>
			<thead>
			<tr>
				<th align="left">
					<label for="ipaddr"><b>Local IP Address:</b></label>
					<select name="ipaddr" onchange="this.form.submit()">
						<option <?php echo $ipaddr == "10.0.0.138/24" ? "selected" : ""?> value="10.0.0.138/24">10.0.0.138/24</option>
						<option <?php echo $ipaddr == "10.5.50.254/24" ? "selected" : ""?> value="10.5.50.254/24">10.5.50.254/24</option>
						<option <?php echo $ipaddr == "192.168.0.254/24" ? "selected" : ""?> value="192.168.0.254/24">192.168.0.254/24</option>
						<option <?php echo $ipaddr == "192.168.1.254/24" ? "selected" : ""?> value="192.168.1.254/24">192.168.1.254/24</option>
						<option <?php echo $ipaddr == "192.168.55.254/24" ? "selected" : ""?> value="192.168.55.254/24">192.168.55.254/24</option>
						<option <?php echo $ipaddr == "172.16.1.1/24" ? "selected" : ""?> value="172.16.1.1/24">172.16.1.1/24</option>
					</select>
					<br>
					<label for="routerboard"><b>Routerboard:</b></label>
					<select name="routerboard" onchange="this.form.submit()">
						<option <?php echo $routerboard == "rb411" ? "selected" : ""?> value="rb411">RB 411</option>
						<option <?php echo $routerboard == "rb411ar" ? "selected" : ""?> value="rb411ar">RB 411 AR</option>
						<option <?php echo $routerboard == "rb951b" ? "selected" : ""?> value="rb951b">RB 951 Bridge</option>
						<option <?php echo $routerboard == "sxt" ? "selected" : ""?> value="sxt">SXT</option>
					</select>
				</th>
			</tr>
			</thead>
			<tr>
				<td>
					<p style="padding:2px"><?php echo $rb_config[$routerboard]; ?></p>
				</td>
			</tr>
		</table>
	</form>
<?php
	include('include/config/logging.php');
?>
</div>
<div id="footer">
<?php
	include 'page-footer.php';
?>
</div>
</body>
