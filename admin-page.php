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

		<div style="margin:8px 0 12px 0; display:flex; gap:12px; align-items:center;">
			<label>Filter by status:
				<select id="dev-cfg-filter-status">
					<option value="any">Any</option>
					<option value="active">Active</option>
					<option value="inactive">Inactive</option>
				</select>
			</label>
			<label>Filter by policy:
				<select id="dev-cfg-filter-policy">
					<option value="any">Any</option>
					<option value="enable">Enable</option>
					<option value="disable">Disable</option>
					<option value="ignore">Ignore</option>
				</select>
			</label>
			<span style="color:#a00;">Rows highlighted indicate policy conflicts (active vs disable, inactive vs enable).</span>
		</div>

		<table id="dev-cfg-plugins" class="widefat fixed striped">
			<thead>
				<tr>
					<th>Name</th>
					<th>Status</th>
					<th>Policy</th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ($allPlugins as $file => $data): ?>
				<?php 
					$active = is_plugin_active($file);
					$policy = isset($ui['plugin_policies'][$file]) ? $ui['plugin_policies'][$file] : 'ignore';
					$mismatch = ($policy === 'enable' && !$active) || ($policy === 'disable' && $active);
					$author = '';
					if (!empty($data['AuthorName'])) { $author = $data['AuthorName']; }
					elseif (!empty($data['Author'])) { $author = wp_strip_all_tags($data['Author']); }
					$version = isset($data['Version']) ? $data['Version'] : '';
				?>
				<tr data-status="<?php echo $active ? 'active' : 'inactive'; ?>" data-policy="<?php echo esc_attr($policy); ?>"<?php echo $mismatch ? ' style="background:#fde8e8;"' : ''; ?>>
					<td>
						<?php 
							$label = $data['Name'];
							if ($author !== '') { $label .= ' (' . $author . ')'; }
							if ($version !== '') { $label .= ' - ' . $version; }
							echo esc_html($label);
						?>
					</td>
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
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<script type="text/javascript">
		(function(){
			var $ = jQuery;
			function applyFilters(){
				var s = $('#dev-cfg-filter-status').val();
				var p = $('#dev-cfg-filter-policy').val();
				$('#dev-cfg-plugins tbody tr').each(function(){
					var rs = this.getAttribute('data-status');
					var rp = this.getAttribute('data-policy');
					var show = (s === 'any' || s === rs) && (p === 'any' || p === rp);
					this.style.display = show ? '' : 'none';
				});
			}

			function evaluateRow(row){
				var rs = row.getAttribute('data-status');
				var rp = row.getAttribute('data-policy');
				var mismatch = (rp === 'enable' && rs === 'inactive') || (rp === 'disable' && rs === 'active');
				row.style.background = mismatch ? '#fde8e8' : '';
			}
			jQuery(function(){
				$('#dev-cfg-filter-status, #dev-cfg-filter-policy').on('change', applyFilters);
				$('#dev-cfg-plugins').on('change', 'input[name^="dev_cfg_policy["]', function(){
					var row = $(this).closest('tr')[0];
					row.setAttribute('data-policy', this.value);
					evaluateRow(row);
					applyFilters();
				});
				applyFilters();
			});
		})();
		</script>

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


