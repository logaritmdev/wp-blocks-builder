<?php

require_once __DIR__ . '/Block.php';

/**
 * @class Layout
 * @since 1.0.0
 */
class Layout extends Block
{
	/**
	 * @method is_editable
	 * @since 1.0.0
	 */
	public function is_editable()
	{
		return false;
	}

	/**
	 * Returns whether this block can be copied.
	 * @method is_copyable
	 * @since 1.0.0
	 */
	public function is_copyable()
	{
		// Features to copy layouts are not completed yet.
		return false;
	}

	/**
	 * Returns whether this block can be moved.
	 * @method is_movable
	 * @since 1.0.0
	 */
	public function is_movable()
	{
		// Features to move layouts are not completed yet.
		return false;
	}

	/**
	 * Renders a specific area of this block.
	 * @method render_children
	 * @since 1.0.0
	 */
	public function render_children($space_id)
	{
		$stack_id = $this->get_stack_id();
		$block_id = $this->get_block_id();

		$blocks = apply_filters('wpbb/sub_blocks', wpbb_get_blocks($stack_id), $this);

		if ($blocks) {

			foreach ($blocks as $page_block) {

				if (!isset($page_block['buid']) ||
					!isset($page_block['block_id']) ||
					!isset($page_block['stack_id'])) {
					continue;
				}

				if ($page_block['super_id'] == $block_id &&
					$page_block['space_id'] == $space_id) {

					wpbb_render_block_template(
						$page_block['buid'],
						$page_block['block_id'],
						$page_block['stack_id']
					);
				}
			}
		}
	}
}