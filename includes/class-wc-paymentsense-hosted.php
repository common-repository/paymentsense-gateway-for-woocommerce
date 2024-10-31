<?php
/**
 * Paymentsense Hosted Payment Method
 *
 * @package WooCommerce_Paymentsense_Gateway
 * @subpackage WooCommerce_Paymentsense_Gateway/includes
 * @author Paymentsense
 * @link http://www.paymentsense.co.uk/
 */

/**
 * Exit if accessed directly
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

if ( ! class_exists( 'WC_Paymentsense_Hosted' ) ) {
	/**
	 * WC_Paymentsense_Hosted class.
	 *
	 * @extends Paymentsense_Base
	 */
	class WC_Paymentsense_Hosted extends Paymentsense_Base {
		/**
		 * Response Status Codes (used in the processing of the notification of the SERVER result delivery method)
		 */
		const STATUS_CODE_OK    = '0';
		const STATUS_CODE_ERROR = '30';

		/**
		 * Response Messages (used in the processing of the notification of the SERVER result delivery method)
		 */
		const MSG_SUCCESS            = 'Request processed successfully.';
		const MSG_UNSUPPORTED_STATUS = 'Unknown or unsupported payment status.';
		const MSG_EXCEPTION          = 'An exception with message "%1$s" has been thrown while processing order #%2$s.';

		/**
		 * Payment method ID
		 *
		 * @var string
		 */
		public $id = 'paymentsense_hosted';

		/**
		 * Payment method title
		 *
		 * @var string
		 */
		public $method_title = 'Paymentsense Hosted';

		/**
		 * Payment method description
		 *
		 * @var string
		 */
		public $method_description = 'Accept payments from Credit/Debit cards through Paymentsense Hosted';

		/**
		 * Specifies whether the payment method shows fields on the checkout
		 *
		 * @var bool
		 */
		public $has_fields = false;

		/**
		 * An array containing the status code and message outputted on the response of the gateway callbacks
		 *
		 * @var array
		 */
		protected $response_vars = array(
			'status_code' => '',
			'message'     => '',
		);

		/**
		 * Specifies whether the hash digest authentication on the data of the gateway
		 * response matches the calculated one
		 *
		 * @var bool
		 */
		protected $authenticated = false;

		/**
		 * Paymentsense Hosted Class Constructor
		 */
		public function __construct() {
			parent::__construct();

			// Hooks actions.
			add_action(
				'woocommerce_update_options_payment_gateways_' . $this->id,
				array( $this, 'process_admin_options' )
			);
			add_action(
				'woocommerce_receipt_' . $this->id,
				array( $this, 'receipt_page' )
			);
			add_action(
				'woocommerce_api_wc_' . $this->id,
				array( $this, 'process_gateway_response' )
			);
			add_action(
				'woocommerce_before_thankyou',
				array( $this, 'process_before_thankyou' )
			);
			add_filter(
				'woocommerce_thankyou_order_received_text',
				array( $this, 'process_order_received_text' ),
				10,
				2
			);
		}

		/**
		 * Initialises settings form fields
		 *
		 * Overrides wc settings api class method
		 */
		public function init_form_fields() {
			$this->form_fields = array(
				'enabled' => array(
					'title'   => __( 'Enable/Disable:', 'woocommerce-paymentsense' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable ', 'woocommerce-paymentsense' ) . $this->method_title,
					'default' => 'yes',
				),

				'module_options' => array(
					'title'       => __( 'Module Options', 'woocommerce-paymentsense' ),
					'type'        => 'title',
					'description' => __( 'The following options affect how the ', 'woocommerce-paymentsense' ) . $this->method_title . __( ' Module is displayed on the frontend.', 'woocommerce-paymentsense' ),
				),

				'title' => array(
					'title'       => __( 'Title:', 'woocommerce-paymentsense' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the customer sees during checkout.', 'woocommerce-paymentsense' ),
					'default'     => $this->method_title,
					'desc_tip'    => true,
				),

				'description' => array(
					'title'       => __( 'Description:', 'woocommerce-paymentsense' ),
					'type'        => 'textarea',
					'description' => __( 'This controls the description which the customer sees during checkout.', 'woocommerce-paymentsense' ),
					'default'     => __( 'Pay securely by Credit or Debit card through ', 'woocommerce-paymentsense' ) . $this->method_title . '.',
					'desc_tip'    => true,
				),

				'order_prefix' => array(
					'title'       => __( 'Order Prefix:', 'woocommerce-paymentsense' ),
					'type'        => 'text',
					'description' => __( 'This is the order prefix that you will see in the MMS.', 'woocommerce-paymentsense' ),
					'default'     => 'WC-',
					'desc_tip'    => true,
				),

				'gateway_settings' => array(
					'title'       => __( 'Gateway Settings', 'woocommerce-paymentsense' ),
					'type'        => 'title',
					'description' => __( 'These are the gateway settings to allow you to connect with the Paymentsense gateway. (These are not the details used to login to the MMS)', 'woocommerce-paymentsense' ),
				),

				'gateway_merchant_id' => array(
					'title'       => __( 'Gateway MerchantID:', 'woocommerce-paymentsense' ),
					'type'        => 'text',
					'description' => __( 'This is the gateway MerchantID not used with the MMS login. The Format should match the following ABCDEF-1234567', 'woocommerce-paymentsense' ),
					'default'     => '',
					'desc_tip'    => true,
				),

				'gateway_password' => array(
					'title'       => __( 'Gateway Password:', 'woocommerce-paymentsense' ),
					'type'        => 'text',
					'description' => __( 'This is the gateway Password not used with the MMS login. The Password should use lower case and uppercase letters, and numbers only.', 'woocommerce-paymentsense' ),
					'default'     => '',
					'desc_tip'    => true,
				),

				'gateway_presharedkey' => array(
					'title'       => __( 'Gateway PreSharedKey:', 'woocommerce-paymentsense' ),
					'type'        => 'text',
					'description' => __( 'This is located within the MMS under "Account Admin Settings" > "Account Settings".', 'woocommerce-paymentsense' ),
					'default'     => '',
					'desc_tip'    => true,
				),

				'gateway_hashmethod' => array(
					'title'       => __( 'Gateway Hash Method:', 'woocommerce-paymentsense' ),
					'type'        => 'select',
					'description' => __( 'This is the hash method set in MMS under "Account Admin" > "Account Settings". By default, this will be SHA1.', 'woocommerce-paymentsense' ),
					'default'     => 'SHA1',
					'desc_tip'    => true,
					'options'     => array(
						'SHA1'       => 'SHA1',
						'MD5'        => 'MD5',
						'HMACSHA1'   => 'HMACSHA1',
						'HMACMD5'    => 'HMACMD5',
						'HMACSHA256' => 'HMACSHA256',
						'HMACSHA512' => 'HMACSHA512',
					),
				),

				'gateway_transaction_type' => array(
					'title'       => __( 'Transaction Type:', 'woocommerce-paymentsense' ),
					'type'        => 'select',
					'description' => __( 'If you wish to obtain authorisation for the payment only, as you intend to manually collect the payment via the MMS, choose Pre-auth.', 'woocommerce-paymentsense' ),
					'default'     => 'SALE',
					'desc_tip'    => true,
					'options'     => array(
						'SALE' => __( 'Sale', 'woocommerce-paymentsense' ),
						// TODO: Implementation of the pre-authorisation support
						// @codingStandardsIgnoreLine
						// 'PREAUTH' => __( 'Pre-Auth', 'woocommerce-paymentsense' ),
					),
				),

				'gateway_result_delivery' => array(
					'title'       => __( 'Result Delivery Method:', 'woocommerce-paymentsense' ),
					'type'        => 'select',
					'description' => __( 'The Server Result Method determines how the transaction results are delivered back to the WooCommerce store.', 'woocommerce-paymentsense' ),
					'default'     => 'POST',
					'desc_tip'    => true,
					'options'     => array(
						'POST'   => 'POST',
						'SERVER' => 'SERVER',
					),
				),

				'amex_accepted' => array(
					'title'       => __( 'Accept American Express?', 'woocommerce-paymentsense' ),
					'type'        => 'checkbox',
					'description' => __( 'Tick only if you have an American Express MID associated with your Paymentsense gateway account.', 'woocommerce-paymentsense' ),
					'label'       => 'Enable American Express',
					'default'     => 'no',
					'desc_tip'    => true,
				),

				'hosted_payment_form_additional_field' => array(
					'title'       => __( 'Payment Form Additional Field', 'woocommerce-paymentsense' ),
					'type'        => 'title',
					'description' => __( 'These options allow the customer to change the email address and phone number on the payment form.', 'woocommerce-paymentsense' ),
				),

				'email_address_editable' => array(
					'title'       => __( 'Email Address can be altered on payment form:', 'woocommerce-paymentsense' ),
					'type'        => 'select',
					'description' => __( 'This option allows the customer to change the email address that entered during checkout. By default the Paymentsense module will pass the customers email address that they entered during checkout.', 'woocommerce-paymentsense' ),
					'default'     => 'false',
					'desc_tip'    => true,
					'options'     => array(
						'true'  => __( 'Yes', 'woocommerce-paymentsense' ),
						'false' => __( 'No', 'woocommerce-paymentsense' ),
					),
				),

				'phone_number_editable' => array(
					'title'       => __( 'Phone Number can be altered on payment form:', 'woocommerce-paymentsense' ),
					'type'        => 'select',
					'description' => __( 'This option allows the customer to change the phone number that entered during checkout. By default the Paymentsense module will pass the customers phone number that they entered during checkout.', 'woocommerce-paymentsense' ),
					'default'     => 'false',
					'desc_tip'    => true,
					'options'     => array(
						'true'  => __( 'Yes', 'woocommerce-paymentsense' ),
						'false' => __( 'No', 'woocommerce-paymentsense' ),
					),
				),

				'hosted_payment_form_mandatory_field' => array(
					'title'       => __( 'Payment Form Mandatory Fields', 'woocommerce-paymentsense' ),
					'type'        => 'title',
					'description' => __( 'These options allow you to change what fields are mandatory for the customers to complete on the payment form. (The default settings are recommended by Paymentsense)', 'woocommerce-paymentsense' ),
				),

				'address1_mandatory' => array(
					'title'       => __( 'Address Line 1 Mandatory:', 'woocommerce-paymentsense' ),
					'type'        => 'select',
					'description' => __( 'Define the Address Line 1 as a Mandatory field on the Payment form. This is used for the Address Verification System (AVS) check on the customers card. Recommended Setting "Yes".', 'woocommerce-paymentsense' ),
					'default'     => 'true',
					'desc_tip'    => true,
					'options'     => array(
						'true'  => __( 'Yes', 'woocommerce-paymentsense' ),
						'false' => __( 'No', 'woocommerce-paymentsense' ),
					),
				),

				'city_mandatory' => array(
					'title'       => __( 'City Mandatory:', 'woocommerce-paymentsense' ),
					'type'        => 'select',
					'description' => __( 'Define the City as a Mandatory field on the Payment form.', 'woocommerce-paymentsense' ),
					'default'     => 'false',
					'desc_tip'    => true,
					'options'     => array(
						'true'  => __( 'Yes', 'woocommerce-paymentsense' ),
						'false' => __( 'No', 'woocommerce-paymentsense' ),
					),
				),

				'state_mandatory' => array(
					'title'       => __( 'State/County Mandatory:', 'woocommerce-paymentsense' ),
					'type'        => 'select',
					'description' => __( 'Define the State/County as a Mandatory field on the Payment form.', 'woocommerce-paymentsense' ),
					'default'     => 'false',
					'desc_tip'    => true,
					'options'     => array(
						'true'  => __( 'Yes', 'woocommerce-paymentsense' ),
						'false' => __( 'No', 'woocommerce-paymentsense' ),
					),
				),

				'postcode_mandatory' => array(
					'title'       => __( 'Post Code Mandatory:', 'woocommerce-paymentsense' ),
					'type'        => 'select',
					'description' => __( 'Define the Post Code as a Mandatory field on the Payment form. This is used for the Address Verification System (AVS) check on the customers card. Recommended Setting "Yes".', 'woocommerce-paymentsense' ),
					'default'     => 'true',
					'desc_tip'    => true,
					'options'     => array(
						'true'  => __( 'Yes', 'woocommerce-paymentsense' ),
						'false' => __( 'No', 'woocommerce-paymentsense' ),
					),
				),

				'country_mandatory' => array(
					'title'       => __( 'Country Mandatory:', 'woocommerce-paymentsense' ),
					'type'        => 'select',
					'description' => __( 'Define the Country as a Mandatory field on the Payment form.', 'woocommerce-paymentsense' ),
					'default'     => 'false',
					'desc_tip'    => true,
					'options'     => array(
						'true'  => __( 'Yes', 'woocommerce-paymentsense' ),
						'false' => __( 'No', 'woocommerce-paymentsense' ),
					),
				),

				'troubleshooting_settings' => array(
					'title'       => __( 'Troubleshooting Settings', 'woocommerce-paymentsense' ),
					'type'        => 'title',
					'description' => __( 'Settings related to troubleshooting and diagnostics of the plugin.', 'woocommerce-paymentsense' ),
				),

				'disable_comm_on_port_4430' => array(
					'title'       => __( 'Port 4430 is NOT open on my server (safe mode with refunds disabled):', 'woocommerce-paymentsense' ),
					'type'        => 'select',
					'description' => __( 'In order to function normally the Paymentsense plugin performs outgoing connections to the Paymentsense gateway on port 4430 which is required to be open. In the case port 4430 on your server is closed you can still use the Paymentsense Hosted method with a limited functionality. Please note that by disabling the communication on port 4430 the online refund functionality will be disabled too. Recommended Setting "No". Please set to "Yes" only as a last resort when your server has port 4430 closed.', 'woocommerce-paymentsense' ),
					'default'     => 'false',
					'desc_tip'    => true,
					'options'     => array(
						'true'  => __( 'Yes', 'woocommerce-paymentsense' ),
						'false' => __( 'No', 'woocommerce-paymentsense' ),
					),
				),

				'extended_plugin_info' => array(
					'title'       => __( 'Allow extended information requests:', 'woocommerce-paymentsense' ),
					'type'        => 'select',
					'description' => __( 'Specifies whether requests for extended plugin information are allowed. Used for troubleshooting and diagnostics. Recommended Setting "Yes".', 'woocommerce-paymentsense' ),
					'default'     => 'true',
					'desc_tip'    => true,
					'options'     => array(
						'true'  => __( 'Yes', 'woocommerce-paymentsense' ),
						'false' => __( 'No', 'woocommerce-paymentsense' ),
					),
				),

				'extended_payment_method_timeout' => array(
					'title'       => __( 'Payment method timeout:', 'woocommerce-paymentsense' ),
					'type'        => 'text',
					'description' => __( 'Specifies the amount of time this payment method will be available since the order "date_modified" timestamp in seconds. Not setting a value or setting 0 is unlimited. Recommended Setting "1800".', 'woocommerce-paymentsense' ),
					'default'     => 1800,
					'desc_tip'    => true,
				),
			);
		}

		/**
		 * Determines if the payment method is available
		 *
		 * Checks whether the gateway merchant ID, password and pre-shared key are set
		 *
		 * @return  bool
		 */
		public function is_valid_for_use() {
			return (
				! empty( $this->gateway_merchant_id ) &&
				! empty( $this->gateway_password ) &&
				! empty( $this->gateway_presharedkey )
			);
		}

		/**
		 * Receipt page
		 *
		 * @param  int $order_id Order ID.
		 */
		public function receipt_page( $order_id ) {
			if ( $this->is_valid_for_use() ) {
				$this->output_redirect_form( $order_id );
			} else {
				$this->output_message(
					__( 'This module is not configured. Please configure gateway settings.', 'woocommerce-paymentsense' )
				);
			}
		}

		/**
		 * Outputs the redirecting form to the hosted page
		 *
		 * @param  int $order_id WooCommerce OrderID.
		 */
		private function output_redirect_form( $order_id ) {
			$order = new WC_Order( $order_id );
			if ($this->within_payment_method_timeframe($order)) {
				$order->update_status(
					'pending',
					__( 'Pending payment', 'woocommerce-paymentsense' )
				);
				$this->show_output(
					'paymentsense-hosted-redirect.php',
					array(
						'title'                => __(
							'Thank you - your order is now pending payment. You should be automatically redirected to Paymentsense to make payment.',
							'woocommerce-paymentsense'
						),
						'hpf_url'              => $this->get_payment_form_url(),
						'hpf_arguments'        => $this->build_hpf_fields( $order ),
						'hpf_submit_button'    => __(
							'Click here if you are not redirected within 10 seconds...',
							'woocommerce-paymentsense'
						),
						'hpf_redirect_message' => __(
							'We are now redirecting you to Paymentsense to complete your payment.',
							'woocommerce-paymentsense'
						),
					)
				);
			} else {
				$this->output_message(
					__( 'Sorry, the allowed time for paying this order has expired.', 'woocommerce-paymentsense' )
				);
			}
		}

		/**
		 * Redirects to Redirect form (receipt page)
		 *
		 * Overrides  wc payment gateway class method
		 *
		 * @param int $order_id WooCommerce OrderId.
		 * @return array
		 */
		public function process_payment( $order_id ) {
			$order = new WC_Order( $order_id );
			return array(
				'result'   => 'success',
				'redirect' => $order->get_checkout_payment_url( true ),
			);
		}

		/**
		 * Processes the payment gateway response
		 *
		 * @throws Exception Throws exception if Order ID is empty.
		 */
		public function process_gateway_response() {
			if ( $this->is_info_request() ) {
				$this->process_info_request();
			}

			if ( $this->is_checksums_request() ) {
				$this->process_checksums_request();
			}

			if ( $this->is_connection_info_request() ) {
				$this->process_connection_info_request();
			}

			switch ( $this->get_option( 'gateway_result_delivery' ) ) {
				case 'POST':
					$this->authenticated = $this->is_hash_digest_valid( self::REQ_NOTIFICATION );
					$this->process_post_response();
					break;
				case 'SERVER':
					$request_type        = is_numeric( $this->get_http_var( 'StatusCode' ) )
						? self::REQ_NOTIFICATION
						: self::REQ_CUSTOMER_REDIRECT;
					$this->authenticated = $this->is_hash_digest_valid( $request_type );
					switch ( $request_type ) {
						case self::REQ_NOTIFICATION:
							$this->process_server_notification();
							break;
						case self::REQ_CUSTOMER_REDIRECT:
							$this->process_server_customer_redirect();
							break;
					}
					break;
				default:
					$this->output_message( __( 'Unsupported Result Delivery Method.', 'woocommerce-paymentsense' ) );
					exit;
			}
		}

		/**
		 * Processes the notification of the SERVER result delivery method
		 *
		 * @throws Exception Throws exception if Order ID is empty.
		 */
		public function process_server_notification() {
			$order_id = null;
			try {
				$order_id = $this->get_http_var( 'OrderID' );
				if ( empty( $order_id ) ) {
					throw new Exception( __( 'Order ID is empty.', 'woocommerce-paymentsense' ) );
				}
				$location  = null;
				$message   = $this->get_http_var( 'Message' );
				$cross_ref = $this->get_http_var( 'CrossReference' );
				$order     = new WC_Order( $order_id );

				if ( 'processing' === $order->get_status() ) {
					$note = __(
						'An unexpected callback notification has been received. This normally happens when the customer clicks on the \"Back\" button on their web browser or/and attempts to perform further payment transactions after a successful one is made.',
						'woocommerce-paymentsense'
					);

					$order->add_order_note( $note );

					$transaction_status = 'duplicated';
					$error_msg          = __(
						'It seems you already have paid for this order. In case of doubts, please contact us.',
						'woocommerce-paymentsense'
					);

					$this->set_success();
				} else {
					$auth_warning = ! $this->authenticated
						? __(
							'WARNING: The authenticity of the status of this transaction cannot be confirmed automatically! Please check the status at the MMS. ',
							'woocommerce-paymentsense'
						)
						: '';

					update_post_meta( (int) $order->get_id(), 'CrossRef', $this->get_http_var( 'CrossReference' ) );
					switch ( $this->get_http_var( 'StatusCode' ) ) {
						case PS_TRX_RESULT_SUCCESS:
							$transaction_status = 'success';
							break;
						case PS_TRX_RESULT_REFERRED:
							$transaction_status = 'failed';
							break;
						case PS_TRX_RESULT_DECLINED:
							$transaction_status = 'failed';
							break;
						case PS_TRX_RESULT_DUPLICATE:
							if ( PS_TRX_RESULT_SUCCESS === wc_get_post_data_by_key( 'PreviousStatusCode' ) ) {
								$transaction_status = 'success';
							} else {
								$transaction_status = 'failed';
							}
							break;
						case PS_TRX_RESULT_FAILED:
							$transaction_status = 'failed';
							break;
						default:
							$transaction_status = 'unsupported';
							break;
					}

					switch ( $transaction_status ) {
						case 'success':
							$order->payment_complete();
							if ( ! $this->authenticated ) {
								$auth_instructions = sprintf(
									// Translators: %1$s - transaction cross reference, %2$s - transaction message.
									__( 'Please log into your account at the MMS and check that transaction %1$s is processed with status SUCCESS and the message: %2$s. ', 'woocommerce-paymentsense' ),
									$cross_ref,
									$message
								);
								$auth_instructions .= __( 'Once the transaction status and authentication code are confirmed set the order status to "Processing" and process the order normally. ', 'woocommerce-paymentsense' );
								$order->update_status( 'on-hold', $auth_warning );
								$order->add_order_note( $auth_instructions );
							} else {
								$order->add_order_note( __( 'Payment processed successfully. ', 'woocommerce-paymentsense' ) . $message );
							}
							$error_msg = '';
							$this->set_success();
							break;
						case 'failed':
							$order->update_status( 'failed', $auth_warning . __( 'Payment failed due to: ', 'woocommerce-paymentsense' ) . $message );
							$error_msg = __( 'Payment failed due to: ', 'woocommerce-paymentsense' ) . $message . '. ' .
								__( 'Please check your card details and try again.', 'woocommerce-paymentsense' );
							$this->set_success();
							break;
						case 'unsupported':
						default:
							$order->update_status( 'failed', __( 'Payment failed due to unknown or unsupported payment status. Payment Status: ', 'woocommerce-paymentsense' ) . $this->get_http_var( 'StatusCode' ) . '.' );
							$error_msg = __( 'An error occurred while processing your payment. Payment status is unknown. Please contact support. Payment Status: ', 'woocommerce-paymentsense' ) . $this->get_http_var( 'StatusCode' ) . '.';
							$this->set_error( self::MSG_UNSUPPORTED_STATUS );
							break;
					}
				}

				update_post_meta( (int) $order->get_id(), 'PaymentStatus', $transaction_status );
				update_post_meta( (int) $order->get_id(), 'CustomerErrorMsg', $error_msg );
			} catch ( Exception $exception ) {
				$this->set_error(
					sprintf(
						self::MSG_EXCEPTION,
						$exception->getMessage(),
						$order_id
					)
				);
			}
			$this->output_response();
			exit;
		}

		/**
		 * Processes the customer redirect of the SERVER result delivery method
		 */
		public function process_server_customer_redirect() {
			try {
				$order_id = $this->get_http_var( 'OrderID' );
				if ( empty( $order_id ) ) {
					$this->output_message( __( 'Order ID is empty.', 'woocommerce-paymentsense' ) );
					exit;
				}

				$location = null;
				$order    = new WC_Order( $order_id );

				$transaction_status = get_post_meta( $order->get_id(), 'PaymentStatus', true );
				$error_msg          = get_post_meta( $order->get_id(), 'CustomerErrorMsg', true );

				switch ( $transaction_status ) {
					case 'success':
						$location = $order->get_checkout_order_received_url();
						break;
					case 'failed':
					case 'duplicated':
					case 'unsupported':
					default:
						wc_add_notice( $error_msg, 'error' );
						$location = $order->get_checkout_payment_url();
						break;
				}

				wp_safe_redirect( $location );
			} catch ( Exception $exception ) {
				$message = sprintf(
					// Translators: %1$s - order number, %2$s - error message.
					__( 'An error occurred while processing order#%1$s. Error message: %2$s', 'woocommerce-paymentsense' ),
					$this->get_http_var( 'OrderID' ),
					$exception->getMessage()
				);
				$this->output_message( $message );
				exit;
			}
		}

		/**
		 * Processes the response of the POST result delivery method
		 */
		public function process_post_response() {
			try {
				$order_id = $this->get_http_var( 'OrderID' );
				if ( empty( $order_id ) ) {
					$this->output_message( __( 'Order ID is empty.', 'woocommerce-paymentsense' ) );
					exit;
				}

				$location  = null;
				$message   = $this->get_http_var( 'Message' );
				$cross_ref = $this->get_http_var( 'CrossReference' );
				$order     = new WC_Order( $order_id );

				if ( 'processing' === $order->get_status() ) {
					$order->add_order_note(
						__(
							'An unexpected callback notification has been received. This normally happens when the customer clicks on the "Back" button on their web browser or/and attempts to perform further payment transactions after a successful one is made.',
							'woocommerce-paymentsense'
						)
					);
					wc_clear_notices();
					wc_add_notice(
						__( 'It seems you already have paid for this order. In case of doubts, please contact us.', 'woocommerce-paymentsense' ),
						'error'
					);
					$location = $order->get_checkout_payment_url();
					wp_safe_redirect( $location );
					return;
				}

				$auth_warning = ! $this->authenticated
					? __(
						'WARNING: The authenticity of the status of this transaction cannot be confirmed automatically! Please check the status at the MMS. ',
						'woocommerce-paymentsense'
					)
					: '';

				update_post_meta( (int) $order->get_id(), 'CrossRef', $this->get_http_var( 'CrossReference' ) );
				switch ( $this->get_http_var( 'StatusCode' ) ) {
					case PS_TRX_RESULT_SUCCESS:
						$transaction_status = 'success';
						break;
					case PS_TRX_RESULT_REFERRED:
						$transaction_status = 'failed';
						break;
					case PS_TRX_RESULT_DECLINED:
						$transaction_status = 'failed';
						break;
					case PS_TRX_RESULT_DUPLICATE:
						if ( PS_TRX_RESULT_SUCCESS === wc_get_post_data_by_key( 'PreviousStatusCode' ) ) {
							$transaction_status = 'success';
						} else {
							$transaction_status = 'failed';
						}
						break;
					case PS_TRX_RESULT_FAILED:
						$transaction_status = 'failed';
						break;
					default:
						$transaction_status = 'unsupported';
						break;
				}

				switch ( $transaction_status ) {
					case 'success':
						$order->payment_complete();
						if ( ! $this->authenticated ) {
							$auth_instructions = sprintf(
								// Translators: %1$s - transaction cross reference, %2$s - transaction message.
								__( 'Please log into your account at the MMS and check that transaction %1$s is processed with status SUCCESS and the message: %2$s. ', 'woocommerce-paymentsense' ),
								$cross_ref,
								$message
							);
							$auth_instructions .= __( 'Once the transaction status and authentication code are confirmed set the order status to "Processing" and process the order normally. ', 'woocommerce-paymentsense' );
							$order->update_status( 'on-hold', $auth_warning );
							$order->add_order_note( $auth_instructions );
						} else {
							$order->add_order_note( __( 'Payment processed successfully. ', 'woocommerce-paymentsense' ) . $message );
						}

						delete_post_meta( (int) $order->get_id(), 'ErrMessage' );

						$location = $order->get_checkout_order_received_url();
						break;
					case 'failed':
						$order->update_status( 'failed', $auth_warning . __( 'Payment failed due to: ', 'woocommerce-paymentsense' ) . $message );
						update_post_meta(
							(int) $order->get_id(),
							'ErrMessage',
							__( 'Payment failed due to: ', 'woocommerce-paymentsense' ) . $message . '. ' .
							__( 'Please check your card details and try again.', 'woocommerce-paymentsense' )
						);
						$location = $order->get_checkout_order_received_url();
						break;
					case 'unsupported':
					default:
						$order->update_status( 'failed', __( 'Payment failed due to unknown or unsupported payment status. Payment Status: ', 'woocommerce-paymentsense' ) . $this->get_http_var( 'StatusCode' ) . '.' );
						update_post_meta(
							(int) $order->get_id(),
							'ErrMessage',
							__( 'An error occurred while processing your payment. Payment status is unknown. Please contact support. Payment Status: ', 'woocommerce-paymentsense' ) . $this->get_http_var( 'StatusCode' ) . '.'
						);
						$location = $order->get_checkout_order_received_url();
						break;
				}
				wp_safe_redirect( $location );
			} catch ( Exception $exception ) {
				$message = sprintf(
				// Translators: %1$s - order number, %2$s - error message.
					__( 'An error occurred while processing order#%1$s. Error message: %2$s', 'woocommerce-paymentsense' ),
					$this->get_http_var( 'OrderID' ),
					$exception->getMessage()
				);
				$this->output_message( $message );
				exit;
			}
		}

		/**
		 * Removes the default "thank you" message when the payment is unsuccessful
		 *
		 * @param string   $message The message.
		 * @param WC_Order $order   WooCommerce order object.
		 *
		 * @return string
		 */
		public function process_order_received_text( $message, $order ) {
			$result = $message;
			if ( ( $order->get_payment_method() === $this->id ) && get_post_meta( $order->get_id(), 'ErrMessage', true ) ) {
				$result = '';
			}
			return $result;
		}

		/**
		 * Inserts additional content when the payment is unsuccessful
		 *
		 * @param int $order_id WooCommerce OrderId.
		 */
		public function process_before_thankyou( $order_id ) {
			$order = new WC_Order( $order_id );
			if ( $order->get_payment_method() === $this->id ) {
				$error_message = get_post_meta( $order->get_id(), 'ErrMessage', true );
				if ( $error_message ) {
					$this->show_output(
						'paymentsense-hosted-unsuccessful-payment.php',
						array(
							'error_message'        => $error_message,
							'checkout_payment_url' => $order->get_checkout_payment_url(),
							'retry_payment_button' => __(
								'Retry payment',
								'woocommerce-paymentsense'
							),
						)
					);
				}
			}
		}

		/**
		 * Sets the success response message and status code
		 */
		protected function set_success() {
			$this->set_response( self::STATUS_CODE_OK, self::MSG_SUCCESS );
		}

		/**
		 * Sets the error response message and status code
		 *
		 * @param string $message Response message.
		 */
		protected function set_error( $message ) {
			$this->set_response( self::STATUS_CODE_ERROR, $message );
		}

		/**
		 * Sets the response variables
		 *
		 * @param string $status_code Response status code.
		 * @param string $message Response message.
		 */
		protected function set_response( $status_code, $message ) {
			$this->response_vars['status_code'] = $status_code;
			$this->response_vars['message']     = $message;
		}

		/**
		 * Outputs the response and exits
		 */
		protected function output_response() {
			// @codingStandardsIgnoreLine
			echo "StatusCode={$this->response_vars['status_code']}&Message={$this->response_vars['message']}";
			exit;
		}

		/**
		 * Gets the message about the connection settings.
		 *
		 * @param bool $text_format Specifies whether the format of the message is text.
		 *
		 * @return array
		 */
		public function get_connection_settings_message( $text_format ) {
			$result = array();
			if ( ! $this->merchant_id_format_valid() ) {
				$result = $this->build_error_settings_message(
					__(
						'Gateway MerchantID is invalid. Please make sure the Gateway MerchantID matches the ABCDEF-1234567 format.',
						'woocommerce-paymentsense'
					)
				);
			} else {
				$merchant_credentials_valid = null;
				foreach ( $this->connection_info[ self::CONN_INFO_GGEP ] as $connection_info ) {
					if ( CURLE_OK === $connection_info['curl_errno'] ) {
						$trx_status_code = $this->get_xml_value( 'StatusCode', $connection_info['response'], '[0-9]+' );
						if ( PS_TRX_RESULT_SUCCESS === $trx_status_code ) {
							$merchant_credentials_valid = true;
							break;
						} elseif ( PS_TRX_RESULT_FAILED === $trx_status_code ) {
							$trx_message = $this->get_xml_value( 'Message', $connection_info['response'], '.+' );
							if ( $this->merchant_credentials_invalid( $trx_message ) ) {
								$merchant_credentials_valid = false;
								break;
							}
						}
					}
				}
				$gateway_settings_response = $this->check_gateway_settings();
				switch ( $gateway_settings_response ) {
					case self::HPF_RESP_OK:
						$result = $this->build_success_settings_message(
							__(
								'Gateway MerchantID, Gateway Password, Gateway PreSharedKey and Gateway Hash Method are valid.',
								'woocommerce-paymentsense'
							)
						);
						break;
					case self::HPF_RESP_MID_MISSING:
					case self::HPF_RESP_MID_NOT_EXISTS:
						$result = $this->build_error_settings_message(
							__(
								'Gateway MerchantID is invalid.',
								'woocommerce-paymentsense'
							)
						);
						break;
					case self::HPF_RESP_HASH_INVALID:
						if ( true === $merchant_credentials_valid ) {
							$result = $this->build_error_settings_message(
								__(
									'Gateway PreSharedKey or/and Gateway Hash Method are invalid.',
									'woocommerce-paymentsense'
								)
							);
						} elseif ( false === $merchant_credentials_valid ) {
							$result = $this->build_error_settings_message(
								__(
									'Gateway Password is invalid.',
									'woocommerce-paymentsense'
								)
							);
						} else {
							$result = $this->build_error_settings_message(
								__(
									'Gateway Password, Gateway PreSharedKey or/and Gateway Hash Method are invalid.',
									'woocommerce-paymentsense'
								)
							);
						}
						break;
					case self::HPF_RESP_NO_RESPONSE:
						if ( true === $merchant_credentials_valid ) {
							$result = $this->build_warning_settings_message(
								__(
									'Gateway PreSharedKey and Gateway Hash Method cannot be validated at this time.',
									'woocommerce-paymentsense'
								)
							);
						} elseif ( false === $merchant_credentials_valid ) {
							$result = $this->build_error_settings_message(
								__(
									'Gateway MerchantID or/and Gateway Password are invalid.',
									'woocommerce-paymentsense'
								)
							);
						} else {
							$result = $this->build_warning_settings_message(
								__(
									'The gateway settings cannot be validated at this time.',
									'woocommerce-paymentsense'
								)
							);
						}
						break;
				}
			}

			if ( $text_format ) {
				$result = $this->getSettingsTextMessage( $result );
			}

			return $result;
		}
	}
}
