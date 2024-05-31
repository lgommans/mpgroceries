<?php 
function checkCSRF($which) {
	global $_pepper;

	if (sha1($_SESSION['csrf'] . $_pepper) != sha1($which . $_pepper)) {
		die('Security error. If you actually see this, it is probably a bug (usually this only occurs during hack attacks, and usually you don\'t get to see this). Close your browser and try again.');
	}
}

function htmlescape($input) {
	return htmlentities($input, ENT_COMPAT | ENT_HTML401 | ENT_QUOTES, 'UTF-8');
}

function splitAmount($amount) {
	if (substr_count($amount, ' ') === 1) {
		return explode(' ', $amount);
	}
	for ($i = 0; $i < strlen($amount); $i++) {
		if ((ord('0') <= ord($amount[$i]) && ord($amount[$i]) <= ord('9')) || $amount[$i] == '.' || $amount[$i] == ',') {
			continue;
		}
		return [substr($amount, 0, $i - 1), substr($amount, $i - 1)];
		break;
	}
	return [$amount, ''];
}

