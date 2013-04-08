(function($) {
	$(document).on('ready', function() {
		$('.cftf-tax-select').chosen();
		$('.cftf-author-select').chosen();
		$('.cftf-date').datepicker();
	});

	$('a.cftf-navigation').on('click', function(e) {
		var url = $(this).attr('href'),
			$form = $('form#cftf-query');

		// Only do this on pages where the old data form is present
		if ($form.length > 0) {
			e.preventDefault();
			$form.attr('action', url);
			$form.submit();
		}
	});

})(jQuery);