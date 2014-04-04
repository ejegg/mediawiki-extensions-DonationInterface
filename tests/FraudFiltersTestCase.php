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
require_once dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'DonationInterfaceTestCase.php';

/**
 * @group Fundraising
 * @group DonationInterface
 * @group FormChooser
 */
class DonationInterface_FraudFiltersTestCase extends DonationInterfaceTestCase {

	/**
	 *
	 */
	public function __construct(){
		global $wgRequest;

		$adapterclass = TESTS_ADAPTER_DEFAULT;
		$this->testAdapterClass = $adapterclass;

		parent::__construct();
		self::setupFraudMaps();
	}

	public static function setupFraudMaps() {
		//yeesh.
		global $wgDonationInterfaceCustomFiltersActionRanges, $wgDonationInterfaceCustomFiltersRefRules, $wgDonationInterfaceCustomFiltersSrcRules,
		$wgDonationInterfaceCustomFiltersFunctions, $wgGlobalCollectGatewayCustomFiltersFunctions, $wgWorldPayGatewayCustomFiltersFunctions,
		$wgDonationInterfaceCountryMap, $wgDonationInterfaceUtmCampaignMap, $wgDonationInterfaceUtmSourceMap, $wgDonationInterfaceUtmMediumMap,
		$wgDonationInterfaceEmailDomainMap;

		$wgDonationInterfaceCustomFiltersActionRanges = array (
			'process' => array ( 0, 25 ),
			'review' => array ( 25, 50 ),
			'challenge' => array ( 50, 75 ),
			'reject' => array ( 75, 100 ),
		);

		$wgDonationInterfaceCustomFiltersRefRules = array (
			'/donate\-error/i' => 5,
		);

		$wgDonationInterfaceCustomFiltersSrcRules = array ( '/wikimedia.org/i' => 80 );

		$wgDonationInterfaceCustomFiltersFunctions = array (
			'getScoreCountryMap' => 50,
			'getScoreUtmCampaignMap' => 50,
			'getScoreUtmSourceMap' => 15,
			'getScoreUtmMediumMap' => 15,
			'getScoreEmailDomainMap' => 75
		);

		$wgGlobalCollectGatewayCustomFiltersFunctions = $wgDonationInterfaceCustomFiltersFunctions;
		$wgGlobalCollectGatewayCustomFiltersFunctions['getCVVResult'] = 20;
		$wgGlobalCollectGatewayCustomFiltersFunctions['getAVSResult'] = 25;

		$wgWorldPayGatewayCustomFiltersFunctions = $wgGlobalCollectGatewayCustomFiltersFunctions;

		$wgDonationInterfaceCountryMap = array (
			'US' => 40,
			'CA' => 15,
			'RU' => -4,
		);

		$wgDonationInterfaceUtmCampaignMap = array (
			'/^(C14\_)/' => 14,
			'/^(spontaneous)/' => 5
		);
		$wgDonationInterfaceUtmSourceMap = array (
			'/somethingmedia/' => 70
		);
		$wgDonationInterfaceUtmMediumMap = array (
			'/somethingmedia/' => 80
		);
		$wgDonationInterfaceEmailDomainMap = array (
			'wikimedia.org' => 42,
			'wikipedia.org' => 50,
		);
	}

	function testGCFraudFilters() {
		global $wgGlobalCollectGatewayEnableMinfraud;
		$wgGlobalCollectGatewayEnableMinfraud = true;

		$options = $this->getDonorTestData();
		$options['email'] = 'somebody@wikipedia.org';
		$class = $this->testAdapterClass;

		$gateway = $this->getFreshGatewayObject( $options );

		$gateway->runAntifraudHooks();

		$this->assertEquals( 'reject', $gateway->getValidationAction(), 'Validation action is not as expected' );
		$this->assertEquals( 157.5, $gateway->getRiskScore(), 'RiskScore is not as expected' );

		unset( $wgGlobalCollectGatewayEnableMinfraud );
	}
}

