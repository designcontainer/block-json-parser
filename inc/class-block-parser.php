<?php

/**
 * Register ACF blocks and manage Gutenberg blocks with json files.
 * This class works as a parser for blocks.json and block.json files.
 */
class Block_Parser {

	/**
	 * Absolute path to the blocks directory.
	 *
	 * @var string
	 */
	public $blocks_path;

	public function __construct($blocks_path) {
		$this->blocks_path = $blocks_path;
		$this->allowed_blocks_per_post_type = (object)[];

		if (file_exists($this->blocks_path . '/blocks.json')) :
			$this->blocks_json = json_decode(file_get_contents($this->blocks_path . '/blocks.json'));
		else :
			$this->blocks_json = (object)[];
		endif;
	}

	/**
	 * Run the parser.
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function run() {
		if (is_admin() || isset($this->blocks_json->debug) && $this->blocks_json->debug === true) :
			add_action('init', array($this, 'parse'), 99);
		else :
			$this->parse();
		endif;
	}

	/**
	 * Runs the parser
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function parse() {
		$blocks = $this->get_blocks();

		// Get global blocks settings
		if (is_admin() || isset($this->blocks_json->debug) && $this->blocks_json->debug === true) : // No need to run this outside the admin panel / debug mode.
			$this->allowed_blocks_per_post_type = $this->get_globally_allowed_blocks_per_post_type_and_domain($this->blocks_json);
		endif;

		foreach ($blocks as $block) :
			// Read and validate json.
			$block_json = json_decode(file_get_contents($block . '/block.json'));
			if ($this->validate_block_data($block_json, $block) === false) continue;
			unset($block_json->parser_version);

			// Handle allowed post types.
			if (is_admin() || isset($this->blocks_json->debug) && $this->blocks_json->debug === true) : // No need to run this outside the admin panel / debug mode.
				$allowed_post_types_for_block = $this->get_allowed_post_types_for_block($block_json);
				$this->set_allowed_blocks_per_post_type($block_json, $allowed_post_types_for_block);
			endif;

			// Register the block.
			$this->unset_custom_objects($block_json);
			$this->register_block($block_json, $block);
		endforeach;

		// Debugging
		if (isset($this->blocks_json->debug) && $this->blocks_json->debug === true) :
			$this->handle_debugging();
		endif;

		// Filter the allowed block types.
		add_filter('allowed_block_types_all', array($this, 'modify_allowed_post_types'), 10, 2);
	}

	/**
	 * Unset custom non-acf objects.
	 * 
	 * @since 1.0.0
	 * @param object $blocks_json
	 * @return void
	 */
	private function unset_custom_objects($block_json) {
		unset(
			$block_json->excludedPostTypes,
			$block_json->includedPostTypes,
			$block_json->debug
		);
	}

	/**
	 * Some output for quickly debugging stuff.
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	protected function handle_debugging() {
		$all_blocks = array_keys(WP_Block_Type_Registry::get_instance()->get_all_registered());
		sort($all_blocks);
		$this->console_log('All blocks:', $all_blocks);
		$this->console_log('Blocks per post type:', $this->allowed_blocks_per_post_type);
	}

	/**
	 * Print a console log script.
	 * 
	 * @since 1.0.0
	 * @param string $title
	 * @param string $arg
	 * @return void
	 */
	protected function console_log($title, $arg) {
		$title = json_encode($title, JSON_PRETTY_PRINT);
		$arg = json_encode($arg, JSON_PRETTY_PRINT);
		printf('<script>console.log(%s, %s)</script>', $title, $arg);
	}

	/**
	 * Set allowed blocks per post type, based on what a block has set as allowed post types.
	 * 
	 * @since 1.0.0
	 * @param object $block_json
	 * @param array $allowed_post_types
	 * @return void
	 */
	protected function set_allowed_blocks_per_post_type($block_json, $allowed_post_types) {
		foreach ($allowed_post_types as $post_type) :
			$this->allowed_blocks_per_post_type->$post_type[] = 'acf/' . $block_json->name;
		endforeach;
	}

