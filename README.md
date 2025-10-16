# Dev Configuration Tools (WordPress)

A simple admin tool under Tools > Dev Configuration to:

- List installed plugins with current status
- Choose a per-plugin policy: Enable / Disable / Ignore
- Toggle predefined "Other actions" (extensible via registry)
- Refresh list (preserves selections) and Apply configuration

This plugin does not auto-enforce; it only applies changes when you click Apply.

## Location
- WordPress admin > Tools > Dev Configuration

## Storage
- Settings are saved in the `dev_config_plugin_settings` option.

## Other actions (initial stubs)
- `noindex`: sets `blog_public = 0`
- `smtp_disable`: sets a soft flag option `dev_cfg_smtp_disabled`
- `wc_webhooks_off`: sets a soft flag option `dev_cfg_wc_webhooks_disabled`

Extend by editing `class-actions.php` and adding more entries to the registry.

## Development
- Main file: `dev-config-plugin.php`
- View: `admin-page.php`
- Actions registry: `class-actions.php`

## Deploy to Staging (GitHub Actions)
A workflow is included at `.github/workflows/deploy-staging.yml` that deploys this plugin directory to your staging server over SSH on push to `main`.

Configure these GitHub repo secrets:
- `SSH_HOST`: your staging host (e.g. `staging-xxxx.kinsta.cloud`)
- `SSH_USER`: SSH username
- `SSH_PORT`: SSH port (optional, default `22`)
- `SSH_PRIVATE_KEY`: private key (contents) with access to the staging server
- `REMOTE_PATH`: absolute path to `wp-content/plugins/dev-config-plugin` on the server

The workflow uses rsync over SSH to sync `dev-config-plugin/` to your server, excluding `.git` and `.github`.
