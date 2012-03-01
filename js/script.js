(function($){

	// prevent double submitting, by disabling submit button after click
	$('form').submit(function() {
		$(this).find('[type=submit]').attr('disabled', 'disabled');
	});

}(jQuery));