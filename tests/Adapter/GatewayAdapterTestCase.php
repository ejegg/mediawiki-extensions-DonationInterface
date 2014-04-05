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
 * @see DonationInterfaceTestCase
 */
require_once dirname( dirname( __FILE__ ) ) . DIRECTORY_SEPARATOR . 'DonationInterfaceTestCase.php';

/**
 * TODO: Test everything. 
 * Make sure all the basic functions in the gateway_adapter are tested here. 
 * Also, the extras and their hooks firing properly and... that the fail score 
 * they give back is acted upon in the way we think it does. 
 * Hint: For that mess, use GatewayAdapter's $debugarray
 * 
 * Also, note that it barely makes sense to test the functions that need to be 
 * defined in each gateway as per the abstract class. If we did that here, we'd 
 * basically be just testing the test code. So, don't do it. 
 * Those should definitely be tested in the various gateway-specific test 
 * classes. 
 * 
 * @group Fundraising
 * @group DonationInterface
 * @group Splunge
 */
class DonationInterface_Adapter_GatewayAdapterTestCase extends DonationInterfaceTestCase {

	public function __construct() {
		global $wgDonationInterfaceAllowedHtmlForms;
		global $wgDonationInterfaceTest;
		$wgDonationInterfaceTest = true;
		parent::__construct();

		$wgDonationInterfaceAllowedHtmlForms['testytest'] = array (
			'gateway' => 'GlobalCollect', //RAR.
		);
	}

	/**
	 *
	 * @covers GatewayAdapter::__construct
	 * @covers GatewayAdapter::defineVarMap
	 * @covers GatewayAdapter::defineReturnValueMap
	 * @covers GatewayAdapter::defineTransactions
	 */
	public function testConstructor() {

		$options = $this->getDonorTestData();
		$class = $this->testAdapterClass;

		$_SERVER['REQUEST_URI'] = GatewayFormChooser::buildPaymentsFormURL( 'testytest', array ( 'gateway' => $class::getIdentifier() ) );
		$gateway = $this->getFreshGatewayObject( $options );

		$this->assertInstanceOf( TESTS_ADAPTER_DEFAULT, $gateway );

		$this->resetAllEnv();
		$gateway = $this->getFreshGatewayObject( $options = array ( ) );
		$this->assertInstanceOf( TESTS_ADAPTER_DEFAULT, $gateway, "Having trouble constructing a blank adapter." );
	}

	/**
	 *
	 * @covers GatewayAdapter::__construct
	 * @covers DonationData::__construct
	 */
	public function testConstructorHasDonationData() {

		$_SERVER['REQUEST_URI'] = '/index.php/Special:GlobalCollectGateway?form_name=TwoStepAmount';
		
		$options = $this->getDonorTestData();
		$gateway = $this->getFreshGatewayObject( $options );

		$this->assertInstanceOf( 'TestingGlobalCollectAdapter', $gateway );

		//please define this function only inside the TESTS_ADAPTER_DEFAULT, 
		//which should be a test adapter object that descende from one of the 
		//production adapters. 
		$this->assertInstanceOf( 'DonationData', $gateway->getDonationData() );
	}

	public function testLanguageChange() {
		$options = $this->getDonorTestData( 'US' );
		$options['payment_method'] = 'cc';
		$options['payment_submethod'] = 'visa';
		$gateway = $this->getFreshGatewayObject( $options );

		$this->assertEquals( $gateway->_getData_Staged( 'language' ), 'en', "'US' donor's language was inproperly set. Should be 'en'" );
		$gateway->do_transaction( 'INSERT_ORDERWITHPAYMENT' );
		//so we know it tried to screw with the session and such.

		$options = $this->getDonorTestData( 'NO' );
		$gateway = $this->getFreshGatewayObject( $options );
		$this->assertEquals( $gateway->_getData_Staged( 'language' ), 'no', "'NO' donor's language was inproperly set. Should be 'no'" );
	}

}

