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

//Extract the lowercased host (with :port if non-default) from an origin/referer URL.
function nchatHostFromUrl($url){
	if(!is_string($url) || $url === '' || $url === 'null')
		return '';
	$parts = @parse_url($url);
	if(!is_array($parts) || empty($parts['host']))
		return '';
	$host = strtolower($parts['host']);
	if(!empty($parts['port']) && !(($parts['scheme'] === 'https' && $parts['port'] == 443) || ($parts['scheme'] === 'http' && $parts['port'] == 80)))
		$host .= ':' . (int) $parts['port'];
	return $host;
}

//True when the request's Origin or Referer header matches the host we are served on.
//Independent of cookies, session state, and network path — the browser guarantees these
//headers cannot be forged by a cross-site attacker's <form>/<img>/no-cors fetch.
function nchatRequestIsSameOrigin(){
	$host = isset($_SERVER['HTTP_HOST']) ? strtolower($_SERVER['HTTP_HOST']) : '';
	if($host === '')
		return false;

	$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
	if($origin !== '' && $origin !== 'null')
		return nchatHostFromUrl($origin) === $host;

	$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
	if($referer !== '')
		return nchatHostFromUrl($referer) === $host;

	return false;
}
?>
