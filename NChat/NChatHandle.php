<?php
//NChat by ThisMod.com
require_once(__DIR__ . '/NChatUtils.php');

//SMF require_once's this file from inside a function when firing integration hooks, so pull the SMF globals into scope before touching them.
global $boardurl, $boarddir, $user_info, $modSettings, $txt, $nchatInfo;

$nchatInfo['url']           = $boardurl.'/NChat/index.php';
$nchatInfo['group']         = isset($user_info['groups'][0]) ? $user_info['groups'][0] : 0;
$nchatInfo['id']            = isset($user_info['id']) ? $user_info['id'] : 0;
$nchatInfo['time']          = date(isset($modSettings['nchat_time_format']) ? $modSettings['nchat_time_format'] : 'Y-m-d H:i');
$nchatInfo['nchatMess']     = $boarddir.'/NChat/NChatMess.php';
$nchatInfo['nchatLast']     = $boarddir.'/NChat/last.html';
$nchatInfo['nchatMutelist'] = $boarddir.'/NChat/NChatMuteList.php';

if(!empty($user_info['is_guest']))
	$nchatInfo['name'] = '<i>' . (isset($txt['guest_title']) ? $txt['guest_title'] : 'Guest') . '</i>';
else
	$nchatInfo['name'] = isset($user_info['name']) ? $user_info['name'] : '';

//|!| separates the fields of the flat file store, and a message is always one line.
function nchatCleanField($string){
	$string = str_replace('|!|', '!!!', $string);

	return trim(preg_replace('/\s+/', ' ', $string));
}

//A one off notice rendered in place of the chat log.
function nchatNote($message){
	return 'var nchat=new Array("' . nchatJsEscape('#ff00ff|!|-|!|0|!||!|Note|!|<b>' . $message . '</b>|!|-1') . '");';
}

//Signals a mute rejection: the client keeps the log intact and disables its input row.
function nchatMuteNote($message){
	return 'var nchat_muted="' . nchatJsEscape($message) . '"; var nchat=null;';
}

//CSRF guard for state-changing requests. Verifies the request came from our own origin
//using browser-set headers that a cross-site attacker cannot forge, so the check does
//not depend on cookies, PHP sessions, or SMF's rotating session_var/session_value token
//(which was flaky over unstable networks and mid-session cookie loss).
//
//AJAX mode: requires both same-origin headers AND X-Requested-With, which a plain
//<form>/<img>/<iframe> or a no-cors fetch cannot set cross-origin without triggering
//a CORS preflight that we never satisfy.
//Html mode (mutelist link click): same-origin headers only, since a top-level GET
//navigation does not carry X-Requested-With.
function nchatCheckSession($mode = 'ajax'){
	if(nchatRequestIsSameOrigin()){
		if($mode !== 'ajax')
			return;
		//Custom header only settable by same-origin XHR/fetch; blocks form/image CSRF that happens to share our host header via other channels.
		$xrw = isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? $_SERVER['HTTP_X_REQUESTED_WITH'] : '';
		if(strcasecmp($xrw, 'XMLHttpRequest') === 0)
			return;
	}

	$message = nchatTxt('nchat_session_expired', 'Your session timed out, please refresh the page and try again.');

	if($mode == 'ajax'){
		header('Content-type: text/javascript; charset=UTF-8');
		die('var nchat_session_expired="' . nchatJsEscape($message) . '";');
	}

	fatal_error($message, false);
}

//Strip the guard line and unserialize what is left.
function nchatDecodeStore($data){
	if(!is_string($data))
		return false;

	$pos = strpos($data, "\n");
	if($pos === false)
		return false;

	//The store only ever holds arrays of scalars, so refuse any serialized object.
	$store = @unserialize(substr($data, $pos + 1), array('allowed_classes' => false));

	return is_array($store) ? $store : false;
}

//Read a store under a shared lock, so a writer can never hand us a half written file.
function nchatReadStore($file){
	$fp = @fopen($file, 'rb');
	if($fp === false)
		return false;

	$data = '';
	if(flock($fp, LOCK_SH)){
		while(!feof($fp))
			$data .= fread($fp, 8192);
		flock($fp, LOCK_UN);
	}
	fclose($fp);

	return nchatDecodeStore($data);
}

