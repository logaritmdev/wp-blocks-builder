<?php
/*
Plugin Name: WP Blocks Builder
Plugin URI: http://logaritm.ca
Description: Creates pages using multiple blocks.
Version: 1.0.0
Author: Jean-Philippe Dery (jean-philippe.dery@logaritm.ca)
Author URI: http://logaritm.ca
License: MIT
Copyright: Jean-Philippe Dery
Mention: JBLP (jblp.ca)
*/

require_once ABSPATH . 'wp-admin/includes/file.php';

define('WP_BLOCKS_BUILDER_VERSION', '1.0.1');
define('WP_BLOCKS_BUILDER_PLUGIN_URL', plugins_url('/', __FILE__));

require_once __DIR__ . '/Block.php';
require_once __DIR__ . '/Layout.php';
require_once __DIR__ . '/lib/functions.php';

//------------------------------------------------------------------------------
// Post Types
//------------------------------------------------------------------------------

$labels = array(
	'name'               => _x('Page Blocks', 'post type general name', 'your-plugin-textdomain' ),
	'singular_name'      => _x('Page Block', 'post type singular name', 'your-plugin-textdomain' ),
	'menu_name'          => _x('Page Blocks', 'admin menu', 'your-plugin-textdomain' ),
	'name_admin_bar'     => _x('Page Block', 'add new on admin bar', 'your-plugin-textdomain' ),
	'add_new'            => _x('Add New', 'book', 'your-plugin-textdomain' ),
	'add_new_item'       => __('Add New Page Block', 'your-plugin-textdomain' ),
	'new_item'           => __('New Page Block', 'your-plugin-textdomain' ),
	'edit_item'          => __('Edit Page Block', 'your-plugin-textdomain' ),
	'view_item'          => __('View Page Block', 'your-plugin-textdomain' ),
	'all_items'          => __('All Page Blocks', 'your-plugin-textdomain' ),
	'search_items'       => __('Search Page Blocks', 'your-plugin-textdomain' ),
	'parent_item_colon'  => __('Parent Page Blocks:', 'your-plugin-textdomain' ),
	'not_found'          => __('No page blocks found.', 'your-plugin-textdomain' ),
	'not_found_in_trash' => __('No page block found in Trash.', 'your-plugin-textdomain' )
);

register_post_type('wpbb-block', array(
	'labels'             => $labels,
	'description'        => '',
	'public'             => false,
	'publicly_queryable' => false,
	'show_ui'            => true,
	'show_in_menu'       => false,
	'query_var'          => false,
	'rewrite'            => false,
	'capability_type'    => 'post',
	'has_archive'        => false,
	'hierarchical'       => false,
	'menu_position'      => null,
	'supports'           => array('revisions')
));

//------------------------------------------------------------------------------
// Actions
//------------------------------------------------------------------------------

/**
 * @activation-hook
 * @since 1.0.0
 */
register_activation_hook(__FILE__, function() {

	global $acf;

	if (is_plugin_active('timber-library/timber.php') === false ||
		version_compare(Timber::$version, '1.0.0', '<')) {
		echo 'Timber-Library version 1.0.0 or higher is required. <br> See https://wordpress.org/plugins/timber-library/';
	}

	if (is_plugin_active('advanced-custom-fields-pro/acf.php') === false ||
		version_compare($acf->settings['version'], '5.4.0', '<')) {
		echo 'Advanced Custom Fields version 5.4.0 or higher is required. <br> See https://wordpress.org/plugins/advanced-custom-fields/';
	}
});

/**
 * @action init
 * @since 1.0.0
 */
add_action('init', function() {

	if (class_exists('Timber')) {
		Timber::$locations = array(__DIR__ . '/templates/');
	}

});

/**
 * @action admin_init
 * @since 1.0.0
 */
