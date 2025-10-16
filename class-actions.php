<?php
namespace DevCfg;

if (!defined('ABSPATH')) { exit; }

class Actions {
	public static function registry() {
		return [
			'noindex' => [
				'label' => 'Force noindex (blog_public=0)',
				'description' => 'Discourage search engines on this site by setting blog_public=0.',
				'runner' => [__CLASS__, 'run_noindex'],
			],
			'fluent_smtp_simulation' => [
				'label' => 'FluentSMTP Email Simulation',
				'description' => 'Control FluentSMTP\'s "Disable sending all emails" (misc.simulate_emails).',
				'runner' => [__CLASS__, 'run_fluent_smtp_simulation'],
			],
		];
	}

	public static function run_noindex() {
		update_option('blog_public', '0');
		return ['ok' => true, 'message' => 'blog_public set to 0'];
	}

	private static function try_update_nested(array &$arr, array $path, $value) {
		$ref =& $arr;
		foreach ($path as $segment) {
			if (!is_array($ref)) { $ref = []; }
			if (!array_key_exists($segment, $ref)) { $ref[$segment] = []; }
			$ref =& $ref[$segment];
		}
		$ref = $value;
	}


	public static function run_fluent_smtp_simulation_on() {
		$optName = 'fluentmail-settings';
		$settings = get_option($optName, []);
		if (!is_array($settings)) {
			return ['ok' => false, 'message' => 'fluentmail-settings option not found', 'changed' => false];
		}
		if (!isset($settings['misc']) || !is_array($settings['misc'])) {
			$settings['misc'] = [];
		}
		$before = isset($settings['misc']['simulate_emails']) ? $settings['misc']['simulate_emails'] : '';
		$settings['misc']['simulate_emails'] = 'yes';
		update_option($optName, $settings);
		return ['ok' => true, 'message' => 'FluentSMTP simulate_emails set to yes', 'changed' => ($before !== 'yes')];
	}

	public static function run_fluent_smtp_simulation_off() {
		$optName = 'fluentmail-settings';
		$settings = get_option($optName, []);
		if (!is_array($settings)) {
			return ['ok' => false, 'message' => 'fluentmail-settings option not found', 'changed' => false];
		}
		if (!isset($settings['misc']) || !is_array($settings['misc'])) {
			$settings['misc'] = [];
		}
		$before = isset($settings['misc']['simulate_emails']) ? $settings['misc']['simulate_emails'] : '';
		$settings['misc']['simulate_emails'] = 'no';
		update_option($optName, $settings);
		return ['ok' => true, 'message' => 'FluentSMTP simulate_emails set to no', 'changed' => ($before !== 'no')];
	}

	public static function run_fluent_smtp_simulation($mode = null) {
		if ($mode === null && isset($_POST['dev_cfg_action']['fluent_smtp_simulation_mode'])) {
			$mode = sanitize_text_field($_POST['dev_cfg_action']['fluent_smtp_simulation_mode']);
		}
		if ($mode !== 'enable' && $mode !== 'disable') {
			return ['ok' => true, 'message' => 'FluentSMTP simulation ignored', 'changed' => false];
		}
		return $mode === 'enable' ? self::run_fluent_smtp_simulation_on() : self::run_fluent_smtp_simulation_off();
	}
}