//Read/modify/write a store while holding an exclusive lock the whole time, so two
//simultaneous posters cannot overwrite each other. $callback gets the array and
//returns the array to save, or false to abort.
function nchatUpdateStore($file, $callback){
	//c+b creates the file when it is missing and, unlike w, never truncates it
	//before we hold the lock.
	$fp = @fopen($file, 'c+b');
	if($fp === false)
		return false;

	if(!flock($fp, LOCK_EX)){
		fclose($fp);
		return false;
	}

	$data = '';
	while(!feof($fp))
		$data .= fread($fp, 8192);

	//A brand new file is an empty store, anything else has to decode cleanly.
	$store = $data === '' ? array() : nchatDecodeStore($data);
	if($store !== false)
		$store = call_user_func($callback, $store);

	if($store === false || !is_array($store)){
		flock($fp, LOCK_UN);
		fclose($fp);
		return false;
	}

	rewind($fp);
	ftruncate($fp, 0);
	$written = fwrite($fp, '<?php die; ?>' . "\n" . serialize($store));
	fflush($fp);
	flock($fp, LOCK_UN);
	fclose($fp);

	return $written !== false;
}

//Bump the poll file. Only ever called once the message store is safely on disk.
function nchatTouchLast(){
	global $nchatInfo;

	return @file_put_contents($nchatInfo['nchatLast'], time(), LOCK_EX) !== false;
}

//read and show all mess, just do it and fast as it can!
function NChatReader(){
	global $modSettings, $nchatInfo, $txt, $context, $user_info;

	//Client compares this to its render-time nchat_own_id to spot an expired SMF session before any post is attempted.
	echo 'var nchat_current_uid=' . (int) $user_info['id'] . ';';

	if(!allowedTo('nchat_read')){
		echo 'var nchat_auth_expired=true; var nchat=new Array();';
		return;
	}

	//Build the muted uid list once and reuse it for both broadcasts below.
	$muted_uids = array();
	$now = time();
	$mutes = nchatReadStore($nchatInfo['nchatMutelist']);
	if(is_array($mutes)){
		foreach($mutes as $muid => $m){
			if(isset($m[0]) && $m[0] > $now)
				$muted_uids[(int) $muid] = $m;
		}
	}

	echo 'nchat_muted_users=[' . implode(',', array_keys($muted_uids)) . '];';

	//Piggy-back on the poll cycle so the current user learns about an active or expired mute.
	if(allowedTo('nchat_write')){
		$uid = (int) $nchatInfo['id'];
		if($uid > 0 && isset($muted_uids[$uid])){
			$m = $muted_uids[$uid];
			echo 'var nchat_muted="' . nchatJsEscape(sprintf($txt['nchat_be_muted'], $m[1], $m[2], date($modSettings['nchat_time_format'], $m[0]))) . '";';
		}
	}

	$mess = nchatReadStore($nchatInfo['nchatMess']);
	if($mess === false || count($mess) == 0){
		echo 'var nchat=new Array();';
		return;
	}

	$lines = array();
	foreach($mess as $line)
		$lines[] = '"' . nchatJsEscape(nchatParser($line)) . '"';

	echo 'var nchat=new Array(' . implode(',', $lines) . ');';
}

//write it to your .txt file
function NChatWriter($subject = ''){
	global $modSettings, $nchatInfo, $context, $txt;

	if(!allowedTo('nchat_write'))
		return;

	$mutes = nchatReadStore($nchatInfo['nchatMutelist']);
	if($mutes === false)
		$mutes = array();

	$id = (int) $nchatInfo['id'];

	if(isset($mutes[$id]) && $mutes[$id][0] > time()){
		header('Content-type: text/javascript; charset=UTF-8');
		die(nchatMuteNote(sprintf($txt['nchat_be_muted'], $mutes[$id][1], $mutes[$id][2], date($modSettings['nchat_time_format'], $mutes[$id][0]))));
	}

	//The mute has run out, drop it.
	if(isset($mutes[$id]))
		nchatUpdateStore($nchatInfo['nchatMutelist'], function($store) use ($id){
			unset($store[$id]);
			return $store;
		});

	$subject = nchatCleanField($subject);
	if($subject === '')
		return;

	$subject = htmlspecialchars(shorten_subject($subject, (int) $modSettings['nchat_lenght']), ENT_QUOTES);

	$format = $modSettings['nchat_text'];

	$onlineColor = getGroupOnlineColors();
	if(isset($onlineColor[$nchatInfo['group']]))
		$format .= '|!|'.$onlineColor[$nchatInfo['group']];
	else
		$format .= '|!|-';

	$format .= '|!|'.$id.'|!|'.nchatCleanField($nchatInfo['name']).'|!|'.nchatCleanField($nchatInfo['time']);

	$line = $format.'|!|'.$subject.'|!|'.$nchatInfo['group'];
	$limit = max(1, (int) $modSettings['nchat_line']);

	$saved = nchatUpdateStore($nchatInfo['nchatMess'], function($store) use ($line, $limit){
		$store[] = $line;
		if(count($store) > $limit)
			array_splice($store, 0, count($store) - $limit);

		return $store;
	});

	if($saved)
		nchatTouchLast();
}

