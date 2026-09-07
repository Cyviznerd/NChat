<?php
//Shared helpers used by both the BoardIndex-embedded render and the AJAX endpoint.

//A language string that an upgraded install may not have yet.
function nchatTxt($key, $default){
	global $txt;

	return isset($txt[$key]) && $txt[$key] !== '' ? $txt[$key] : $default;
}

//Make a string safe to drop inside a double quoted javascript literal.
function nchatJsEscape($string){
	//U+2028 and U+2029 end a javascript line even inside a string.
	$string = str_replace(array("\xE2\x80\xA8", "\xE2\x80\xA9"), '', $string);
	$string = preg_replace_callback('/[\x00-\x1F\x7F]/', function($matches){
		return sprintf('%%%02X', ord($matches[0]));
	}, $string);

	return str_replace(array('\\', '"'), array('\\\\', '\\"'), $string);
}
?>