	/**
	 * Make sure all required object keys are in the block.json file.
	 *
	 * @since 1.0.0
	 * @param object $block_json
	 * @param string $block
	 * @return boolean
	 */
	public function validate_block_data($block_json, $block) {
		$mandatory = ['parser_version', 'name'];
		foreach ($mandatory as $obj) :
			if (!isset($block_json->$obj)) :
				trigger_error(sprintf('%s is not set in %s/block.json', $obj, $block), E_USER_NOTICE);
				return false;
			endif;
		endforeach;

		return true;
	}

	/**
	 * Get globally allowed blocks per post type and domain.
	 * Used for the global blocks.json file.
	 * 
	 * @since 1.0.0
	 * @param object $blocks_json
	 * @return object
	 */
	protected function get_globally_allowed_blocks_per_post_type_and_domain($blocks_json) {
		// Setup initial post type block arrays
		$allowed_blocks_per_post_type = (object)[];
		foreach ($this->get_all_post_types() as $post_type) :
			// Create array
			$allowed_blocks_per_post_type->$post_type = [];
			// If an all object is set, we will get the intial blocks from there
			if (isset($blocks_json->allowedBlocks->all) && !isset($blocks_json->allowedBlocks->$post_type)) :
				foreach ($blocks_json->allowedBlocks->all as $domain => $blocks) :
					$allowed_blocks_per_post_type->$post_type = array_merge($allowed_blocks_per_post_type->$post_type, $this->get_blocks_per_domain($domain, $blocks));
				endforeach;
			endif;
		endforeach;

		if (!isset($blocks_json->allowedBlocks)) :
			// If allowedBlocks is no set, we will get all blocks and allow it for that post type.
			foreach ($allowed_blocks_per_post_type as $post_type => $domains) :
				if (!isset($blocks_json->allowedBlocks->$post_type) && !isset($blocks_json->allowedBlocks->all)) :
					$allowed_blocks_per_post_type->$post_type = array_keys(WP_Block_Type_Registry::get_instance()->get_all_registered());
					continue;
				endif;
			endforeach;
		else :
			// Loop through all post types to check for an existing object in included Blocks.
			// If it does not exist and an all object is not set, get all available blocks.
			$allowed_blocks_per_post_type->all = [];
			foreach ($blocks_json->allowedBlocks as $post_type => $domains) :
				foreach ($blocks_json->allowedBlocks->$post_type as $domain => $blocks) :
					if (
						! isset( $allowed_blocks_per_post_type->$post_type ) || 
						$allowed_blocks_per_post_type->$post_type === null
					) continue;
					$allowed_blocks_per_post_type->$post_type = array_merge($allowed_blocks_per_post_type->$post_type, $this->get_blocks_per_domain($domain, $blocks));
				endforeach;
			endforeach;
			unset($allowed_blocks_per_post_type->all);
		endif;

		// Remove duplicates
		foreach ($allowed_blocks_per_post_type as $post_type => $blocks) :
			$allowed_blocks_per_post_type->$post_type = array_unique($blocks);
		endforeach;

		return $allowed_blocks_per_post_type;
	}

	/**
	 * Create an array with blocks from object key and block names in an array.
	 * 
	 * @since 1.0.0
	 * @param string $domain
	 * @param array|string $blocks
	 * @return array
	 */
	private function get_blocks_per_domain($domain, $blocks) {
		$allowed_blocks = [];
		// If the blocks data is a string of all, get all blocks within the domain.
		if (!is_array($blocks) && $blocks == "all") :
			$all_block_types = array_keys(WP_Block_Type_Registry::get_instance()->get_all_registered());
			foreach ($all_block_types as $key => $block) :
				if (!preg_match('%\b(' . $domain . ')/\\b%i', $block)) :
					unset($all_block_types[$key]);
				endif;
			endforeach;
			$allowed_blocks = $all_block_types;
		else :
			foreach ($blocks as $block) :
				$allowed_blocks[] = $domain . '/' . $block;
			endforeach;
		endif;
		return $allowed_blocks;
	}

