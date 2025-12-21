<?php
/**
 * Register the gatherpress-attendee-count module to altis
 *
 * @package           figuren-theater/gatherpress-attendee-count
 * @author            figuren.theater
 * @copyright         2023 figuren.theater
 * @license           GPL-3.0+
 *
 * @wordpress-plugin
 * Plugin Name:       figuren.theater | gatherpress_attendee_count
 * Plugin URI:        https://github.com/figuren-theater/gatherpress-attendee-count
 * Description:       ... like the figuren.theater WordPress Multisite network.
 * Version:           0.1.0-alpha
 * Requires at least: 6.0
 * Requires PHP:      7.1
 * Author:            figuren.theater
 * Author URI:        https://figuren.theater
 * Text Domain:       figurentheater
 * Domain Path:       /languages
 * License:           GPL-3.0+
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 * Update URI:        https://github.com/figuren-theater/gatherpress-attendee-count
 */

namespace Figuren_Theater\gatherpress_attendee_count;

const DIRECTORY = __DIR__;

add_action( 'altis.modules.init', __NAMESPACE__ . '\\register' );
