<?php
//Required'd from inside template_main() before it globalises anything, so pull the SMF globals in here.
global $context, $txt, $scripturl, $modSettings, $boarddir, $boardurl, $settings, $user_info;

//Small helpers shared with NChatHandle.php. require_once is a no-op when the
//AJAX endpoint has already loaded them.
require_once(__DIR__ . '/NChatUtils.php');

$nchatInfo['id']        = $context['user']['id'];
$nchatInfo['url']       = $boardurl.'/NChat/index.php';
$nchatInfo['nchatLast'] = '/NChat/last.html';

// SMF 2.1's Curve2 dropped the upperframe/innerframe/title_barIC markup used by SMF 2.0's Curve.
$nchatIsSmf21 = defined('SMF_VERSION') && version_compare(SMF_VERSION, '2.1', '>=');

//Modifications.english.php isn't auto-loaded, so $txt['nchat_*'] would be blank without this.
loadLanguage('Modifications');

if(allowedTo('nchat_read')){
	//Sizing settings resolved once up front so the compose input, shoutbox and history entries all use the same numbers.
	$nchat_height = !empty($modSettings['nchat_height']) ? max(100, min(2000, (int) $modSettings['nchat_height'])) : 400;
	//Per-member preference (Profile -> Look and Layout) trumps the site default; clamped to a safe range.
	global $options;
	if(isset($options['nchat_text_size']) && (int) $options['nchat_text_size'] > 0)
		$nchat_text_size = (int) $options['nchat_text_size'];
	elseif(!empty($modSettings['nchat_text_size']))
		$nchat_text_size = (int) $modSettings['nchat_text_size'];
	else
		$nchat_text_size = 14;
	$nchat_text_size = max(10, min(32, $nchat_text_size));
	//iOS Safari auto-zooms any focused input below 16px and never fully unzooms, so pin the compose field to 16.
	$nchat_input_size = max(16, $nchat_text_size);

	if($nchatIsSmf21){
		echo '
	<div class="cat_bar"><h3 class="catbg">'.$txt['nchat_chatbox'].'</h3></div>
	<div class="roundframe">
	<div class="sub_bar"><h4 class="subbg">'.parse_bbc($modSettings['nchat_admin_mess']).'</h4></div>';
	}else{
		echo '
	<span class="clear upperframe"><span></span></span>
	<div class="roundframe">
	<div class="innerframe">
	<div class="cat_bar"><h3 class="catbg">'.$txt['nchat_chatbox'].'</h3></div>
	<div class="title_barIC"><h4 class="titlebg">'.parse_bbc($modSettings['nchat_admin_mess']).'</h4></div>';
	}

	if(allowedTo('nchat_write')){
		echo '
		<div id="nchat_editor" class="windowbg" style="padding:8px;">
		<div style="margin-bottom:6px;">
			' . (allowedTo('nchat_delete') ? '<a href="javascript: void(0);" onclick="nchat_clean_all(); return false;">'.$txt['nchat_clean'].'</a>  |  ' : '') . (allowedTo('nchat_mute') ? '<a href="' . $nchatInfo['url'] . '?action=mutelist">' . $txt['nchat_mute_list'] . '</a>' : '') .'
		</div>
		<div style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;margin-bottom:6px;">
			<button class="button" id="bold" onclick="nchat_format(this.id);" style="font-weight:bold;min-width:2.5em;">'.$txt['nchat_blod'].'</button>
			<button class="button" id="underline" onclick="nchat_format(this.id);" style="text-decoration:underline;min-width:2.5em;">'.$txt['nchat_underline'].'</button>
			<button class="button" id="italic" onclick="nchat_format(this.id);" style="font-style:italic;min-width:2.5em;">'.$txt['nchat_italic'].'</button>
			<select onchange="nchat_color();" id="nchat_color">
				<option style="background: '.$modSettings['nchat_text'].';" value="'.$modSettings['nchat_text'].'" selected="selected">'.$txt['nchat_default_color'].'</option>
				<option style="background: Red;" value="#ff0000">'.$txt['nchat_red'].'</option>
				<option style="background: Teal;" value="#008080">'.$txt['nchat_teal'].'</option>
				<option style="background: Blue;" value="#0000ff">'.$txt['nchat_blue'].'</option>
				<option style="background: Green;" value="#00ff00">'.$txt['nchat_green'].'</option>
				<option style="background: Brown;" value="#996633">'.$txt['nchat_brown'].'</option>
				<option style="background: Orange;" value="#ffa500">'.$txt['nchat_orange'].'</option>
			</select>
			'.(($modSettings['nchat_smile'] != 0) ? '<button class="button" type="button" onclick="nchat_toggle_smiles();">'.$txt['nchat_add_smileys'].'</button>' : '').'
		</div>
		<div id="nchat_input_row" style="display:flex;gap:6px;align-items:center;">
			<input class="input_text" id="nchat_input" onkeypress="if(event.keyCode == 13){ nchat_submit(); return false;}" style="flex:1;min-width:0;font-size:'.$nchat_input_size.'px;" />
			<input type="button" class="button" id="nchat_submit_btn" value="'.$txt['nchat_save'].'" onclick="nchat_submit(); return false;" />
			<input type="button" class="button" id="nchat_cancel_btn" value="'.nchatTxt('nchat_cancel', 'Cancel').'" onclick="nchat_edit_cancel(); return false;" style="display:none;" />
		</div>
		<div id="nchat_mute_notice" class="errorbox" style="display:none;"></div>
		</div>';
		if($modSettings['nchat_smile'] != 0){
			echo '
	<div id="nchat_smiles_modal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.4);z-index:9999;align-items:center;justify-content:center;" onclick="if(event.target === this) nchat_close_smiles();">
		<div style="background:#fff;color:#000;border-radius:6px;box-shadow:0 4px 20px rgba(0,0,0,0.3);max-width:500px;width:90%;padding:16px;box-sizing:border-box;position:relative;">
			<button type="button" class="button" title="'.nchatJsEscape(nchatTxt('nchat_close', 'Close')).'" onclick="nchat_close_smiles();" style="position:absolute;top:8px;right:8px;padding:2px 8px;line-height:1;">&times;</button>
			<h4 style="margin:0 0 12px 0;padding-right:32px;">'.$txt['nchat_add_smileys'].'</h4>
			<div id="nchat_smiles_grid" style="display:flex;flex-wrap:wrap;gap:4px;max-height:60vh;overflow:auto;"></div>
		</div>
	</div>';
		}
	}
	echo '
	<div id="nchat_error" class="errorbox" style="display:none"></div>
	<div id="nchat_admin_shoutbox" class="windowbg" style="background-color:'.$modSettings['nchat_background'].';width:100%;height:'.$nchat_height.'px;font-size:'.$nchat_text_size.'px;-webkit-text-size-adjust:100%;text-size-adjust:100%;overflow:auto;padding:8px;margin-top:6px;border:1px solid #ccc;border-radius:4px;box-sizing:border-box;"></div>';
	if($nchatIsSmf21){
		echo '
	</div>';
	}else{
		echo '
	<hr style="clear: both;" />
	</div>
	</div>
	<span class="clear lowerframe"><span></span></span>';
	}


	if($modSettings['nchat_order'] == 0 && $modSettings['nchat_sound'] == 1){
		echo '
	<audio id="nchat_sound" preload="auto" style="display:none;">
		<source src="'.$boardurl.'/NChat/sounds/bip.ogg" type="audio/ogg" />
		<source src="'.$boardurl.'/NChat/sounds/bip.mp3" type="audio/mpeg" />
	</audio>';
	}
	if(allowedTo('nchat_delete')){
		echo '
	<div id="nchat_confirm_modal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.4);z-index:9999;align-items:center;justify-content:center;">
		<div style="background:#fff;color:#000;border-radius:6px;box-shadow:0 4px 20px rgba(0,0,0,0.3);max-width:500px;width:90%;padding:16px;box-sizing:border-box;">
			<div id="nchat_confirm_preview" style="width:100%;box-sizing:border-box;margin-bottom:12px;max-height:10em;overflow:auto;padding:8px;border:1px solid #ccc;border-radius:4px;background:#f8f8f8;"></div>
			<p id="nchat_confirm_text" style="margin:0 0 12px 0;"></p>
			<input type="text" id="nchat_confirm_challenge" autocomplete="off" style="display:none;width:100%;box-sizing:border-box;margin-bottom:12px;padding:6px;font-size:1.1em;" />
			<div style="display:flex;justify-content:space-between;align-items:center;">
				<button type="button" class="button" id="nchat_confirm_ok">'.nchatTxt('yes', 'Yes').'</button>
				<button type="button" class="button" id="nchat_confirm_cancel">'.nchatTxt('no', 'No').'</button>
			</div>
		</div>
	</div>';
	}
}


