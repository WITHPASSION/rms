<?php
error_reporting(E_ALL);
ignore_user_abort(true);

function syscall ($cmd, $cwd) {
	$descriptorspec = array(
		1 => array('pipe', 'w')
	);
	$resource = proc_open($cmd, $descriptorspec, $pipes, $cwd);
	if (is_resource($resource)) {
		$output = stream_get_contents($pipes[1]);
		fclose($pipes[1]);
		proc_close($resource);
		return $output;
	}
}

$update = '';
if (!isset($_POST['payload'])) {
	error_log("github invalid access.", 0);
	return;
}
error_log("github payload:[".$_POST['payload']."]", 0);
$payload = json_decode($_POST['payload']);
if ($payload->ref === 'refs/heads/develop') {
	$update = 'develop';
}
else if ($payload->ref === 'refs/heads/master') {
	$update = 'master';
}
else {
	$update = 'all';
}
$result = "no action";
if ($update != '') {
	if ($update === 'develop' || $update == 'all') {
		if (file_exists("/usr/local/test.rms_system/rms")) {
			$result = syscall('git pull origin develop', '/usr/local/test.rms_system/rms');
		}
	}
	if ($update === 'master' || $update == 'all') {
		if (file_exists("/usr/local/www.rms_system/rms")) {
			$result = syscall('git pull origin master', '/usr/local/www.rms_system/rms');
		}
	}
}
error_log("github result:[".$result."]", 0);
