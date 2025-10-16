<?php
/**
 * Plugin Name: Dev Configuration Tools
 * Description: One-click dev/staging setup under Tools → Dev Configuration. Choose plugins to force enable/disable and run predefined actions (e.g., noindex). Changes apply only when you click Apply; no auto-enforcement.
 * Version: 0.1.5
 * Author: HolisticPeople
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!function_exists('dev_cfg_array_get')) {
	function dev_cfg_array_get($array, $key, $default = null) {
		return is_array($array) && array_key_exists($key, $array) ? $array[$key] : $default;
	}
}

if (!defined('DEV_CFG_PLUGIN_VERSION')) {
	define('DEV_CFG_PLUGIN_VERSION', '0.1.5');
}

class DevCfgPlugin {
	const OPTION_KEY = 'dev_config_plugin_settings';
	const MENU_SLUG = 'dev-config-tools';

	public static function init() {
		add_action('admin_menu', [__CLASS__, 'register_tools_page']);
		add_action('admin_init', [__CLASS__, 'handle_post_actions']);
		// Add Settings link in Plugins list
		add_filter('plugin_action_links_' . plugin_basename(__FILE__), [__CLASS__, 'plugin_action_links']);
	}

	public static function register_tools_page() {
		add_management_page(
			'Dev Configuration',
			'Dev Configuration',
			'manage_options',
			self::MENU_SLUG,
			[__CLASS__, 'render_page']
		);
	}

	public static function get_settings() {
		$defaults = [
			'plugin_policies' => [],
			'other_actions' => [
				'noindex' => true, // default checked
				'fluent_smtp_simulation_on' => true, // default checked
			],
			'ui_prefs' => [
				'preserve_refresh' => true,
			],
		];
		$settings = get_option(self::OPTION_KEY, []);
		if (!is_array($settings)) {
			$settings = [];
		}
		return array_replace_recursive($defaults, $settings);
	}

	public static function update_settings($settings) {
		update_option(self::OPTION_KEY, $settings);
	}

	private static function sanitize_policies($rawPolicies) {
		$policies = [];
		if (!is_array($rawPolicies)) {
			return $policies;
		}
		foreach ($rawPolicies as $pluginFile => $policy) {
			$pluginFile = sanitize_text_field($pluginFile);
			$policy = sanitize_text_field($policy);
			if (!in_array($policy, ['enable', 'disable', 'ignore'], true)) {
				$policy = 'ignore';
			}
			$policies[$pluginFile] = $policy;
		}
		return $policies;
	}

private static function sanitize_other_actions($rawActions) {
	$actions = [];
	if (!is_array($rawActions)) {
		return $actions;
	}
	// Special handling: FluentSMTP simulation should be a radio (on/off/none)
	if (isset($rawActions['fluent_smtp_simulation'])) {
		$mode = sanitize_text_field($rawActions['fluent_smtp_simulation']);
		if ($mode === 'on') {
			$actions['fluent_smtp_simulation_on'] = true;
		} elseif ($mode === 'off') {
			$actions['fluent_smtp_simulation_off'] = true;
		}
		unset($rawActions['fluent_smtp_simulation']);
	}
	foreach ($rawActions as $key => $val) {
		$key = sanitize_key($key);
		$actions[$key] = (bool)$val;
	}
	return $actions;
}

	public static function handle_post_actions() {
		if (!is_admin() || !current_user_can('manage_options')) {
			return;
		}
		if (!isset($_GET['page']) || $_GET['page'] !== self::MENU_SLUG) {
			return;
		}

		if (!function_exists('get_plugins')) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$settings = self::get_settings();
		$postedPolicies = isset($_POST['dev_cfg_policy']) ? self::sanitize_policies($_POST['dev_cfg_policy']) : null;
		$postedActions = isset($_POST['dev_cfg_action']) ? self::sanitize_other_actions($_POST['dev_cfg_action']) : null;
		$uiSelections = [
			'plugin_policies' => is_array($postedPolicies) ? $postedPolicies : dev_cfg_array_get($settings, 'plugin_policies', []),
			'other_actions' => is_array($postedActions) ? $postedActions : dev_cfg_array_get($settings, 'other_actions', []),
		];
		$GLOBALS['dev_cfg_ui_selections'] = $uiSelections;

		if (isset($_POST['dev_cfg_refresh'])) {
			check_admin_referer('dev_cfg_refresh');
			add_settings_error('dev_cfg', 'refreshed', 'Plugin list refreshed. Selections preserved.', 'updated');
			return;
		}

		// Save configuration only (non-destructive)
		if (isset($_POST['dev_cfg_save'])) {
			if (!isset($_POST['dev_cfg_nonce_save']) || !wp_verify_nonce($_POST['dev_cfg_nonce_save'], 'dev_cfg_save')) {
				wp_die('Security check failed. Please try again.');
			}
			$policies = is_array($postedPolicies) ? $postedPolicies : dev_cfg_array_get($settings, 'plugin_policies', []);
			$actions = is_array($postedActions) ? $postedActions : dev_cfg_array_get($settings, 'other_actions', []);

			$save = self::get_settings();
			$save['plugin_policies'] = $policies;
			$save['other_actions'] = $actions;
			self::update_settings($save);

			add_settings_error('dev_cfg', 'saved', 'Configuration saved (no changes applied yet).', 'updated');
			return;
		}

		if (isset($_POST['dev_cfg_apply'])) {
			if (!isset($_POST['dev_cfg_nonce_apply']) || !wp_verify_nonce($_POST['dev_cfg_nonce_apply'], 'dev_cfg_apply')) {
				wp_die('Security check failed. Please try again.');
			}
			$policies = is_array($postedPolicies) ? $postedPolicies : [];
			$actions = is_array($postedActions) ? $postedActions : [];

			$save = self::get_settings();
			$save['plugin_policies'] = $policies;
			$save['other_actions'] = $actions;
			self::update_settings($save);

			$results = self::apply_configuration($policies, $actions);
			$summary = self::format_results_notice($results);
			// Build popup summary counts
			$enabledCount = 0; $disabledCount = 0; $pluginFailed = 0;
			foreach (dev_cfg_array_get($results, 'plugins', []) as $res) {
				if ($res === 'activated') { $enabledCount++; }
				elseif ($res === 'deactivated') { $disabledCount++; }
				elseif (is_string($res) && (stripos($res, 'error') !== false || stripos($res, 'failed') !== false || stripos($res, 'missing') !== false)) { $pluginFailed++; }
			}
			$actionsOk = 0; $actionsFailed = 0; $actionLines = [];
			foreach (dev_cfg_array_get($results, 'actions', []) as $key => $res) {
				$actionLines[] = $key . ': ' . $res;
				if (is_string($res) && (stripos($res, 'error') !== false || stripos($res, 'failed') !== false)) { $actionsFailed++; } else { $actionsOk++; }
			}
			$popup = "Plugins enabled: $enabledCount\nPlugins disabled: $disabledCount\nPlugin failures: $pluginFailed\nActions success: $actionsOk, failed: $actionsFailed";
			if ($actionLines) { $popup .= "\n\nActions detail:\n" . implode("\n", $actionLines); }
			set_transient('dev_cfg_apply_summary_popup', $popup, 60);

			add_settings_error('dev_cfg', 'applied', $summary, 'updated');
		}
	}

private static function format_results_notice($results) {
	$lines = [];
	if (!empty($results['plugins'])) {
		foreach ($results['plugins'] as $file => $res) {
			if (is_array($res)) {
				$text = isset($res['result']) ? $res['result'] : '';
			} else {
				$text = (string)$res;
			}
			$lines[] = sprintf('%s: %s', esc_html($file), esc_html($text));
		}
	}
	if (!empty($results['actions'])) {
		foreach ($results['actions'] as $key => $res) {
			if (is_array($res)) {
				$text = isset($res['message']) ? $res['message'] : (isset($res['result']) ? $res['result'] : '');
			} else {
				$text = (string)$res;
			}
			$lines[] = sprintf('%s: %s', esc_html($key), esc_html($text));
		}
	}
	return implode('<br>', $lines);
}

private static function apply_configuration($policies, $actions) {
	$pluginResults = [];
	$actionResults = [];

	foreach ($policies as $pluginFile => $policy) {
		if ($policy === 'ignore') {
			$pluginResults[$pluginFile] = ['result' => 'ignored', 'changed' => false];
			continue;
		}
		$pluginPath = WP_PLUGIN_DIR . '/' . $pluginFile;
		if (!file_exists($pluginPath)) {
			$pluginResults[$pluginFile] = ['result' => 'file missing', 'changed' => false];
			continue;
		}
		$before = is_plugin_active($pluginFile);
		if ($policy === 'enable') {
			if ($before) {
				$pluginResults[$pluginFile] = ['result' => 'already active', 'changed' => false];
			} else {
				$res = activate_plugin($pluginFile);
				if (is_wp_error($res)) {
					$pluginResults[$pluginFile] = ['result' => 'activate error: ' . $res->get_error_message(), 'changed' => false];
				} else {
					$after = is_plugin_active($pluginFile);
					$pluginResults[$pluginFile] = ['result' => $after ? 'activated' : 'activation uncertain', 'changed' => $after != $before];
				}
			}
		} elseif ($policy === 'disable') {
			if (!$before) {
				$pluginResults[$pluginFile] = ['result' => 'already inactive', 'changed' => false];
			} else {
				deactivate_plugins([$pluginFile], true);
				$after = is_plugin_active($pluginFile);
				$pluginResults[$pluginFile] = ['result' => $after ? 'deactivation failed' : 'deactivated', 'changed' => $after != $before];
			}
		}
	}

	require_once __DIR__ . '/class-actions.php';
	$registry = DevCfg\Actions::registry();
	foreach ($actions as $key => $enabled) {
		if (!$enabled) {
			continue;
		}
		if (!isset($registry[$key]) || !is_callable($registry[$key]['runner'])) {
			$actionResults[$key] = ['result' => 'unknown action', 'changed' => false];
			continue;
		}
		try {
			$out = call_user_func($registry[$key]['runner']);
			if (is_array($out) && isset($out['ok'])) {
				$actionResults[$key] = [
					'result'  => $out['ok'] ? ($out['message'] ?? 'ok') : ('failed' . (!empty($out['message']) ? ': ' . $out['message'] : '')),
					'changed' => isset($out['changed']) ? (bool)$out['changed'] : true,
					'message' => $out['message'] ?? ''
				];
			} else {
				$actionResults[$key] = ['result' => 'ok', 'changed' => true];
			}
		} catch (Throwable $e) {
			$actionResults[$key] = ['result' => 'error: ' . $e->getMessage(), 'changed' => false];
		}
	}

	return [
		'plugins' => $pluginResults,
		'actions' => $actionResults,
	];
}

	public static function render_page() {
		if (!current_user_can('manage_options')) {
			wp_die('Insufficient permissions');
		}

		$settings = self::get_settings();
		$ui = isset($GLOBALS['dev_cfg_ui_selections']) && is_array($GLOBALS['dev_cfg_ui_selections'])
			? $GLOBALS['dev_cfg_ui_selections']
			: [
				'plugin_policies' => dev_cfg_array_get($settings, 'plugin_policies', []),
				'other_actions' => dev_cfg_array_get($settings, 'other_actions', []),
			];

		if (!function_exists('get_plugins')) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$allPlugins = get_plugins();

		require_once __DIR__ . '/admin-page.php';
	}

	public static function plugin_action_links($links) {
		$url = admin_url('tools.php?page=' . self::MENU_SLUG);
		$links[] = '<a href="' . esc_url($url) . '">Settings</a>';
		return $links;
	}
}

DevCfgPlugin::init();


