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
}


