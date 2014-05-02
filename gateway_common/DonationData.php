<?php

/**
 * DonationData
 * This class is responsible for pulling all the data used by DonationInterface 
 * from various sources. Once pulled, DonationData will then normalize and 
 * sanitize the data for use by the various gateway adapters which connect to 
 * the payment gateways, and through those gateway adapters, the forms that 
 * provide the user interface.
 * 
 * DonationData was not written to be instantiated by anything other than a 
 * gateway adapter (or class descended from GatewayAdapter). 
 * 
 * @author khorn
 */
class DonationData {
	protected $normalized = array( );
	protected $gateway;
	protected $validationErrors = null;

	/**
	 * DonationData constructor
	 * @param GatewayAdapter $gateway
	 * @param mixed $data An optional array of donation data that will, if 
	 * present, circumvent the usual process of gathering the data from various 
	 * places in $wgRequest, or 'false' to gather the data the usual way. 
	 * Default is false. 
	 */
	function __construct( $gateway, $data = false ) {
		$this->gateway = $gateway;
		$this->gatewayID = $this->getGatewayIdentifier();
		$this->populateData( $data );
	}

	/**
	 * populateData, called on construct, pulls donation data from various 
	 * sources. Once the data has been pulled, it will handle any session data 
	 * if present, normalize the data regardless of the source, and handle the 
	 * caching variables.  
	 * @global Webrequest $wgRequest 
	 * @param mixed $external_data An optional array of donation data that will, 
	 * if present, circumvent the usual process of gathering the data from 
	 * various places in $wgRequest, or 'false' to gather the data the usual way. 
	 * Default is false. 
	 */
	protected function populateData( $external_data = false ) {
		global $wgRequest;
		$this->normalized = array( );
		if ( is_array( $external_data ) ) {
			//I don't care if you're a test or not. At all.
			$this->normalized = $external_data;
		} else {
			$this->normalized = array(
				'amount' => $wgRequest->getText( 'amount', null ),
				'amountGiven' => $wgRequest->getText( 'amountGiven', null ),
				'amountOther' => $wgRequest->getText( 'amountOther', null ),
				'email' => $wgRequest->getText( 'email' ),
				'emailAdd' => $wgRequest->getText( 'emailAdd' ), //@TODO: Kill this legacy field for increased happiness. Er, once production does the same.
				'fname' => $wgRequest->getText( 'fname' ),
				'lname' => $wgRequest->getText( 'lname' ),
				'street' => $wgRequest->getText( 'street' ),
				'street_supplemental' => $wgRequest->getText( 'street_supplemental' ),
				'city' => $wgRequest->getText( 'city' ),
				'state' => $wgRequest->getText( 'state' ),
				'zip' => $wgRequest->getText( 'zip' ),
				'country' => $wgRequest->getText( 'country' ),
				'premium_language' => $wgRequest->getText( 'premium_language', null ),
				'card_num' => str_replace( ' ', '', $wgRequest->getText( 'card_num' ) ),
				'card_type' => $wgRequest->getText( 'card_type' ),
				'expiration' => $wgRequest->getText( 'expiration' ),
				'cvv' => $wgRequest->getText( 'cvv' ),
				//Leave both of the currencies here, in case something external didn't get the memo.
				'currency' => $wgRequest->getVal( 'currency' ),
				'currency_code' => $wgRequest->getVal( 'currency_code' ),
				'payment_method' => $wgRequest->getText( 'payment_method', null ),  // NOTE: If things are breaking because session data is overwriting this; please fix elsewhere!
				'payment_submethod' => $wgRequest->getText( 'payment_submethod', null ), // Used by GlobalCollect for payment types
				'paymentmethod' => $wgRequest->getText( 'paymentmethod', null ), //used by the FormChooser (and the newest banners) for some reason.
				'submethod' => $wgRequest->getText( 'submethod', null ), //same as above. Ideally, the newer banners would stop using these vars and go back to the old ones...
				'issuer_id' => $wgRequest->getText( 'issuer_id' ),
				'order_id' => $wgRequest->getText( 'order_id', null ),
				'referrer' => ( $wgRequest->getVal( 'referrer' ) ) ? $wgRequest->getVal( 'referrer' ) : $wgRequest->getHeader( 'referer' ),
				'utm_source' => $wgRequest->getText( 'utm_source' ),
				'utm_source_id' => $wgRequest->getVal( 'utm_source_id', null ),
				'utm_medium' => $wgRequest->getText( 'utm_medium' ),
				'utm_campaign' => $wgRequest->getText( 'utm_campaign' ),
				'utm_key' => $wgRequest->getText( 'utm_key' ),
				// Pull both of these here. We can logic out which one to use in the normalize bits. 
				'language' => $wgRequest->getText( 'language', null ),
				'uselang' => $wgRequest->getText( 'uselang', null ),
				'token' => $wgRequest->getText( 'token', null ),
				'contribution_tracking_id' => $wgRequest->getText( 'contribution_tracking_id' ),
				'data_hash' => $wgRequest->getText( 'data_hash' ),
				'action' => $wgRequest->getText( 'action' ),
				'gateway' => $wgRequest->getText( 'gateway' ), //likely to be reset shortly by setGateway();
				'owa_session' => $wgRequest->getText( 'owa_session', null ),
				'owa_ref' => $wgRequest->getText( 'owa_ref', null ),
				'descriptor' => $wgRequest->getText( 'descriptor', null ),

				'account_name' => $wgRequest->getText( 'account_name', null ),
				'account_number' => $wgRequest->getText( 'account_number', null ),
				'authorization_id' => $wgRequest->getText( 'authorization_id', null ),
				'bank_check_digit' => $wgRequest->getText( 'bank_check_digit', null ),
				'bank_name' => $wgRequest->getText( 'bank_name', null ),
				'bank_code' => $wgRequest->getText( 'bank_code', null ),
				'branch_code' => $wgRequest->getText( 'branch_code', null ),
				'country_code_bank' => $wgRequest->getText( 'country_code_bank', null ),
				'date_collect' => $wgRequest->getText( 'date_collect', null ),
				'direct_debit_text' => $wgRequest->getText( 'direct_debit_text', null ),
				'iban' => $wgRequest->getText( 'iban', null ),
				'fiscal_number' => $wgRequest->getText( 'fiscal_number', null ),
				'transaction_type' => $wgRequest->getText( 'transaction_type', null ),
				'form_name' => $wgRequest->getText( 'form_name', null ),
				'ffname' => $wgRequest->getText( 'ffname', null ),
				'recurring' => $wgRequest->getVal( 'recurring', null ), //boolean type
				'recurring_paypal' => $wgRequest->getVal( 'recurring_paypal', null ), //boolean type, legacy key
				'user_ip' => null, //placeholder. We'll make these in a minute.
				'server_ip' => null,
			);
			if ( !$this->wasPosted() ) {
				$this->setVal( 'posted', false );
			}
		}
		
		//if we have saved any donation data to the session, pull them in as well.
		$this->integrateDataFromSession();

		$this->normalize();

	}
	
