<?php
class TJ_WC_Test_Customer_Sync extends WP_UnitTestCase {

	function setUp() {
		parent::setUp();

		TaxJar_Woocommerce_Helper::prepare_woocommerce();
		$this->tj = TaxJar();
	}

	function tearDown() {
		parent::tearDown();
		WC_Taxjar_Record_Queue::clear_queue();
	}

	function test_get_exemption_type() {
		$customer = TaxJar_Customer_Helper::create_customer();
		$customer->set_email( 'test@test.com' );
		$customer->save();

		$record = new TaxJar_Customer_Record( $customer->get_id(), true );
		$record->load_object();
		$exemption_type = $record->get_exemption_type();
		$this->assertEquals( 'non_exempt', $exemption_type );

		update_user_meta( $customer->get_id(), 'tax_exemption_type', 'wholesale' );
		$exemption_type = $record->get_exemption_type();
		$this->assertEquals( 'wholesale', $exemption_type );

		update_user_meta( $customer->get_id(), 'tax_exemption_type', 'government' );
		$exemption_type = $record->get_exemption_type();
		$this->assertEquals( 'government', $exemption_type );

		update_user_meta( $customer->get_id(), 'tax_exemption_type', 'other' );
		$exemption_type = $record->get_exemption_type();
		$this->assertEquals( 'other', $exemption_type );

		update_user_meta( $customer->get_id(), 'tax_exemption_type', 'non_exempt' );
		$exemption_type = $record->get_exemption_type();
		$this->assertEquals( 'non_exempt', $exemption_type );

		update_user_meta( $customer->get_id(), 'tax_exemption_type', 'invalid_type' );
		$exemption_type = $record->get_exemption_type();
		$this->assertEquals( 'non_exempt', $exemption_type );
	}

	function test_get_exempt_regions() {
		$customer = TaxJar_Customer_Helper::create_customer();
		$customer->set_email( 'test@test.com' );
		$customer->save();

		$record = new TaxJar_Customer_Record( $customer->get_id(), true );
		$record->load_object();
		$exempt_regions = $record->get_exempt_regions();
		$this->assertEquals( array(), $exempt_regions );

		$exempt_regions_string = 'AL,AK';
		update_user_meta( $customer->get_id(), 'tax_exempt_regions', $exempt_regions_string );
		$exempt_regions = $record->get_exempt_regions();
		$expected = array(
			array(
				'country' => 'US',
				'state' => 'AL'
			),
			array(
				'country' => 'US',
				'state' => 'AK'
			)
		);
		$this->assertEquals( $expected, $exempt_regions );

		// test invalid state string
		$exempt_regions_string = 'AL,XX';
		update_user_meta( $customer->get_id(), 'tax_exempt_regions', $exempt_regions_string );
		$exempt_regions = $record->get_exempt_regions();
		$expected = array(
			array(
				'country' => 'US',
				'state' => 'AL'
			)
		);
		$this->assertEquals( $expected, $exempt_regions );

		$exempt_regions_string = 'AL';
		update_user_meta( $customer->get_id(), 'tax_exempt_regions', $exempt_regions_string );
		$exempt_regions = $record->get_exempt_regions();
		$expected = array(
			array(
				'country' => 'US',
				'state' => 'AL'
			)
		);
		$this->assertEquals( $expected, $exempt_regions );

		$exempt_regions_string = 'AL,,AK';
		update_user_meta( $customer->get_id(), 'tax_exempt_regions', $exempt_regions_string );
		$exempt_regions = $record->get_exempt_regions();
		$expected = array(
			array(
				'country' => 'US',
				'state' => 'AL'
			),
			array(
				'country' => 'US',
				'state' => 'AK'
			)
		);
		$this->assertEquals( $expected, $exempt_regions );
	}

	function test_customer_sync_validation() {
		$customer = TaxJar_Customer_Helper::create_customer();
		$customer->set_email( 'test@test.com' );
		$customer->save();

		// test no object loaded
		$record = new TaxJar_Customer_Record( $customer->get_id(), true );
		$should_sync = $record->should_sync();
		$this->assertFalse( $should_sync );

		// test no name
		$record = new TaxJar_Customer_Record( $customer->get_id(), true );
		$record->load_object();
		$should_sync = $record->should_sync();
		$this->assertFalse( $should_sync );

		$customer->set_billing_first_name( 'Test' );
		$customer->set_billing_last_name( 'Test' );
		$customer->save();

		$record = new TaxJar_Customer_Record( $customer->get_id(), true );
		$record->load_object();
		$should_sync = $record->should_sync();
		$this->assertTrue( $should_sync );
	}

	function test_get_customer_data() {
		$customer = TaxJar_Customer_Helper::create_exempt_customer();

		$record = new TaxJar_Customer_Record( $customer->get_id(), true );
		$record->load_object();
		$data = $record->get_data();

		$expected_data = array(
			'customer_id' => $customer->get_id(),
			'exemption_type' => 'wholesale',
			'name' => 'First Last',
			'exempt_regions' => array(
				array(
					'country' => 'US',
					'state' => 'CO'
				),
				array(
					'country' => 'US',
					'state' => 'UT'
				)
			),
			'country' => 'US',
			'state' => 'CO',
			'zip' => '80111',
			'city' => 'Greenwood Village',
			'street' => '123 Test St'
		);

		$this->assertEquals( $expected_data[ 'customer_id' ], $data[ 'customer_id' ] );
		$this->assertEquals( $expected_data[ 'exemption_type' ], $data[ 'exemption_type' ] );
		$this->assertEquals( $expected_data[ 'name' ], $data[ 'name' ] );
		$this->assertEquals( $expected_data[ 'exempt_regions' ], $data[ 'exempt_regions' ] );
		$this->assertEquals( $expected_data[ 'country' ], $data[ 'country' ] );
		$this->assertEquals( $expected_data[ 'state' ], $data[ 'state' ] );
		$this->assertEquals( $expected_data[ 'zip' ], $data[ 'zip' ] );
		$this->assertEquals( $expected_data[ 'city' ], $data[ 'city' ] );
		$this->assertEquals( $expected_data[ 'street' ], $data[ 'street' ] );
	}