if(allowedTo('nchat_read')){
	if($modSettings['nchat_smile'] == 1){
		//NChatSmiles.js builds its image paths from this, so the mod works in a subfolder.
		echo '
	<script type="text/javascript">var nchat_smiles_url = "'.nchatJsEscape($boardurl.'/NChat/Smileys/').'";</script>
	<script type="text/javascript" src="'.$boardurl.'/NChat/NChatSmiles.js"></script>';
	}
	if($modSettings['nchat_smile'] == 2){
		echo '
	<script type="text/javascript" src="'.$nchatInfo['url'].'?action=.js"></script>';
	}
	if($modSettings['nchat_smile'] != 1 && $modSettings['nchat_smile'] != 2){
		//The parser walks this list unconditionally, so it always has to exist.
		echo '
	<script type="text/javascript">var nchat_smiles_list = new Array();</script>';
	}
	echo '
	<script type="text/javascript">
	var refreshtime = '.max(500, (int) $modSettings['nchat_time']).';
	var nchatLast = '.(int) @file_get_contents($boarddir.$nchatInfo['nchatLast']).';
	var nchatError = document.getElementById("nchat_error");
	var nchatInput = document.getElementById("nchat_input");
	var nchatOutput = document.getElementById("nchat_admin_shoutbox");
	var nchat_txt_unavailable = "'.nchatJsEscape($txt['nchat_index_unavailable']).'";
	var nchat_txt_reconnecting = "'.nchatJsEscape(nchatTxt('nchat_reconnecting', 'Trying to reconnect... updates will resume automatically when the connection is back.')).'";
	var nchat_txt_add_mute = "'.nchatJsEscape($txt['nchat_add_mute']).'";
	var nchat_txt_so_fast = "'.nchatJsEscape($txt['nchat_so_fast']).'";
	var nchat_txt_empty_mess = "'.nchatJsEscape($txt['nchat_empty_mess']).'";
	var nchat_txt_confirm_delete = "'.nchatJsEscape(nchatTxt('nchat_confirm_delete', 'Delete this message?')).'";
	var nchat_txt_confirm_clean = "'.nchatJsEscape(nchatTxt('nchat_confirm_clean', 'Clear the whole chat box?')).'";
	var nchat_txt_edit_title = "'.nchatJsEscape(nchatTxt('nchat_edit_link_title', 'Edit your message')).'";
	var nchat_txt_delete_title = "'.nchatJsEscape(nchatTxt('nchat_delete_link_title', 'Delete this message')).'";
	var nchat_txt_mute_title = "'.nchatJsEscape(nchatTxt('nchat_mute_link_title', 'Mute this user')).'";
	var nchat_txt_muted_icon_title = "'.nchatJsEscape(nchatTxt('nchat_muted_icon_title', 'This user is currently muted')).'";
	var nchat_txt_link_name = "'.nchatJsEscape(nchatTxt('nchat_link_name_prompt', 'Enter a name for this link:')).'";
	var nchat_own_id = '.(int) $nchatInfo['id'].';';
	if($modSettings['nchat_order'] == 0 && $modSettings['nchat_sound'] == 1){
	echo '
	var nchatSound = document.getElementById("nchat_sound");';
	}
	echo '
	var show_err = true;
	var first_load = true;
	var last_chat = 0;
	var limit_time = 1; //in second
	var reload;
	var shut_it_down;
	var nchat_messages = null;
	//Filled lazily on the first parse; the smiley list never changes after page load.
	var nchat_smiles_regex = null;
	//Only one row can be in edit mode; while set, polls skip repainting.
	var nchat_editing = false;
	var nchat_edit_state = null;
	var nchat_edit_draft = "";
	//Sticks once the server has rejected a write with a mute; input row stays disabled until refresh.
	var nchat_is_muted = false;
	//Latest snapshot of currently-muted user ids, replayed on every read for the ⏱ marker.
	var nchat_muted_users = [];
	//Set once the SMF session is gone; all further polling stops so we do not hammer the server.
	var nchat_session_dead = false;
	//Epoch of the most recent successful poll response; used to suppress the offline banner from any older overlapping request whose ontimeout/onerror fires afterwards.
	var nchat_success_at = 0;
	//Consecutive auth mismatches; a single mid-hand-off guest reply on 4G/WiFi swap should not lock the UI.
	var nchat_auth_flap = 0;

	function nchat_shut_it_down()
	{
		nchatOutput.scrollTop = nchatOutput.scrollHeight;
	}

	window.onbeforeunload = function (e) {
		show_err = false;
	}

	function nchat_stop_session(message)
	{
		if(nchat_session_dead) return;
		nchat_session_dead = true;
		clearTimeout(reload);
		if(!show_err) return;
		nchatError.innerHTML = message || "'.nchatJsEscape(nchatTxt('nchat_session_expired', 'Your session timed out, please refresh the page and try again.')).'";
		nchatError.style.display = "block";
	}

	//True whenever the client should not fire a state-changing request. Used to gate writes/edits so the typed message is not lost when send fails.
	function nchat_offline_now()
	{
		if(nchat_session_dead) return true;
		return nchatError && nchatError.style.display && nchatError.style.display !== "none";
	}

	function nchat_reload()
	{
		if(nchat_session_dead) return;
		//An open editor keeps the local view frozen so the row it targets stays put.
		if(!nchat_editing)
			nchat_ajax("", false);
		reload = setTimeout(nchat_reload, refreshtime);
	}

	//Every write/edit/delete/clean/mute goes through here so the reload timer is reset consistently after any state change.
	function nchat_send_change(params)
	{
		if(nchat_session_dead) return;
		nchat_ajax(params, true);
		clearTimeout(reload);
		nchat_reload();
	}

	function nchat_ajax(param, load)
	{
		if(nchat_session_dead) return;
		var xmlhttp;
		var nchatUrl;
		var nchatscroll = false;
		//Timestamp of when THIS request was issued; if a newer request has already succeeded, our late timeout/error is stale and must not repaint the banner.
		var issued_at = Date.now();
		if(load){
			nchatUrl = "'.$nchatInfo['url'].'";
		}else{
			nchatUrl = "'.$boardurl.$nchatInfo['nchatLast'].'";
		}
		xmlhttp = new XMLHttpRequest();
		function nchat_show_offline(reason){
			if(!show_err) return;
			//A newer request already proved connectivity; suppress this late error so the banner does not flap.
			if(nchat_is_stale()) return;
			nchatError.innerHTML = nchat_txt_unavailable + " (" + reason + ")<br>" + nchat_txt_reconnecting;
			nchatError.style.display = "block";
		}
		function nchat_is_stale(){
			//Poll interval is refreshtime and the request timeout is refreshtime*2, so up to one older request can still be in flight when a newer one succeeds.
			return nchat_success_at >= issued_at;
		}
		xmlhttp.timeout = Math.max(5000, refreshtime * 2);
		xmlhttp.ontimeout = function(){ nchat_show_offline("timeout"); };
		xmlhttp.onerror   = function(){ nchat_show_offline("network error"); };
		xmlhttp.onabort   = function(){ /* user navigation; stay quiet */ };
		xmlhttp.onreadystatechange = function(){
			if(xmlhttp.readyState == 4){
				if (xmlhttp.status == 200){
					//Record the success epoch first so any older in-flight request that fires ontimeout/onerror after us is recognised as stale.
					nchat_success_at = Date.now();
					//Any 2xx from either endpoint means we are back online; clear a lingering offline banner even when no full read follows.
					if(nchatError.style.display !== "none" && !nchat_session_dead){
						nchatError.style.display = "none";
					}
					if(load){
						//Declared up-front so an eval that omits any of them falls back to a safe value instead of a ReferenceError.
						var nchat_muted = null;
						var nchat_session_expired = null;
						var nchat_auth_expired = null;
						var nchat_current_uid = null;
						var nchat;
						try {
							eval(xmlhttp.responseText);
						} catch(evalErr) {
							nchat_show_offline("bad response");
							return;
						}
						if(nchat_session_expired){
							nchat_stop_session(typeof nchat_session_expired === "string" ? nchat_session_expired : "");
							return;
						}
						//Auth mismatch: page was rendered logged-in but the request now runs as guest or a different user.
						if(nchat_auth_expired || (nchat_own_id > 0 && nchat_current_uid !== null && nchat_current_uid !== nchat_own_id)){
							//One transient guest reply during a network hand-off (Wi-Fi <-> 4G) is not enough to lock; require it twice in a row.
							nchat_auth_flap++;
							if(nchat_auth_flap < 2) return;
							nchat_stop_session("");
							return;
						}
						nchat_auth_flap = 0;
						if(nchat_muted){
							nchat_apply_mute(nchat_muted);
						} else if(nchat_is_muted){
							nchat_clear_mute();
						}
						if(nchat === undefined || nchat === null) return;
						'.(($modSettings['nchat_order'] == 0) ? 'if((nchatOutput.scrollTop + nchatOutput.clientHeight) >= (nchatOutput.scrollHeight - 20))
						{
							nchatscroll = true;
						}' : '').'
						//Cache the freshest server data but leave the DOM alone while an editor is open.
						nchat_messages = nchat;
						if(!nchat_editing){
							try { nchat_parser(nchat); } catch(parseErr) { nchat_show_offline("render error"); return; }
							nchatError.style.display = "none";
							if(nchatscroll)
							{
								nchatOutput.scrollTop = nchatOutput.scrollHeight;
							}
							'.(($modSettings['nchat_order'] == 0 && $modSettings['nchat_sound'] == 1) ? 'if(!first_load && nchatSound){ try { nchatSound.play(); } catch(e) {} }' : '').'
							first_load = false;
						}
					}else{
						var stamp = parseInt(xmlhttp.responseText, 10);
						if(isNaN(stamp)){
							nchat_show_offline("bad stamp");
							return;
						}
						if(stamp > nchatLast || nchat_is_muted){
							if(stamp > nchatLast) nchatLast = stamp;
							nchat_ajax("nchat=read", true);
						}
					}
				}else if(show_err){
					nchat_show_offline("Status: " + (xmlhttp.status || 0) + (xmlhttp.statusText ? " - " + xmlhttp.statusText : ""));
				}
			}
		}

		// Poll of last.html must be GET: static files reject POST on IIS (405).
		if(load){
			xmlhttp.open("POST", nchatUrl, true);
			xmlhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
			//X-Requested-With is our CSRF proof: only same-origin XHR/fetch can set custom headers, so a cross-site form/img cannot forge this.
			xmlhttp.setRequestHeader("X-Requested-With", "XMLHttpRequest");
			xmlhttp.send(param);
		}else{
			xmlhttp.open("GET", nchatUrl + (nchatUrl.indexOf("?") === -1 ? "?" : "&") + "_=" + Date.now(), true);
			xmlhttp.setRequestHeader("Cache-Control","no-cache");
			xmlhttp.setRequestHeader("X-Requested-With", "XMLHttpRequest");
			xmlhttp.send();
		}
	}
	function nchat_parser(nchat)
	{
		nchat_messages = nchat;
		var nchat_content = "";
		var nchat_small_mess;
		var nchat_orig_html;
		var i, j, k;
		//Build the per-smiley regex table once and reuse it on every redraw.
		if(nchat_smiles_regex === null){
			nchat_smiles_regex = [];
			for(var s=0; s<nchat_smiles_list.length; s++){
				nchat_smiles_regex.push(new RegExp("( |^|\t)"+nchat_smiles_list[s][0]+"(?= |$|\t)", "gi"));
			}
		}
		if(typeof nchat[0] !== "undefined" && nchat[0] !== null)
		{
			'.(($modSettings['nchat_order'] == 0) ? 'for(var i=0;i<nchat.length;i++){' : 'for(var i=(nchat.length-1);i>=0;i--){').'
				nchat_small_mess = nchat[i].split("|!|");
				nchat_content += "<div id=\"nchat_msg_" + i + "\" class=\"nchat_msg\">";
				nchat_content += '.(allowedTo('nchat_delete') ? '"[<a href=\"javascript: void(0);\" title=\"" + nchat_txt_delete_title + "\" onclick=\"nchat_delete(" + i + "); return false;\">X</a>] " + ': '').'"<span style=\"font-size:70%;\">[" + nchat_small_mess[4] + "]</span>"'.($user_info['is_guest'] ? '' : (allowedTo('nchat_write') ? ' + ((nchat_small_mess[2] == nchat_own_id && !nchat_is_muted) ? " [<a href=\"javascript: void(0);\" title=\"" + nchat_txt_edit_title + "\" onclick=\"nchat_edit(" + i + "); return false;\">E</a>]" : "")' : '')).';

				if(nchat_small_mess[1] != "-")
				{
					nchat_content +=  " <a href=\"'.$boardurl.'/index.php?action=profile;u=" + nchat_small_mess[2] + "\" style=\"color:" + nchat_small_mess[1] + ";\">" + nchat_small_mess[3] + "</a>";
				}else{
					nchat_content +=  " <a href=\"'.$boardurl.'/index.php?action=profile;u=" + nchat_small_mess[2] + "\">" + nchat_small_mess[3] + "</a>";
				}

				'.(allowedTo('nchat_mute') ? 'if(nchat_small_mess[6] != -1 && nchat_small_mess[6] != 1 && nchat_small_mess[6] != 2){
					var isMuted = false;
					for(var mi=0; mi<nchat_muted_users.length; mi++){
						if(nchat_muted_users[mi] == nchat_small_mess[2]){ isMuted = true; break; }
					}
					if(isMuted){
						nchat_content += " <span title=\"" + nchat_txt_muted_icon_title + "\">⏱️</span>";
					} else {
						nchat_content += " [<a href=\"javascript: void(0);\" title=\"" + nchat_txt_mute_title + "\" onclick=\"nchat_mute("+nchat_small_mess[2]+"); return false;\">M</a>]";
					}
				}' : 'for(var mi=0; mi<nchat_muted_users.length; mi++){
					if(nchat_muted_users[mi] == nchat_small_mess[2]){
						nchat_content += " <span title=\"" + nchat_txt_muted_icon_title + "\">⏱️</span>";
						break;
					}
				}').'

				nchat_content += ":";

				nchat_small_mess[5] = nchat_apply_markers(nchat_small_mess[5]);

				for(var j=0;j<nchat_smiles_list.length;j++)
				{
					nchat_small_mess[5] = nchat_small_mess[5].replace(nchat_smiles_regex[j], "$1<img src=\'" + nchat_smiles_list[j][1] + "\' />");
				}

				if(typeof nchat_small_mess[7] !== "undefined" && nchat_small_mess[7] !== "")
				{
					var nchat_orig_bodies = nchat_small_mess[7].split("|~|");
					var nchat_orig_times = (typeof nchat_small_mess[8] !== "undefined") ? nchat_small_mess[8].split("|~|") : [];
					var nchat_orig_name = (nchat_small_mess[1] != "-")
						? " <a href=\"'.$boardurl.'/index.php?action=profile;u=" + nchat_small_mess[2] + "\" style=\"color:" + nchat_small_mess[1] + ";\">" + nchat_small_mess[3] + "</a>:"
						: " <a href=\"'.$boardurl.'/index.php?action=profile;u=" + nchat_small_mess[2] + "\">" + nchat_small_mess[3] + "</a>:";
					var nchat_history_html = "";
					for(var h=0; h<nchat_orig_bodies.length; h++){
						if(nchat_orig_bodies[h] === "") continue;
						nchat_orig_html = nchat_apply_markers(nchat_orig_bodies[h]);
						for(var k=0;k<nchat_smiles_list.length;k++){
							nchat_orig_html = nchat_orig_html.replace(nchat_smiles_regex[k], "$1<img src=\'" + nchat_smiles_list[k][1] + "\' />");
						}
						var nchat_orig_time = (typeof nchat_orig_times[h] !== "undefined" && nchat_orig_times[h] !== "")
							? "<span style=\"font-size:70%;\">[" + nchat_orig_times[h] + "]</span>"
							: "";
						nchat_history_html += "<br />" + nchat_orig_time + nchat_orig_name + " <span style=\"color:" + nchat_small_mess[0] + ";\"><del>" + nchat_orig_html + "</del></span>";
					}
					nchat_small_mess[5] = "<span style=\"color:" + nchat_small_mess[0] + ";\">" + nchat_small_mess[5] + "</span>" + nchat_history_html + "</div>";
				}
				else
				{
					nchat_small_mess[5] = "<span style=\"color:" + nchat_small_mess[0] + ";\">" + nchat_small_mess[5] + "</span></div>";
				}
				nchat_content += nchat_small_mess[5];
			}
		}
		nchatOutput.innerHTML = nchat_content;
	}
	function nchat_format(what)
	{
		nchat_wrap("[" + what[0] + "]", "[/" + what[0] + "]");
	}
	function nchat_color()
	{
		var sel = document.getElementById("nchat_color");
		if(!sel) return;
		var color = sel.value;
		//The default-color option is just a no-op reset.
		if(sel.selectedIndex !== 0)
			nchat_wrap("[color=" + color + "]", "[/color]");
		sel.selectedIndex = 0;
		nchatInput.focus();
	}
	//Wrap the current selection in nchat_input with the given tag pair; if there is no
	//selection, drops an empty pair at the caret with the caret between the tags.
	function nchat_wrap(open, close)
	{
		if(!nchatInput) return;
		if(nchatInput.disabled) return;
		var start = 0, end = 0;
		try { start = nchatInput.selectionStart; end = nchatInput.selectionEnd; } catch(e) {}
		var val = nchatInput.value;
		var before = val.substring(0, start);
		var sel = val.substring(start, end);
		var after = val.substring(end);
		nchatInput.value = before + open + sel + close + after;
		var caret = before.length + open.length + sel.length + (sel.length ? close.length : 0);
		try { nchatInput.setSelectionRange(caret, caret); } catch(e) {}
		nchatInput.focus();
	}
	//Convert the [b]/[i]/[u]/[color=#RRGGBB]/[url] markers back into safe HTML for rendering.
	function nchat_apply_markers(s)
	{
		s = s.replace(/\\[url=((?:https?|ftp):\\/\\/[^\\]\\s]+)\\]([\\s\\S]*?)\\[\\/url\\]/gi, "<mark><a href=\"$1\" target=\"_blank\">$2</a></mark>");
		s = s.replace(/\\[url\\]((?:https?|ftp):\\/\\/[^\\[\\s]+)\\[\\/url\\]/gi, "<mark><a href=\"$1\" target=\"_blank\">$1</a></mark>");
		s = s.replace(/\\[b\\]([\\s\\S]*?)\\[\\/b\\]/gi, "<b>$1</b>");
		s = s.replace(/\\[i\\]([\\s\\S]*?)\\[\\/i\\]/gi, "<i>$1</i>");
		s = s.replace(/\\[u\\]([\\s\\S]*?)\\[\\/u\\]/gi, "<u>$1</u>");
		s = s.replace(/\\[color=(#[a-fA-F0-9]{3,6})\\]([\\s\\S]*?)\\[\\/color\\]/gi, "<span style=\"color:$1;\">$2</span>");
		return s;
	}
	function nchat_open_confirm(previewHtml, promptHtml, challenge, onOk)
	{
		var modal = document.getElementById("nchat_confirm_modal");
		var box = document.getElementById("nchat_confirm_preview");
		var input = document.getElementById("nchat_confirm_challenge");
		var okBtn = document.getElementById("nchat_confirm_ok");
		if(previewHtml){
			box.innerHTML = previewHtml;
			box.style.display = "";
		}else{
			box.innerHTML = "";
			box.style.display = "none";
		}
		document.getElementById("nchat_confirm_text").innerHTML = promptHtml;
		if(challenge){
			input.value = "";
			input.style.display = "";
			okBtn.disabled = true;
			input.oninput = function(){
				okBtn.disabled = input.value.replace(/^\s+|\s+$/g, "") !== String(challenge);
			};
			setTimeout(function(){ input.focus(); }, 0);
		}else{
			input.style.display = "none";
			input.oninput = null;
			okBtn.disabled = false;
		}
		modal.style.display = "flex";
		okBtn.onclick = function(){
			if(challenge && input.value.replace(/^\s+|\s+$/g, "") !== String(challenge)) return;
			modal.style.display = "none";
			onOk();
		};
		document.getElementById("nchat_confirm_cancel").onclick = function(){
			modal.style.display = "none";
		};
	}
	function nchat_delete(id)
	{
		var previewHtml = "";
		if(nchat_messages && nchat_messages[id]){
			var parts = nchat_messages[id].split("|!|");
			var msgColor = parts[0], nameColor = parts[1], uid = parts[2], name = parts[3], ts = parts[4], body = parts[5] || "";
			var profileHref = "'.$boardurl.'/index.php?action=profile;u=" + encodeURIComponent(uid);
			var nameHtml = nameColor != "-"
				? "<a href=\"" + profileHref + "\" style=\"color:" + nameColor + ";\">" + name + "</a>"
				: "<a href=\"" + profileHref + "\">" + name + "</a>";
			previewHtml = "<span style=\"font-size:70%;\">[" + ts + "]</span> " + nameHtml + ":<span style=\"color:" + msgColor + ";\">" + nchat_apply_markers(body) + "</span>";
			if(typeof parts[7] !== "undefined" && parts[7] !== ""){
				var histBodies = parts[7].split("|~|");
				var histTimes = (typeof parts[8] !== "undefined") ? parts[8].split("|~|") : [];
				for(var h=0; h<histBodies.length; h++){
					if(histBodies[h] === "") continue;
					var histTs = (typeof histTimes[h] !== "undefined" && histTimes[h] !== "")
						? "<span style=\"font-size:70%;\">[" + histTimes[h] + "]</span>"
						: "";
					previewHtml += "<br />" + histTs + " " + nameHtml + ":<span style=\"color:" + msgColor + ";\"><del>" + nchat_apply_markers(histBodies[h]) + "</del></span>";
				}
			}
		}
		nchat_open_confirm(previewHtml, nchat_txt_confirm_delete, null, function(){
			nchat_send_change("nchat=clean&nchat_mess=" + id);
		});
		return false;
	}
	function nchat_clean_all()
	{
		var num = Math.floor(1000 + Math.random() * 9000);
		var promptHtml = nchat_txt_confirm_clean + "<div style=\"font-size:1.6em;font-weight:bold;letter-spacing:0.15em;text-align:center;margin:8px 0;\">" + num + "</div>";
		nchat_open_confirm("", promptHtml, num, function(){
			nchat_send_change("nchat=clean");
		});
		return false;
	}
	function nchat_mute(user_id)
	{
		var a = prompt(nchat_txt_add_mute, 5);

		if(a != null && a != "")
			nchat_send_change("nchat=setmute&nchat_mess=" + encodeURIComponent(user_id) + "&nchat_mute=" + encodeURIComponent(a));

		return false;
	}
	//Server-signalled mute: swap the compose row for the mute notice and freeze it.
	function nchat_apply_mute(html)
	{
		if(nchat_is_muted) return;
		if(nchat_editing) nchat_edit_cancel();
		nchat_is_muted = true;
		var row = document.getElementById("nchat_input_row");
		var notice = document.getElementById("nchat_mute_notice");
		if(row) row.style.display = "none";
		if(notice){
			notice.innerHTML = html;
			notice.style.display = "block";
		}
		if(nchatInput) nchatInput.disabled = true;
		var submitBtn = document.getElementById("nchat_submit_btn");
		if(submitBtn) submitBtn.disabled = true;
	}
	//The next read after the mute expires reverses everything nchat_apply_mute did.
	function nchat_clear_mute()
	{
		if(!nchat_is_muted) return;
		nchat_is_muted = false;
		var row = document.getElementById("nchat_input_row");
		var notice = document.getElementById("nchat_mute_notice");
		if(row) row.style.display = "flex";
		if(notice){
			notice.style.display = "none";
			notice.innerHTML = "";
		}
		if(nchatInput) nchatInput.disabled = false;
		var submitBtn = document.getElementById("nchat_submit_btn");
		if(submitBtn) submitBtn.disabled = false;
	}
	//Rewrite legacy outer <b>/<i>/<u> wrappers into inline [b]/[i]/[u] markers so that
	//re-saving an older message preserves the format under the new inline model. A textarea
	//decodes HTML entities into the raw user text without ever parsing tags.
	function nchat_edit_prefill(html)
	{
		var core = String(html);
		var tags = [];
		for(var n=0; n<3; n++){
			var m = core.match(/^<(b|i|u)>([\\s\\S]*)<\\/(b|i|u)>$/);
			if(!m || m[1] !== m[3]) break;
			tags.push(m[1]);
			core = m[2];
		}
		var out = core;
		for(var t=tags.length-1; t>=0; t--) out = "[" + tags[t] + "]" + out + "[/" + tags[t] + "]";
		var ta = document.createElement("textarea");
		ta.innerHTML = out;
		return ta.value;
	}
	//Save button + Enter key route through here so the same box serves new posts and edits.
	function nchat_submit()
	{
		if(nchat_editing) nchat_edit_save();
		else nchat_sender();
		return false;
	}
	function nchat_edit(id)
	{
		if(nchat_is_muted) return false;
		if(!nchat_messages || !nchat_messages[id]) return false;
		var parts = nchat_messages[id].split("|!|");
		if(parts[2] != nchat_own_id) return false;
		if(nchat_editing) nchat_edit_cancel();
		var el = document.getElementById("nchat_msg_" + id);
		if(!el) return false;
		var rawBody = String(parts[5] || "");
		var initial = nchat_edit_prefill(rawBody);
		nchat_edit_draft = nchatInput.value;
		nchat_edit_state = { index: id, initial: initial, rowElem: el };
		nchat_editing = true;
		el.style.outline = "2px solid #3572e3";
		el.style.outlineOffset = "2px";
		document.getElementById("nchat_cancel_btn").style.display = "";
		nchatInput.value = initial;
		nchatInput.focus();
		try { nchatInput.setSelectionRange(nchatInput.value.length, nchatInput.value.length); } catch(e) {}
		return false;
	}
	function nchat_edit_teardown()
	{
		if(nchat_edit_state && nchat_edit_state.rowElem){
			nchat_edit_state.rowElem.style.outline = "";
			nchat_edit_state.rowElem.style.outlineOffset = "";
		}
		document.getElementById("nchat_cancel_btn").style.display = "none";
		nchatInput.value = nchat_edit_draft;
		nchat_edit_draft = "";
		nchat_editing = false;
		nchat_edit_state = null;
	}
	function nchat_edit_cancel()
	{
		nchat_edit_teardown();
		if(nchat_messages) nchat_parser(nchat_messages);
		return false;
	}
	function nchat_edit_save()
	{
		if(nchat_edit_state == null){ nchat_edit_cancel(); return false; }
		var newVal = nchatInput.value;
		var newTrim = newVal.replace(/^\\s+|\\s+$/g, "");
		var initialTrim = String(nchat_edit_state.initial).replace(/^\\s+|\\s+$/g, "");
		//Empty or unchanged: same as cancel, no round-trip.
		if(newTrim === "" || newTrim === initialTrim){ nchat_edit_cancel(); return false; }
		//Keep the edit open while offline; teardown would restore the original draft and lose the edits.
		if(nchat_offline_now()) return false;
		var idx = nchat_edit_state.index;
		nchat_edit_teardown();
		nchat_send_change("nchat=edit&nchat_id=" + encodeURIComponent(idx) + "&nchat_mess=" + encodeURIComponent(newVal));
		return false;
	}
	function nchat_toggle_smiles()
	{
		var modal = document.getElementById("nchat_smiles_modal");
		if(!modal) return;
		if(modal.style.display === "none" || modal.style.display === ""){
			var grid = document.getElementById("nchat_smiles_grid");
			//Populate the grid on first open; the smiley list never changes at runtime.
			if(grid && !grid.firstChild){
				var html = "";
				for(var i=0; i<nchat_smiles_list.length; i++){
					html += "<span style=\"cursor:pointer;padding:2px;\" onclick=\"nchat_smile(nchat_smiles_list["+i+"][2]); nchat_close_smiles();\"><img src=\"" + nchat_smiles_list[i][1] + "\" alt=\"*\" /></span>";
				}
				grid.innerHTML = html;
			}
			modal.style.display = "flex";
		} else {
			nchat_close_smiles();
		}
	}
	function nchat_close_smiles()
	{
		var modal = document.getElementById("nchat_smiles_modal");
		if(modal) modal.style.display = "none";
	}

	function nchat_smile(what)
	{
		nchatInput.value += " " + what.replace(/&lt;/g, "<").replace(/&gt;/g, ">").replace(/&quot;/g, "\"").replace(/&#0?39;/g, "\'").replace(/&amp;/g, "&") + " ";
		nchatInput.focus();
	}
	function nchat_sender()
	{
		var d=new Date();
		if(nchatInput.value.length > 0)
		{
			//Do not consume the typed text while offline; the user keeps the message and can hit Save again once the banner clears.
			if(nchat_offline_now()) return;
			if((d.getTime() - last_chat) >= limit_time*1000){
				nchat_send_change("nchat=write&nchat_mess=" + encodeURIComponent(nchatInput.value));
				nchatInput.value = "";
				last_chat = d.getTime();
			}else{
				alert(nchat_txt_so_fast);
			}
		}else{
			alert(nchat_txt_empty_mess);
		}
	}
	//Intercept single-URL pastes so the user can label the link before it is inserted as [url=...]name[/url].
	if(nchatInput && nchatInput.addEventListener){
		nchatInput.addEventListener("paste", function(ev){
			if(nchatInput.disabled) return;
			var cd = ev.clipboardData || window.clipboardData;
			if(!cd) return;
			var text = cd.getData("text") || cd.getData("Text");
			if(!text) return;
			var trimmed = text.replace(/^\\s+|\\s+$/g, "");
			if(!/^(?:https?|ftp):\\/\\/\\S+$/i.test(trimmed)) return;
			ev.preventDefault();
			nchat_paste_link_prompt(trimmed);
		});
	}
	function nchat_paste_link_prompt(url)
	{
		var name = window.prompt(nchat_txt_link_name + "\n\n" + url, url);
		if(name === null) return;
		var insert;
		if(name === "" || name === url) insert = "[url]" + url + "[/url]";
		else insert = "[url=" + url + "]" + name + "[/url]";
		var start = 0, end = 0;
		try { start = nchatInput.selectionStart; end = nchatInput.selectionEnd; } catch(e) {}
		var val = nchatInput.value;
		nchatInput.value = val.substring(0, start) + insert + val.substring(end);
		var caret = start + insert.length;
		try { nchatInput.setSelectionRange(caret, caret); } catch(e) {}
		nchatInput.focus();
	}
	nchat_ajax("nchat=read", true);
	nchat_reload();
	'.(($modSettings['nchat_order'] == 0) ? 'shut_it_down = setTimeout(nchat_shut_it_down, 1000);' : '').'
	window.onunload = function() {
		clearTimeout(reload);
	};
	</script>';
}
?>
