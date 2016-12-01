/*

*/
(function($) {

$(document).ready(function() {

	var vars = {}
	window.location.href.replace(/[?&]+([^=&]+)=([^&]*)/gi, function(m, key, value) {
		vars[key] = value;
	})

	if (window.location.hash == '#block_saved') {
    	var post = vars['post']
    	if (post) {
    		window.top.wpbb_refreshBlock(post)
    	}
	}

	$('#publish').on('click', function() {
		$(document.body).removeClass('loaded')
	})
})

$(window).load(function() {
	$(document.body).addClass('loaded')
})

})(jQuery);