	/**
	 * populateData helper function 
	 * If donor session data has been set, pull the fields in the session that 
	 * are populated, and merge that with the data set we already have. 
	 */
	protected function integrateDataFromSession() {
		/** if the thing coming in from the session isn't already something,
		 * replace it.
		 * if it is: assume that the session data was meant to be replaced
		 * with better data.
		 * ...unless it's an explicit $overwrite * */
		$c = $this->getAdapterClass();
		if ( $c::session_exists() && array_key_exists( 'Donor', $_SESSION ) ) {
			//fields that should always overwrite with their original values
			$overwrite = array ( 'referrer' );
			foreach ( $_SESSION['Donor'] as $key => $val ) {
				if ( !$this->isSomething( $key ) ){
					$this->setVal( $key, $val );
				} else {
					if ( in_array( $key, $overwrite ) ) {
						$this->setVal( $key, $val );
					}
				}
			}
		}
	}

	/**
	 * Returns an array of normalized and escaped donation data
	 * @return array
	 */
	public function getDataEscaped() {
		$escaped = $this->normalized;
		array_walk( $escaped, array( $this, 'sanitizeInput' ) );
		return $escaped;
	}

	/**
	 * Returns an array of normalized (but unescaped) donation data
	 * @return array 
	 */
	public function getDataUnescaped() {
		return $this->normalized;
	}

	/**
	 * Tells you if a value in $this->normalized is something or not. 
	 * @param string $key The field you would like to determine if it exists in 
	 * a usable way or not. 
	 * @return boolean true if the field is something. False if it is null, or 
	 * an empty string. 
	 */
	public function isSomething( $key ) {
		if ( array_key_exists( $key, $this->normalized ) ) {
			if ( is_null($this->normalized[$key]) || $this->normalized[$key] === '' ) {
				return false;
			}
			return true;
		} else {
			return false;
		}
	}

