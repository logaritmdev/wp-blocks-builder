<?php

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once __DIR__ . '/../Block.php';
require_once __DIR__ . '/../Layout.php';
require_once __DIR__ . '/page-walker.php';

$_block_types_cache = null;

/**
 * @function wpbb_json
 * @since 1.0.0
 */
function wpbb_json($file)
{
	$json = json_decode(file_get_contents($file), true);

	if ($json == null) {
		throw new Exception("$file contains invalid JSON.");
	}

	return $json;
}

/**
 * Returns an array that contains all templates path.
 * @function wpbb_get_block_paths
 * @since 1.0.0
 */
function wpbb_get_block_paths()
{
	return apply_filters('wpbb/block_template_paths', array(WP_CONTENT_DIR . '/plugins/wp-blocks-builder/blocks', get_template_directory() . '/blocks'));
}

/**
 * Returns an array that contains data about all available templates.
 * @function wpbb_get_block_types
 * @since 1.0.0
 */
function wpbb_get_block_types()
{
	global $_block_types_cache;

	if ($_block_types_cache == null) {
		$_block_types_cache = array();

		foreach (wpbb_get_block_paths() as $path) {

			foreach (glob($path . '/*' , GLOB_ONLYDIR) as $path) {

				$type = str_replace(WP_CONTENT_DIR, '', $path);

				$data = wpbb_json($path . '/block.json');
				$data['category'] = isset($data['category']) ? $data['category'] : 'Uncategorized';
				$data['fields'] = isset($data['fields']) ? $data['fields'] : array();
				$data['buid'] = $type;
				$data['path'] = $path;

				if (wpbb_user_has_access($data) == false) {
					continue;
				}

				foreach (glob($path . '/fields/*.json') as $file) {
					$data['fields'][] = wpbb_json($file);
				}

				$_block_types_cache[] = $data;
			}
		}

		usort($_block_types_cache, function($a, $b) {
			return strcmp($a['name'], $b['name']);
		});

		$_block_types_cache = apply_filters('wpbb/block_types', $_block_types_cache);
	}

	return $_block_types_cache;
}

/**
 * Returns the block template data using a block unique identifier. This
 * identifier is made from the block path relative to the app directory.
 * @function wpbb_get_block_type_by_buid
 * @since 1.0.0
 */
function wpbb_get_block_type_by_buid($buid)
{
	static $block_types = null;

	if ($block_types == null) {
		$block_types = wpbb_get_block_types();
	}

	foreach ($block_types as $block_template_info) {
		if ($block_template_info['buid'] == $buid) return $block_template_info;
	}

	return null;
}

/**
 * @function wpbb_get_content_types
 * @since 1.0.0
 */
function wpbb_get_content_types()
{
	return apply_filters('wpbb/content_types', array('page'));
}

/**
 * @function wpbb_get_blocks
 * @since 1.0.0
 */
function wpbb_get_blocks($stack_id)
{
	return get_post_meta($stack_id, '_wpbb_blocks', true);
}

/**
 * @function wpbb_get_block
 * @since 1.0.0
 */
function wpbb_get_block($buid, $block_id, $stack_id)
{
	$block_type = wpbb_get_block_type_by_buid($buid);

	if ($block_type == null) {
		return null;
	}

	$class_file = isset($block_type['class_file']) ? $block_type['class_file'] : null;
	$class_name = isset($block_type['class_name']) ? $block_type['class_name'] : null;
	require_once $block_type['path'] . '/' . $class_file;

	return new $class_name($block_id, $stack_id, $block_type);
}

/**
 * @function wpbb_render_block_space
 * @since 1.0.0
 */
function wpbb_render_block_space($space_id)
{
	$block = Block::get_current();

	if ($block == null) {
		return;
	}

	$block_id = $block->get_block_id();
	$stack_id = $block->get_stack_id();

	echo '<ul class="blocks" data-space-id="' . $space_id . '">';

	$blocks = apply_filters('wpbb/sub_blocks', wpbb_get_blocks($stack_id), $block);

	if ($blocks) {

		foreach ($blocks as $block) {

			if (!isset($block['buid']) ||
				!isset($block['block_id']) ||
				!isset($block['stack_id']) ||
				!isset($block['space_id'])) {
				continue;
			}

			$disable = isset($block['disable']);

			if ($block['super_id'] == $block_id &&
				$block['space_id'] == $space_id) {
				wpbb_render_block_preview(
			 		$block['buid'],
			 		$block['block_id'],
			 		$block['stack_id'], array(
			 			'disable' => $disable,
			 			'super_id' => $block_id,
			 			'space_id' => $space_id
			 		)
				);
			}
		}
	}

	echo '</ul>';

	echo '<div class="button block-add-button" data-space-id="' . $space_id . '">Add block</div>';
}

/**
 * @function wpbb_render_block_outline
 * @since 1.0.0
 */
function wpbb_render_block_outline($buid)
{
	wpbb_get_block($buid, 0, 0)->render_outline();
}

/**
 * @function wpbb_render_block_preview
 * @since 1.0.0
 */
function wpbb_render_block_preview($buid, $block_id, $stack_id, $options = array())
{
	wpbb_get_block($buid, $block_id, $stack_id)->render_preview($options);
}

/**
 * @function wpbb_render_block_template
 * @since 1.0.0
 */
function wpbb_render_block_template($buid, $block_id, $stack_id)
{
	wpbb_get_block($buid, $block_id, $stack_id)->render_template();
}

/**
 * @function wpbb_render_sub_blocks
 * @since 1.0.0
 */
function wpbb_render_sub_blocks($space_id)
{
	Block::get_current()->render_children($space_id);
}

/**
 * @function wpbb_render_block_attr
 * @since 1.0.0
 */
function wpbb_render_block_attr($post, $base, $classes = array()) {

	$more = [];

	foreach ($classes as $class => $value) if ($value) {
		$more[] = $class;
	}

	return strtr('id="post-{id}" class="{base} {more}"', array('{id}' => $post->ID, '{base}' => $base, '{more}' => implode(' ', array_unique($more))));
}

/**
 * Returns whether the user has access to a specified block for admin editing.
 * @function wpbb_user_has_access
 * @since 1.0.0
 */
function wpbb_user_has_access($page_block) {

	$role = isset($page_block['role']) ? $page_block['role'] : null;

	if ($role == null) {
		return true;
	}

	$trim = function($str) {
		return trim($str);
	};

	$role = array_map($trim, explode(',', $role));

	return count(array_intersect($role, wp_get_current_user()->roles)) > 0;
}

//------------------------------------------------------------------------------
// Twig Filters
//------------------------------------------------------------------------------

if (class_exists('TimberHelper')) {
	TimberHelper::function_wrapper('wpbb_render_block_outline');
	TimberHelper::function_wrapper('wpbb_render_block_preview');
	TimberHelper::function_wrapper('wpbb_render_block_template');
	TimberHelper::function_wrapper('wpbb_render_sub_blocks');
	TimberHelper::function_wrapper('wpbb_render_block_space');
	TimberHelper::function_wrapper('wpbb_render_block_attr');
}
