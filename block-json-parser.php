<?php
/**
 * Plugin Name:       Block Json Parser
 * Plugin URI:        https://github.com/designcontainer/block-json-parser
 * Description:       Manage Gutenberg blocks with json files.
 * Version:           1.0.0
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
    define('BLOCK_JSON_PARSER', '1.0.0');
}

/**
 * The Block Parser class.
 */
require plugin_dir_path(__FILE__) . 'inc/class-block-parser.php';

/**
 * Begins execution of the plugin.
 *
 * @since 1.0.0
 */
function run_block_json_parser() {
    $blocks_path = '/src/blocks';
    $blocks_path = apply_filters('block_json_parser_blocks_path', $blocks_path); // Filter for changing the default Block_Parser path.
    $block_parser = new Block_Parser(get_template_directory() . $blocks_path);
    $block_parser->run();
}
run_block_json_parser();



