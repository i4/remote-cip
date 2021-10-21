<?php
/*  Remote IDE via Xpra
 *  Copyright (C) 2020 Bernhard Heinloth and Felix Kraus
 *  Friedrich-Alexander-Universität Erlangen-Nürnberg (FAU).
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *   along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
$debug = false;
error_reporting($debug ? E_ALL : 0);

// Path to Xpra binary -- Debian Bullseye has Xpra 3 in its official packages
// however, we use https://xpra.org/dists/bullseye/main/binary-amd64/xpra_4.2.3-r7-2_amd64.deb
// extracted in a local folder due to better performance
$xpra_bin = 'PYTHONPATH=/local/xpra-4.2.X/usr/lib/python3/dist-packages /local/xpra-4.2.X/usr/bin/xpra';

// Parameters for Xpra server
$xpra_param = '--idle-timeout=3600 --server-idle-timeout=30 --mdns=no --webcam=off --html=off --bell=no --terminate-children=yes';

// Sharing
$xpra_param_sharing = '--sharing=yes --resize-display=no --desktop-scaling=auto --start-after-connect=/proj/ciptmp/heinloth/exit-with-first-client.sh ';

// Full desktop sessions (not shareable)
$xpra_desktop = 'start-desktop --start-after-connect=/proj/ciptmp/heinloth/disable-bell.sh --start=';
$xpra_mode['xfce']    = $xpra_desktop . 'xfce4-session';

// Application window sessions (shareable)
$xpra_app = 'start --exit-with-children=yes --exit-with-client=yes --start-child=';
$xpra_mode['xterm']    = $xpra_app . 'xterm';
$xpra_mode['spic-ide'] = $xpra_app . '/proj/i4spic/bin/editor';
$xpra_mode['eclipse']  = $xpra_app . '/local/eclipse/bin/eclipse';
$xpra_mode['atom']     = $xpra_app . '/proj/ciptmp/heinloth/atom.sh';
$xpra_mode['intellij'] = $xpra_app . '/local/intellij-idea/bin/idea';
$xpra_mode['vscode']   = $xpra_app . '/proj/ciptmp/heinloth/vscode.sh';
$xpra_mode['vscodium'] = $xpra_app . '/proj/ciptmp/heinloth/vscodium.sh';
$xpra_mode['matlab']   = $xpra_app . '/local/matlab/bin/matlab';

$xpra_mode['path'] = $xpra_app;

// Default mode
$application = $xpra_mode['xfce'];

// generate a temporary websocket secret
// (not cryptograpically secure, but it's just for a few minutes)
$wssecretchars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
$wssecret = '';
while (strlen($wssecret) != 32) {
	$wssecret .= $wssecretchars[random_int(0, strlen($wssecretchars) - 1)];
}

# HTML 5 Client
$xpra_client_base = 'https://'.$_SERVER['SERVER_NAME'].'/';
$xpra_client_param = array(
		'ssl' => 'true',
		'video' => 'false',
		'floating_menu' => 'false',
		'printing' => 'false',
		'keyboard_layout' => 'de',
		'reconnect' => 'true',
		'sharing' => 'false',
		'sound' => 'false',
		'steal' => 'true',
		'server' => $_SERVER['HTTP_HOST'],
		'password' => $wssecret
	);


// Xpra Hosts + Ports
$host_ids = array("4a0", "4b0", "4b1", "4b2", "4b3", "4c0", "4c1", "4c2", "4c3", "4d0", "4d1", "4d2", "4d3", "4e0", "4e1", "4e2", "4e3", "3a0", "3b0", "3b1", "3b2", "3b3", "3c0", "3c1", "3c2", "3c3", "3d0", "3d1", "3d2", "3d3", "3e0", "3e1", "3e2", "3e3", "3f0", "3f1", "3f2", "3f3");
$host_prefix = 'cip';
$host_suffix = '.cip.cs.fau.de';

$ports = range(23200, 23299);

// Use only HTTPS
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === "off") {
	$location = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
	header('HTTP/1.1 301 Moved Permanently');
	header('Location: ' . $location);
	exit;
}


// Helper
function ssh2_exec_wrapper($con, $cmd) {
	if ($debug) {
		echo "\e[1m$cmd\e[0m\n";
	}
	$stdoutStream = ssh2_exec($con, $cmd);
	if ($stdoutStream) {
		$stderrStream = ssh2_fetch_stream($stdoutStream, SSH2_STREAM_STDERR);
		stream_set_blocking($stdoutStream, true );
		stream_set_blocking($stderrStream, true );
		$stdout = stream_get_contents($stdoutStream);
		$stderr = stream_get_contents($stderrStream);
		if ($debug) {
			echo $stdout;
			echo $stderr;
		}
		return true;
	} else {
		return false;
	}
}

function connectpage($msg) {
	header('Location: '.$xpra_client_base.'connect.html?disconnect='.urlencode($msg), true, 307);
	die($msg);
}

//.Authentication
// Check if user/password provided
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
if (!empty($_REQUEST['user']) && !empty($_REQUEST['password'])) {
	$user = strtolower($_REQUEST['user']);
	$password = $_REQUEST['password'];
} else if (!empty($_SERVER['PHP_AUTH_USER']) && !empty($_SERVER['PHP_AUTH_PW'])) {
	$user = strtolower($_SERVER['PHP_AUTH_USER']);
	$password = $_SERVER['PHP_AUTH_PW'];
} else {
	$user = '';
}

$keyboard_layouts = array('de', 'us', 'gb', 'fr', 'ad', 'af', 'al', 'ara', 'am', 'az', 'bd', 'by', 'be', 'bt', 'ba', 'br', 'bg', 'kh', 'ca', 'cn', 'cd', 'hr', 'cz', 'dk', 'epo', 'ee', 'et', 'ir', 'iq', 'fo', 'fi', 'fr', 'ge', 'gh', 'gr', 'gn', 'hu', 'is', 'in', 'ie', 'il', 'it', 'jp', 'kz', 'kr', 'kg', 'latam', 'lv', 'la', 'lt', 'mao', 'mk', 'mv', 'ml', 'mt', 'mn', 'me', 'ma', 'mm', 'np', 'nl', 'ng', 'no', 'pk', 'pl', 'pt', 'ro', 'ru', 'sn', 'rs', 'sk', 'si', 'za', 'es', 'lk', 'se', 'ch', 'sy', 'tw', 'tj', 'tz', 'th', 'tr', 'tm', 'ua', 'uz', 'vn');

if (!empty($_REQUEST['keyboard_layout']) && in_array($_REQUEST['keyboard_layout'], $keyboard_layouts)) {
	$xpra_client_param['keyboard_layout'] = $_REQUEST['keyboard_layout'];
}

// Select application
if (!empty($_REQUEST['application']) && in_array($_REQUEST['application'], array_keys($xpra_mode))) {
	if ($_REQUEST['application'] == 'path') {
		if (!empty($_REQUEST['application-path']) && preg_match('/[\/0-9a-zA-Z._-]+/i' ,$_REQUEST['application-path'])) {
			$application = $xpra_mode['path'] . $_REQUEST['application-path'];
		}
	} else {
		$application = $xpra_mode[$_REQUEST['application']];
	}
}

// Session Sharing
if (!empty($_REQUEST['sharing']) && $_REQUEST['sharing'] == "true") {
	$xpra_param .= ' '.$xpra_param_sharing;
	$xpra_client_param['floating_menu'] = 'true';
	$xpra_client_param['sharing'] = 'true';
} else {
	$xpra_client_param['sharing'] = 'false';
	if (strpos($application, $xpra_desktop) === false) {
		$xpra_client_param['floating_menu'] = 'true';
	}
}

// Try all hosts (in randomized order)
shuffle($host_ids);
foreach ($host_ids as $host_id) {
	$host = $host_prefix.$host_id.$host_suffix;
	// Try to establish ssh connection to host
	$ssh = ssh2_connect($host);
	if ($ssh !== false) {
		// Authenticate
		if (!@ssh2_auth_password($ssh, $user, $password)) {
			connectpage("Authentication failed!");
			exit;
		} else {
			// Find unused port
			shuffle($ports);
			foreach ($ports as $port) {
				if ($debug) {
					echo "Using $host:$port\n";
				}
				$con = @fsockopen($host, $port, $errno, $errstr, 1);
				if ($con) {
					// Port in use
					fclose($con);
					break;
				} else {
					$host_link = $host_id.($port % 100 < 10 ? '0' : '').($port % 100);
					// Store websocket secret on server
					if (ssh2_exec_wrapper($ssh, 'XPRA_PASSWORD='.$wssecret.' ' . $xpra_bin. ' '.$application.' --bind-ws=0.0.0.0:'.$port.' --ws-auth=env '.$xpra_param)) {
						// Set websocket
						$xpra_client_param['path'] = '/' . $host_link . '/';
						// Set sharing
						if ($xpra_client_param['sharing'] == 'true') {
							$xpra_client_param['shareurl'] = $xpra_client_base . $host_link . $wssecret;
						}
						// Redirect
						header('Location: '.$xpra_client_base.'index.html?'.http_build_query($xpra_client_param), true, 307);
					} else {
						connectpage("Xpra host has encountered a problem.");
					}
					ssh2_disconnect($ssh);
					exit;
				}
			}
		}
	}
}
connectpage("Xpra hosts are exhausted (no free slots).");
?>
