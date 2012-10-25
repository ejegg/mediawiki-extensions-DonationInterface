<?php
	/**
	 * ensureSession
	 * Ensure that we have a session set for the current user.
	 * If we do not have a session set for the current user,
	 * start the session.
	 * BE CAREFUL with this one, as creating sessions willy-nilly will break 
	 * squid caching for reasons that are not immediately obvious. 
	 * (See DonationData::doCacheStuff, and basically everything about setting 
	 * headers in $wgOut)
	 */
	protected static function ensureSession() {
		// if the session is already started, do nothing
		if ( self::sessionExists() )
			return;

		// otherwise, fire it up using global mw function wfSetupSession
		wfSetupSession();
	}
	
	/**
	 * sessionExists
	 * Checks to see if the session exists without actually creating one. 
	 * @return bool true if we have a session, otherwise false.  
	 */
	protected static function sessionExists() {
		if ( session_id() )
			return true;
		return false;
	}

	/**
	 * addDonorDataToSession
	 * Adds all the fields that are required to make a well-formed stomp 
	 * message, to the user's session for later use. This mechanism is used by gateways that 
	 * have a user being directed somewhere out of our control, and then coming 
	 * back to complete a transaction. (Globalcollect Hosted Credit Card, for 
	 * example)
	 * 
	 */
	public function addDonorDataToSession() {
		self::ensureSession();
		$donordata = $this->getStompMessageFields();
		$donordata[] = 'order_id';
		
		foreach ( $donordata as $item ) {
			if ( $this->isSomething( $item ) ) {
				$_SESSION['Donor'][$item] = $this->getVal( $item );
			}
		}
	}
	
	/**
	 * Checks to see if we have donor data in our session. 
	 * This can be useful for determining if a user should be at a certain point 
	 * in the workflow for certain gateways. For example: This is used on the 
	 * outside of the adapter in GlobalCollect's resultswitcher page, to 
	 * determine if the user is actually in the process of making a credit card 
	 * transaction. 
	 * @param string $key Optional: A particular key to check against the 
	 * donor data in session. 
	 * @param string $value Optional (unless $key is set): A value that the $key 
	 * should contain, in the donor session.  
	 * @return boolean true if the session contains donor data (and if the data 
	 * key matches, when key and value are set), and false if there is no donor 
	 * data (or if the key and value do not match)
	 */
	public function hasDonorDataInSession(  $key = false, $value= ''  ) {
		if ( self::sessionExists() && array_key_exists( 'Donor', $_SESSION ) ) {
			if ( $key == false ){
				return true;
			}
			if ( array_key_exists($key, $_SESSION['Donor'] ) && $_SESSION['Donor'][$key] === $value ){
				return true;
			} else {
				return false;
			}
			
			
		} else {
			return false;
		}
	}

	/**
	 * Unsets the session data, in the case that we've saved it for gateways 
	 * like GlobalCollect that require it to persist over here through their 
	 * iframe experience. 
	 */
	public function unsetDonorSessionData() {
		unset( $_SESSION['Donor'] );
	}
	
	/**
	 * This should kill the session as hard as possible.
	 * It will leave the cookie behind, but everything it could possibly 
	 * reference will be gone. 
	 */
	public function killAllSessionEverything() {
		//yes: We do need all of these things, to be sure we're killing the 
		//correct session data everywhere it could possibly be. 
		self::ensureSession(); //make sure we are killing the right thing. 
		session_unset(); //frees all registered session variables. At this point, they can still be re-registered. 
		session_destroy(); //killed on the server. 
	}

