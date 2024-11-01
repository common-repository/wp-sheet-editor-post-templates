<p><?php _e('Thank you for installing our plugin.', vgse_post_templates_init()->textname); ?></p>

<?php
$steps = array();

$missing_plugins = array();

if (!class_exists('ReduxFramework')) {
	$missing_plugins[] = 'Redux Framework';
}

if (!class_exists('WP_Sheet_Editor')) {
	$missing_plugins[] = 'WP Sheet Editor';
}
if (!empty($missing_plugins)) {
	$steps['install_dependencies'] = '<p>' . sprintf(__('Install the free plugin: <a href="%s" target="_blank" class="button">WP Sheet Editor</a>', vgse_post_templates_init()->textname), WP_Sheet_Editor_Post_Templates::_get_plugin_install_url('wp-sheet-editor-bulk-spreadsheet-editor-for-posts-and-pages')) . '</p>';
}

if (!class_exists('WP_Sheet_Editor')) {
	$steps['setup_spreadsheet'] = '<p>' . sprintf(__('When you activate the "WP Sheet Editor" plugin, you will be sent to the spreadsheet setup page. Follow the setup steps in that page', vgse_post_templates_init()->textname)) . '</p>';
}


$steps['setup_templates'] = '<p>' . sprintf(__('Go to "WP Sheet Editor > settings > Post Templates". And set the post templates.', vgse_post_templates_init()->textname)) . '</p>';
$steps['done'] = '<p>' . sprintf(__('Done. Now when you create new posts in the spreadsheet, the post information will be autofilled from the template post.', vgse_post_templates_init()->textname)) . '</p>';

$steps = apply_filters('vg_sheet_editor/post_templates/welcome_steps', $steps);

if (!empty($steps)) {
	echo '<ol class="steps">';
	foreach ($steps as $key => $step_content) {
		?>
		<li><?php echo $step_content; ?></li>		
		<?php
	}

	echo '</ol>';
}	