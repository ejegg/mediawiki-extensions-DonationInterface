<?php
class WMF_Incoming_Donation {
	function __construct( $gateway ) {
		$this->membrane = new Membrane(
			array(
				new Donor(),
				new Donation(),
				new WMFTracking(),
				$gateway,
			)
		);
	}

	function execute( $action, $data ) {
		$this->membrane->addData( $data );
		$this->membrane->integrateDataFromSession();
	}

 /* extends Payee ??? */
class Donor {
	public $boss; //XXX what about?  restrict which operations we might want to do
	protected $validationErrors = null; //XXX part of validator?

		$this->values = array();
		foreach ( $this->fields as $name ) {
			$this->membrane[ $name ] = $wgRequest->getText( $name, null ); // XXX forcing null if unset is unuseful?
		}

				//XXX not from request, just null:
				'user_ip',
				'server_ip',
	}

	function __construct() {
		$this->defaults = array(
			'amount' => null,
			'amountGiven' => null,
			'amountOther' => null,
			'numAttempt' => 0,
		);
		$this->fields = array(
			'amount',
			'amountGiven',
			'amountOther',
			'email',
			'fname',
			'mname',
			'lname',
			'street',
			'street_supplemental',
			'city',
			'state',
			'zip',
			'country',
			// XXX *-2 fields??
			'size',
			'premium_language',
			'card_num',
			'card_type',
			'expiration',
			'cvv',
			//Leave both of the currencies here, in case something external didn't get the memo.
			'currency',
			'currency_code',
			'payment_method',
			'payment_submethod',
			'issuer_id',
			'order_id',
			'i_order_id',
			'numAttempt',
			'referrer',
			'utm_source',
			'utm_source_id',
			'utm_medium',
			'utm_campaign',
			'utm_key',
			// Pull both of these here. We can logic out which one to use in the normalize bits. 
			'language',
			'uselang',
			'comment',
			'_cache_',
			'token',
			'contribution_tracking_id',
			'data_hash',
			'action',
			'gateway',
			'owa_session',
			'owa_ref',
			'descriptor',

			'account_name',
			'account_number',
			'authorization_id',
			'bank_check_digit',
			'bank_name',
			'bank_code',
			'branch_code',
			'country_code_bank',
			'date_collect',
			'direct_debit_text',
			'iban',
			'transaction_type',
			'form_name',
			'ffname',
			'recurring',
			'user_ip',
			'server_ip',
		);

		//if we have saved any donation data to the session, pull them in as well.
		$this->integrateDataFromSession();

		$this->doCacheStuff();

		$this->normalize();

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
			'i_order_id',
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
	//XXX permutation cannot cause fail, so never read from normalizzed while normalizing

	//XXX ??? public function isCaching(){
	/**
	 * normalize helper function.
	 * If the language has not yet been set or is not valid, pulls the language code 
	 * from the current global language object. 
	 * Also sets the premium_language as the calculated language if it's not 
	 * already set coming in (had been defaulting to english). 
	 */
	protected function normalize_language() {
		global $wgLang;
		$language = false;
		
		if ( $this->isSomething( 'uselang' ) ) {
			$language = $this->getVal( 'uselang' );
		} elseif ( $this->isSomething( 'language' ) ) {
			$language = $this->getVal( 'language' );
		}
		
		if ( $language == false
			|| !Language::isValidBuiltInCode( $this->normalized['language'] ) )
		{
			$language = $wgLang->getCode() ;
		}
		
		$this->setVal( 'language', $language );
		$this->expunge( 'uselang' );
		
		if ( !$this->isSomething( 'premium_language' ) ){
			$this->setVal( 'premium_language', $language );
		}
		
	}
	//XXX backoff... grouped with what functionality?
	/**
	 * incrementNumAttempt
	 * Adds one to the 'numAttempt' field we use to keep track of how many times 
	 * a donor has tried to do something. 
	 */
	public function incrementNumAttempt() {
		if ( $this->isSomething( 'numAttempt' ) ) {
			$attempts = $this->getVal( 'numAttempt' );
			if ( is_numeric( $attempts ) ) {
				$this->setVal( 'numAttempt', $attempts + 1 );
			} else {
				//assume garbage = 0, so...
				$this->setVal( 'numAttempt', 1 );
			}
		}
	}
	
	/**
	 * Returns an array of field names we intend to send to activeMQ via a Stomp 
	 * message. Note: These are field names from the FORM... not the field names 
	 * that will appear in the stomp message. 
	 * TODO: Move the mapping for donation data from 
	 * /extensions/DonationData/activemq_stomp/activemq_stomp.php
	 * to somewhere in DonationData. 	 * 
	 */
	public function getMessageFields(){
		$stomp_fields = array(
			'contribution_tracking_id',
			'optout',
			'anonymous',
			'comment',
			'size',
			'premium_language',
			'utm_source',
			'utm_medium',
			'utm_campaign',
			'language',
			'referrer',
			'email',
			'fname',
			'mname',
			'lname',
			'street',
			'street_supplemental',
			'city',
			'state',
			'country',
			'zip',
			'fname2',
			'lname2',
			'street2',
			'city2',
			'state2',
			'country2',
			'zip2',
			'gateway',
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
		return $fields;
	}
	
	/**
	 * validate_email
	 * Determines if the $value passed in is a valid email address. 
	 * @param string $value The piece of data that is supposed to be an email 
	 * address. 
	 * @return boolean True if $value is a valid email address, otherwise false.  
	 */
	protected static function validate_email( $value ){
		// is email address valid?
		$isEmail = Sanitizer::validateEmail( $value );
		return $isEmail;
	}
	/**
	 * Takes either an IP address, or an IP address with a CIDR block, and 
	 * expands it to an array containing all the relevent addresses so we can do 
	 * things like save the expanded list to memcache, and use in_array(). 
	 * @param string $ip Either a single address, or a block. 
	 * @return array An expanded list of IP addresses denoted by $ip. 
	 */
	public static function expandIPBlockToArray( $ip ){
		$parts = explode('/', $ip);
		if ( count( $parts ) === 1 ){
			return array( $ip );
		} else {
			//expand that mess.
			//this next bit was stolen from php.net and smacked around some
			$corr = ( pow( 2, 32 ) - 1) - ( pow( 2, 32 - $parts[1] ) - 1 );
			$first = ip2long( $parts[0] ) & ( $corr );
			$length = pow( 2, 32 - $parts[1] ) - 1;
			$ips = array( );
			for ( $i = 0; $i <= $length; $i++ ) {
				$ips[] = long2ip( $first + $i );
			}
			return $ips;
		}
	}
	
	/**
	 * Eventually, this function should pull from here and memcache. 
	 * @staticvar array $blacklist A cached and expanded blacklist
	 * @param string $ip The IP addx we want to check
	 * @param string $list_name The global list, ostensibly full of IP addresses, 
	 * that we want to check against.
	 * @param string $gateway The gateway we're concerned with. Only matters if, 
	 * for instance, $wgDonationInterfaceIPBlacklist is different from 
	 * $wgGlobalcollectGatewayIPBlacklist for some silly reason.
	 */
	public static function ip_is_listed( $ip, $list_name, $gateway = '' ) {
		//cache this mess
		static $ip_list_cache = array();
		$globalIPLists = array(
			'IPWhitelist',
			'IPBlacklist',
		);
		
		if ( !in_array( $list_name, $globalIPLists ) ){
			throw new MWException( __FUNCTION__ . " BAD PROGRAMMER. No recognized global list of IPs called $list_name. Do better." );
		}
		
		$class = self::getGatewayClass( $gateway );
		if ( !$class ){
			$class = 'GatewayAdapter';
		}
		
		if ( !array_key_exists( $class, $ip_list_cache ) || !array_key_exists( $list_name, $ip_list_cache[$class] ) ){
			//go get it and expand the block entries
			$list = $class::getGlobal( $list_name );
			$expanded = array();
			foreach ( $list as $address ){
				$expanded = array_merge( $expanded, self::expandIPBlockToArray( $address ) );
			}
			$ip_list_cache[$class][$list_name] = $expanded;
			//TODO: This seems like an excellent time to stash this expanded 
			//thing in memcache. Later, we can look for that value earlier. Yup.
		}
		
		if ( in_array( $ip, $ip_list_cache[$class][$list_name] ) ){
			return true;
		} else {
			return false;
		}
	}
	
}

?>
