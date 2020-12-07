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

#include('library/check_operator_perm.php');

// set session's page variable
$_SESSION['PREV_LIST_PAGE'] = $_SERVER['REQUEST_URI'];


//setting values for the order by and order type variables
isset($_REQUEST['orderBy']) ? $orderBy = $_REQUEST['orderBy'] : $orderBy = "id";
isset($_REQUEST['orderType']) ? $orderType = $_REQUEST['orderType'] : $orderType = "asc";

include_once('library/config_read.php');
$log = "visited page: ";
$logQuery = "performed query for listing of records on page: ";


if (isset($_REQUEST['subnet'])) {
	$subnet = trim($_REQUEST['subnet']);
} else {
	$subnet = "83.218.179.";
}

if (isset($_REQUEST['search'])) {
	$iparr = explode(".",trim($_REQUEST['search']));
	$searchip = end($iparr);
} else {
	$searchip = "";
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
if($searchip !== "") {
	$sql = "SELECT username, value FROM " . $configValues['CONFIG_DB_TBL_RADREPLY'] . " WHERE attribute='Framed-IP-Address' AND value like '" . $dbSocket->escapeSimple($subnet) .  $dbSocket->escapeSimple($searchip) . "' ORDER BY INET_ATON(value)";
} else {
	$sql = "SELECT username, value FROM " . $configValues['CONFIG_DB_TBL_RADREPLY'] . " WHERE attribute='Framed-IP-Address' AND value like '" . $dbSocket->escapeSimple($subnet) . "%' ORDER BY INET_ATON(value)";
}
$res = $dbSocket->query($sql);
$logDebugSQL .= $sql . "\n";
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
	<h2 id="Intro"><a href="#">Public IP List :: <?php if (isset($subnet)) {
				echo $subnet;
			} ?>
		</a>
	</h2>
	<form method="post" action="mng-list-ips.php" name="mtconfig">
		<table>
			<thead>
			<tr>
				<th align="left">
					<label for="subnet"><b>IP Subnet:</b></label>
					<select name="subnet" onchange="this.form.submit()">
						<option <?php echo $subnet== "83.218.179." ? "selected" : ""?> value="83.218.179.">83.218.179.0/24</option>
						<option <?php echo $subnet== "10.0.0." ? "selected" : ""?> value="10.0.0.">10.0.0.0/24</option>
					</select>
				</th>
				<th align="left">
					<label for="search"><b>Search Host (last part of IP):</b></label>
					<input name="search" type="text" value="<?php echo $searchip;?>" >
					<input type=submit value="Search">
				</th>
			</tr>
			</thead>
		 	<?php 
				while($row = $res->fetchRow()) {
			?>
			<tr>
				<td>
					<p style="padding:2px; margin:0px;line-height:inherit;"><?php
						echo $row[1];
					?></p>
				</td>
				<td>
						<p style="padding:2px; margin:0px;line-height:inherit;"><?php
							echo $row[0];
						?></p>
				</td>
			</tr>
			<?php
				}
			?>
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
</html>