add_action('admin_init', function() {

	/**
	 * Adds a metabox on the block edit page used to store the block id and page
	 * it was added to. This metabox is hidden.
	 * @since 1.0.0
	 */
	add_meta_box('wpbb_disabled_editor', 'Page', function() {

		echo 'The post editor has been disabled because this page contains blocks.';

	}, 'page', 'normal', 'high');

	foreach (wpbb_get_content_types() as $post_type) {

		$title = apply_filters('wpbb/metabox_title', 'Blocks', $post_type);
		$priority = apply_filters('wpbb/metabox_priority', 'low', $post_type);

		add_meta_box('wpbb_metabox', $title, function() {

			$block_types = wpbb_get_block_types();
			$block_paths = wpbb_get_block_paths();

			$filter = function($block) {
				return wpbb_get_block_type_by_buid($block['buid']);
			};

			$blocks = wpbb_get_blocks(get_the_id());

			if ($blocks) {
				$blocks = array_filter($blocks, $filter);
			}

			$categories = array();

			foreach ($block_types as $block_type) {
				$categories[$block_type['category']] = array();
			}

			foreach ($block_types as $block_type) {
				$categories[$block_type['category']][] = $block_type;
			}

			ksort($categories);

			$items = get_posts(array(
				'posts_per_page'   => -1,
				'offset'           => 0,
				'post_type'        => get_post_type(),
				'post_status'      => 'any',
				'suppress_filters' => true 
			));

			$targets = '';

			foreach ($items as $item) {
				$targets .= sprintf(
					'<li data-stack-id="%s"><a href="#">%s</a></a></li>',
					$item->ID,
					$item->post_title
				);
			}

			$data = Timber::get_context();
			$data['blocks'] = $blocks;
			$data['targets'] = $targets;
			$data['categories'] = $categories;
			$data['block_types'] = $block_types;
			$data['block_paths'] = $block_paths;
			Timber::render('block-metabox.twig', $data);

		}, $post_type, 'normal', $priority);

		add_filter('postbox_classes_' . $post_type . '_wpbb_metabox', function($classes = array()) {
			$classes[] = 'wpbb-postbox';
			$classes[] = 'seamless';
			return $classes;
		});
	}

});

/**
 * Adds a special class to disable the post box if there is blocks on that page
 * @action admin_body_class
 * @since 1.1.0
 */
add_filter('admin_body_class', function($classes) {

	global $post;

	if ($post) {

		$blocks_data = wpbb_get_blocks($post->ID);

		if ($blocks_data) {

			foreach ($blocks_data as $block) {

				if (!isset($block['buid']) ||
					!isset($block['block_id']) ||
					!isset($block['stack_id']) ||
					!isset($block['space_id'])) {
					continue;
				}

				return $classes . ' ' . 'wp-blocks-builder-post-editor-disabled';
			}
		}
	}

	return $classes;

});

/**
 * Adds the required CSS and JavaScript to the admin page.
 * @action admin_enqueue_scripts
 * @since 1.0.0
 */
add_action('admin_enqueue_scripts', function() {

	foreach (wpbb_get_content_types() as $post_type) {
		if (get_post_type() == $post_type) {
			wp_enqueue_script('wpbb_admin_metabox_js', WP_BLOCKS_BUILDER_PLUGIN_URL . 'assets/js/block-metabox.js', false, WP_BLOCKS_BUILDER_VERSION);
			wp_enqueue_style('wpbb_admin_metabox_css', WP_BLOCKS_BUILDER_PLUGIN_URL . 'assets/css/block-metabox.css', false, WP_BLOCKS_BUILDER_VERSION);
			wp_enqueue_style('wpbb_admin_metabox_grid_css', WP_BLOCKS_BUILDER_PLUGIN_URL . 'assets/css/admin-grid.css', false, WP_BLOCKS_BUILDER_VERSION);
		}
	}

	if (get_post_type() == 'wpbb-block') {
		wp_enqueue_script('wpbb_admin_editor_js', WP_BLOCKS_BUILDER_PLUGIN_URL . 'assets/js/block-edit.js', false, WP_BLOCKS_BUILDER_VERSION);
		wp_enqueue_style('wpbb_admin_editor_css', WP_BLOCKS_BUILDER_PLUGIN_URL . 'assets/css/block-edit.css', false, WP_BLOCKS_BUILDER_VERSION);
	}

	if (is_readable(get_template_directory() . '/editor-style-shared.css')) {
		wp_enqueue_style('wpbb_admin_block_css', get_template_directory_uri() . '/editor-style-shared.css', false, WP_BLOCKS_BUILDER_VERSION);
	}

});

