<?php
/**
 * The code in this file generally runs when a site administrator
 * is using the site. PMPro will show more detailed error messages,
 * give admins a default level, generate temporary dummy orders, and
 * generally help administrators to preview PMPro pages and functionality.
 */

/**
 * Don't redirect admins away from the account page.
 */
function pmpro_stop_redirect_on_account_page() {	

	if( current_user_can( 'manage_options') && ! pmpro_hasMembershipLevel() ) {
		add_filter( 'pmpro_account_preheader_redirect', '__return_false' ); // stop user from 	
	}
}
add_action( 'init', 'pmpro_stop_redirect_on_account_page' );

/**
 * Get a temporary dummy order.
 */
function pmpro_temp_order_for_admins() {

	global $current_user;

	$test_order = new MemberOrder();
	$all_levels = pmpro_getAllLevels();

	if ( ! empty( $all_levels ) ) {
		$first_level                = array_shift( $all_levels );
		$test_order->membership_id  = $first_level->id;
		$test_order->total = $first_level->initial_payment;
	} else {
		$test_order->membership_id  = 1;
		$test_order->total = 1;
	}

	$test_order->code = 'pmpro1234';
	$test_order->id = '9999999999';
	$test_order->user_id = $current_user->ID;
	$test_order->cardtype = "Visa";
	$test_order->accountnumber = "4111111111111111";
	$test_order->expirationmonth = date( 'm', current_time( 'timestamp' ) );
	$test_order->expirationyear = ( intval( date( 'Y', current_time( 'timestamp' ) ) ) + 1 );
	$test_order->ExpirationDate = $test_order->expirationmonth . $test_order->expirationyear;
	$test_order->CVV2 = '123';
	$test_order->FirstName = 'Jane';
	$test_order->LastName = 'Doe';
	$test_order->Address1 = '123 Street';
	$test_order->billing = new stdClass();
	$test_order->billing->name = 'Jane Doe';
	$test_order->billing->street = '123 Street';
	$test_order->billing->city = 'City';
	$test_order->billing->state = 'ST';
	$test_order->billing->country = 'US';
	$test_order->billing->zip = '12345';
	$test_order->billing->phone = '5558675309';
	$test_order->gateway_environment = 'sandbox';
	$test_order->notes  = __( 'This is a temporary order for testing purposes.', 'paid-memberships-pro' );
	$test_order->timestamp = time();
	$test_order->test = true;
		
	return $test_order;
}


/**
 * If an admin without a membership level edits the checkout page, it will default to the first membership level.
 */
function pmpro_default_editor_level_for_admins( $level ) {

	if ( ( current_user_can( 'manage_options') && ! pmpro_hasMembershipLevel() ) && ! isset( $_REQUEST['level'] ) ) {
		$all_levels = pmpro_getAllLevels();
		$level = array_shift( $all_levels ); // get first level and use as default.	
	}

	return $level;
}
add_filter( 'pmpro_checkout_level', 'pmpro_default_editor_level_for_admins' );

/**
 * If an admin is viewing the checkout page without passing through the "lev$el" parameter, 
 * show a notice that this is a test view and will default to the first membership level.
 */
function pmpro_show_notice_if_admin_edits_checkout_without_level(){
	global $pmpro_pages, $pmpro_msg, $pmpro_msgt;
	
	if ( is_page( $pmpro_pages['checkout'] ) && ( current_user_can( 'manage_options') && ! pmpro_hasMembershipLevel() ) && !isset( $_REQUEST['level']) ) {
		pmpro_setMessage( __( "You are currently viewing this page as admin. If you process this checkout, this will change your membership level.", 'paid-memberships-pro' ), 'alert' );
	}
}
add_action( 'wp', 'pmpro_show_notice_if_admin_edits_checkout_without_level' );

/**
 * Give single level as well for some checks in PMPro.
 * global $pmpro_pages returns null in this function.
 */
function pmpro_give_level_to_admin_for_testing( $level, $user_id ) {

	if ( empty( $level ) && current_user_can( 'manage_options' ) && ( empty( $_REQUEST['levelstocancel']) && empty( $_REQUEST['levels'] ) ) ) {
		$all_levels = pmpro_getAllLevels();
		$level = array_shift( $all_levels ); // get first level and use as default.	
		$level->ID = $level->id;
		$level->enddate = '';
	}

	return $level;
}
add_filter( 'pmpro_get_membership_level_for_user', 'pmpro_give_level_to_admin_for_testing', 10, 2 );

/**
 * Temporarily give membership level to admin if they don't have one.
 * We need to filter this one as well to support Multiple Memberships per User
 */
function pmpro_give_admin_temp_mmpu_level_for_testing( $level, $user_id ) {

	if ( empty( $level ) && current_user_can( 'manage_options' ) && pmpro_has_loaded_page() && ( !isset( $_REQUEST['levelstocancel']) ||  !isset( $_REQUEST['levels'] ) ) ) {
		$all_levels = pmpro_getAllLevels();
		$level_obj = array_shift( $all_levels ); // get first level and use as default.	
		$level_obj->ID = $level_obj->id; // Capitalize the ID in this case.
		$level_obj->enddate = '';

		$level[] = $level_obj;
	}

	return $level;
}
add_filter( 'pmpro_get_membership_levels_for_user', 'pmpro_give_admin_temp_mmpu_level_for_testing', 10, 2 );

/**
 * This requires a custom filter in PMPro account page to cater for a temp order.
 * Only shows if admin doesn't have an order.
 */
function pmpro_add_temp_order_to_account_page( $invoices ) {

	global $wpdb, $current_user;

	if ( current_user_can( 'manage_options' ) ) {

		// get any orders for current user.
		$sql = "SELECT id from $wpdb->pmpro_membership_orders WHERE user_id = '" . $current_user->ID . "'";
		$orders = $wpdb->get_results( $sql );

		if ( empty( $orders ) ) {
			$invoices[] = pmpro_temp_order_for_admins();
		}	
	}
	
	return $invoices;
}
add_filter( 'pmpro_account_invoices_array', 'pmpro_add_temp_order_to_account_page', 10, 1 );