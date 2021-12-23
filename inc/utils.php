<?php

/**
 * Check if a cached file exists, or create one with a value if it doesn't.
 * 
 * @param string $key
 * @param string $value (optional)
 * 
 * @return string|false
 */
function block_json_parser_cache_static($key, $value = null) {
	$blocks_path = apply_filters('block_json_parser_blocks_path', '/src/blocks'); // Filter for changing the default Block_Parser path.
	$cache_path = get_template_directory() . $blocks_path . '/.cache';

	if (!file_exists($cache_path)) {
		mkdir($cache_path, 0777, true);
	}

	if (strpos($key, '.') !== false) {
		$cache_file = $cache_path . '/' . $key;
	} else {
		$cache_file = $cache_path . '/' . $key . '.txt';
	}

	if (file_exists($cache_file)) {
		$file_contents = file_get_contents($cache_file);
		$first_line = substr($file_contents, 0, strpos($file_contents, PHP_EOL));
		preg_match('/<!--(.*)-->/', $first_line, $cache_data);
		$unserialized_cache_data = unserialize($cache_data[1]);
		if ($unserialized_cache_data['key'] === $key) {
			return substr($file_contents, strpos($file_contents, PHP_EOL) + 1);
		}
	}

	if ($value === null) {
		return false;
	}

	$cache_data = array(
		'key' => $key,
		'time' => time()
	);

	$file_contents = sprintf("<!--%s-->\n", serialize($cache_data));
	$file_contents .= $value;
	file_put_contents($cache_file, $file_contents);

	return $file_contents;
}