/**
 * Moves the submit div to the bottom of the block post type page.
 * @action add_meta_boxes_block
 * @since 1.0.0
 */
add_action('add_meta_boxes_wpbb-block', function() {
	remove_meta_box('submitdiv', 'wpbb-block', 'side');
	add_meta_box('submitdiv', __('Save'), 'post_submit_meta_box', 'wpbb-block', 'normal', 'default');
}, 0, 1);

/**
 * Renames the "Publish" button to a "Save" button on the block post type page.
 * @filter gettext
 * @since 1.0.0
 */
add_filter('gettext', function($translation, $text) {

	if (get_post_type() == 'wpbb-block') {
		switch ($text) {
			case 'Publish':
				return 'Save';
		}
	}

	return $translation;

}, 10, 2);

//------------------------------------------------------------------------------
// Post
//------------------------------------------------------------------------------

/**
 * Saves the block order.
 * @action save_post
 * @since 1.0.0
 */
add_action('save_post', function($block_id, $post) {

	if (wp_is_post_revision($block_id)) {
		return;
	}

	if (get_post_type() == 'wpbb-block') {

		$revs = wp_get_post_revisions($block_id);

		// the first revision seems to be the actual post
		$rev = array_shift($revs);
		$rev = array_shift($revs);

		if ($rev) {

			$stack_id = get_post($post->post_parent)->ID;

			$blocks_data = get_post_meta($stack_id, '_wpbb_blocks', true);

			foreach ($blocks_data as &$block) {
				if ($block['block_id'] == $block_id) {
					$block['block_revision_id'] = !isset($block['block_revision_id']) || $block['block_revision_id'] == null ? $rev->ID : $block['block_revision_id'];
				}
			}

			update_post_meta($stack_id, '_wpbb_blocks', $blocks_data);
		}

		return $block_id;
	}

	foreach (wpbb_get_content_types() as $post_type) {

		if (get_post_type() == $post_type && isset($_POST['_wpbb_blocks']) && is_array($_POST['_wpbb_blocks'])) {

			$blocks_data = $_POST['_wpbb_blocks'];
			$blocks_super_id_data = $_POST['_wpbb_blocks_super_id'];
			$blocks_space_id_data = $_POST['_wpbb_blocks_space_id'];

			$blocks_old = get_post_meta(get_the_id(), '_wpbb_blocks', true);
			$blocks_new = array();

			foreach ($blocks_data as $index => $block_id) {
				foreach ($blocks_old as $block_old) {
					if ($block_old['block_id'] == $block_id) {
						$block_old['block_revision_id'] = null;
						$block_old['super_id'] = $blocks_super_id_data[$index];
						$block_old['space_id'] = $blocks_space_id_data[$index];
						$blocks_new[] = $block_old;
					}
				}
			}

			update_post_meta(get_the_id(), '_wpbb_blocks', $blocks_new);

			do_action('wpbb/save_block', get_the_id(), $blocks_new);
		}
	}

	return $block_id; // necessary ?

}, 10, 2);

/**
 * Updates
 * @action wp_restore_post_revision
 * @since 1.0.0
 */
add_action('wp_restore_post_revision', function($block_id, $revision_id) {

}, 10, 2);

/**
 * Adds a special keyword in the block post type page url that closes the page
 * when the page is saved and redirected.
 * @filter redirect_post_location
 * @since 1.0.0
 */
add_filter('redirect_post_location', function($location, $post_id) {

	switch (get_post_type()) {
		case 'wpbb-block':
			$location = $location . '#block_saved';
			break;
	}

	return $location;

}, 10, 2);

/**
 * Hides the page content and displays block instead.
 * @filter the_content
 * @since 1.0.0
 */
