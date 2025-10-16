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
			'delete_debug_logs' => [
				'label' => 'Delete debug logs (wp-content/debug*.log)',
				'description' => 'Removes debug.log and rotated variants from wp-content to reduce noise on staging.',
				'runner' => [__CLASS__, 'run_delete_debug_logs'],
			],
			'fluent_smtp_simulation_on' => [
				'label' => 'FluentSMTP: Enable Email Simulation (block sends)',
				'description' => 'Turns on FluentSMTP\'s "Disable sending all emails" (misc.simulate_emails = yes).',
				'runner' => [__CLASS__, 'run_fluent_smtp_simulation_on'],
			],
			'fluent_smtp_simulation_off' => [
				'label' => 'FluentSMTP: Disable Email Simulation (allow sends)',
				'description' => 'Turns off FluentSMTP\'s "Disable sending all emails" (misc.simulate_emails = no).',
				'runner' => [__CLASS__, 'run_fluent_smtp_simulation_off'],
			],
		];
	}

	public static function run_noindex() {
		update_option('blog_public', '0');
		return ['ok' => true, 'message' => 'blog_public set to 0'];
	}

	public static function run_delete_debug_logs() {
		$dir = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : WP_CONTENT_DIR; // WP_CONTENT_DIR is defined by WP
		$patterns = [
			$dir . '/debug.log',
			$dir . '/debug.log.*',
			$dir . '/debug-*.log'
		];
		$deleted = 0; $attempted = 0; $errors = [];
		foreach ($patterns as $pattern) {
			$files = glob($pattern);
			if ($files === false) { continue; }
			foreach ($files as $file) {
				$attempted++;
				if (is_file($file) && is_writable($file)) {
					if (@unlink($file)) {
						$deleted++;
					} else {
						$errors[] = basename($file);
					}
				}
			}
		}
		$changed = $deleted > 0;
		$ok = empty($errors);
		$message = $deleted . ' file(s) deleted';
		if (!$ok && $errors) { $message .= '; failed: ' . implode(', ', $errors); }
		return ['ok' => $ok, 'message' => $message, 'changed' => $changed];
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


}