	/**
	 * getVal_Escaped
	 * @param string $key The data field you would like to retrieve. Pulls the 
	 * data from $this->normalized if it is found to be something. 
	 * @return mixed The normalized and escaped value of that $key. 
	 */
	public function getVal_Escaped( $key ) {
		if ( $this->isSomething( $key ) ) {
			//TODO: If we ever start sanitizing in a more complicated way, we should move this 
			//off to a function and have both getVal_Escaped and sanitizeInput call that. 
			return htmlspecialchars( $this->normalized[$key], ENT_COMPAT, 'UTF-8', false );
		} else {
			return null;
		}
	}
	
	/**
	 * getVal
	 * For Internal Use Only! External objects should use getVal_Escaped.
	 * @param string $key The data field you would like to retrieve directly 
	 * from $this->normalized. 
	 * @return mixed The normalized value of that $key, or null if it isn't
	 * something.
	 */
	protected function getVal( $key ) {
		if ( $this->isSomething( $key ) ) {
			return $this->normalized[$key];
		} else {
			return null;
		}
	}

	/**
	 * Sets a key in the normalized data array, to a new value.
	 * This function should only ever be used for keys that are not listed in 
	 * DonationData::getCalculatedFields().
	 * TODO: If the $key is listed in DonationData::getCalculatedFields(), use 
	 * DonationData::addData() instead. Or be a jerk about it and throw an 
	 * exception. (Personally I like the second one)
	 * @param string $key The key you want to set.
	 * @param string $val The value you'd like to assign to the key. 
	 */
	public function setVal( $key, $val ) {
		$this->normalized[$key] = $val;
	}

	/**
	 * Removes a value from $this->normalized. 
	 * @param string $key type
	 */
	public function expunge( $key ) {
		if ( array_key_exists( $key, $this->normalized ) ) {
			unset( $this->normalized[$key] );
		}
	}
	
	/**
	 * Returns an array of all the fields that get re-calculated during a 
	 * normalize. 
	 * This can be used on the outside when in the process of changing data, 
	 * particularly if any of the recalculted fields need to be restaged by the 
	 * gateway adapter. 
	 * @return array An array of values matching all recauculated fields.  
	 */
	public function getCalculatedFields() {
		$fields = array(
			'utm_source',
			'amount',
			'order_id',
			'gateway',
			'optout',
			'anonymous',
			'language',
			'premium_language',
			'contribution_tracking_id', //sort of...
			'currency_code',
			'user_ip',
		);
		return $fields;
	}

	/**
	 * Normalizes the current set of data, just after it's been 
	 * pulled (or re-pulled) from a data source. 
	 * Care should be taken in the normalize helper functions to write code in 
	 * such a way that running them multiple times on the same array won't cause 
	 * the data to stroll off into the sunset: Normalize will definitely need to 
	 * be called multiple times against the same array.
	 */
	protected function normalize() {
		if ( !empty( $this->normalized ) ) {
			$updateCtRequired = $this->handleContributionTrackingID(); // Before Order ID
			$this->setNormalizedOrderIDs();
			$this->setIPAddresses();
			$this->setNormalizedRecurring();
			$this->setNormalizedPaymentMethod(); //need to do this before utm_source.
			$this->setUtmSource();
			$this->setNormalizedAmount();
			$this->setGateway();
			$this->setLanguage();
			$this->setCountry(); //must do this AFTER setIPAddress...
			$this->setCurrencyCode(); // AFTER setCountry
			$this->renameCardType();
			$this->setEmail();

			if ( $updateCtRequired ) {
				$this->saveContributionTrackingData();
			}

			$this->getValidationErrors();
		}
	}
	
