( function ( $, mw ) {
	var di = mw.donationInterface;

	function showIframe( result ) {
		var $form = $( '<iframe>' )
			.attr( {
				src: result.formaction,
				width: 318,
				height: 314,
				frameborder: 0
			} );
		$( '#payment-form' ).append( $form );
	}

	di.forms.submit = function () {
		di.forms.callDonateApi( showIframe );
	};
} )( jQuery, mediaWiki );