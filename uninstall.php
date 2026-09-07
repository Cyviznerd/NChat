<?php
//NChat by ThisMod.com

//Drop the theme-options integration hook first so a half-removed install cannot fire our callback against missing files. $permanent = true removes the row from {db_prefix}settings.integrate_theme_options.
if(function_exists('remove_integration_function')){
	remove_integration_function('integrate_theme_options', 'nchat_theme_options', true, '$boarddir/NChat/NChatHandle.php');
	remove_integration_function('integrate_pre_profile_areas', 'nchat_sanitize_theme_options', true, '$boarddir/NChat/NChatHandle.php');
}

$smcFunc['db_query']('', '
	DELETE FROM {db_prefix}settings
	WHERE variable IN ({array_string:nchat_settings})',
	array(
		'nchat_settings' => array(
			'nchat_order',
			'nchat_sound',
			'nchat_censor',
			'nchat_smile',
			'nchat_admin_mess',
			'nchat_time',
			'nchat_line',
			'nchat_lenght',
			'nchat_background',
			'nchat_text',
			'nchat_time_format',
			'nchat_height',
			'nchat_text_size',
		),
	)
);

//Per-member theme option rows for nchat_text_size live in {db_prefix}themes; drop them too.
$smcFunc['db_query']('', '
	DELETE FROM {db_prefix}themes
	WHERE variable = {string:variable}',
	array(
		'variable' => 'nchat_text_size',
	)
);

$smcFunc['db_query']('', '
	DELETE FROM {db_prefix}permissions
	WHERE permission IN ({array_string:nchat_permissions})',
	array(
		'nchat_permissions' => array(
			'nchat_read',
			'nchat_write',
			'nchat_delete',
			'nchat_mute',
		),
	)
);
?>
