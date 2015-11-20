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
 * @group Fundraising
 * @group DonationInterface
 * @group GatewayPage
 */
class GatewayPageTest extends DonationInterfaceTestCase {

	protected $page;
	protected $adapter;

	public function setUp() {
		$this->page = new TestingGatewayPage();
		// put these here so tests can override them
		TestingGenericAdapter::$fakeGlobals = array ( 'FallbackCurrency' => 'USD' );
		TestingGenericAdapter::$acceptedCurrencies[] = 'USD';
		TestingGenericAdapter::$fakeIdentifier = 'globalcollect';
		parent::setUp();
	}

	protected function setUpAdapter( $extra = array() ) {
		$externalData = array_merge(
			array(
				'amount' => '200',
				'currency_code' => 'BBD',
				'contribution_tracking_id' => mt_rand( 10000, 10000000 ),
			),
			$extra
		);
		$this->adapter = new TestingGenericAdapter( array(
			'external_data' => $externalData,
		) );
		$this->page->adapter = $this->adapter;
	}

	public function tearDown() {
		TestingGenericAdapter::$acceptedCurrencies = array();
		TestingGenericAdapter::$fakeGlobals = array();
		TestingGenericAdapter::$fakeIdentifier = false;
		parent::tearDown();
	}

	public function testFallbackWithNotification() {
		TestingGenericAdapter::$fakeGlobals['NotifyOnConvert'] = true;
		$this->setUpAdapter();

		$this->page->validateForm();

		$this->assertTrue( $this->adapter->validatedOK() );

		$manualErrors = $this->adapter->getManualErrors();
		$msg = $this->page->msg( 'donate_interface-fallback-currency-notice', 'USD' )->text();
		$this->assertEquals( $msg, $manualErrors['general'] );
		$this->assertEquals( 100, $this->adapter->getData_Unstaged_Escaped( 'amount' ) );
		$this->assertEquals( 'USD', $this->adapter->getData_Unstaged_Escaped( 'currency_code' ) );
	}

	public function testFallbackIntermediateConversion() {
		TestingGenericAdapter::$fakeGlobals['FallbackCurrency'] = 'OMR';
		TestingGenericAdapter::$fakeGlobals['NotifyOnConvert'] = true;
		TestingGenericAdapter::$acceptedCurrencies[] = 'OMR';
		$this->setUpAdapter();

		$this->page->validateForm();

		$manualErrors = $this->adapter->getManualErrors();
		$msg = $this->page->msg( 'donate_interface-fallback-currency-notice', 'OMR' )->text();
		$this->assertEquals( $msg, $manualErrors['general'] );
		$this->assertEquals( 38, $this->adapter->getData_Unstaged_Escaped( 'amount' ) );
		$this->assertEquals( 'OMR', $this->adapter->getData_Unstaged_Escaped( 'currency_code' ) );
	}

	public function testFallbackWithoutNotification() {
		TestingGenericAdapter::$fakeGlobals['NotifyOnConvert'] = false;
		$this->setUpAdapter();

		$this->page->validateForm();

		$this->assertTrue( $this->adapter->validatedOK() );

		$manualErrors = $this->adapter->getManualErrors();
		$this->assertEquals( null, $manualErrors['general'] );
		$this->assertEquals( 100, $this->adapter->getData_Unstaged_Escaped( 'amount' ) );
		$this->assertEquals( 'USD', $this->adapter->getData_Unstaged_Escaped( 'currency_code' ) );
	}

	public function testFallbackAlwaysNotifiesIfOtherErrors() {
		TestingGenericAdapter::$fakeGlobals['NotifyOnConvert'] = false;
		$this->setUpAdapter( array( 'email' => 'notanemail' ) );

		$this->page->validateForm();

		$manualErrors = $this->adapter->getManualErrors();
		$msg = $this->page->msg( 'donate_interface-fallback-currency-notice', 'USD' )->text();
		$this->assertEquals( $msg, $manualErrors['general'] );
		$this->assertEquals( 100, $this->adapter->getData_Unstaged_Escaped( 'amount' ) );
		$this->assertEquals( 'USD', $this->adapter->getData_Unstaged_Escaped( 'currency_code' ) );
	}

	public function testNoFallbackForSupportedCurrency() {
		TestingGenericAdapter::$acceptedCurrencies[] = 'BBD';
		$this->setUpAdapter();

		$this->page->validateForm();

		$manualErrors = $this->adapter->getManualErrors();
		$this->assertEquals( null, $manualErrors['general'] );
		$this->assertEquals( 200, $this->adapter->getData_Unstaged_Escaped( 'amount' ) );
		$this->assertEquals( 'BBD', $this->adapter->getData_Unstaged_Escaped( 'currency_code' ) );
	}
}
