<?php
//actually, as a maintenance script, this totally is a valid entry point. 

//If you want to use this script, you will have to add the following line to LocalSettings.php:
//$wgAutoloadClasses['GlobalCollectOrphanAdapter'] = $IP . '/extensions/DonationInterface/globalcollect_gateway/scripts/orphan_adapter.php';

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../../..';
}

//If you get errors on this next line, set (and export) your MW_INSTALL_PATH var. 
require_once( "$IP/maintenance/Maintenance.php" );

class GlobalCollectOrphanRectifier extends Maintenance {

	protected $killfiles = array();
	protected $order_ids = array();
	protected $target_execute_time;
	protected $max_per_execute; //only really used if you're going by-file.
	protected $adapter;

	public function execute() {
		// Have to turn this off here, until we know it's using the user's ip, and
		// not 127.0.0.1 during the batch process.  Otherwise, we'll immediately
		// lock ourselves out when processing multiple charges.
		global $wgDonationInterfaceEnableIPVelocityFilter;
		$wgDonationInterfaceEnableIPVelocityFilter = false;

		if ( !$this->getOrphanGlobal( 'enable' ) ){
			$this->warning( 'Orphan cron disabled. Have a nice day.' );
			return;
		}

		$this->target_execute_time = $this->getOrphanGlobal( 'target_execute_time' );
		$this->max_per_execute = $this->getOrphanGlobal( 'max_per_execute' );

		// FIXME: Is this just to trigger batch mode?
		$data = array(
			'wheeee' => 'yes'
		);
		$this->adapter = new GlobalCollectOrphanAdapter(array('external_data' => $data));
		$this->logger = DonationLoggerFactory::getLogger( $this->adapter );

		//Now, actually do the processing. 
		$this->orphan_stomp();
	}

	protected function orphan_stomp(){
		echo "Orphan Stomp\n";
		$this->removed_message_count = 0;
		$this->start_time = time();

		//I want to be clear on the problem I hope to prevent with this.  Say,
		//for instance, we pull a legit orphan, and for whatever reason, can't
		//completely rectify it.  Then, we go back and pull more... and that
		//same one is in the list again. We should stop after one try per
		//message per execute.  We should also be smart enough to not process
		//things we believe we just deleted.
		$this->handled_ids = array();

		//first, we need to... clean up the limbo queue. 

		// TODO: Remove STOMP code.
		//building in some redundancy here.
		$collider_keepGoing = true;
		$am_called_count = 0;
		while ( $collider_keepGoing ){
			$antimessageCount = $this->handleStompAntiMessages();
			$am_called_count += 1;
			if ( $antimessageCount < 10 ){
				$collider_keepGoing = false;
			} else {
				sleep(2); //two seconds. 
			}
		}
		$this->logger->info( 'Removed ' . $this->removed_message_count . ' messages and antimessages.' );

		do {
			//Pull a batch of CC orphans, keeping in mind that Things May Have Happened in the small slice of time since we handled the antimessages. 
			$orphans = $this->getOrphans();
			echo count( $orphans ) . " orphans left this batch\n";
			//..do stuff.
			foreach ( $orphans as $correlation_id => $orphan ) {
				//process
				if ( $this->keepGoing() ){
					// TODO: Maybe we can simplify by checking that modified time < job start time.
					$this->logger->info( "Attempting to rectify orphan $correlation_id" );
					if ( $this->rectifyOrphan( $orphan ) ) {
						$this->handled_ids[$correlation_id] = 'rectified';
					} else {
						$this->handled_ids[$correlation_id] = 'error';
					}
				}
			}
		} while ( count( $orphans ) && $this->keepGoing() );

		//TODO: Make stats squirt out all over the place.  
		$am = 0;
		$rec = 0;
		$err = 0;
		$fe = 0;
		foreach( $this->handled_ids as $id=>$whathappened ){
			switch ( $whathappened ){
				case 'antimessage' : 
					$am += 1;
					break;
				case 'rectified' : 
					$rec += 1;
					break;
				case 'error' :
					$err += 1;
					break;
				case 'false_orphan' :
					$fe += 1;
					break;
			}
		}
		$final = "\nDone! Final results: \n";
		$final .= " $am destroyed via antimessage (called $am_called_count times) \n";
		$final .= " $rec rectified orphans \n";
		$final .= " $err errored out \n";
		$final .= " $fe false orphans caught \n";
		if ( isset( $this->adapter->orphanstats ) ){
			foreach ( $this->adapter->orphanstats as $status => $count ) {
				$final .= "\n   Status $status = $count";
			}
		}
		$final .= "\n Approximately " . $this->getProcessElapsed() . " seconds to execute.\n";
		$this->logger->info( $final );
		echo $final;
	}

