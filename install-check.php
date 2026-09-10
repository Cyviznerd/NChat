<?php
//Runs from <code> in package-info.xml <install> BEFORE any file changes. Refuses the fresh-install path if an NChat row is already active in log_packages.
global $smcFunc;

$installedVersion = null;
$request = $smcFunc['db_query']('', '
	SELECT version
	FROM {db_prefix}log_packages
	WHERE package_id = {string:pkg}
		AND install_state = {int:installed}
	ORDER BY time_installed DESC
	LIMIT 1',
	array(
		'pkg' => 'nvcnvn:NChat',
		'installed' => 1,
	));
if($row = $smcFunc['db_fetch_assoc']($request))
	$installedVersion = $row['version'];
$smcFunc['db_free_result']($request);

if($installedVersion === null)
	return;

if($installedVersion === '1.3.2')
	$msg = 'NChat 1.3.2 is already installed. The <strong>Install</strong> button here would re-apply the SMF file modifications on top of themselves. To upgrade in place and keep your chat history, go back to <em>Admin &rarr; Package Manager &rarr; Browse Packages</em> and click <strong>Apply Upgrade</strong> next to this package instead.';
elseif($installedVersion === '1.4.0')
	$msg = 'NChat 1.4.0 is already installed. Use <strong>Apply Upgrade</strong> from <em>Admin &rarr; Package Manager &rarr; Browse Packages</em> to refresh the code files in place (no file mods, no data changes). Running <strong>Install</strong> here would re-apply the SMF file modifications on top of themselves.';
else
	$msg = 'NChat ' . htmlspecialchars($installedVersion) . ' is already installed. This installer only supports fresh installs or upgrades from 1.3.2 / 1.4.0. Please uninstall the existing NChat package first. <strong>Warning:</strong> the current uninstaller deletes <code>NChat/NChatMess.php</code> and your chat history.';

if(function_exists('fatal_error'))
	fatal_error($msg, false);

die('<div style="padding:1em;border:1px solid #c33;background:#fee;color:#900;font-family:sans-serif;">' . $msg . '</div>');
?>
