$( document ).ready( function () {
	$( "#amazon" ).click( function() {
		/* safety check for people who hit the back button */
		/*
		checkedValue = $( "input[name='amountRadio']:checked" ).val();
		if ( $( 'input[name="amount"]' ).val() == '0.00' && checkedValue && !isNaN( checkedValue ) ) {
			setAmount( checkedValue );
		}
		*/
		if ( validateAmount() ) {
			$( 'input[name="gateway"]' ).val( "amazon" );
			$( 'input[name="PaypalRedirect"]' ).val( "1" );
			$( 'input[name="payment_method"]' ).val( "amazon" );
			$( "#loading" ).html( '<img alt="loading" src="'+mw.config.get( 'wgScriptPath' )+'/extensions/DonationInterface/gateway_forms/includes/loading-white.gif" /> Redirecting to Amazon…' );
			document.payment.action = actionURL;
			document.payment.submit();
		}
	} );
} );