	protected function keepGoing(){
		$elapsed = $this->getProcessElapsed();
		if ( $elapsed < $this->target_execute_time ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * This will both return the elapsed process time, and echo something for 
	 * the cronspammer.
	 * @return int elapsed time since start in seconds
	 */
	protected function getProcessElapsed(){
		$elapsed = time() - $this->start_time;
		echo "Elapsed Time: $elapsed\n";
		return $elapsed;
	}

	function addStompCorrelationIDToAckBucket( $correlation_id, $ackNow = false ){
		static $bucket = array();
		$count = 50; //sure. Why not?
		if ( $correlation_id ) {
			$bucket[$correlation_id] = "'$correlation_id'"; //avoiding duplicates.
			$this->handled_ids[$correlation_id] = 'antimessage';

			// Delete from Memcache
			DonationQueue::instance()->delete(
				$correlation_id, GlobalCollectAdapter::GC_CC_LIMBO_QUEUE );
		}
		if ( count( $bucket ) && ( count( $bucket ) >= $count || $ackNow ) ){
			//ack now.
			echo 'Acking ' . count( $bucket ) . " bucket messages.\n";
			$selector = 'JMSCorrelationID IN (' . implode( ", ", $bucket ) . ')';
			$ackMe = stompFetchMessages( 'cc-limbo', $selector, $count * 100 ); //This is outrageously high, but I just want to be reasonably sure we get all the matches. 
			$retrieved_count = count( $ackMe );
			if ( $retrieved_count ){
				stompAckMessages( $ackMe );
				$this->removed_message_count += $retrieved_count;
				echo "Done acking $retrieved_count messages. \n";
			} else {
				echo "Oh noes! No messages retrieved for $selector...\n";
			}
			$bucket = array();
		}

	}

	function handleStompAntiMessages(){
		$selector = "antimessage = 'true' AND gateway='globalcollect'";
		$antimessages = stompFetchMessages( 'cc-limbo', $selector, 1000 );
		$count = 0;
		while ( count( $antimessages ) > 10 && $this->keepGoing() ){ //if there's an antimessage, we can ack 'em all right now. 
			echo "Colliding " . count( $antimessages ) . " antimessages\n";
			$count += count( $antimessages );
			foreach ( $antimessages as $message ){
				//add the correlation ID to the ack bucket. 
				if (array_key_exists('correlation-id', $message->headers)) {
					$this->addStompCorrelationIDToAckBucket( $message->headers['correlation-id'] );
				} else {
					echo 'The STOMP message ' . $message->headers['message-id'] . " has no correlation ID!\n";
				}
			}
			$this->addStompCorrelationIDToAckBucket( false, true ); //ack all outstanding.
			$antimessages = stompFetchMessages( 'cc-limbo', $selector, 1000 );
		}
		$this->addStompCorrelationIDToAckBucket( false, true ); //this just acks everything that's waiting for it.
		$this->logger->info( "Found $count antimessages." );
		return $count;
	}

	protected function getOrphans() {
		// TODO: Make this configurable.
		$time_buffer = 60*20; //20 minutes? Sure. Why not?

		$orphans = array();
		$false_orphans = array();
		while ( $message = DonationQueue::instance()->pop( GlobalCollectAdapter::GC_CC_LIMBO_QUEUE ) ) {
			$correlation_id = 'globalcollect-' . $message['gateway_txn_id'];
			if ( array_key_exists( $correlation_id, $this->handled_ids ) ) {
				continue;
			}

			// Check the timestamp to see if it's old enough, and stop when
			// we're below the threshold.  Messages are guaranteed to pop in
			// chronological order.
			$elapsed = $this->start_time - $message['date'];
			if ( $elapsed < $time_buffer ) {
				// Put it back!
				DonationQueue::instance()->set( $correlation_id, $message, GlobalCollectAdapter::GC_CC_LIMBO_QUEUE );
				break;
			}

			// We got ourselves an orphan!
			$order_id = explode('-', $correlation_id);
			$order_id = $order_id[1];
			$message['order_id'] = $order_id;
			$message = unCreateQueueMessage($message);
			$orphans[$correlation_id] = $message;
			$this->logger->info( "Found an orphan! $correlation_id" );

			// TODO: stop stomping
			$this->addStompCorrelationIDToAckBucket( $correlation_id );
		}

		// TODO: stop stomping
		$this->addStompCorrelationIDToAckBucket( false, true );

		return $orphans;
	}

	/**
	 * TODO: Remove this along with other STOMP code.  Use getOrphans() instead.
	 * Returns an array of at most $batch_size decoded orphans that we don't
	 * think we've rectified yet. 
	 *
	 * @return array keys are the correlation_id, and the values are the
	 *     decoded stomp message body. 
	 */
	protected function getStompOrphans(){
		$time_buffer = 60*20; //20 minutes? Sure. Why not? 
		$selector = "payment_method = 'cc' AND gateway='globalcollect'";
		echo "Fetching 300 Orphans\n";
		$messages = stompFetchMessages( 'cc-limbo', $selector, 300 );

		$batch_size = 300;
		echo "Fetching {$batch_size} Orphans\n";

		$orphans = array();
		$false_orphans = array();
		foreach ( $messages as $message ){
			//this next block will do quite a lot of antimessage collision 
			//when the queue is not being railed. 
			if ( array_key_exists('antimessage', $message->headers ) ){
				$correlation_id = $message->headers['correlation-id'];
				$false_orphans[] = $correlation_id;
				echo "False Orphan! $correlation_id \n";
			} else { 
				//legit message
				if ( !array_key_exists( $message->headers['correlation-id'], $this->handled_ids ) ) {
					//check the timestamp to see if it's old enough. 
					$decoded = json_decode($message->body, true);
					if ( array_key_exists( 'date', $decoded ) ){
						$elapsed = $this->start_time - $decoded['date'];
						if ( $elapsed > $time_buffer ){
							//we got ourselves an orphan! 
							$correlation_id = $message->headers['correlation-id'];
							$order_id = explode('-', $correlation_id);
							$order_id = $order_id[1];
							$decoded['order_id'] = $order_id;
							$decoded = unCreateQueueMessage($decoded);
							$decoded['card_num'] = '';
							$orphans[$correlation_id] = $decoded;
							echo "Found an orphan! $correlation_id \n";
						}
					}
				}
			}
		}

		// TODO: Remove STOMP block.
		foreach ( $orphans as $cid => $data ){
			if ( in_array( $cid, $false_orphans ) ){
				unset( $orphans[$cid] );
				$this->addStompCorrelationIDToAckBucket( $cid );
				$this->handled_ids[ $cid ] = 'false_orphan';
			}
		}

		return $orphans;
	}

	function parse_files(){
		//all the old stuff goes here. 
		$order_ids = file( 'orphanlogs/order_ids.txt', FILE_SKIP_EMPTY_LINES );
		foreach ( $order_ids as $key=>$val ){
			$order_ids[$key] = trim( $val );
		}
		foreach ( $order_ids as $id ){
			$this->order_ids[$id] = $id; //easier to unset this way. 
		}
		$outstanding_count = count( $this->order_ids );
		echo "Order ID count: $outstanding_count \n";

		$files = $this->getAllLogFileNames();
		$payments = array();
		foreach ( $files as $file ){
			if ( count( $payments ) < $this->max_per_execute ){
				$file_array = $this->getLogfileLines( $file );
				$payments = array_merge( $this->findTransactionLines( $file_array ), $payments );
				if ( count( $payments ) === 0 ){
					$this->killfiles[] = $file;
					echo print_r( $this->killfiles, true );
				}
			}
		}

		$this->adapter->setCurrentTransaction('INSERT_ORDERWITHPAYMENT');
		$xml = new DomDocument;

		//fields that have generated notices if they're not there. 
		$additional_fields = array(
			'card_num',
			'utm_medium',
			'utm_campaign',
			'referrer',
		);

		foreach ($payments as $key => $payment_data){
			$xml->loadXML($payment_data['xml']);
			$parsed = $this->adapter->parseResponseData($xml);
			$payments[$key]['parsed'] = $parsed;
			$payments[$key]['unstaged'] = $this->adapter->unstage_data($parsed);
			$payments[$key]['unstaged']['contribution_tracking_id'] = $payments[$key]['contribution_tracking_id'];
			foreach ($additional_fields as $val){
				if (!array_key_exists($val, $payments[$key]['unstaged'])){
					$payments[$key]['unstaged'][$val] = null;
				}
			}
		}

		// ADDITIONAL: log out what you did here, to... somewhere. 
		// Preferably *before* you rewrite the Order ID file. 

		//we may need to unset some hooks out here. Anything that requires user interaction would make no sense here.
		$i = 0;
		foreach($payments as $payment_data){
			if ($i < $this->max_per_execute){
				++$i;
				if ( $this->rectifyOrphan( $payment_data['unstaged'] ) ) {
					unset( $this->order_ids[$payment_data['unstaged']['order_id']] );
				}
			}
		}

		if ($outstanding_count != count($this->order_ids)){
			$this->rewriteOrderIds();
		}
	}

	/**
	 * Uses the Orphan Adapter to rectify (complete the charge for) a single orphan. Returns a boolean letting the caller know if
	 * the orphan has been fully rectified or not. 
	 * @param array $data Some set of orphan data. 
	 * @param boolean $query_contribution_tracking A flag specifying if we should query the contribution_tracking table or not.
	 * @return boolean True if the orphan has been rectified, false if not. 
	 */
	protected function rectifyOrphan( $data, $query_contribution_tracking = true ){
		echo 'Rectifying Orphan ' . $data['order_id'] . "\n";
		$rectified = false;

		$this->adapter->loadDataAndReInit( $data, $query_contribution_tracking );
		$results = $this->adapter->do_transaction( 'Confirm_CreditCard' );
		$message = $results->getMessage();
		if ( $results->getCommunicationStatus() ){
			$this->logger->info( $data['contribution_tracking_id'] . ": FINAL: " . $this->adapter->getValidationAction() );
			$rectified = true;
		} else {
			$this->logger->info( $data['contribution_tracking_id'] . ": ERROR: " . $message );
			if ( strpos( $message, "GET_ORDERSTATUS reports that the payment is already complete." ) === 0  ){
				$rectified = true;
			}

			//handles the transactions we've cancelled ourselves... though if they got this far, that's a problem too. 
			$errors = $results->getErrors();
			if ( !empty( $errors ) && array_key_exists( '1000001', $errors ) ){
				$rectified = true;
			}

			//apparently this is well-formed GlobalCollect for "iono". Get rid of it.
			if ( strpos( $message, "No processors are available." ) === 0 ){
				$rectified = true;
			}
		}
		echo $message . "\n";
		
		return $rectified;
	}

	/**
	 * Gets the global setting for the key passed in.
	 * @param type $key
	 *
	 * FIXME: Reuse GatewayAdapter::getGlobal.
	 */
	protected function getOrphanGlobal( $key ){
		global $wgDonationInterfaceOrphanCron;
		if ( array_key_exists( $key, $wgDonationInterfaceOrphanCron ) ){
			return $wgDonationInterfaceOrphanCron[$key];
		} else {
			return NULL;
		}
	}

	function getAllLogFileNames(){
		$files = array();
		if ($handle = opendir(__DIR__ . '/orphanlogs/')){
			while ( ($file = readdir($handle)) !== false ){
				if (trim($file, '.') != '' && $file != 'order_ids.txt' && $file != '.svn'){
					$files[] = __DIR__ . '/orphanlogs/' . $file;
				}
			}
		}
		closedir($handle);
		return $files;
	}

	function findTransactionLines($file){
		$lines = array();
		$orders = array();
		$contrib_id_finder = array();
		foreach ($file as $line_no=>$line_data){
			if (strpos($line_data, '<XML><REQUEST><ACTION>INSERT_ORDERWITHPAYMENT') === 0){
				$lines[$line_no] = $line_data;
			} elseif (strpos($line_data, 'Raw XML Response')){
				$contrib_id_finder[] = $line_data;
			} elseif (strpos(trim($line_data), '<ORDERID>') === 0){
				$contrib_id_finder[] = trim($line_data);
			}
		}

		$order_ids = $this->order_ids;
		foreach ($lines as $line_no=>$line_data){
			if (count($orders) < $this->max_per_execute){
				$pos1 = strpos($line_data, '<ORDERID>') + 9;
				$pos2 = strpos($line_data, '</ORDERID>');
				if ($pos2 > $pos1){
					$tmp = substr($line_data, $pos1, $pos2-$pos1);
					if (isset($order_ids[$tmp])){
						$orders[$tmp] = trim($line_data);
						unset($order_ids[$tmp]);
					}
				}
			}
		}

		//reverse the array, so we find the last instance first.
		$contrib_id_finder = array_reverse($contrib_id_finder);
		foreach ($orders as $order_id => $xml){
			$finder = array_search("<ORDERID>$order_id</ORDERID>", $contrib_id_finder);

			//now search forward (which is actually backward) to the "Raw XML" line, so we can get the contribution_tracking_id
			//TODO: Some kind of (in)sanity check for this. Just because we've found it one step backward doesn't mean...
			//...but it's kind of good. For now. 
			$explode_me = false;
			while (!$explode_me){
				++$finder;
				if (strpos($contrib_id_finder[$finder], "Raw XML Response")){
					$explode_me = $contrib_id_finder[$finder];
				}
			}
			if (strlen($explode_me)){
				$explode_me = explode(': ', $explode_me);
				$contribution_tracking_id = trim($explode_me[1]);
				$orders[$order_id] = array(
					'xml' => $xml,
					'contribution_tracking_id' => $contribution_tracking_id,
				);
			}
		}

		return $orders;
	}

	function rewriteOrderIds() {
		$file = fopen('orphanlogs/order_ids.txt', 'w');
		$outstanding_orders = implode("\n", $this->order_ids);		
		fwrite($file, $outstanding_orders);
		fclose($file);
	}

	function getLogfileLines( $file ){
		$array = file($file, FILE_SKIP_EMPTY_LINES);
		//now, check about 50 lines to make sure we're not seeing any of that #012, #015 crap.
		$checkcount = 50;
		if (count($array) < $checkcount){
			$checkcount = count($array);
		}
		$convert = false;
		for ($i=0; $i<$checkcount; ++$i){
			if( strpos($array[$i], '#012') || strpos($array[$i], '#015') ){
				$convert = true;
				break;
			}
		}
		if ($convert) {
			$array2 = array(); 
			foreach ($array as $line){
				if (strpos($line, '#012')){
					$line = str_replace('#012', "\n", $line);
				}
				if (strpos($line, '#015') ){
					$line = str_replace('#015', "\r", $line);	
				}
				$array2[] = $line;
			}
			$newfile = implode("\n", $array2);

			$handle = fopen($file, 'w');
			fwrite($handle, $newfile);
			fclose($handle);
			$array = file($file, FILE_SKIP_EMPTY_LINES);
		}

		return $array;
	}
}

$maintClass = 'GlobalCollectOrphanRectifier';
require_once RUN_MAINTENANCE_IF_MAIN;
