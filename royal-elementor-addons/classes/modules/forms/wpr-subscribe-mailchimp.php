<?php
namespace WprAddons\Classes\Modules\Forms;

use Elementor\Utils;
use Elementor\Group_Control_Image_Size;
use WprAddons\Classes\Utilities;


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
/**
 * WPR_Subscribe_Mailchimp setup
 *
 * @since 3.4.6
 */

 class WPR_Subscribe_Mailchimp {

    public function __construct() {
        // For form builder
        add_action( 'wp_ajax_wpr_form_builder_mailchimp', [$this, 'wpr_form_builder_mailchimp'] );
        add_action( 'wp_ajax_nopriv_wpr_form_builder_mailchimp', [$this, 'wpr_form_builder_mailchimp'] );
    }

	/**
	** Mailchimp AJAX Subscribe
	*/
	public static function wpr_form_builder_mailchimp() {

        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

        if ( ! wp_verify_nonce( $nonce, 'wpr-addons-js' ) ) {
            return; // Get out of here, the nonce is rotten!
        }

		$document_id = isset( $_POST['wpr_form_document_id'] ) ? absint( wp_unslash( $_POST['wpr_form_document_id'] ) ) : 0;
		$widget_id   = isset( $_POST['wpr_form_id'] ) ? sanitize_text_field( wp_unslash( $_POST['wpr_form_id'] ) ) : '';
		$list_id     = isset( $_POST['listId'] ) ? sanitize_text_field( wp_unslash( $_POST['listId'] ) ) : '';

		if ( ! Utilities::wpr_verify_mailchimp_audience( $document_id, $widget_id, 'wpr-form-builder', $list_id ) ) {
			wp_send_json_error( [
				'action'  => 'wpr_form_builder_mailchimp',
				'status'  => 'error',
				'message' => esc_html__( 'Mailchimp subscription failed', 'wpr-addons' ),
			] );
		}

		$api = Utilities::wpr_mailchimp_api_parts();
		if ( ! $api ) {
			wp_send_json_error( [
				'action'  => 'wpr_form_builder_mailchimp',
				'status'  => 'error',
				'message' => esc_html__( 'Mailchimp subscription failed', 'wpr-addons' ),
			] );
		}

		$api_key        = $api['key'];
		$api_key_suffix = $api['dc'];

        // Get Available Fields (PHPCS - fields are sanitized later on input)
        $fields = isset($_POST['form_data']) ? $_POST['form_data'] : []; // phpcs:ignore

        $group_ids = isset($fields['group_id']) ? array_map('sanitize_text_field', array_map('trim', explode(',', wp_unslash($fields['group_id'])))) : [];

        // Merge Additional Fields
        $merge_fields = [
            'FNAME' => !empty( $fields['first_name_field'] ) ? sanitize_text_field($fields['first_name_field']) : '',
            'LNAME' => !empty( $fields['last_name_field'] ) ? sanitize_text_field($fields['last_name_field']) : '',
			'PHONE' => !empty ( $fields['phone_field'] ) ? sanitize_text_field($fields['phone_field']) : '',
			'BIRTHDAY' => !empty ( $fields['birthday_field'] ) ? sanitize_text_field($fields['birthday_field']) : '',
		];

		$requiredKeys = ['address_field', 'country_field', 'city_field', 'state_field', 'zip_field'];
		
		if ( !empty(array_intersect_key($fields, array_flip($requiredKeys))) ) {
			$merge_fields = array_merge($merge_fields, [
				'ADDRESS' => [
					'addr1' => !empty ( $fields['address_field'] ) ? sanitize_text_field($fields['address_field']) : 'none',
					'country' =>  !empty ( $fields['country_field'] ) ? sanitize_text_field($fields['country_field']) : 'none',
					'city' => !empty ( $fields['city_field'] ) ? sanitize_text_field($fields['city_field']) : 'none',
					'state' => !empty ( $fields['state_field'] ) ? sanitize_text_field($fields['state_field']) : 'none',
					'zip' =>!empty ( $fields['zip_field'] ) ? sanitize_text_field($fields['zip_field']) : 'none',
				]
			]);
		}

        $email = isset( $fields['email_field'] ) ? sanitize_email( $fields['email_field'] ) : '';
        if ( ! is_email( $email ) ) {
			wp_send_json_error( [
				'action'  => 'wpr_form_builder_mailchimp',
				'status'  => 'error',
				'message' => esc_html__( 'Mailchimp subscription failed', 'wpr-addons' ),
			] );
        }

        // API URL
        $api_url = 'https://'. $api_key_suffix .'.api.mailchimp.com/3.0/lists/'. $list_id .'/members/'. md5( strtolower( $email ) );
		
		$api_body = [
			'email_address' => $email,
			'status' => 'subscribed',
			'merge_fields' => $merge_fields
		];
			
		if ( !empty($group_ids) ) {
			$api_body['interests'] = self::group_ids_to_interests_array($group_ids);
		}

        // API Args
        $api_args = [
			'method' => 'PUT',
			'headers' => [
				'Content-Type' => 'application/json',
				'Authorization' => 'apikey '. $api_key,
			],
			'body' => json_encode($api_body),
        ];

        // Send Request
        $request = wp_remote_post( $api_url, $api_args );

		if ( ! is_wp_error($request) ) {
			$request = json_decode( wp_remote_retrieve_body($request) );

			// Set Status
			if ( ! empty($request) ) {
				if ($request->status == 'subscribed') {

					wp_send_json_success( [
						'action'  => 'wpr_form_builder_mailchimp',
						'status'  => 'success',
						'message' => esc_html__( 'Mailchimp subscription was successful', 'wpr-addons' ),
					] );

				} else {
					wp_send_json_error( [
						'action'  => 'wpr_form_builder_mailchimp',
						'status'  => 'error',
						'message' => esc_html__( 'Mailchimp subscription failed', 'wpr-addons' ),
					] );
				}
			}
		} else {

			wp_send_json_error( [
				'action'  => 'wpr_form_builder_mailchimp',
				'status'  => 'error',
				'message' => esc_html__( 'Mailchimp subscription failed', 'wpr-addons' ),
			] );
		}
	}

	public static function group_ids_to_interests_array($group_ids) {
		$interests_array = [];
		
		foreach ($group_ids as $group_id) {
			$interests_array[$group_id] = true;
		}
	
		return $interests_array;
	}
	
 }

 new WPR_Subscribe_Mailchimp();