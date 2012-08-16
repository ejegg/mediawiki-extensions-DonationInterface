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
 * AdyenAdapter
 *
 */
class AdyenAdapter extends GatewayAdapter {
	const GATEWAY_NAME = 'Adyen';
	const IDENTIFIER = 'adyen';
	const COMMUNICATION_TYPE = 'namevalue';
	const GLOBAL_PREFIX = 'wgAdyenGateway';

	function defineAccountInfo() {
		$this->accountInfo = array();
	}

	function defineDataConstraints() {
	}

	function defineErrorMap() {
	}

	function defineStagedVars() {
		$this->staged_vars = array(
			'amount',
			'hpp_signature',
		);
	}
	
	/**
	 * Define var_map
	 */
	function defineVarMap() {
		
		$this->var_map = array(
			'allowedMethods'	=> 'allowed_methods',
			'currencyCode'		=> 'currency_code',
			'merchantAccount'	=> 'merchant_account',
			'merchantReference'	=> 'order_id',
			'merchantSig'		=> 'hpp_signature',
			'orderData'			=> 'order_data',
			'paymentAmount'		=> 'amount',
			'sessionValidity'	=> 'session_expiration',
			'shipBeforeDate'	=> 'expiration',
			'shopperLocale'		=> 'language',
			'skinCode'			=> 'skin_code',
			#'RETURNURL'			=> 'returnto',
		);
	}

	function defineReturnValueMap() {
	}

	/**
	 * Define transactions
	 */
	function defineTransactions() {
		
		$this->transactions = array( );

		$this->transactions[ 'donate' ] = array(
			'request' => array(
				'allowedMethods',
				'currencyCode',
				'merchantAccount',
				'merchantReference',
				'merchantSig',
				'paymentAmount',
				'sessionValidity',
				'shipBeforeDate',
				'skinCode',
				//'shopperEmail',
				//'shopperReference',
				//'recurringContract',
				//'blockedMethods',
				//'shopperStatement',
				//'merchantReturnData',
				//'billingAddressType',
				//'deliveryAddressType',
				//'offset'
			),
			'values' => array(
				'allowedMethods' => implode( ',', $this->getAllowedPaymentMethods() ),
				'merchantAccount' => $this->getGlobal( 'AccountName' ),
				'sessionValidity' => date( 'c', strtotime( '+2 days' ) ),
				'shipBeforeDate' => date( 'Y-M-d', strtotime( '+2 days' ) ),
				'skinCode' => $this->getGlobal( 'SkinCode' ),
				//'shopperLocale' => language _ country
			),
			'iframe' => TRUE,
		);
	}
	
	protected function getAllowedPaymentMethods() {
		return array(
			'card',
		);
	}

	/**
	 * Because GC has some processes that involve more than one do_transaction 
	 * chained together, we're catching those special ones in an overload and 
	 * letting the rest behave normally. 
	 */
	function do_transaction( $transaction ) {
		$this->setCurrentTransaction( $transaction );

		if ( $this->transaction_option( 'iframe' ) ) {
			// slightly different than other gateways' iframe method,
			// we don't have to make the round-trip, instead just
			// stage the variables and return the iframe url in formaction.

			switch ( $transaction ) {
				case 'donate':
					$formaction = $this->getGlobal( 'BaseURL' ) . '/hpp/pay.shtml';
					$this->doStage_hpp_signature();
					$this->setTransactionResult(
						array( 'FORMACTION' => $formaction ),
						'data'
					);
					$this->setTransactionResult(
						$this->buildRequestParams(),
						'gateway_params'
					);
					break;
			}
		}
		return $this->getTransactionAllResults();
	}
	
	function getResponseStatus( $response ) {
	}

	function getResponseErrors( $response ) {
	}

	function getResponseData( $response ) {
	}
	
	function getCurrencies() {
		$currencies = array(
			//XXX
			'USD', // U.S. dollar
		);
		return $currencies;
	}
	
	protected function buildRequestParams() {
		// Look up the request structure for our current transaction type in the transactions array
		$structure = $this->getTransactionRequestStructure();
		if ( !is_array( $structure ) ) {
			return FALSE;
		}

		$queryvals = array();
		foreach ( $structure as $fieldname ) {
			$fieldvalue = $this->getTransactionSpecificValue( $fieldname );
			if ( $fieldvalue !== '' && $fieldvalue !== false ) {
				$queryvals[$fieldname] = $fieldvalue;
			}
		}
		return $queryvals;
	}

	function processResponse( $response, &$retryVars = null ) {
		$result_code = isset( $response['data']['authResult'] ) ? $response['data']['authResult'] : '';
		if ( $result_code == 'AUTHORISED' ) {
			return null;
		}
		return $result_code;
	}

	#protected function stage_language( $type = 'request' ) {
	
	/**
	 * Stage: amount
	 *
	 * For example: JPY 1000.05 get changed to 100005. This need to be 100000.
	 * For example: JPY 1000.95 get changed to 100095. This need to be 100000.
	 *
	 * @param string	$type	request|response
	 */
	protected function stage_amount( $type = 'request' ) {
		switch ( $type ) {
			case 'request':
				
				// JPY cannot have cents.
				$floorCurrencies = array ( 'JPY' );
				if ( in_array( $this->staged_data['currency_code'], $floorCurrencies ) ) {
					$this->staged_data['amount'] = floor( $this->staged_data['amount'] );
				}
				
				$this->staged_data['amount'] = $this->staged_data['amount'] * 100;

				break;
			case 'response':
				$this->staged_data['amount'] = $this->staged_data['amount'] / 100;
				break;
		}
	}

	// XXX should be a stage_ function, but this isn't possible
	// until we have control over the order of assignment.
	protected function doStage_hpp_signature( $type = 'request' ) {
		$keys = array(
			'paymentAmount',
			'currencyCode',
			'shipBeforeDate',
			'merchantReference',
			'skinCode',
			'merchantAccount',
			'sessionValidity',
			'shopperEmail',
			'shopperReference',
			'recurringContract',
			'allowedMethods',
			'blockedMethods',
			'shopperStatement',
			'merchantReturnData',
			'billingAddressType',
			'deliveryAddressType',
			'offset'
		);
		$this->staged_data['hpp_signature'] = $this->getSignature( $keys );
	}

	protected function getSignature( $keys ) {
		$joined = "";
		foreach ( $keys as $key ) {
			if ( FALSE === array_search( $key, $this->transactions[ $this->getCurrentTransaction() ][ 'request' ] ) ) {
				continue;
			}
			$s = $this->getTransactionSpecificValue( $key );
			if ( $s ) {
				$joined .= $s;
			}
		}
		return base64_encode( hash_hmac( 'sha1', $joined, $this->getGlobal( 'SharedSecret' ), TRUE ) );
	}
}