	/**
	 * Get the allowed post types for block.
	 *
	 * @since 1.0.0
	 * @param object $block_json
	 * @return array
	 */
	private function get_allowed_post_types_for_block($block_json) {
		if (isset($block_json->includedPostTypes)) {
			$allowed_post_types = $block_json->includedPostTypes;
		} else {
			// If obj includedPostTypes is not set, we will get all available post types.
			$allowed_post_types = $this->get_all_post_types();
		}

		// If obj excludedPostTypes is set, remove all the listed post types from the post types array.
		if (isset($block_json->excludedPostTypes)) :
			$allowed_post_types = array_diff($allowed_post_types, $block_json->excludedPostTypes);
		endif;

		return $allowed_post_types;
	}

	/**
	 * Get all post types.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	private function get_all_post_types() {
		$args = array('public' => true);
		$post_types = get_post_types($args, 'names', 'and');
		// Remove the native WordPress post types from the array.
		$ignored_wp_post_types = array('attachment');
		return array_diff($post_types, $ignored_wp_post_types);
	}

	/**
	 * Modify allowed post types based on the allowed_blocks_per_post_type object.
	 * 
	 * @since 1.0.0
	 * @param array $allowed_blocks
	 * @param object $post
	 * @return array
	 */
	public function modify_allowed_post_types($allowed_blocks, $post) {
		if (!is_admin()) return;
		$post_type = $post->post->post_type;
		return $this->allowed_blocks_per_post_type->$post_type;
	}

	/**
	 * Get all blocks in the defined blocks path.
	 *
	 * @since 1.0.0
	 * @return array The path to the blocks
	 */
	private function get_blocks() {
		$blocks = [];
		$folders = scandir($this->blocks_path);
		foreach ($folders as $folder) :
			$block_path =  $this->blocks_path . '/' . $folder;
			// Check if block.json exists in folder.
			if (!file_exists($block_path . '/block.json')) continue;
			$blocks[] = $block_path;
		endforeach;

		return $blocks;
	}

	/**
	 * Register a block with the acf_register_block_type function.
	 * 
	 * @since 1.0.0
	 * @param object $block_json
	 * @param string $block
	 * @return void
	 */
	private function register_block($block_args, $block) {
		if (!function_exists('acf_register_block_type')) :
			throw new ErrorException('acf_register_block_type is not defined!');
		endif;

		// Set slug.
		$slug = basename($block);

		// Capitalize, trim, replace dashes and underscores with spaces.
		$name = ucfirst(trim(preg_replace('/[\-_]/', ' ', $block_args->name)));

		// Set default args.
		$default_args = [
			'name'              => $slug,
			'title'             => $name,
			'description'       => sprintf('%s Gutenberg block', $name),
			'render_template'   => sprintf('%s/template.php', $block),
			'multiple'			=> true,
		];
		// Apply filters to default args.
		$default_args = apply_filters('block_json_parser_block_defaults', $default_args);

		// Enqueue block styles if they exist.
		$css_dist_path = apply_filters('block_json_parser_css_dist_path', '/dist/css/blocks/frontend');
		if (file_exists(get_template_directory() . $css_dist_path . '/' . $slug . '.css')) {
			$default_args['enqueue_style'] = get_template_directory_uri() . $css_dist_path . '/' . $slug . '.css';
		}

		// Enqueue block scripts if they exist.
		$js_dist_path = apply_filters('block_json_parser_js_dist_path', '/dist/js/blocks');
		if (file_exists(get_template_directory() . $js_dist_path . '/' . $slug . '.js')) {
			$default_args['enqueue_script'] = get_template_directory_uri() . $js_dist_path . '/' . $slug . '.js';
		}

		// Merge new block args on top of default args.
		$acf_args = array_merge((array) $default_args, (array) $block_args);

		// Apply filters to acf args.
		$acf_args = apply_filters('block_json_parser_block_args', $acf_args);

		// Register block.
		acf_register_block_type($acf_args);
	}
}
