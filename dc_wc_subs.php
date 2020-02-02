<?php
/**
 * Plugin Name: WP-Discourse+WooCommerce Memberships Suspend/Unsuspend
 * Version: 2.0
 * Author: scossar
 */

namespace dc_wc_sub;

use WPDiscourse\Utilities\Utilities as DiscourseUtilities;

add_action( 'init', __NAMESPACE__ . '\\init' );

/**
 * Initializes the plugin.
 *
 * Only sets up the hooks if the WP Discourse plugin is loaded.
 */
function init() {
	if ( class_exists( 'WPDiscourse\Discourse\Discourse' ) ) {
		add_action( 'woocommerce_subscription_status_active', __NAMESPACE__ . '\\member_added' );
		add_action( 'woocommerce_subscription_status_expired', __NAMESPACE__ . '\\membership_expired', 10, 2 );
		add_action( 'woocommerce_subscription_status_pending-cancel_to_cancelled', __NAMESPACE__ . '\\membership_expired', 10, 2 );
	}
}

/**
 * Suspends a Discourse user for 100 years.
 *
 * @param int $user_id The id of the user to suspend.
 *
 * @return array|mixed|object|\WP_Error
 */
function suspend_user( $user_id ) {
	$discourse_user = DiscourseUtilities::get_discourse_user( $user_id, true );
	if ( ! is_wp_error( $discourse_user ) ) {
		$discourse_user_id = $discourse_user->id;
		$options = DiscourseUtilities::get_options();
		$suspend_url = esc_url_raw( $options['url'] . "/admin/users/{$discourse_user_id}/suspend" );
		$api_key = $options['api-key'];
		$api_username = $options['publish-username'];

		// Set the 'reason' and the 'message' here.
		$response = wp_remote_post(
			$suspend_url, array(
				'method' => 'PUT',
				'body' => array(
					'api_key' => $api_key,
					'api_username' => $api_username,
					'suspend_until' => date( "Y-m-d", strtotime( "+100 years" ) ),
					'reason' => 'membership expired',
					'message' => 'Your membership has expired',
				)
			)
		);

		return $response;
	}

	return $discourse_user;
}

// Suspends a user upon membership expiration
function membership_expired( $user_id, $membership_id ) {
	$group_name = get_level_for_id( $membership_id );
	if ( is_wp_error( $group_name ) ) {

		return new \WP_Error( 'dc_wc_sub_group_not_found', 'There is no Discourse group for the corresponding membership level.' );
	}

	return null;
}


/**
 * Unsuspends a Discourse user.
 *
 * @param int $user_id The id of the user to unsuspend.
 *
 * @return string|\WP_Error
 */
function unsuspend_user( $user_id ) {
	$discourse_user = DiscourseUtilities::get_discourse_user( $user_id, true );

	if ( ! is_wp_error( $discourse_user ) ) {
		$discourse_user_id = $discourse_user->id;
		$suspended = ! empty( $discourse_user->suspended_till );

		if ( $suspended ) {
			$options = DiscourseUtilities::get_options();
			$suspend_url = esc_url_raw( $options['url'] . "/admin/users/{$discourse_user_id}/unsuspend" );
			$api_key = $options['api-key'];
			$api_username = $options['publish-username'];

			$response = wp_remote_post(
				$suspend_url, array(
					'method' => 'PUT',
					'body' => array(
						'api_key' => $api_key,
						'api_username' => $api_username,
					)
				)
			);

			if ( ! DiscourseUtilities::validate( $response ) ) {

				return new \WP_Error( 'dc_wc_sub_response_error', 'The user could not be unsuspended' );
			}

			// The user was unsuspended.
			return 'unsuspended';
		}

		return 'no_action_taken';
	}

	return new \WP_Error( 'dc_wc_sub_response_error', 'The user could not be retrieved from Discourse.' );
}
