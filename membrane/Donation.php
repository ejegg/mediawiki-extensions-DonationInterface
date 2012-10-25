<?php
	
	/**
	 * $card_types
	 * @var array A list of SOME card types we recognize
	 */
	protected static $card_types = array( 
		'amex',
		'mc',
		'visa',
		'discover'
	);

				'card_num' => str_replace( ' ', '', $wgRequest->getText( 'card_num' ) ),
				'expiration' => $wgRequest->getText( 'mos' ) . substr( $wgRequest->getText( 'year' ), 2, 2 ),

	/**
	 * normalize helper function.
	 * Takes all possible sources for the intended donation amount, and 
	 * normalizes them into the 'amount' field.  
	 */
	protected function normalize_NormalizedAmount() {
		if ( !($this->isSomething( 'amount' )) || !(preg_match( '/^\d+(\.(\d+)?)?$/', $this->getVal( 'amount' ) ) ) ) {
			if ( $this->isSomething( 'amountGiven' ) && preg_match( '/^\d+(\.(\d+)?)?$/', $this->getVal( 'amountGiven' ) ) ) {
				$this->setVal( 'amount', number_format( $this->getVal( 'amountGiven' ), 2, '.', '' ) );
			} elseif ( $this->isSomething( 'amount' ) && $this->getVal( 'amount' ) == '-1' ) {
				$this->setVal( 'amount', $this->getVal( 'amountOther' ) );
			} else {
				$this->setVal( 'amount', '0.00' );
			}
		}
	}

	/**
	 * munge the legacy card_type field into payment_submethod
	 */
	protected function normalize_ameCardType()
	{
		if ($this->getVal('payment_method') == 'cc')
		{
			if ($this->isSomething('card_type'))
			{
				$this->setVal('payment_submethod', $this->getVal('card_type'));
			}
		}
	}
	
	/**
	 * normalize helper function
	 * Setting the country correctly.
	 * If we have no country, we try to get something rational through GeoIP 
	 * lookup.
	 */
	protected function normalize_Country() {
		global $wgRequest;
		if ( !$this->isSomething('country') ){
			// If no country was passed, try to do GeoIP lookup
			// Requires php5-geoip package
			if ( function_exists( 'geoip_country_code_by_name' ) ) {
				$ip = $this->getVal( 'user_ip' );
				if ( IP::isValid( $ip ) ) {
					$country = geoip_country_code_by_name( $ip );
					$this->setVal('country', $country);
				}
			}
		}
	}
	
	/**
	 * normalize helper function
	 * Setting the currency code correctly. 
	 * Historically, this value could come in through 'currency' or 
	 * 'currency_code'. After this fires, we will only have 'currency_code'. 
	 */
	protected function normalize_CurrencyCode() {
		global $wgRequest;
		
		//at this point, we can have either currency, or currency_code. 
		//-->>currency_code has the authority!<<-- 
		$currency = false;
		
		if ( $this->isSomething( 'currency' ) ) {
			$currency = $this->getVal( 'currency' );
			$this->expunge( 'currency' );
		}
		if ( $this->isSomething( 'currency_code' ) ) {
			$currency = $this->getVal( 'currency_code' );
		}
		
		if ( $currency ){
			$this->setVal( 'currency_code', $currency );
		} else {
			require_once( dirname( __FILE__ ) . '/nationalCurrencies.inc' );
			$country_default_currency = getNationalCurrency($this->getVal('country'));

			$this->setVal('currency_code', $country_default_currency);
		}
	}
	
	/**
	 * normalize helper function
	 * Sets user_ip and server_ip. 
	 */
	protected function normalize_IPAddresses(){
		global $wgRequest;
		//if we are coming in from the orphan slayer, the client ip should 
		//already be populated with something un-local, and we'd want to keep 
		//that.
		if ( !$this->isSomething( 'user_ip' ) || $this->getVal( 'user_ip' ) === '127.0.0.1' ){
			if ( isset($wgRequest) ){
				$this->setVal( 'user_ip', $wgRequest->getIP() );
			}
		}
		
		if ( array_key_exists( 'SERVER_ADDR', $_SERVER ) ){
			$this->setVal( 'server_ip', $_SERVER['SERVER_ADDR'] );
		} else {
			//command line? 
			$this->setVal( 'server_ip', '127.0.0.1' );
		}
		
		
	}
	
	/**
	 * validate_amount
	 * Determines if the $value passed in is a valid amount. 
	 * NOTE: You will need to make sure that currency_code is populated before 
	 * you get here. 
	 * @param string $value The piece of data that is supposed to be an amount. 
	 * @param string $currency_code Valid amounts depend on there being a 
	 * currency code also. This also needs to be passed in. 
	 * @param string $gateway The gateway needs to be provided so we can 
	 * determine that gateway's current price floor and ceiling.  
	 * @return boolean True if $value is a valid amount, otherwise false.  
	 */
	protected static function validate_amount( $value, $currency_code, $gateway ){
		if ( !$value || !$currency_code || !is_numeric( $value ) ) {
			return false;
		}
		
		// check amount
		$gateway_class = self::getGatewayClass($gateway);
		if ( !$gateway_class ){
			return false;
		}
		
		$priceFloor = $gateway_class::getGlobal( 'PriceFloor' );
		$priceCeiling = $gateway_class::getGlobal( 'PriceCeiling' );
		if ( !preg_match( '/^\d+(\.(\d+)?)?$/', $value ) ||
			( ( float ) self::convert_to_usd( $currency_code, $value ) < ( float ) $priceFloor ||
			( float ) self::convert_to_usd( $currency_code, $value ) > ( float ) $priceCeiling ) ) {
			return false;
		}
		
		return true;
	}
	
	
	/**
	 * validate_card_type
	 * Determines if the $value passed in is (possibly) a valid credit card type.
	 * @param string $value The piece of data that is supposed to be a credit card type.
	 * @param string $card_number The card number associated with this card type. Optional.
	 * @return boolean True if $value is a reasonable credit card type, otherwise false.  
	 */
	protected static function validate_card_type( $value, $card_number = '' ) {
		if ( !array_key_exists( $value, self::$card_types ) ){
			return false;
		}
		
		if ( $card_number != '' ){
			$calculated_card_type = self::getCardType( $card_number );
			if ( $calculated_card_type != $value ){
				return false;
			}
		}
		
		return true;
	}
	
	
	/**
	 * validate_credit_card
	 * Determines if the $value passed in is (possibly) a valid credit card number.
	 * @param string $value The piece of data that is supposed to be a credit card number.
	 * @return boolean True if $value is a reasonable credit card number, otherwise false.  
	 */
	protected static function validate_credit_card( $value ) {
		$calculated_card_type = self::getCardType( $value );
		if ( !$calculated_card_type ){
			return false;
		}
		
		return true;
	}
	
	
	/**
	 * Convert an amount for a particular currency to an amount in USD
	 *
	 * This is grosley rudimentary and likely wildly inaccurate.
	 * This mimicks the hard-coded values used by the WMF to convert currencies
	 * for validatoin on the front-end on the first step landing pages of their
	 * donation process - the idea being that we can get a close approximation
	 * of converted currencies to ensure that contributors are not going above
	 * or below the price ceiling/floor, even if they are using a non-US currency.
	 *
	 * In reality, this probably ought to use some sort of webservice to get real-time
	 * conversion rates.
	 *
	 * @param string $currency_code
	 * @param float $amount
	 * @return float
	 */
	public static function convert_to_usd( $currency_code, $amount ) {
		require_once( dirname( __FILE__ ) . '/currencyRates.inc' );
		$rates = getCurrencyRates();
		$code = strtoupper( $currency_code );
		if ( array_key_exists( $code, $rates ) ) {
			$usd_amount = $amount / $rates[$code];
		} else {
			$usd_amount = $amount;
		}
		return $usd_amount;
	}
	
	
	/**
	 * Calculates and returns the card type for a given credit card number. 
	 * @param numeric $card_num A credit card number.
	 * @return mixed 'american', 'mastercard', 'visa', 'discover', or false. 
	 */
	public static function getCardType( $card_num ) {
		// validate that credit card number entered is correct and set the card type
		if ( preg_match( '/^3[47][0-9]{13}$/', $card_num ) ) { // american express
			return 'amex';
		} elseif ( preg_match( '/^5[1-5][0-9]{14}$/', $card_num ) ) { //	mastercard
			return 'mc';
		} elseif ( preg_match( '/^4[0-9]{12}(?:[0-9]{3})?$/', $card_num ) ) {// visa
			return 'visa';
		} elseif ( preg_match( '/^6(?:011|5[0-9]{2})[0-9]{12}$/', $card_num ) ) { // discover
			return 'discover';
		} else { // an unrecognized card type was entered
			return false;
		}
	}
	
