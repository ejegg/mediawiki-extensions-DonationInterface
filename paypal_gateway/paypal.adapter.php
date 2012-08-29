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

class PaypalAdapter extends GatewayAdapter {
	const GATEWAY_NAME = 'Paypal';
	const IDENTIFIER = 'paypal';
	const COMMUNICATION_TYPE = 'namevalue';
	const GLOBAL_PREFIX = 'wgPaypalGateway';

	function __construct( $options = array() ) {
		parent::__construct( $options );

		if ($this->getData_Unstaged_Escaped( 'payment_method' ) == null ) {
			$this->addData(
				array( 'payment_method' => 'paypal' )
			);
		}
	}

	function defineStagedVars() {}
	function defineVarMap() {
		$this->var_map = array(
			'amount' => 'amount',
			'currency_code',
			'item_name' => 'description',
			'item_number' => 'order_id',
			'return' => 'return',
			'tx' => 'gateway_txn_id',
			//'language' => 
			//'notify_url' => 
		);
	}
			#http://127.0.0.1/index.php/Special:PaypalGatewayResult?
			#merchant_return_link=Return+to+merchant+v%27s+Test+Store
			#auth=AheT4azJT4sD5ULxY5Kkk2T1.9jXDFuWQnhHdLIChOXr1vFc0ZSou3hZzdlXSw9P510wuHc3KdCxCOOd41IkgPA

	function defineAccountInfo() {
		$this->accountInfo = array();
	}
	function defineReturnValueMap() {}
	function getResponseStatus( $response ) {}
	function getResponseErrors( $response ) {}
	function getResponseData( $response ) {}
	function processResponse( $response, &$retryVars = null ) {}
	function defineDataConstraints() {}

	public function defineErrorMap() {

		$this->error_map = array(
			// Internal messages
			'internal-0000' => 'donate_interface-processing-error', // Failed failed pre-process checks.
			'internal-0001' => 'donate_interface-processing-error', // Transaction could not be processed due to an internal error.
			'internal-0002' => 'donate_interface-processing-error', // Communication failure
		);
	}

	function defineTransactions() {
		$this->transactions = array();
		$this->transactions[ 'Donate' ] = array(
			'request' => array(
				'amount',
				'business',
				'cancel_return',
				'cmd',
				'item_name',
				'item_number',
				'return',
			),
			'values' => array(
				'business' => $this->getGlobal( 'AccountEmail' ),
				'cancel_return' => $this->getGlobal( 'ReturnURL' ),
				'cmd' => '_donations',
				'item_name' => wfMsg( 'donate_interface-donation-description' ),
				'return' => $this->getGlobal( 'ReturnURL' ),
			),
			'communication_type' => 'redirect',
		);
		// API docs at https://cms.paypal.com/us/cgi-bin/?cmd=_render-content&content_ID=developer/howto_html_paymentdatatransfer
		$this->transactions[ 'GetResponse' ] = array(
			'request' => array(
				'cmd',
				'tx',
				'at',
			),
			'values' => array(
				'cmd' => '_notify-synch',
				'at' => $this->getGlobal( 'PDTToken' ),
			),
		);
	}

	function do_transaction( $transaction ) {
		global $wgRequest, $wgOut;

		$this->setCurrentTransaction( $transaction );

		$url = $this->getGlobal( 'URL' ) . '?' . http_build_query( $this->buildRequestParams() );
error_log($url);

		switch ( $transaction ) {
			case 'Donate':
				$this->redirect_transaction( $url );
				return;
		}
	}

	function getCurrencies() {
		return array(
			'USD',
		);
	}

}
