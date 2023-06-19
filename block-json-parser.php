<?php

/**
 * Plugin Name:       Block Json Parser
 * Plugin URI:        https://github.com/designcontainer/block-json-parser
 * Description:       Manage Gutenberg blocks with json files.
 * Version:           1.1.4
 * Author:            Design Container
 * Author URI:        https://designcontainer.no
 * Text Domain:       block-json-parser
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
if (!defined('BLOCK_JSON_PARSER')) {
    define('BLOCK_JSON_PARSER', '1.1.4');
}

/**
 * The Block Parser class.
 */
require plugin_dir_path(__FILE__) . 'inc/class-block-parser.php';

/**
 * Utility functions.
 */
require plugin_dir_path(__FILE__) . 'inc/utils.php';

/**
 * Begins execution of the plugin.
 *
 * @since 1.0.0
 */
function run_block_json_parser() {
    $blocks_path = apply_filters('block_json_parser_blocks_path', '/src/blocks'); // Filter for changing the default Block_Parser path.
    $secondary_blocks_paths = [];

    $has_blocks_in_child_theme = apply_filters( 'block_json_parser_has_blocks_in_child_theme', false );
    if (!empty($has_blocks_in_child_theme)) {
        $primary_blocks_path = get_stylesheet_directory() . $blocks_path;
        $secondary_blocks_paths[] = get_template_directory() . $blocks_path;
    } else {
        $primary_blocks_path = get_template_directory() . $blocks_path;
    }
    
    $block_parser = new Block_Parser($primary_blocks_path, $secondary_blocks_paths);
    $block_parser->run();
}
add_action('acf/init', 'run_block_json_parser');