	/**
	 * normalize helper function
	 * Sets user_ip and server_ip. 
	 */
	protected function setIPAddresses(){
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
	 * munge the legacy card_type field into payment_submethod
	 */
	protected function renameCardType()
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
	 * Setting the country correctly. Country is... kinda important.
	 * If we have no country, or nonsense, we try to get something rational
	 * through GeoIP lookup.
	 */
	protected function setCountry() {
		$regen = true;
		$country = '';

		if ( $this->isSomething( 'country' ) ) {
			$country = strtoupper( $this->getVal( 'country' ) );
			if ( DataValidator::is_valid_iso_country_code( $country ) ) {
				$regen = false;
			} else {
				//check to see if it's one of those other codes that comes out of CN, for the logs
				//If this logs annoying quantities of nothing useful, go ahead and kill this whole else block later.
				//we're still going to try to regen.
				$near_countries = array ( 'XX', 'EU', 'AP', 'A1', 'A2', 'O1' );
				if ( !in_array( $country, $near_countries ) ) {
					$this->log( __FUNCTION__ . ": $country is not a country, or a recognized placeholder.", LOG_WARNING );
				}
			}
		} else {
			$this->log( __FUNCTION__ . ': Country not set.', LOG_WARNING );
		}

		//try to regenerate the country if we still don't have a valid one yet
		if ( $regen ) {
			// If no valid country was passed, try to do GeoIP lookup
			// Requires php5-geoip package
			if ( function_exists( 'geoip_country_code_by_name' ) ) {
				$ip = $this->getVal( 'user_ip' );
				if ( IP::isValid( $ip ) ) {
					//I hate @suppression at least as much as you do, but this geoip function is being genuinely horrible.
					//try/catch did not help me suppress the notice it emits when it can't find a host.
					//The goggles; They do *nothing*.
					$country = @geoip_country_code_by_name( $ip );
					if ( !$country ) {
						$this->log( __FUNCTION__ . ": GeoIP lookup function found nothing for $ip! No country available.", LOG_WARNING );
					}
				}
			} else {
				$this->log( 'GeoIP lookup function is missing! No country available.', LOG_WARNING );
			}

			//still nothing good? Give up.
			if ( !DataValidator::is_valid_iso_country_code( $country ) ) {
				$country = 'XX';
			}
		}

		if ( $country != $this->getVal( 'country' ) ) {
			$this->setVal( 'country', $country );
		}
	}
	
	/**
	 * normalize helper function
	 * Setting the currency code correctly. 
	 * Historically, this value could come in through 'currency' or 
	 * 'currency_code'. After this fires, we will only have 'currency_code'. 
	 */
	protected function setCurrencyCode() {
		//at this point, we can have either currency, or currency_code.
		//-->>currency_code has the authority!<<-- 
		$currency = false;

		if ( $this->isSomething( 'currency' ) ) {
			$currency = $this->getVal( 'currency' );
			$this->expunge( 'currency' );
			$this->log( "Got currency from 'currency', now: $currency", LOG_DEBUG );
		} elseif ( $this->isSomething( 'currency_code' ) ) {
			$currency = $this->getVal( 'currency_code' );
			$this->log( "Got currency from 'currency_code', now: $currency", LOG_DEBUG );
		}
		
		//TODO: This is going to fail miserably if there's no country yet.
		if ( !$currency ){
			require_once( dirname( __FILE__ ) . '/nationalCurrencies.inc' );
			$currency = getNationalCurrency($this->getVal('country'));
			$this->log( "Got currency from 'country', now: $currency", LOG_DEBUG );
		}
		
		$this->setVal( 'currency_code', $currency );
		$this->expunge( 'currency', $currency );  //honestly, we don't want this.
	}
	
	/**
	 * normalize helper function.
	 * Assures that if no contribution_tracking_id is present, a row is created 
	 * in the Contribution tracking table, and that row is assigned to the 
	 * current contribution we're tracking. 
	 * If a contribution tracking id is already present, no new rows will be 
	 * assigned.
	 *
	 * @return bool True if a new record was created
	 */
	protected function handleContributionTrackingID() {
		if ( !$this->isSomething( 'contribution_tracking_id' ) ) {
			$ctid = $this->saveContributionTrackingData();
			if ( $ctid ) {
				$this->setVal( 'contribution_tracking_id', $ctid );
				return true;
			}
		}
		return false;
	}
	
	/**
	 * normalize helper function.
	 * Takes all possible sources for the intended donation amount, and 
	 * normalizes them into the 'amount' field.  
	 */
	protected function setNormalizedAmount() {
		if ( $this->getVal( 'amount' ) === 'Other' ){
			$this->setVal( 'amount', $this->getVal( 'amountGiven' ) );
		}

		$amountIsNotValidSomehow = ( !( $this->isSomething( 'amount' )) ||
			!is_numeric( $this->getVal( 'amount' ) ) ||
			$this->getVal( 'amount' ) <= 0 );

		if ( $amountIsNotValidSomehow &&
			( $this->isSomething( 'amountGiven' ) && is_numeric( $this->getVal( 'amountGiven' ) ) ) ) {
			$this->setVal( 'amount', $this->getVal( 'amountGiven' ) );
		} else if ( $amountIsNotValidSomehow &&
			( $this->isSomething( 'amountOther' ) && is_numeric( $this->getVal( 'amountOther' ) ) ) ) {
			$this->setVal( 'amount', $this->getVal( 'amountOther' ) );
		}
		
		if ( !($this->isSomething( 'amount' )) ){
			$this->setVal( 'amount', '0.00' );
		}
		
		$this->expunge( 'amountGiven' );
		$this->expunge( 'amountOther' );

		if ( !is_numeric( $this->getVal( 'amount' ) ) ){
			//fail validation later, log some things.
			$mess = 'Non-numeric Amount.';
			$keys = array(
				'amount',
				'utm_source',
				'utm_campaign',
				'email',
				'user_ip', //to help deal with fraudulent traffic.
			);
			foreach ( $keys as $key ){
				$mess .= ' ' . $key . '=' . $this->getVal( $key );
			}
			$this->log( $mess, LOG_DEBUG );
			$this->setVal('amount', 'invalid');
			return;
		}
		
		if ( DataValidator::is_fractional_currency( $this->getVal( 'currency_code' ) ) ){
			$this->setVal( 'amount', number_format( $this->getVal( 'amount' ), 2, '.', '' ) );
		} else {
			$this->setVal( 'amount', floor( $this->getVal( 'amount' ) ) );
		}
	}

	/**
	 * normalize helper function.
	 * Takes all possible names for recurring and normalizes them into the 'recurring' field.
	 */
	protected function setNormalizedRecurring() {
		if ( $this->isSomething( 'recurring_paypal' ) && ( $this->getVal( 'recurring_paypal' ) === '1' || $this->getVal( 'recurring_paypal' ) === 'true' ) ) {
			$this->setVal( 'recurring', true );
			$this->expunge('recurring_paypal');
		}
		if ( $this->isSomething( 'recurring' ) && ( $this->getVal( 'recurring' ) === '1' || $this->getVal( 'recurring' ) === 'true' ) ) {
			$this->setVal( 'recurring', true );
		}
		else{
			$this->setVal( 'recurring', false );
		}
	}

	/**
	 * normalize helper function.
	 * Gets an appropriate orderID from the gateway class.
	 *
	 * @return null
	 */
	protected function setNormalizedOrderIDs() {
		$override = null;
		if ( $this->gateway->isBatchProcessor() ) {
			$override = $this->getVal( 'order_id' );
		}
		$this->setVal( 'order_id', $this->gateway->normalizeOrderID( $override ) );
	}

	/**
	 * normalize helper function.
	 * Collapses the various versions of payment method and submethod.
	 *
	 * @return null
	 */
	protected function setNormalizedPaymentMethod() {
		$method = '';
		$submethod = '';
		// payment_method and payment_submethod are currently preferred within DonationInterface
		if ( $this->isSomething( 'payment_method' ) ) {
			$method = $this->getVal( 'payment_method' );

			//but they can come in a little funny.
			$exploded = explode( '.', $method );
			if ( count( $exploded ) > 1 ) {
				$method = $exploded[0];
				$submethod = $exploded[1];
			}
		}

		if ( $this->isSomething( 'payment_submethod' ) ) {
			if ( $submethod != '' ) {
				//squak a little if they don't match, and pick one.
				if ( $submethod != $this->getVal( 'payment_submethod' ) ) {
					$message = "Submethod normalization conflict!: ";
					$message .= 'payment_submethod = ' . $this->getVal( 'payment_submethod' );
					$message .= ", and exploded payment_method = '$submethod'. Going with the first option.";
					$this->log( $message, LOG_DEBUG );
				}
			}
			$submethod = $this->getVal( 'payment_submethod' );
		}

		if ( $this->isSomething( 'paymentmethod' ) ) { //gross. Why did we do this?
			//...okay. So, if we have this value, we've likely just come in from the form chooser,
			//which has just used *this* value to choose a form with.
			//so, go ahead and prefer this version, and then immediately nuke it.
			$method = $this->getVal( 'paymentmethod' );
			$this->expunge( 'paymentmethod' );
		}

		if ( $this->isSomething( 'submethod' ) ) { //same deal
			$submethod = $this->getVal( 'submethod' );
			$this->expunge( 'submethod' );
		}

		$this->setVal( 'payment_method', $method );
		$this->setVal( 'payment_submethod', $submethod );
	}

	/**
	 * Sanitize user input.
	 *
	 * Intended to be used with something like array_walk.
	 *
	 * @param $value string The value of the array
	 * @param $key string The key of the array
	 * @param $flags int The flag constant for htmlspecialchars
	 * @param $double_encode bool Whether or not to double-encode strings
	 */
	protected function sanitizeInput( &$value, $key, $flags=ENT_COMPAT, $double_encode=false ) {
		$value = htmlspecialchars( $value, $flags, 'UTF-8', $double_encode );
	}

	/**
	 * log: This grabs the adapter class that instantiated DonationData, and
	 * uses its log function.
	 * @TODO: Once the DonationData constructor does less, we can stop using
	 * the static log function in the gateway. As it is, we're trying to log
	 * things as we're constructing, when as far as the gateway cares we
	 * don't exist yet. Very circular.
	 * @param string $message The message to log.
	 * @param int|string $log_level
	 */
	protected function log( $message, $log_level = LOG_INFO ) {
		$message = $this->getLogMessagePrefix() . $message;
		$this->gateway->_log( $message, $log_level );
	}

	/**
	 * getGatewayIdentifier
	 * This grabs the adapter class that instantiated DonationData, and returns 
	 * the result of its 'getIdentifier' function. Used for normalizing the 
	 * 'gateway' value, and stashing and retrieving the edit token (and other 
	 * things, where needed) in the session. 
	 * @return type 
	 */
	protected function getGatewayIdentifier() {
		$c = $this->getAdapterClass();
		if ( $c && is_callable( array( $c, 'getIdentifier' ) ) ){
			return $c::getIdentifier();
		} else {
			return 'DonationData';
		}
	}

	/**
	 * getGatewayGlobal
	 * This grabs the adapter class that instantiated DonationData, and returns 
	 * the result of its 'getGlobal' function for the $varname passed in. Used 
	 * to determine gateway-specific configuration settings. 
	 * @param string $varname the global variable (minus prefix) that we want to 
	 * check. 
	 * @return mixed  The value of the gateway global if it exists. Else, the 
	 * value of the Donation Interface global if it exists. Else, null.
	 */
	protected function getGatewayGlobal( $varname ) {
		$c = $this->getAdapterClass();
		if ( $c && is_callable( array( $c, 'getGlobal' ) ) ){
			return $c::getGlobal( $varname );
		} else {
			return false;
		}
	}

	/**
	 * normalize helper function.
	 * Sets the gateway to be the gateway that called this class in the first 
	 * place.
	 */
	protected function setGateway() {
		//TODO: Hum. If we have some other gateway in the form data, should we go crazy here? (Probably)
		$gateway = $this->gatewayID;
		$this->setVal( 'gateway', $gateway );
	}
	
	/**
	 * normalize helper function.
	 * If the language has not yet been set or is not valid, pulls the language code 
	 * from the current global language object. 
	 * Also sets the premium_language as the calculated language if it's not 
	 * already set coming in (had been defaulting to english). 
	 */
	protected function setLanguage() {
		global $wgLang;
		$language = false;

		if ( $this->isSomething( 'uselang' ) ) {
			$language = $this->getVal( 'uselang' );
		} elseif ( $this->isSomething( 'language' ) ) {
			$language = $this->getVal( 'language' );
		}
		
		if ( $language == false || !Language::isValidBuiltInCode( $language ) ) {
			$language = $wgLang->getCode();
		}
		
		$this->setVal( 'language', $language );
		$this->expunge( 'uselang' );
		
		if ( !$this->isSomething( 'premium_language' ) ){
			$this->setVal( 'premium_language', $language );
		}
		
	}

	/**
	 * Normalize email
	 * Check regular name, and horrible old name for values (preferring the
	 * reasonable name over the legacy version)
	 * Set the value to 'nobody@wikimedia.org' if nothing has been entered.
	 */
	protected function setEmail() {
		// Look at the old style value (because that's canonical if populated first)
		$email = $this->getVal( 'emailAdd' );
		if ( is_null( $email ) ) {
			$email = $this->getVal( 'email' );
		}

		if ( is_null( $email ) ) {
			// We still have nothing, populate with default
			$email = 'nobody@wikimedia.org';
		}

		$this->setVal( 'email', $email );
		$this->expunge( 'emailAdd' );
	}

	/**
	 * getLogMessagePrefix
	 * Constructs and returns the standard ctid:order_id log line prefix.
	 * The gateway function of identical name now calls this one, because
	 * DonationData always has fresher data.
	 * @return string "ctid:order_id " 
	 */
	public function getLogMessagePrefix() {
		return $this->getVal( 'contribution_tracking_id' ) . ':' . $this->getVal( 'order_id' ) . ' ';
	}

	/**
	 * normalize helper function.
	 * 
	 * the utm_source is structured as: banner.landing_page.payment_method_family
	 */
	protected function setUtmSource() {
		
		$utm_source = $this->getVal( 'utm_source' );
		$utm_source_id = $this->getVal( 'utm_source_id' );
		
		$payment_method_family = PaymentMethod::getUtmSourceName(
			$this->getVal( 'payment_method' ),
			$this->getVal( 'recurring' )
		);

		$this->log( "Setting utm_source payment method to {$payment_method_family}", LOG_INFO );

		// split the utm_source into its parts for easier manipulation
		$source_parts = explode( ".", $utm_source );

		// If we don't have the banner or any utm_source, set it to the empty string.
		if ( empty( $source_parts[0] ) ) {
			$source_parts[0] = '';
		}

		// If the utm_source_id is set, include that in the landing page
		// portion of the string.
		if ( $utm_source_id ) {
			$source_parts[1] = $payment_method_family . $utm_source_id;
		} else {
			if ( empty( $source_parts[1] ) ) {
				$source_parts[1] = '';
			}
		}

		$source_parts[2] = $payment_method_family;
		if ( empty( $source_parts[2] ) ) {
			$source_parts[2] = '';
		}

		// reconstruct, and set the value.
		$utm_source = implode( ".", $source_parts );
		$this->setVal( 'utm_source' , $utm_source );
	}

	/**
	 * Clean array of tracking data to contain valid fields
	 *
	 * Compares tracking data array to list of valid tracking fields and
	 * removes any extra tracking fields/data.  Also sets empty values to
	 * 'null' values.
	 * @param bool $unset If set to true, empty values will be unset from the 
	 * return array, rather than set to null. (default: false)
	 * @return array Clean tracking data 
	 */
	public function getCleanTrackingData( $unset = false ) {
		global $wgContributionTrackingAnalyticsUpgrade;

		// define valid tracking fields
		$tracking_fields = array(
			'note',
			'referrer',
			'anonymous',
			'utm_source',
			'utm_medium',
			'utm_campaign',
			'utm_key',
			'optout',
			'language',
			'country',
			'ts'
		);

		$tracking_data = array();

		foreach ( $tracking_fields as $value ) {
			if ( $this->isSomething( $value ) ) {
				$tracking_data[$value] = $this->getVal( $value );
			} else {
				if ( !$unset ){
					$tracking_data[$value] = null;
				}
			}
		}

		if( $this->isSomething( 'currency_code' ) && $this->isSomething( 'amount' ) ){
			$tracking_data['form_amount'] = $this->getVal( 'currency_code' ) . " " . $this->getVal( 'amount' );
		}
		if( $this->isSomething( 'form_name' ) ){
			$tracking_data['payments_form'] = $this->getVal( 'form_name' );
			if( $this->isSomething( 'ffname' ) ){
				$tracking_data['payments_form'] .= '.' . $this->getVal( 'ffname' );
			}
		}

		return $tracking_data;
	}

	/**
	 * Inserts a new or updates a record in the contribution_tracking table.
	 *
	 * @return mixed Contribution tracking ID or false on failure
	 */
	public function saveContributionTrackingData() {
		$ctid = $this->getVal( 'contribution_tracking_id' );
		$tracking_data = $this->getCleanTrackingData( true );
		$db = ContributionTrackingProcessor::contributionTrackingConnection();

		if ( !$db ) {
			// TODO: This might be a critical failure; do we want to throw an exception instead?
			$this->log( 'Failed to create a connect to contribution_tracking database', LOG_ERR );
			return false;
		}

		if ( $ctid ) {
			// We're updating a record, but only if we actually have data to update
			if ( count( $tracking_data ) ) {
				$db->update(
					'contribution_tracking',
					$tracking_data,
					array( 'id' => $ctid )
				);
			}
		} else {
			// We need a new record
			// set the time stamp if it's not already set
			if ( !isset( $tracking_data['ts'] ) || !strlen( $tracking_data['ts'] ) ) {
				$tracking_data['ts'] = $db->timestamp();
			}

			// Store the contribution data
			if ( $db->insert( 'contribution_tracking', $tracking_data ) ) {
				$ctid =  $db->insertId();
			} else {
				$this->log( 'Failed to create a new contribution_tracking record', LOG_ERR );
				return false;
			}
		}
		return $ctid;
	}

	/**
	 * Adds an array of data to the normalized array, and then re-normalizes it. 
	 * NOTE: If any gateway is using this function, it should then immediately 
	 * repopulate its own data set with the DonationData source, and then 
	 * re-stage values as necessary.
	 *
	 * @param array $newdata An array of data to integrate with the existing 
	 * data held by the DonationData object.
	 */
	public function addData( $newdata ) {
		if ( is_array( $newdata ) && !empty( $newdata ) ) {
			foreach ( $newdata as $key => $val ) {
				if ( !is_array( $val ) ) {
					$this->setVal( $key, $val );
				}
			}
		}
		$this->normalize();
	}

	/**
	 * Gets the name of the adapter class that instantiated DonationData. 
	 * @return mixed The name of the class if it exists, or false. 
	 */
	protected function getAdapterClass(){
		return get_class( $this->gateway );
	}
	
	/**
	 * Returns an array of field names we intend to send to activeMQ via a Stomp 
	 * message. Note: These are field names from the FORM... not the field names 
	 * that will appear in the stomp message. 
	 * TODO: Move the mapping for donation data from 
	 * /extensions/DonationData/activemq_stomp/activemq_stomp.php
	 * to somewhere in DonationData. 	 * 
	 */
	public static function getStompMessageFields() {
		$stomp_fields = array(
			'contribution_tracking_id',
			'optout',
			'anonymous',
			'size',
			'premium_language',
			'utm_source',
			'utm_medium',
			'utm_campaign',
			'language',
			'referrer',
			'email',
			'fname',
			'lname',
			'street',
			'street_supplemental',
			'city',
			'state',
			'country',
			'zip',
			'gateway',
			'gateway_account',
			'gateway_txn_id',
			'recurring',
			'payment_method',
			'payment_submethod',
			'response',
			'currency_code',
			'amount',
			'user_ip',
			'date',
		);
		return $stomp_fields;
	}

	/**
	 * Returns an array of field names we need in order to retry a payment
	 * after the session has been destroyed by... overzealousness.
	 */
	public static function getRetryFields() {
		$fields = array (
			'gateway',
			'country',
			'currency_code',
			'amount',
			'language',
			'utm_source',
			'utm_medium',
			'utm_campaign',
			'payment_method',
		);
		return $fields;
	}

	/**
	 * Basically, this is a wrapper for the $wgRequest wasPosted function that 
	 * won't give us notices if we weren't even a web request. 
	 * I realize this is pretty lame. 
	 * Notices, however, are more lame. 
	 * @staticvar string $posted Keeps track so we don't have to figure it out twice.
	 */
	public function wasPosted(){
		global $wgRequest;
		static $posted = null;
		if ($posted === null){
			$posted = (array_key_exists('REQUEST_METHOD', $_SERVER) && $wgRequest->wasPosted());
		}
		return $posted; 
	}
	
	/**
	 * getValidationErrors
	 * This function will go through all the data we have pulled from wherever 
	 * we've pulled it, and make sure it's safe and expected and everything. 
	 * If it is not, it will return an array of errors ready for any 
	 * DonationInterface form class derivitive to display. 
	 */
	public function getValidationErrors( $recalculate = false, $check_not_empty = array() ){
		if ( is_null( $this->validationErrors ) || $recalculate ) {
			$this->validationErrors = DataValidator::validate( $this->gateway, $this->normalized, $check_not_empty );
		}
		return $this->validationErrors;
	}
	
	/**
	 * validatedOK
	 * Checks to see if the data validated ok (no errors). 
	 * @return boolean True if no errors, false if errors exist. 
	 */
	public function validatedOK() {
		if ( is_null( $this->validationErrors ) ){
			$this->getValidationErrors();
		}
		
		if ( count( $this->validationErrors ) === 0 ){
			return true;
		}
		return false;
	}

	/**
	 * Take data from the return get string; must be in the passed in var_map. After calling this
	 * function data will need to be restated.
	 *
	 * @param $var_map
	 */
	public function addVarMapDataFromURI( $var_map ) {
		global $wgRequest;

		// Obtain data parameters for STOMP message injection
		//n.b. these request vars were from the _previous_ api call
		$add_data = array();
		foreach ( $var_map as $gateway_key => $normal_key ) {
			$value = $wgRequest->getVal( $gateway_key, null );
			if ( !empty( $value ) ) {
				// Deal with some fun special cases
				switch ( $gateway_key ) {
					case 'transactionAmount':
						list ($currency, $amount) = explode( ' ', $value );
						$add_data[ 'currency' ] = $currency;
						$add_data[ 'amount' ] = $amount;
						break;

					case 'buyerName':
						list ($fname, $lname) = explode( ' ', $value, 2 );
						$add_data[ 'fname' ] = $fname;
						$add_data[ 'lname' ] = $lname;
						break;

					default:
						$add_data[ $normal_key ] = $value;
						break;
				}
			}
		}

		//TODO: consider prioritizing the session vars
		$this->addData( $add_data );
	}
}

?>
