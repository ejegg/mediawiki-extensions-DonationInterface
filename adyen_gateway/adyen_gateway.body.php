<?php
/**
 * Wikimedia Foundation
 *
 * LICENSE
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 */

/**
 * AdyenGateway
 *
 */
class AdyenGateway extends GatewayForm {

	/**
	 * Constructor - set up the new special page
	 */
	public function __construct() {
		$this->adapter = new AdyenAdapter();
		parent::__construct(); //the next layer up will know who we are.
	}

	/**
	 * Show the special page
	 *
	 * @todo
	 * - Finish error handling
	 *
	 * @param $par Mixed: parameter passed to the page or null
	 */
	public function execute( $par ) {
		global $wgRequest, $wgOut, $wgExtensionAssetsPath;
		$CSSVersion = $this->adapter->getGlobal( 'CSSVersion' );

		$wgOut->allowClickjacking();

		$wgOut->addExtensionStyle(
			$wgExtensionAssetsPath . '/DonationInterface/gateway_forms/css/gateway.css?284' .
			$CSSVersion );

		// Hide unneeded interface elements
		$wgOut->addModules( 'donationInterface.skinOverride' );

		// Make the wiki logo not clickable.
		// @fixme can this be moved into the form generators?
		$js = <<<EOT
<script type="text/javascript">
jQuery(document).ready(function() {
	jQuery("div#p-logo a").attr("href","#");
});
</script>
EOT;
		$wgOut->addHeadItem( 'logolinkoverride', $js );

		$this->setHeaders();

		if ( $wgRequest->getText( 'redirect', 0 ) ) {
			$this->paypalRedirect();
			return;
		}

		// dispatch forms/handling
		if ( $this->adapter->checkTokens() ) {

			if ( $this->adapter->posted ) {

				// Check form for errors
				$form_errors = $this->validateForm();

				// If there were errors, redisplay form, otherwise proceed to next step
				if ( $form_errors ) {
					$this->displayForm();
				} else { // The submitted form data is valid, so process it
					// allow any external validators to have their way with the data
					// Execute the proper transaction code:

					if ( $payment_method == 'cc' ) {

						$this->adapter->do_transaction( 'donate' );

						// Display an iframe for credit cards
						if ( $this->executeIframeForCreditCard() ) {
							$this->displayResultsForDebug();
							// Nothing left to process
							return;
						}
					}

					return $this->resultHandler();

				}
			} else {
				$this->displayForm();
			}
		} else { //token mismatch
			$error['general']['token-mismatch'] = wfMsg( 'donate_interface-token-mismatch' );
			$this->adapter->addManualError( $error );
			$this->displayForm();
		}
	}

	/**
	 * Execute iframe for credit card
	 *
	 * @return boolean	Returns true if formaction exists for iframe.
	 */
	protected function executeIframeForCreditCard() {

		global $wgOut;

		$formAction = $this->adapter->getTransactionDataFormAction();

		if ( $formAction ) {

			$paymentFrame = Xml::openElement( 'iframe', array(
					'id' => 'adyenframe',
					'name' => 'adyenframe',
					'width' => '680',
					'height' => '300',
					'frameborder' => '0',
					'style' => 'display:block;',
					'src' => $formAction,
				)
			);
			$paymentFrame .= Xml::closeElement( 'iframe' );

			$wgOut->addHTML( $paymentFrame );

			return true;
		}

		return false;
	}
}
