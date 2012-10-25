<?php

class Tracking {
class WMF_Tracking {
				'referrer' => ( $wgRequest->getVal( 'referrer' ) ) ? $wgRequest->getVal( 'referrer' ) : $wgRequest->getHeader( 'referer' ),

	protected function getDonationId/*Detailed*/() {
		return $this->getVal( 'order_id' ) . ' ' . $this->getVal( 'i_order_id' ) . ': ';
	}
	/**
	 * normalize helper function.
	 * 
	 * Checks to see if the utm_source is set properly for the credit card
	 * form including any cc form variants (identified by utm_source_id).  If
	 * anything cc form related is out of place for the utm_source, this
	 * will fix it.
	 *
	 * the utm_source is structured as: banner.landing_page.payment_instrument
	 */
	protected function normalize_UtmSource() {
		
		$utm_source = $this->getVal( 'utm_source' );
		$utm_source_id = $this->getVal( 'utm_source_id' );
		
		//TODO: Seriously, you need to move this. 
		if ( $this->isSomething('payment_method') ){
			$payment_method = $this->getVal( 'payment_method' );
		} else {
			$payment_method = 'cc';
		}
		
		// this is how the payment method portion of the utm_source should be defined
		$correct_payment_method_source = ( $utm_source_id ) ? $payment_method . $utm_source_id . '.' . $payment_method : $payment_method;

		// check to see if the utm_source is already correct - if so, return
		if ( !is_null( $utm_source ) && preg_match( '/' . str_replace( ".", "\.", $correct_payment_method_source ) . '$/', $utm_source ) ) {
			return; //nothing to do. 
		}

		// split the utm_source into its parts for easier manipulation
		$source_parts = explode( ".", $utm_source );

		// if there are no sourceparts element, then the banner portion of the string needs to be set.
		// since we don't know what it is, set it to an empty string
		if ( !count( $source_parts ) )
			$source_parts[0] = '';

		// if the utm_source_id is set, set the landing page portion of the string to cc#
		$source_parts[1] = ( $utm_source_id ) ? $payment_method . $utm_source_id : ( isset( $source_parts[1] ) ? $source_parts[1] : '' );

		// the payment instrument portion should always be 'cc' if this method is being accessed
		$source_parts[2] = $payment_method;

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
			'ts'
		);

		foreach ( $tracking_fields as $value ) {
			if ( $this->isSomething( $value ) ) {
				$tracking_data[$value] = $this->getVal( $value );
			} else {
				if ( !$unset ){
					$tracking_data[$value] = null;
				}
			}
		}

		return $tracking_data;
	}

	/**
	 * Saves a NEW ROW in the Contribution Tracking table and returns the new ID. 
	 * @return boolean true if we got a contribution tracking # back, false if 
	 * something went wrong.  
	 */
	public function saveContributionTracking() {

		$tracked_contribution = $this->getCleanTrackingData();

		// insert tracking data and get the tracking id
		$result = self::insertContributionTracking( $tracked_contribution );

		$this->setVal( 'contribution_tracking_id', $result );

		if ( !$result ) {
			return false;
		}
		return true;
	}

	/**
	 * Insert a record into the contribution_tracking table
	 *
	 * @param array $tracking_data The array of tracking data to insert to contribution_tracking
	 * @return mixed Contribution tracking ID or false on failure
	 */
	public static function insertContributionTracking( $tracking_data ) {
		$db = ContributionTrackingProcessor::contributionTrackingConnection();

		if ( !$db ) {
			return false;
		}

		// set the time stamp if it's not already set
		if ( !isset( $tracking_data['ts'] ) || !strlen( $tracking_data['ts'] ) ) {
			$tracking_data['ts'] = $db->timestamp();
		}

		// Store the contribution data
		if ( $db->insert( 'contribution_tracking', $tracking_data ) ) {
			return $db->insertId();
		} else {
			return false;
		}
	}

	/**
	 * Update contribution_tracking table
	 *
	 * @param array $data Form data
	 * @param bool $force If set to true, will ensure that contribution tracking is updated
	 */
	public function updateContributionTracking( $force = false ) {
		// ony update contrib tracking if we're coming from a single-step landing page
		// which we know with cc# in utm_source or if force=true or if contribution_tracking_id is not set
		if ( !$force &&
			!preg_match( "/cc[0-9]/", $this->getVal( 'utm_source' ) ) &&
			is_numeric( $this->getVal( 'contribution_tracking_id' ) ) ) {
			return;
		}

		$db = ContributionTrackingProcessor::contributionTrackingConnection();

		// if contrib tracking id is not already set, we need to insert the data, otherwise update
		if ( !$this->getVal( 'contribution_tracking_id' ) ) {
			$tracked_contribution = $this->getCleanTrackingData();
			$this->setVal( 'contribution_tracking_id', $this->insertContributionTracking( $tracked_contribution ) );
		} else {
			$tracked_contribution = $this->getCleanTrackingData( true );
			$db->update( 'contribution_tracking', $tracked_contribution, array( 'id' => $this->getVal( 'contribution_tracking_id' ) ) );
		}
	}


	/**
	 * Resets the order ID and re-normalizes the data set. This effectively creates a new
	 * transaction.
	 */
	public function resetOrderId() {
		$this->expunge( 'order_id' );
		$this->normalize();
	}

	/**
	 * normalize helper function.
	 * Ensures that order_id and i_order_id are ready to go, depending on what 
	 * comes in populated or not, and where it came from.
	 *
	 * @return null
	 */
	protected function normalize_NormalizedOrderIDs( ) {

		static $idGenThisRequest = false;
		$id = null;

		// We need to obtain and set the order_id every time this function is called. If there's
		// one already in existence (ie: in the GET string) we will use that one.
		if ( array_key_exists( 'order_id', $_GET ) ) {
			// This must come only from the get string. It's there to process return calls.
			// TODO: Move this somewhere more sane! We shouldn't be doing anything with requests
			// in normalization functions.
			$id = $_GET['order_id'];
		} elseif ( ( $this->isSomething( 'order_id' ) ) && ( $idGenThisRequest == true ) ) {
			// An order ID already exists, therefore we do nothing
			$id = $this->getVal( 'order_id' );
		} else {
			// Apparently we need a new one
			$idGenThisRequest = true;
			$id = $this->generateOrderId();
		}

		// HACK: We used to have i_order_id remain consistent; but that might confuse things,
		// so now it just follows order_id; and we only keep it for legacy reasons (ie: I have
		// no idea what it would do if I removed it.)

		$this->setVal( 'order_id', $id );
		$this->setVal( 'i_order_id', $id );
	}

	/**
	 * Generate an order id
	 *
	 * @return A randomized order ID
	 */
	protected static function generateOrderId() {
		$order_id = ( double ) microtime() * 1000000 . mt_rand( 1000, 9999 );

		return $order_id;
	}

	/**
	 * normalize helper function.
	 * Assures that if no contribution_tracking_id is present, a row is created 
	 * in the Contribution tracking table, and that row is assigned to the 
	 * current contribution we're tracking. 
	 * If a contribution tracking id is already present, no new rows will be 
	 * assigned. 
	 */
	protected function handleContributionTrackingID(){
		if ( !$this->isSomething( 'contribution_tracking_id' ) && 
			( !$this->isCaching() ) ){
			$this->saveContributionTracking();
		} 
	}
	
