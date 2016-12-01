<?php

require_once WP_CONTENT_DIR . '/plugins/wp-blocks-builder/Block.php';

class ImageBlock extends Block
{
	/**
	 * Called when the block is about to be rendered.
	 * @method on_render
	 * @since 1.0.0
	 */
	protected function on_render($template, array &$data)
	{
		$data['image'] = new TimberImage(get_field('image', $this->get_block_id()));
	}

}