	function test_customer_api_requests() {
		$customer = TaxJar_Customer_Helper::create_exempt_customer();

		$record = new TaxJar_Customer_Record( $customer->get_id(), true );
		$record->load_object();
		$record->delete_in_taxjar();

		// test create new customer in TaxJar
		$record = new TaxJar_Customer_Record( $customer->get_id(), true );
		$record->load_object();
		$response = $record->create_in_taxjar();
		$this->assertEquals( 201, $response['response']['code'] );

		// test update existing customer in TaxJar
		$record = new TaxJar_Customer_Record( $customer->get_id(), true );
		$record->load_object();
		$response = $record->update_in_taxjar();
		$this->assertEquals( 200, $response['response']['code'] );

		// test get customer from TaxJar
		$record = new TaxJar_Customer_Record( $customer->get_id(), true );
		$record->load_object();
		$response = $record->get_from_taxjar();
		$this->assertEquals( 200, $response['response']['code'] );
		$body = json_decode( $response[ 'body' ] );
		$this->assertEquals( 'wholesale', $body->customer->exemption_type );
		$this->assertEquals( 'First Last', $body->customer->name );
		$this->assertEquals( 'US', $body->customer->country );
		$this->assertEquals( 'CO', $body->customer->state );
		$this->assertEquals( '80111', $body->customer->zip );
		$this->assertEquals( 'Greenwood Village', $body->customer->city );
		$this->assertEquals( '123 Test St', $body->customer->street );

		$this->assertEquals( 'US', $body->customer->exempt_regions[ 0 ]->country );
		$this->assertEquals( 'CO', $body->customer->exempt_regions[ 0 ]->state );
		$this->assertEquals( 'US', $body->customer->exempt_regions[ 1 ]->country );
		$this->assertEquals( 'UT', $body->customer->exempt_regions[ 1 ]->state );

		// test delete customer from TaxJar
		$record = new TaxJar_Customer_Record( $customer->get_id(), true );
		$record->load_object();
		$response = $record->delete_in_taxjar();
		$this->assertEquals( 200, $response['response']['code'] );

		// test get customer after deletion from TaxJar
		$record = new TaxJar_Customer_Record( $customer->get_id(), true );
		$record->load_object();
		$response = $record->get_from_taxjar();
		$this->assertEquals( 404, $response['response']['code'] );
	}

	function test_sync_customer() {
		$customer = TaxJar_Customer_Helper::create_exempt_customer();

		$record = new TaxJar_Customer_Record( $customer->get_id(), true );
		$record->load_object();
		$record->delete_in_taxjar();

		// test sync new customer
		$record = new TaxJar_Customer_Record( $customer->get_id(), true );
		$record->load_object();
		$result = $record->sync();
		$this->assertTrue( $result );

		// test sync non updated customer already in TaxJar
		$record = new TaxJar_Customer_Record( $customer->get_id(), true );
		$record->load_object();
		$result = $record->sync();
		$this->assertFalse( $result );

		// test sync updated customer already in TaxJar
		update_user_meta( $customer->get_id(), 'tax_exemption_type', 'other' );
		$record = new TaxJar_Customer_Record( $customer->get_id(), true );
		$record->load_object();
		$result = $record->sync();
		$this->assertTrue( $result );

		$record->delete_in_taxjar();

		// test sync updated customer not in TaxJar
		$record = new TaxJar_Customer_Record( $customer->get_id(), true );
		$record->load_object();
		$record->set_status( 'awaiting' );
		$record->set_force_push( true );
		$result = $record->sync();
		$this->assertTrue( $result );

		$record->delete_in_taxjar();
	}

	function test_sync_on_customer_save() {
		$customer = TaxJar_Customer_Helper::create_non_exempt_customer();
		$record = new TaxJar_Customer_Record( $customer->get_id(), true );
		$record->load_object();
		$record->delete_in_taxjar();

		$customer_id = $customer->get_id();

		$_POST[ 'user_id' ] = $customer->get_id();
		$_POST[ 'tax_exemption_type' ] = 'wholesale';
		$_POST[ 'tax_exempt_regions' ] = array( 'UT', 'CO' );

		$current_user = wp_get_current_user();
		$current_user->add_cap( 'manage_woocommerce' );

		do_action( 'edit_user_profile_update', $customer->get_id() );

		$this->assertGreaterThan( 0, did_action( 'taxjar_customer_exemption_settings_updated' ) );

		$record = new TaxJar_Customer_Record( $customer->get_id(), true );
		$record->load_object();
		$response = $record->get_from_taxjar();
		$this->assertEquals( 200, $response['response']['code'] );
		$body = json_decode( $response[ 'body' ] );
		$this->assertEquals( 'wholesale', $body->customer->exemption_type );
		$this->assertEquals( 'US', $body->customer->exempt_regions[ 0 ]->country );
		$this->assertEquals( 'CO', $body->customer->exempt_regions[ 0 ]->state );
		$this->assertEquals( 'US', $body->customer->exempt_regions[ 1 ]->country );
		$this->assertEquals( 'UT', $body->customer->exempt_regions[ 1 ]->state );

		$record->delete_in_taxjar();
	}
}