(function($) {

$(document).ready(function() {
console.log('WAT 1')
	//--------------------------------------------------------------------------
	// Public Functions
	//--------------------------------------------------------------------------

	/**
	 * @function wpbb_refreshBlock
	 * @since 1.0.0
	 */
	window.wpbb_refreshBlock = function(blockId) {

		var content = $('[data-block-id="' + blockId + '"] .block-content').addClass('block-content-updating')

		$.ajax({
			url: $('[data-block-id="' + blockId + '"]').attr('data-stack-url'),
			success: function(html) {
				html = $(html).find('[data-block-id="' + blockId + '"] .block-content')
				content.replaceWith(html)
				content.removeClass('block-content-updating')
			}
		})

		$('#wpbb-edit-modal').wpbb_modal('hide')
	}

	/**
	 * @function wpbb_appendBlock
	 * @since 1.0.0
	 */
	window.wpbb_appendBlock = function(blocks, buid, superId, spaceId, callback) {

		var stackId = $('#post_ID').val()
		if (stackId == null)  {
			return;
		}

		$.post(ajaxurl, {
			'action': 'add_block',
			'buid': buid,
			'stack_id': stackId,
			'super_id': superId,
			'space_id': spaceId,
		}, function(result) {

			var block = createBlock(result)
			 block.find('> [name="_wpbb_blocks_super_id[]"]').val(superId)
			 block.find('> [name="_wpbb_blocks_space_id[]"]').val(spaceId)

			if (callback) {
				callback(block)
			}

			blocks.append(block)
		})

		$(document.body).addClass('wp-blocks-builder-post-editor-disabled')
	}

	/**
	 * @function wpbb_replaceBlock
	 * @since 1.0.0
	 */
	window.wpbb_replaceBlock = function(blockId, buid, callback) {

		var block = $('[data-block-id="' + blockId + '"]')

		var stackId = block.attr('data-stack-id')

		$.post(ajaxurl, {
			'action': 'remove_block',
			'block_id': blockId,
			'stack_id': stackId
		}, function() {

			wpbb_appendBlock(block.closest('.blocks'), buid, null, null, callback)

			block.remove()
		})

		block.find('.block-content').addClass('block-content-updating')
	}

	//--------------------------------------------------------------------------
	// Private Functions
	//--------------------------------------------------------------------------

	/**
	 * @function createBlock
	 * @since 1.0.0
	 */
	var createBlock = function(block) {

		block = $(block)

		if (block.is('.disable')) {
			return
		}

		var cancel = function(e) {
			e.preventDefault()
			e.stopPropagation()
		}

		block.on('mousedown', function() {
			var parent = block.closest('.blocks')
			var marginT = parseFloat(parent.css('margin-top'))
			var marginB = parseFloat(parent.css('margin-bottom'))
			parent.css('height', parent.get(0).scrollHeight - marginT - marginB)
		})

		block.on('mouseup', function() {
			block.closest('.blocks').css('height', '')
		})

		var onEditButtonClick = function(e) {

			cancel(e)

			var href = $(this).attr('href')

			$('#wpbb-edit-modal').wpbb_modal('show')
			$('#wpbb-edit-modal iframe').attr('src', href)
		}

		var onMoveButtonClick = function(e) {

			cancel(e)

			var link = $(e.target).closest('a')
			var stackId = link.attr('data-stack-id')
			var blockId = link.attr('data-block-id')

			$('#wpbb-move-modal').wpbb_modal('show')
			$('#wpbb-move-modal').attr('data-source-block-id', blockId)
			$('#wpbb-move-modal').attr('data-source-stack-id', stackId)
		}

		var onCopyButtonClick = function(e) {

			cancel(e)

			var link = $(e.target).closest('a')
			var stackId = link.attr('data-stack-id')
			var blockId = link.attr('data-block-id')

			$('#wpbb-copy-modal').wpbb_modal('show')
			$('#wpbb-copy-modal').attr('data-source-block-id', blockId)
			$('#wpbb-copy-modal').attr('data-source-stack-id', stackId)
		}

		var onRemoveButtonClick = function(e) {

			cancel(e)

			var answer = confirm('This block will be removed, continue ?')
			if (answer) {

				var blockId = $(this).attr('data-block-id')
				var stackId = $(this).attr('data-stack-id')

				$.post(ajaxurl, {
					'action': 'remove_block',
					'block_id': blockId,
					'stack_id': stackId
				})

				$(this).closest('.block[data-block-id="' + blockId + '"]').remove()

				$(document.body).toggleClass('wp-blocks-builder-post-editor-disabled', $('.block').length > 0)
			}
		}

		block.find('> .block-bar > .block-actions > .block-edit a').on('mousedown', cancel)
		block.find('> .block-bar > .block-actions > .block-move a').on('mousedown', cancel)
		block.find('> .block-bar > .block-actions > .block-copy a').on('mousedown', cancel)
		block.find('> .block-bar > .block-actions > .block-remove a').on('mousedown', cancel)

		block.find('> .block-bar > .block-actions > .block-edit a').on('click', onEditButtonClick)
		block.find('> .block-bar > .block-actions > .block-copy a').on('click', onCopyButtonClick)
		block.find('> .block-bar > .block-actions > .block-move a').on('click', onMoveButtonClick)
		block.find('> .block-bar > .block-actions > .block-remove a').on('click', onRemoveButtonClick)

		$('.blocks').sortable('refresh')

		return block
	}

	$('#wpbb-add-modal').wpbb_modal({
		onHide: function() {

		}
	})

	$('#wpbb-edit-modal').wpbb_modal({
		onHide: function() {
			$('#wpbb-edit-modal iframe').attr('src', '')
		}
	})

	$('#wpbb-move-modal').wpbb_modal({
		onHide: function() {
			$('#wpbb-move-modal .block-metabox-pages li a.selected').removeClass('selected')
			$('#wpbb-move-modal').removeClass('wpbb-processing')
		}
	})

	$('#wpbb-copy-modal').wpbb_modal({
		onHide: function() {
			$('#wpbb-copy-modal .block-metabox-pages li a.selected').removeClass('selected')
			$('#wpbb-copy-modal').removeClass('wpbb-processing')
		}
	})

	var addInBlockId = null
	var addInStackId = null
	var addInSpaceId = null

	/**
	 * Initializes each blocks.
	 * @since 1.0.0
	 */
	$('.wp-admin #poststuff #wpbb_metabox').each(function(i, element) {

		$(document.body).toggleClass('wp-blocks-builder-post-editor-disabled', $('.block').length > 0)

		var options = {
			connectWith: '.blocks',
			cancel: '.disable, select, input',
			stop: function(event, ui) {
			 	var item = $(ui.item)
			 	var superIdInput = item.find('> [name="_wpbb_blocks_super_id[]"]')
			 	var spaceIdInput = item.find('> [name="_wpbb_blocks_space_id[]"]')
			 	var superId = item.parent().closest('[data-block-id]').attr('data-block-id') || 0
			 	var spaceId = item.parent().closest('[data-space-id]').attr('data-space-id') || 0
			 	superIdInput.val(superId)
				spaceIdInput.val(spaceId)
			}
		}

		$('.blocks').sortable(options)
		$('.blocks').disableSelection()
		$('.block').each(function(i, element) {
			createBlock(element)
		})
	})

	$('.wp-admin').on('click', '.button.block-add-button', function() {
		addInSpaceId = $(this).attr('data-space-id') || 0
		addInBlockId = $(this).closest('[data-block-id]').attr('data-block-id') || 0
		addInStackId = $(this).closest('[data-stack-id]').attr('data-stack-id') || 0
		$('#wpbb-add-modal').wpbb_modal('show')
	})

	$('.wp-admin #wpbb-add-modal').on('click', '.button.block-insert-button', function() {

		var buid = $(this).closest('.block-template-info').attr('data-buid')
		if (buid) {

			if (addInSpaceId && addInBlockId) {

				wpbb_appendBlock(
					$('.block[data-stack-id="' + addInStackId + '"][data-block-id="' + addInBlockId + '"] .blocks[data-space-id="' + addInSpaceId + '"]'),
					buid,
					addInBlockId,
					addInSpaceId
				)

			} else {

				wpbb_appendBlock(
					$('.blocks.blocks-root'),
					buid,
					addInBlockId,
					addInSpaceId
				)

			}
		}

		$('#wpbb-add-modal').wpbb_modal('hide')
	})

	$.each([
		'#wpbb-move-modal',
		'#wpbb-copy-modal'
	], function(i, id) {

		var selected = null

		$(id + ' .block-metabox-pages').on('click', 'a', function(e) {

			e.preventDefault()

			if (selected) {
				selected.removeClass('selected')
				selected = null
			}

			selected = $(e.target).closest('a')
			selected.addClass('selected')
		})
	})

	$('#wpbb-move-modal').on('click', '.button.button-primary', function(e) {

		var sourceBlockId = $('#wpbb-move-modal').attr('data-source-block-id')
		var sourceStackId = $('#wpbb-move-modal').attr('data-source-stack-id')
		var targetStackId = $('#wpbb-move-modal .block-metabox-pages li a.selected').closest('li').attr('data-stack-id');

		if (targetStackId == null) {
			return
		}

		$('#wpbb-move-modal').addClass('wpbb-processing')

		$.post(ajaxurl, {
			'action': 'move_block',
			'source_block_id': sourceBlockId,
			'source_stack_id': sourceStackId,
			'target_stack_id': targetStackId
		}, function() {
			$('#wpbb-move-modal').wpbb_modal('hide')
		})

		$('.block[data-block-id="' + sourceBlockId + '"]').remove()
	})

	$('#wpbb-copy-modal').on('click', '.button.button-primary', function(e) {

		var sourceBlockId = $('#wpbb-copy-modal').attr('data-source-block-id')
		var sourceStackId = $('#wpbb-copy-modal').attr('data-source-stack-id')
		var targetStackId = $('#wpbb-copy-modal .block-metabox-pages li a.selected').closest('li').attr('data-stack-id');

		if (targetStackId == null) {
			return
		}

		$('#wpbb-copy-modal').addClass('wpbb-processing')

		$.post(ajaxurl, {
			'action': 'copy_block',
			'source_block_id': sourceBlockId,
			'source_stack_id': sourceStackId,
			'target_stack_id': targetStackId
		}, function() {
			$('#wpbb-copy-modal').wpbb_modal('hide')
		})
	})
})

$.fn.wpbb_modal = function(options) {

	var element = $(this)

	if (typeof options === 'string') {

		switch (options) {
			case 'show':
				element.data('wpbb_modal').show()
				break
			case 'hide':
				element.data('wpbb_modal').hide()
				break
		}

		return this
	}

	options = options || {}

	var instance = {

		show: function() {
			element.toggleClass('block-metabox-modal-visible', true)
			if (options.onShow) {
				options.onShow()
			}
		},

		hide: function() {
			element.toggleClass('block-metabox-modal-visible', false)
			if (options.onHide) {
				options.onHide()
			}
		}
	}

	element.data('wpbb_modal', instance)
	element.find('.block-metabox-modal-hide').on('click', function() {
		instance.hide()
	})
}

})(jQuery);