add_filter('the_content', function($content) {

	global $post;

	if (is_admin()) {
		return $content;
	}

	foreach (wpbb_get_content_types() as $post_type) {

		if (get_post_type() == $post_type) {

			$blocks = wpbb_get_blocks($post->ID);

			if ($blocks) {

				ob_start();

				foreach ($blocks as $block) {

					if (!isset($block['buid']) ||
						!isset($block['stack_id']) ||
						!isset($block['block_id'])) {
						continue;
					}

					if (is_preview() === false && isset($block['block_revision_id'])) {

						$rev = wp_get_post_revision($block['block_revision_id']);

						if ($rev) {
							$block['block_id'] = $rev->ID;
						}
					}

					if ($block['super_id'] == 0) wpbb_render_block_template(
						$block['buid'],
						$block['block_id'],
						$block['stack_id']
					);
				}

				$content = ob_get_contents();

				ob_end_clean();
			}
		}
	}

	return $content;

}, 20);

//------------------------------------------------------------------------------
// AJAX
//------------------------------------------------------------------------------

/**
 * Adds a block to a page.
 * @action wp_ajax_add_block
 * @since 1.0.0
 */
add_action('wp_ajax_add_block', function() {

	global $post;

	$buid = $_POST['buid'];
	$stack_id = $_POST['stack_id'];
	$super_id = $_POST['super_id'];
	$space_id = $_POST['space_id'];

	if (wpbb_get_block_type_by_buid($buid) == null) {
		return;
	}

	$block_id = wp_insert_post(array(
		'post_parent'  => $stack_id,
		'post_type'    => 'wpbb-block',
		'post_title'   => sprintf('Page %s : Block %s', $stack_id, $buid),
		'post_content' => '',
		'post_status'  => 'publish',
	));

	$blocks = wpbb_get_blocks($stack_id);
	if ($blocks == null) {
		$blocks = array();
	}

	$block = array(
		'buid' => $buid,
		'space_id' => $space_id,
		'block_id' => (int) $block_id,
		'stack_id' => (int) $stack_id,
		'super_id' => (int) $super_id,
	);

	$blocks[] = $block;

	update_post_meta($stack_id, '_wpbb_blocks', $blocks);

	$post = get_post($stack_id);

	setup_postdata($post);

	wpbb_render_block_preview($buid, $block_id, $stack_id);

	exit;
});

/**
 * Moves a block to another page.
 * @action wp_ajax_move_block
 * @since 1.0.0
 */
add_action('wp_ajax_move_block', function() {

	$source_stack_id = $_POST['source_stack_id'];
	$source_block_id = $_POST['source_block_id'];
	$target_stack_id = $_POST['target_stack_id'];

	$source_page_blocks = wpbb_get_blocks($source_stack_id);
	$target_page_blocks = wpbb_get_blocks($target_stack_id);

	if ($source_page_blocks == null) $source_page_blocks = array();
	if ($target_page_blocks == null) $target_page_blocks = array();

	foreach ($source_page_blocks as $i => $source_page_block) {

		if ($source_page_block['block_id'] == $source_block_id) {

			$source_page_block['stack_id'] = $target_stack_id;
			$source_page_block['super_id'] = 0;
			$source_page_block['space_id'] = 0;

			$target_page_blocks[  ] = $source_page_block;
			$source_page_blocks[$i] = null;

			break;
		}
	}

	$source_page_blocks = array_filter($source_page_blocks, function($item) {
		return !!$item;
	});

	update_post_meta($source_stack_id, '_wpbb_blocks', $source_page_blocks);
	update_post_meta($target_stack_id, '_wpbb_blocks', $target_page_blocks);
	exit;
});

/**
 * Copies a block to another container.
 * @action wp_ajax_copy_block
 * @since 1.0.0
 */
