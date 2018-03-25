<?php
/*
 * This file is part of the "Download as PDF" plugin package.
 *
 * (c) Giuseppe Mazzapica
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @wordpress-plugin
 * Plugin Name: Download as PDF
 * Description: Adds a "Download as PDF" link at the end of posts.
 * Author: Giuseppe Mazzapica
 * Author URI: https://gmazzap.me
 * Version: 1.0.0
 * Text Domain: gmazzap_as_pdf
 * License: MIT
 * Requires at least: 4.9
 * Requires PHP: 5.4
 */

namespace Gmazzap\ComposerWorkshop\DownloadAsPdf;

if (!defined('ABSPATH')) {
    return;
}

require_once __DIR__ . '/inc/functions.php';

/**
 * Checks minimum required PHP version.
 *
 * @param null $currentVersion
 */
function checkPhp($currentVersion)
{

    $requiredVer = '5.4';
    if (version_compare($currentVersion, $requiredVer, '<')) {
        loadLocale();
        // translators: %s is the minimum PHP version required.
        $message = __('"Download as PDF" requires PHP %s minimum.', 'gmazzap_as_pdf');

        throw new \RuntimeException(sprintf($message, $requiredVer));
    }
}

/**
 * Check if the plugin should be disabled.
 *
 * @return array Two-items array where 1st item is the reason, 2nd item is a boolean that is true
 *               in case reason was stored in user meta and false if it was stored in a transient.
 */
function shouldDisablePlugin()
{

    $current_user = get_current_user_id();
    $basename = plugin_basename(__FILE__);

    $disableReason = get_user_meta($current_user, $basename, true);
    if ($disableReason) {
        delete_user_meta($current_user, $basename);

        return [$disableReason, true];
    }

    $disableReason = get_transient("{$basename}-disabled");
    if ($disableReason) {
        delete_transient("{$basename}-disabled");

        return [$disableReason, false];
    }

    return ['', null];
}

/**
 * Runs on plugin activation, checking for requirements.
 */
function onActivation()
{

    try {

        checkPhp(PHP_VERSION);

    } catch (\RuntimeException $exception) {

        // For WP CLI activations we disable plugin right away.
        // And use WP CLI methods to output error message.
        if (defined('WP_CLI')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
            deactivate_plugins(__FILE__);
            loadLocale();
            $message = __('"Download as PDF" activation failed.', 'gmazzap_as_pdf');
            $message .= ' ' . $exception->getMessage();
            \WP_CLI::error(\WP_CLI::colorize("%r{$message}%n"));
        }

        // For regular activations we store an user meta so we can deactivating "gracefully"
        // later, showing a notice to the user who activated the plugin.
        update_user_meta(
            get_current_user_id(),
            plugin_basename(__FILE__),
            $exception->getMessage()
        );
    }
}

register_activation_hook(__FILE__, __NAMESPACE__ . '\\onActivation');

// In backend plugin does nothing, so we just check activation then return.
if (is_admin()) {

    add_action('init', function () {

        // To make sure `is_plugin_active_for_network` and `deactivate_plugins` are available
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        $hook = 'admin_notices';
        if (is_multisite()) {
            is_plugin_active_for_network(__FILE__) and $hook = 'network_admin_notices';
        }

        add_action($hook, function () {

            // When notice is stored in user meta only the user who activated plugin can see it,
            // no need to check capabilities.
            list($disableReason, $onlyForAdmins) = shouldDisablePlugin();

            if ($disableReason) {
                deactivate_plugins(__FILE__);
                disablePluginNotice($disableReason, $onlyForAdmins);
                // This prevents the "Plugin updated." notice from WP.
                unset($_GET['activate']);
            }
        });
    });

    return;
}

// In frontend, we check again the installation, then bootstrap plugin logic.
add_action('wp', function () {

    try {
        checkPhp(PHP_VERSION);

        add_action('template_redirect', __NAMESPACE__ . '\\streamPdf');
        add_action('the_post', __NAMESPACE__ . '\\appendDownloadUrl');

    } catch (\RuntimeException $exception) {
        // On frontend there's no user, so we store reason for disabling in a transient.
        // Only administrators will see the notice, though.
        set_transient(plugin_basename(__FILE__) . '-disabled', $exception->getMessage());
    }
});
