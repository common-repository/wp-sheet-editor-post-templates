<?php
/* start-wp-plugin-header */
/*
  Plugin Name: WP Sheet Editor - Post Templates
  Description: Create new posts in the spreadsheet faster. Autofill new posts.
  Version: 1.0.1
  Author: VegaCorp
  Author URI: http://vegacorp.me
  Plugin URI: http://wpsheeteditor.com
 */
/* end-wp-plugin-header */

if (!defined('VGSE_POST_TEMPLATES_DIR')) {
	define('VGSE_POST_TEMPLATES_DIR', __DIR__);
}

if (file_exists('vendor/vg-plugin-sdk/index.php')) {
	require 'vendor/vg-plugin-sdk/index.php';
}
if (!class_exists('WP_Sheet_Editor_Post_Templates')) {

	/**
	 * Rename the columns of the spreadsheet editor to something more meaningful.
	 */
	class WP_Sheet_Editor_Post_Templates {

		static private $instance = false;
		var $version = '1.0.1';
		var $plugin_url = null;
		var $plugin_dir = null;
		var $textname = 'wpsept';

		private function __construct() {
			
		}

		static function _get_plugin_install_url($plugin_slug) {
			$install_plugin_base_url = ( is_multisite() ) ? network_admin_url() : admin_url();
			$install_plugin_url = add_query_arg(array(
				's' => $plugin_slug,
				'tab' => 'search',
				'type' => 'term'
					), $install_plugin_base_url . 'plugin-install.php');
			return $install_plugin_url;
		}
		/**
		 * Creates or returns an instance of this class.
		 */
		static function get_instance() {
			if (null == WP_Sheet_Editor_Post_Templates::$instance) {
				WP_Sheet_Editor_Post_Templates::$instance = new WP_Sheet_Editor_Post_Templates();
				WP_Sheet_Editor_Post_Templates::$instance->init();
			}
			return WP_Sheet_Editor_Post_Templates::$instance;
		}

		function notify_wrong_core_version() {
			$plugin_data = get_plugin_data(__FILE__, false, false);
			?>
			<div class="notice notice-error">
				<p><?php _e('Please update the WP Sheet Editor plugin to the version 2.0.0 or higher. The plugin "' . $plugin_data['Name'] . '" requires that version.', VGSE()->textname); ?></p>
			</div>
			<?php
		}

		function init() {
    
			$this->plugin_url = plugins_url('/', __FILE__);
			$this->plugin_dir = __DIR__;
			
			$this->args = array(
				'main_plugin_file' => __FILE__,
				'show_welcome_page' => true,
				'welcome_page_file' => $this->plugin_dir . '/views/welcome-page-content.php',
				'logo' => plugins_url('/assets/imgs/logo-248x102.png', __FILE__),
				'plugin_name' => 'WP Post Templates',
				'plugin_prefix' => 'wpsept_',
				'show_whatsnew_page' => false,
			);
			if (class_exists('VG_Freemium_Plugin_SDK') && strpos(__FILE__, 'modules') === false) {
			$this->vg_plugin_sdk = new VG_Freemium_Plugin_SDK($this->args);
			}
			add_action('vg_sheet_editor/initialized', array($this, 'late_init'));
		}

		function late_init() {

			if (version_compare(VGSE()->version, '2.0.0') < 0) {
				$this->notify_wrong_core_version();
				return;
			}			

			add_filter('redux/options/' . VGSE()->options_key . '/sections', array($this, 'add_options_to_settings_page'));
			
			// Priority 9 to execute before everything else.
			// The WC extension uses this filter with priority 10 to create products using wc api
			add_filter('vg_sheet_editor/add_new_posts/create_new_posts', array($this, 'duplicate_post'), 9, 3);
		}

		function _duplicate_post($post_id = null, $custom_post_data = array()) {

			if (empty($post_id)) {
				return new WP_Error('wpse', 'Empty $post_id');
			}
			$post_data = get_post($post_id, ARRAY_A);

			$post_data = wp_parse_args($custom_post_data, $post_data);

			$post_meta = get_post_custom($post_id);
			$taxonomies = get_object_taxonomies($post_data['post_type']); // returns array of taxonomy names for post type, ex array("category", "post_tag");
			$taxonomies_terms = array();
			foreach ($taxonomies as $taxonomy) {
				$post_terms = wp_get_object_terms($post_id, $taxonomy, array('fields' => 'slugs'));
				$taxonomies_terms[$taxonomy] = $post_terms;
			}

			unset($post_data['ID']);

			$new_post_id = wp_insert_post($post_data);
			// Copy post metadata
			foreach ($post_meta as $key => $values) {

				if (!empty($custom_post_data['meta_input']) && isset($custom_post_data['meta_input'][$key])) {
					continue;
				}
				foreach ($values as $value) {
					add_post_meta($new_post_id, $key, $value);
				}
			}
			foreach ($taxonomies_terms as $taxonomy => $post_terms) {
				wp_set_object_terms($new_post_id, $post_terms, $taxonomy, false);
			}

			return $new_post_id;
		}

		function duplicate_post($post_ids, $post_type, $rows) {
			$option_key = 'be_template_' . $post_type;
			if (empty(VGSE()->options[$option_key])) {
				return $post_ids;
			}

			$template_id = (int) VGSE()->options[$option_key];

			if (!get_post_type($template_id)) {
				return $post_ids;
			}
			if ($post_type === 'product' && function_exists('WC')) {

				if (!class_exists('WC_Admin_Duplicate_Product')) {
					include_once( WC_ABSPATH . 'includes/admin/class-wc-admin-duplicate-product.php' );
				}
				$duplicate = new WC_Admin_Duplicate_Product();
				$template_product = wc_get_product($template_id);

				if (empty($template_product)) {
					return $post_ids;
				}

				for ($i = 0; $i < $rows; $i++) {
					$new_post = $duplicate->product_duplicate($template_product);
					$new_post_id = $new_post->get_id();
					$post_ids[] = $new_post_id;
				}
			} else {

				for ($i = 0; $i < $rows; $i++) {
					$new_post_id = $this->_duplicate_post($template_id, array(
						'post_status' => 'draft',
						'post_title' => get_the_title($template_id) . ' (Copy)'
					));

					if (is_int($new_post_id)) {
						$post_ids[] = $new_post_id;
					}
				}
			}


			return $post_ids;
		}

		function __set($name, $value) {
			$this->$name = $value;
		}

		function __get($name) {
			return $this->$name;
		}

		/**
		 * Add fields to options page
		 * @param array $sections
		 * @return array
		 */
		function add_options_to_settings_page($sections) {

			$pts = VGSE()->helpers->get_allowed_post_types();
			$fields[] = array(
				'id' => 'info_normal',
				'type' => 'info',
				'desc' => __('In this page you can select the template posts for each post type. When you create new posts in the spreadsheet we will copy the values from the template posts.', VGSE()->textname),
			);

			foreach ($pts as $post_type => $post_type_label) {
				$fields[] = array(
					'id' => 'be_template_' . $post_type,
					'title' => sprintf(__('Template for %s', VGSE()->textname), $post_type_label),
					'type' => 'select',
					'multi' => false,
					'data' => 'posts',
					'args' => ( VGSE()->helpers->is_settings_page() ) ? array('post_type' => $post_type, 'post_status' => array('publish', 'draft'), 'posts_per_page' => -1) : array('posts_per_page' => 1)
				);
			}

			if (count($fields) > 1) {
				$sections[] = array(
					'icon' => 'el-icon-cogs',
					'title' => __('Post templates', VGSE()->textname),
					'fields' => $fields
				);
			}
			return $sections;
		}

	}

	add_action('plugins_loaded', 'vgse_post_templates_init');

	function vgse_post_templates_init() {
	WP_Sheet_Editor_Post_Templates::get_instance();
	}

}