//Edit the user's own message. First edit stashes the original body in field 7 so
//the rendered log can show "old struck-through" next to "new".
function NChatEditor($MessID = '', $subject = ''){
	global $modSettings, $nchatInfo, $txt;

	if(!allowedTo('nchat_write'))
		return;

	if(!is_numeric($MessID))
		return;

	$MessID = (int) $MessID;
	$uid = (int) $nchatInfo['id'];

	//Guests share uid 0, so any guest could rewrite any guest's line.
	if($uid <= 0)
		return;

	$mutes = nchatReadStore($nchatInfo['nchatMutelist']);
	if($mutes === false)
		$mutes = array();

	if(isset($mutes[$uid]) && $mutes[$uid][0] > time()){
		header('Content-type: text/javascript; charset=UTF-8');
		die(nchatMuteNote(sprintf($txt['nchat_be_muted'], $mutes[$uid][1], $mutes[$uid][2], date($modSettings['nchat_time_format'], $mutes[$uid][0]))));
	}

	if(isset($mutes[$uid]))
		nchatUpdateStore($nchatInfo['nchatMutelist'], function($store) use ($uid){
			unset($store[$uid]);
			return $store;
		});

	$subject = nchatCleanField($subject);
	if($subject === '')
		return;

	$subject = htmlspecialchars(shorten_subject($subject, (int) $modSettings['nchat_lenght']), ENT_QUOTES);

	$saved = nchatUpdateStore($nchatInfo['nchatMess'], function($store) use ($MessID, $uid, $subject){
		if($MessID < 0 || $MessID >= count($store))
			return false;

		$parts = explode('|!|', $store[$MessID]);
		//Anyone else's line, silently drop.
		if(!isset($parts[2]) || (int) $parts[2] !== $uid)
			return false;
		if(!isset($parts[5]))
			return false;
		//Body unchanged, nothing to write.
		if($parts[5] === $subject)
			return false;

		//Prepend the outgoing body/time to a newest-first history chain, sub-delimited by |~|.
		$prev_body = isset($parts[7]) ? $parts[7] : '';
		$prev_time = isset($parts[8]) ? $parts[8] : '';
		$parts[7] = $parts[5] . ($prev_body !== '' ? '|~|' . $prev_body : '');
		$parts[8] = (isset($parts[4]) ? $parts[4] : '') . ($prev_time !== '' ? '|~|' . $prev_time : '');
		$parts[5] = $subject;
		$parts[4] = nchatCleanField($GLOBALS['nchatInfo']['time']);

		//An edit re-posts the row: pull it from its old slot and push it to the tail
		//so the display order matches a fresh message.
		array_splice($store, $MessID, 1);
		$store[] = implode('|!|', $parts);
		return $store;
	});

	if($saved)
		nchatTouchLast();
}

//Delete a specific row or clean all
function NChatCleaner($MessID = ''){
	global $modSettings, $nchatInfo;

	if(!allowedTo('nchat_delete'))
		return;

	if(is_numeric($MessID)){
		$MessID = (int) $MessID;
		$saved = nchatUpdateStore($nchatInfo['nchatMess'], function($store) use ($MessID){
			if($MessID < 0 || $MessID >= count($store))
				return false;

			array_splice($store, $MessID, 1);
			return $store;
		});
	}else{
		$format = $modSettings['nchat_text'];
		$format .= '|!|-';
		$format .= '|!|'.((int) $nchatInfo['id']).'|!|<mark>server message:</mark>|!|'.nchatCleanField($nchatInfo['time']);
		$subject = htmlspecialchars(nchatCleanField($nchatInfo['name']), ENT_QUOTES).' asked server to clean chat.';
		$line = $format.'|!|'.$subject.'|!|'.$nchatInfo['group'];

		$saved = nchatUpdateStore($nchatInfo['nchatMess'], function($store) use ($line){
			return array($line);
		});
	}

	if($saved)
		nchatTouchLast();
}


