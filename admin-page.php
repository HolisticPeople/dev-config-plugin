<?php
if (!defined('ABSPATH')) { exit; }

$allPlugins = isset($allPlugins) ? $allPlugins : [];
$ui = isset($ui) ? $ui : ['plugin_policies' => [], 'other_actions' => []];

require_once __DIR__ . '/class-actions.php';
$registry = DevCfg\Actions::registry();

settings_errors('dev_cfg');
?>
<div class="wrap">
	<h1>Dev Configuration</h1>

	<form method="post">
		<?php wp_nonce_field('dev_cfg_refresh'); ?>
		<p>
			<button type="submit" name="dev_cfg_refresh" class="button">Refresh plugin list</button>
		</p>
	</form>

	<form method="post">
		<?php wp_nonce_field('dev_cfg_apply'); ?>

		<h2>Plugins</h2>
		<table class="widefat fixed striped">
			<thead>
				<tr>
					<th>Name</th>
					<th>Status</th>
					<th>Policy</th>
					<th>Version</th>
					<th>File</th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ($allPlugins as $file => $data): ?>
				<?php $active = is_plugin_active($file); $policy = isset($ui['plugin_policies'][$file]) ? $ui['plugin_policies'][$file] : 'ignore'; ?>
				<tr>
					<td><?php echo esc_html($data['Name']); ?></td>
					<td>
						<span style="display:inline-block;padding:2px 6px;border-radius:3px;background:<?php echo $active ? '#46b450' : '#777'; ?>;color:#fff;">
							<?php echo $active ? 'Active' : 'Inactive'; ?>
						</span>
					</td>
					<td>
						<label style="margin-right:8px;"><input type="radio" name="dev_cfg_policy[<?php echo esc_attr($file); ?>]" value="ignore" <?php checked($policy === 'ignore'); ?> /> Ignore</label>
						<label style="margin-right:8px;"><input type="radio" name="dev_cfg_policy[<?php echo esc_attr($file); ?>]" value="enable" <?php checked($policy === 'enable'); ?> /> Enable</label>
						<label><input type="radio" name="dev_cfg_policy[<?php echo esc_attr($file); ?>]" value="disable" <?php checked($policy === 'disable'); ?> /> Disable</label>
					</td>
					<td><?php echo isset($data['Version']) ? esc_html($data['Version']) : ''; ?></td>
					<td><code><?php echo esc_html($file); ?></code></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<h2 style="margin-top:24px;">Other actions</h2>
		<table class="widefat fixed striped">
			<thead>
				<tr>
					<th>Action</th>
					<th>Description</th>
					<th>Enable</th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ($registry as $key => $meta): ?>
				<tr>
					<td><strong><?php echo esc_html($meta['label']); ?></strong> <code><?php echo esc_html($key); ?></code></td>
					<td><?php echo esc_html($meta['description']); ?></td>
					<td>
						<input type="checkbox" name="dev_cfg_action[<?php echo esc_attr($key); ?>]" value="1" <?php checked(!empty($ui['other_actions'][$key])); ?> />
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<p style="margin-top:16px;">
			<button type="submit" name="dev_cfg_apply" class="button button-primary">Apply fresh dev configuration</button>
		</p>
	</form>
</div>


