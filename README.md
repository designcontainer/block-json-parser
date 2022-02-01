# Block Json Parser

Manage Gutenberg blocks with json files.

### Example directory structure

```bash
src/
├─ blocks/
│  ├─ blocks.json
│  ├─ slider/
│  │  ├─ assets/
│  │  │  ├─ slider.scss/
│  │  │  ├─ slider.js
│  │  ├─ block.json
│  │  ├─ template.php
```

### Use block.json

In your block's directory, create a `block.json` file. \
The json file accepts all the same arguments [`acf_register_block_type()`](https://www.advancedcustomfields.com/resources/acf_register_block_type/) does, as well as:

-   `parser_version` (required)
-   `includedPostTypes` (array)
-   `excludedPostTypes` (array)

Every directory with a `block.json` file will automatically register a block. \
If a `[BLOCK-NAME].css` or `[BLOCK-NAME].js` file exists in blocks specified assets dist directory, the asset will automatically be enqueued.

Here is an example of a `block.json` file would look:

```json
{
	"parser_version": 1,
	"name": "image-slider",
	"title": "Image slider",
	"icon": "slides",
	"includedPostTypes": ["page", "post"]
}
```

By not specifying `includedPostTypes` and `excludedPostTypes`, your block will be included and shown for all post types by default.

### Use blocks.json

In your blocks directory (the directory where all your blocks live), create a `blocks.json` file. \
The json file accepts the following:

-   `parser_version` (integer, required)
-   `allowedBlocks` (object or arrays)
-   `debug` (boolean)

This is the file responsible for handling vendor blocks per post type.

Here is an example of a `blocks.json` file would look:

```json
{
	"parser_version": 1,
	"allowedBlocks": {
		"page": {
			"cookiebot": ["cookie-declaration"],
			"core": "all"
		},
		"post": {
			"core": ["paragraph", "heading", "list", "quote", "image"]
		},
		"all": {
			"core": ["paragraph", "heading", "image"]
		}
	}
}
```

You can specify an `all` object to allow certain blocks for all post types. \
If a rule is more specific than the other, that rule will override the original rule set.
By not specifying a post type or `all`, your post type will have all block types allowed.

### Filters

#### Change the default Block_Parser path.

```php
add_filter('block_json_parser_blocks_path', function() {
    return '/src/blocks'; // Starts from theme root.
});
```

#### Change the default block css dist path.

```php
add_filter('block_json_parser_css_dist_path', function() {
    return '/dist/css/blocks/frontend'; // Starts from theme root.
});
```

#### Change the default block js dist path.

```php
add_filter('block_json_parser_js_dist_path', function() {
    return '/dist/js/blocks'; // Starts from theme root.
});
```

#### Modify default block args.

```php
// Example of setting a default block category for blocks.
add_filter('block_json_parser_block_defaults', function($args) {
    $args['category'] = 'your_category';

    return $args;
});
```

#### Modify block args.

```php
// Example of handling block icons with a custom function.
add_filter('block_json_parser_block_args', function($args) {
    if ( isset( $args['icon'] ) && function_exists('material_icon') ) :
        $args['icon'] = material_icon($args['icon']);
    endif;

    return $args;
});
```

### Functions

#### `block_json_parser_cache_static()`

Utility function for caching static data, such as icons.
Usage example:

```php
add_filter('block_json_parser_block_args', function ($args) {
    if (isset($args['icon']) && function_exists('material_icon')) {
        // This acts as the file name for the cached data
        $key = $args['icon'] . '.svg';

	if ($cached_icon = block_json_parser_cache_static($key)) {
            $args['icon'] =  $cached_icon;
        } else {
            $icon_data = material_icon($args['icon']);
            // This will cache the content and return the data back
            $args['icon'] = block_json_parser_cache_static($key, $icon_data);
        }
    }
	
    return $args;
});
```