//You can you your forum Smiles but you can use yahoo smile to save you BW
function NChatParser($subject = ''){
	global $modSettings;

	if($modSettings['nchat_censor'] == 1)
		censortext($subject);

	return $subject;
}


function getGroupOnlineColors(){
	global $smcFunc;
	$onlineColor = cache_get_data('onlineColor', 3600);

	if(!is_array($onlineColor)){
		$onlineColor = array();
		$request = $smcFunc['db_query']('', '
			SELECT id_group, online_color
			FROM {db_prefix}membergroups
			WHERE online_color <> {string:blank}',
			array(
				'blank' => '',
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request)){
			$onlineColor[$row['id_group']] = $row['online_color'];
		}
		$smcFunc['db_free_result']($request);
		cache_put_data('onlineColor', $onlineColor, 3600);
	}
	return $onlineColor;
}

//Groups that must never be muted: admins, global moderators, board moderators and
//anyone holding the mute permission themselves.
function nchatGetProtectedGroups(){
	global $smcFunc;

	$groups = cache_get_data('nchatProtectedGroups', 3600);

	if(!is_array($groups)){
		$groups = array(1, 2, 3);
		$request = $smcFunc['db_query']('', '
			SELECT id_group
			FROM {db_prefix}permissions
			WHERE permission = {string:permission}
				AND add_deny = {int:add}',
			array(
				'permission' => 'nchat_mute',
				'add' => 1,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request)){
			$groups[] = (int) $row['id_group'];
		}
		$smcFunc['db_free_result']($request);

		$groups = array_values(array_unique($groups));
		cache_put_data('nchatProtectedGroups', $groups, 3600);
	}

	return $groups;
}

function getSmilesList(){
	global $modSettings, $smcFunc, $user_info;

	//The list depends on the viewer's smiley set, so it cannot share one cache entry.
	$cacheKey = 'smilesListString_' . $user_info['smiley_set'];
	$smilesListString = cache_get_data($cacheKey, 3600);

	if($smilesListString === NULL){
		if (empty($modSettings['smiley_enable']))
		{
			$smilesList = array(
				array(":))", "laugh.gif"),
				array(":)", "smiley.gif"),
				array(";)", "wink.gif"),
				array(">:D", "evil.gif"),
				array(":D", "cheesy.gif"),
				array(";D", "grin.gif"),
				array(">:(", "angry.gif"),
				array(":(", "sad.gif"),
				array(":o", "shocked.gif"),
				array("8)", "cool.gif"),
				array("???", "huh.gif"),
				array("::)", "rolleyes.gif"),
				array(":P", "tongue.gif"),
				array(":-[", "embarrassed.gif"),
				array(":-X", "lipsrsealed.gif"),
				array(":-//", "undecided.gif"),
				array(":-*", "kiss.gif"),
				array(":'(", "cry.gif"),
				array("^-^", "azn.gif"),
				array("O0", "afro.gif"),
				array("C:-)", "police.gif"),
				array("O:-)", "angel.gif"),
			);
		}else{
				$result = $smcFunc['db_query']('', '
					SELECT code, filename
					FROM {db_prefix}smileys',
					array(
					)
				);
				$smilesList = array();
				while ($row = $smcFunc['db_fetch_assoc']($result))
				{
					$smilesList[] = array($row['code'], $row['filename']);
				}
				$smcFunc['db_free_result']($result);
		}

		$base = $modSettings['smileys_url'] . '/' . $user_info['smiley_set'] . '/';
		$entries = array();
		for($i = 0; $i < count($smilesList); $i++)
		{
			$entries[] = '
	Array("'.addslashes(preg_quote(htmlentities($smilesList[$i][0]))).'", "'.addslashes($base.$smilesList[$i][1]).'", "'.addslashes(htmlentities($smilesList[$i][0])).'")';
		}

		$smilesListString = 'var nchat_smiles_list =new Array('.implode(',', $entries).');';
		cache_put_data($cacheKey, $smilesListString, 3600);
	}
	echo $smilesListString;
}

