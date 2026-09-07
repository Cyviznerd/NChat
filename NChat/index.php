<?php
//NChat by ThidMod.com
require_once('../SSI.php');
require_once('NChatHandle.php');

if(!empty($_REQUEST['nchat'])){

	$mess = '';
	if(isset($_REQUEST['nchat_mess']))
		$mess = $_REQUEST['nchat_mess'];

	//Anything that changes state needs the session token, or a third party page could
	//post, delete or mute on behalf of whoever is logged in.
	if(in_array($_REQUEST['nchat'], array('write', 'clean', 'setmute', 'edit')))
		nchatCheckSession();

	header('Content-type: text/javascript; charset=UTF-8');

	if($_REQUEST['nchat'] == 'write')
		NChatWriter($mess);

	if($_REQUEST['nchat'] == 'clean')
		NChatCleaner($mess);

	if($_REQUEST['nchat'] == 'edit')
		NChatEditor(isset($_REQUEST['nchat_id']) ? $_REQUEST['nchat_id'] : '', $mess);

	if($_REQUEST['nchat'] == 'setmute'){
		if(isset($_REQUEST['nchat_mute']))
			NChatSetMute($mess, (int) $_REQUEST['nchat_mute']);
		else
			NChatSetMute($mess);
	}

	NChatReader();
}
elseif(!empty($_REQUEST['action']) && $_REQUEST['action'] == '.js'){
	header('Content-type: text/javascript; charset=UTF-8');
	getSmilesList();
}
elseif(!empty($_REQUEST['action']) && $_REQUEST['action'] == 'mutelist'){
	$user_id = '';
	if(isset($_REQUEST['u'])){
		//Removing a mute is a state change, so it needs the token too.
		nchatCheckSession('html');
		$user_id = $_REQUEST['u'];
	}

	NChatRemoveMute($user_id);
}
elseif(!empty($_REQUEST['action']) && $_REQUEST['action'] == 'room'){
	template_init();
	$context['page_title_html_safe'] = $txt['nchat_chatbox'];
	$context['linktree'][] = array(
		'url' => $nchatInfo['url'] . '?action=mutelist',
		'name' => $txt['nchat_mute_list'],
		);
	template_html_above();
	template_body_above();
	require_once('NChatBoardIndex.php');
	template_body_below();
	template_html_below();
}
?>
