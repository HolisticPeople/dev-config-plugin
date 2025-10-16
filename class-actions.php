<?php
namespace DevCfg;

if (!defined('ABSPATH')) { exit; }

class Actions {
	public static function registry() {
		return [
			// Stubs; safe no-ops you can later implement
			'noindex' => [
				'label' => 'Force noindex (blog_public=0)',
				'description' => 'Discourage search engines on this site by setting blog_public=0.',
				'runner' => [__CLASS__, 'run_noindex'],
			],
			'smtp_disable' => [
				'label' => 'Disable SMTP/mail sending (soft)',
				'description' => 'Attempt to disable/soft-block outbound mail via option flags (non-destructive).',
				'runner' => [__CLASS__, 'run_smtp_disable'],
			],
			'wc_webhooks_off' => [
				'label' => 'Disable WooCommerce webhooks (soft)',
				'description' => 'Set wc_webhooks_disabled option or related flags (non-destructive).',
				'runner' => [__CLASS__, 'run_wc_webhooks_off'],
			],
			'fluent_smtp_simulation_on' => [
				'label' => 'FluentSMTP: Enable Email Simulation (block sends)',
				'description' => 'Turns on FluentSMTP\'s "Disable sending all emails" setting when detected. Falls back to safe flag if structure differs.',
				'runner' => [__CLASS__, 'run_fluent_smtp_simulation_on'],
			],
		];
	}

	public static function run_noindex() {
		update_option('blog_public', '0');
		return ['ok' => true, 'message' => 'blog_public set to 0'];
	}

	public static function run_smtp_disable() {
		// Non-destructive: set a flag option many SMTP plugins respect (may vary by plugin)
		// You can extend this to specific plugin options later.
		update_option('dev_cfg_smtp_disabled', 1);
		return ['ok' => true, 'message' => 'SMTP soft-disabled flag set'];
	}

	public static function run_wc_webhooks_off() {
		// Non-destructive: store our own flag; teams can hook this later or extend
		update_option('dev_cfg_wc_webhooks_disabled', 1);
		return ['ok' => true, 'message' => 'WC webhooks soft-disabled flag set'];
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
		$attempts = [
			// Common/likely option structures across FluentSMTP versions
			['option' => 'fluent_smtp_settings', 'paths' => [ ['misc', 'email_simulation'], ['email_simulation'], ['simulate_emails'], ['misc', 'disable_emails'] ]],
			['option' => 'fluentmail_settings',   'paths' => [ ['misc', 'email_simulation'], ['email_simulation'], ['simulate_emails'], ['misc', 'disable_emails'] ]],
			['option' => 'fluent_smtp',           'paths' => [ ['email_simulation'], ['simulate_emails'] ]],
		];

		$updatedAny = false;
		$messages = [];
		foreach ($attempts as $attempt) {
			$optName = $attempt['option'];
			$settings = get_option($optName, null);
			if (!is_array($settings)) {
				continue;
			}
			foreach ($attempt['paths'] as $path) {
				$local = $settings; // copy for comparison
				self::try_update_nested($settings, $path, true);
			}
			update_option($optName, $settings);
			$updatedAny = true;
			$messages[] = "updated $optName";
		}

		if (!$updatedAny) {
			// Fallback: set our own flag so other code can honor it if needed
			update_option('dev_cfg_smtp_simulation', 1);
			$messages[] = 'set dev_cfg_smtp_simulation flag (could not detect FluentSMTP settings)';
		}

		return ['ok' => true, 'message' => implode('; ', $messages)];
	}
}