function NChatSetMute($id_member = '', $time_mute = 5){
	global $modSettings, $smcFunc, $nchatInfo;
	//No one can mute an admin or mod...
	if(!allowedTo('nchat_mute'))
		return;

	$id_member = (int) $id_member;
	if($id_member <= 0)
		return;

	//Between a minute and a year.
	$time_mute = min(max((int) $time_mute, 1), 525600);

	$result = $smcFunc['db_query']('', '
		SELECT member_name, id_group, additional_groups
		FROM {db_prefix}members
		WHERE id_member = {int:id_member}
		LIMIT 1',
		array(
			'id_member' => $id_member,
		)
	);
	$row = $smcFunc['db_fetch_assoc']($result);
	$smcFunc['db_free_result']($result);

	//No such member, nothing to mute.
	if(empty($row))
		return;

	$groups = array_map('intval', array_filter(explode(',', (string) $row['additional_groups']), 'strlen'));
	$groups[] = (int) $row['id_group'];

	if(count(array_intersect(nchatGetProtectedGroups(), $groups)) > 0)
		return;

	$mute = array(time() + $time_mute*60, nchatCleanField($row['member_name']), nchatCleanField($nchatInfo['name']));

	$saved = nchatUpdateStore($nchatInfo['nchatMutelist'], function($store) use ($id_member, $mute){
		$store[$id_member] = $mute;
		return $store;
	});

	//Bump the poll file so the muted user's next tick picks up the new state.
	if($saved)
		nchatTouchLast();
}

function NChatRemoveMute($id_member = ''){
	global $modSettings, $txt, $context, $boardurl, $nchatInfo;

	if(!allowedTo('nchat_mute'))
		return;

	$id_member = (int) $id_member;

	if($id_member > 0){
		$saved = nchatUpdateStore($nchatInfo['nchatMutelist'], function($store) use ($id_member){
			if(!isset($store[$id_member]))
				return false;

			unset($store[$id_member]);
			return $store;
		});

		if($saved)
			nchatTouchLast();
	}

	$mutes = nchatReadStore($nchatInfo['nchatMutelist']);
	if($mutes === false)
		$mutes = array();

	template_init();
	$context['page_title_html_safe'] = $txt['nchat_mute_list'];
	$context['linktree'][] = array(
		'url' => $nchatInfo['url'] . '?action=mutelist',
		'name' => $txt['nchat_mute_list'],
		);
	template_html_above();
	template_body_above();
	foreach($mutes as $id => $mute){
		$id = (int) $id;
		$removeUrl = $nchatInfo['url'] . '?action=mutelist;u=' . $id;
		$profile = '<a href="' . $boardurl . '/index.php?action=profile;u=' . $id . '">' . htmlspecialchars($mute[1], ENT_QUOTES) . '</a>';

		echo '<b>[<a href="' . $removeUrl . '">X</a>] </b>' . sprintf($txt['nchat_be_muted'], $profile, htmlspecialchars($mute[2], ENT_QUOTES), date($modSettings['nchat_time_format'], $mute[0])) . '<br />';
	}
	template_body_below();
	template_html_below();
}

//SMF integrate_theme_options callback. Registered in install.php, dropped in uninstall.php. Puts a Chat text size dropdown on the theme-options page (visible both to admins editing site defaults and to members via Profile -> Look and Layout). SMF stores the value per member in {db_prefix}themes and auto-loads it into $options for the current user. Rendered as a <select> because SMF 2.0.x template_options() only supports select/checkbox; the same markup works on SMF 2.1.
function nchat_theme_options()
{
	global $context, $txt;

	loadLanguage('Modifications');

	$sizes = array('' => isset($txt['nchat_text_size_default']) ? $txt['nchat_text_size_default'] : 'Site default');
	for ($px = 10; $px <= 32; $px++)
		$sizes[(string) $px] = $px . ' px';

	$context['theme_options'][] = array(
		'id' => 'nchat_text_size',
		'label' => isset($txt['nchat_text_size_own']) ? $txt['nchat_text_size_own'] : 'NChat text size override',
		'description' => isset($txt['nchat_text_size_own_desc']) ? $txt['nchat_text_size_own_desc'] : 'Leave on Site default to inherit the forum-wide size.',
		'options' => $sizes,
		'default' => '',
	);
}

//SMF integrate_pre_profile_areas callback. Fires before the theme-options save runs, so we can clamp the posted value to a safe range and defeat any tampering with the number input.
function nchat_sanitize_theme_options(&$profile_areas)
{
	if (empty($_POST) || !isset($_POST['default_options']['nchat_text_size']))
		return;

	$raw = trim((string) $_POST['default_options']['nchat_text_size']);

	if ($raw === '' || !is_numeric($raw)) {
		$_POST['default_options']['nchat_text_size'] = '';
		return;
	}

	$_POST['default_options']['nchat_text_size'] = (string) max(10, min(32, (int) $raw));
}
?>
