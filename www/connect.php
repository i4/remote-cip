<?php
$debug = false;
error_reporting($debug ? E_ALL : 0);

// Parameters for Xpra server
$xpra_param = '--idle-timeout=300 --server-idle-timeout=30 --mdns=no --webcam=off --html=off';
$xpra_mode['xfce'] = 'start-desktop --start=xfce4-session';
$xpra_mode['xterm'] = 'start --start=xterm';
$xpra_mode['spic-ide'] = 'start --start-child=/proj/i4spic/bin/editor --exit-with-children=yes';

// Default mode
$application = 'xfce';

// Password file for Xpra websocket auth
$wssecretfile = '$XDG_RUNTIME_DIR/web-xpra-secret';
// generate a temporary websocket secret
// (not cryptograpically secure, but it's just for a few minutes)
$wssecretchars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
$wssecret = '';
for ($i = 0; $i < 32; ++$i) {
	$wssecret .= $wssecretchars[random_int(0, strlen($wssecretchars))];
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
$host_ids = array('1e0', '1e1', '1e2', '1e3', '1e4', '1e5', '1e6', '1e7');
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
header('Cache-Control: no-cache, must-revalidate, max-age=0');
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
	$application = $_REQUEST['application'];
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
					// Store websocket secret on server
					if (ssh2_exec_wrapper($ssh, 'umask 077; echo -n '.$wssecret.' > '.$wssecretfile)
					 && ssh2_exec_wrapper($ssh, 'xpra '.$xpra_mode[$application].' --bind-ws=0.0.0.0:'.$port.' --ws-auth=file,filename='.$wssecretfile.' '.$xpra_param)) {
						// Set websocket
						$xpra_client_param['path'] = '/remote'.$host_id.($port % 100 < 10 ? '0' : '').($port % 100).'/';
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
