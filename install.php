<?php
//NChat by ThisMod.com
$smcFunc['db_insert']('ignore',
'{db_prefix}settings',
array('variable' => 'string-255', 'value' => 'string-65534'),
array('nchat_order', '1'),
array('variable'));

$smcFunc['db_insert']('ignore',
'{db_prefix}settings',
array('variable' => 'string-255', 'value' => 'string-65534'),
array('nchat_sound', '0'),
array('variable'));

$smcFunc['db_insert']('ignore',
'{db_prefix}settings',
array('variable' => 'string-255', 'value' => 'string-65534'),
array('nchat_censor', '1'),
array('variable'));

$smcFunc['db_insert']('ignore',
'{db_prefix}settings',
array('variable' => 'string-255', 'value' => 'string-65534'),
array('nchat_smile', '1'),
array('variable'));

$smcFunc['db_insert']('ignore',
'{db_prefix}settings',
array('variable' => 'string-255', 'value' => 'string-65534'),
array('nchat_admin_mess', 'Thank for choosing NChat! Need Help or any questions? Read here: http://thismod.com/community/index.php?topic=2.0'),
array('variable'));

$smcFunc['db_insert']('ignore',
'{db_prefix}settings',
array('variable' => 'string-255', 'value' => 'string-65534'),
array('nchat_time', '3000'),
array('variable'));

$smcFunc['db_insert']('ignore',
'{db_prefix}settings',
array('variable' => 'string-255', 'value' => 'string-65534'),
array('nchat_line', '30'),
array('variable'));

$smcFunc['db_insert']('ignore',
'{db_prefix}settings',
array('variable' => 'string-255', 'value' => 'string-65534'),
array('nchat_lenght', '100'),
array('variable'));

$smcFunc['db_insert']('ignore',
'{db_prefix}settings',
array('variable' => 'string-255', 'value' => 'string-65534'),
array('nchat_background', '#ffffff'),
array('variable'));

$smcFunc['db_insert']('ignore',
'{db_prefix}settings',
array('variable' => 'string-255', 'value' => 'string-65534'),
array('nchat_text', '#000000'),
array('variable'));

$smcFunc['db_insert']('ignore',
'{db_prefix}settings',
array('variable' => 'string-255', 'value' => 'string-65534'),
array('nchat_time_format', 'M d H:i:s'),
array('variable'));

$smcFunc['db_insert']('ignore',
'{db_prefix}settings',
array('variable' => 'string-255', 'value' => 'string-65534'),
array('nchat_height', '400'),
array('variable'));

$smcFunc['db_insert']('ignore',
'{db_prefix}settings',
array('variable' => 'string-255', 'value' => 'string-65534'),
array('nchat_text_size', '14'),
array('variable'));

//Register the theme-options hook so each member gets a Chat text size picker on Profile -> Look and Layout with no core edits. $permanent = true persists it into {db_prefix}settings.integrate_theme_options.
if(function_exists('add_integration_function')){
	add_integration_function('integrate_theme_options', 'nchat_theme_options', true, '$boarddir/NChat/NChatHandle.php');
	add_integration_function('integrate_pre_profile_areas', 'nchat_sanitize_theme_options', true, '$boarddir/NChat/NChatHandle.php');
}

//Seed the flat-file data stores on fresh installs. Existing files are left untouched so an upgrade preserves chat history and the mute list.
global $boarddir;
$nchatDir = $boarddir . '/NChat';
if(!is_dir($nchatDir))
	@mkdir($nchatDir, 0755, true);

$nchatMessFile = $nchatDir . '/NChatMess.php';
if(!file_exists($nchatMessFile)){
	$nchatWelcome = '#ff00ff|!|#FF0000|!|1|!|ThisMod.com|!|' . date('M d H:i:s') . '|!|Hi there! Delete this line and start your own chat, enjoy!|!|1';
	@file_put_contents($nchatMessFile, '<?php die; ?>' . "\n" . serialize(array(0 => $nchatWelcome)));
}

$nchatMuteFile = $nchatDir . '/NChatMuteList.php';
if(!file_exists($nchatMuteFile))
	@file_put_contents($nchatMuteFile, '<?php die; ?>' . "\n" . serialize(array()));
?>