add_action('wp_ajax_copy_block', function() {

	global $wpdb;

	$source_stack_id = $_POST['source_stack_id'];
	$source_block_id = $_POST['source_block_id'];
	$target_stack_id = $_POST['target_stack_id'];

	$source_page_blocks = wpbb_get_blocks($source_stack_id);
	$target_page_blocks = wpbb_get_blocks($target_stack_id);

	if ($source_page_blocks == null) $source_page_blocks = array();
	if ($target_page_blocks == null) $target_page_blocks = array();

	foreach ($source_page_blocks as $i => $source_page_block) {

		if ($source_page_block['block_id'] == $source_block_id) {

			$source_page_block['stack_id'] = $target_stack_id;
			$source_page_block['super_id'] = 0;
			$source_page_block['space_id'] = 0;

			$post = get_post($source_page_block['block_id']);

			$args = array(
				'post_content'   => $post->post_content,
				'post_excerpt'   => $post->post_excerpt,
				'post_name'      => $post->post_name,
				'post_parent'    => $post->post_parent,
				'post_password'  => $post->post_password,
				'post_status'    => $post->post_status,
				'post_title'     => $post->post_title,
				'post_type'      => $post->post_type,
			);

			$target_block_id = wp_insert_post($args);

			$source_metas = $wpdb->get_results("SELECT meta_key, meta_value FROM $wpdb->postmeta WHERE post_id=$source_block_id");

			if (count($source_metas)) {

				$sql_query = "INSERT INTO $wpdb->postmeta (post_id, meta_key, meta_value) ";

				foreach ($source_metas as $source_meta) {

					$key = $source_meta->meta_key;
					$val = $source_meta->meta_value;
					$val = addslashes($val);

					$sql_query_sel[] = "SELECT $target_block_id, '$key', '$val'";
				}

				$sql_query .= implode(" UNION ALL ", $sql_query_sel);

				$wpdb->query($sql_query);
			}

			$source_page_block['block_id'] = $target_block_id;

			$target_page_blocks[] = $source_page_block;

			break;
		}
	}

	update_post_meta($target_stack_id, '_wpbb_blocks', $target_page_blocks);
	exit;
});

/**
 * Removes a block from a page.
 * @action wp_ajax_remove_block
 * @since 1.0.0
 */
add_action('wp_ajax_remove_block', function() {

	$post_id = $_POST['block_id'];
	$stack_id = $_POST['stack_id'];

	$blocks_data = wpbb_get_blocks($stack_id);

	if ($blocks_data == null) {
		return;
	}

	$blocks_data = array_filter($blocks_data, function($block) use ($post_id) {

		if ($block['block_id'] == $post_id ||
			$block['super_id'] == $post_id) {
			return false;
		}

		return true;

	});

	update_post_meta($stack_id, '_wpbb_blocks', $blocks_data);

	wp_delete_post($post_id);
});

//------------------------------------------------------------------------------
// Advanced Custom Fields
//------------------------------------------------------------------------------

/**
 * Adds the folder where block fields are stored to ACF.
 * @filter acf/settings/load_json
 * @since 1.0.0
 */
add_filter('acf/settings/load_json', function($paths) {

	static $block_types = null;

	if ($block_types == null) {
		$block_types = wpbb_get_block_types();
	}

	foreach ($block_types as $block_type) {
		$paths[] = $block_type['path'] . '/fields';
	}

	return $paths;
});

/**
 * Returns the fields that needs to be displayed for a specific block.
 * @filter acf/get_field_groups
 * @since 1.0.0
 */
add_filter('acf/get_field_groups', function($field_groups) {

	if (isset($_GET['post_status']) && $_GET['post_status'] === 'sync') {
		return $field_groups;
	}

	if (get_post_type() != 'wpbb-block') {

		$blocks_data = wpbb_get_block_types();

		foreach ($blocks_data as $block) {

			$block_template = wpbb_get_block_type_by_buid($block['buid']);
			if ($block_template == null) {
				continue;
			}

			$path = $block_template['path'];

			foreach ($block_template['fields'] as $json) {

				$json['ID'] = null;
				$json['style'] = 'seamless';
				$json['position'] = 'normal';
				$json['location'] = array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'block'
						),
					)
				);

				$field_groups[] = $json;
			}
		}

		return $field_groups;
	}

	$post_id = $_GET['post'];
	$stack_id = $_GET['stack_id'];

	$blocks_data = array_filter(wpbb_get_blocks($stack_id), function($block) use($post_id) {
		return $block['block_id'] == $post_id;
	});

	if ($blocks_data) foreach ($blocks_data as $block) {

		$block_template = wpbb_get_block_type_by_buid($block['buid']);
		if ($block_template == null) {
			continue;
		}

		$path = $block_template['path'];

		foreach ($block_template['fields'] as $json) {

			$json['ID'] = null;
			$json['style'] = 'seamless';
			$json['position'] = 'normal';
			$json['location'] = array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'wpbb-block'
					),
				)
			);

			$field_groups[] = $json;
		}
	}

	return $field_groups;
});
