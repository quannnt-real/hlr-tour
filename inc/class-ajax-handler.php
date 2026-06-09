<?php
/**
 * AJAX Handler for HaLong Tour Theme
 *
 * @package HaLong_Tour
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Halong_Ajax_Handler' ) ) {

	class Halong_Ajax_Handler {

		/**
		 * Constructor — register all AJAX actions and cron hooks.
		 */
		public function __construct() {
			// Public AJAX (no login required)
			add_action( 'wp_ajax_nopriv_halong_create_booking', [ $this, 'create_booking' ] );
			add_action( 'wp_ajax_halong_create_booking',        [ $this, 'create_booking' ] );

			add_action( 'wp_ajax_nopriv_halong_lookup_tax', [ $this, 'lookup_tax_code' ] );
			add_action( 'wp_ajax_halong_lookup_tax',        [ $this, 'lookup_tax_code' ] );

			add_action( 'wp_ajax_nopriv_halong_check_capacity', [ $this, 'check_capacity' ] );
			add_action( 'wp_ajax_halong_check_capacity',        [ $this, 'check_capacity' ] );

			// WP Cron for expiring pending orders
			add_action( 'halong_expire_bookings_cron', [ $this, 'expire_pending_bookings' ] );
		}

		// -------------------------------------------------------------------------
		// Helper: verify nonce and die with 403 on failure
		// -------------------------------------------------------------------------

		/**
		 * Verify the booking nonce. Sends a 403 JSON error and exits on failure.
		 *
		 * @return void
		 */
		private function verify_nonce() {
			$result = check_ajax_referer( 'halong_booking_nonce', 'nonce', false );
			if ( false === $result ) {
				wp_send_json_error(
					[ 'message' => __( 'Yêu cầu không hợp lệ. Vui lòng tải lại trang.', 'halong-tour' ) ],
					403
				);
			}
		}

		// -------------------------------------------------------------------------
		// Helper: check booking system toggle
		// -------------------------------------------------------------------------

		/**
		 * Check whether the booking system is enabled.
		 * Sends a JSON error and exits when disabled.
		 *
		 * @return void
		 */
		private function check_booking_enabled() {
			$enabled = get_option( 'halong_enable_booking', true );
			if ( ! $enabled ) {
				wp_send_json_error(
					[ 'message' => __( 'Hệ thống đặt tour tạm ngưng', 'halong-tour' ) ],
					503
				);
			}
		}

		// -------------------------------------------------------------------------
		// Helper: resolve slot capacity for a given tour / time
		// -------------------------------------------------------------------------

		/**
		 * Return the capacity for a specific time slot on a tour.
		 *
		 * Falls back to the tour-level max_guests, then to 15.
		 *
		 * @param int    $tour_id Post ID of the tour.
		 * @param string $time    Time string to match against the repeater.
		 * @return int
		 */
		private function get_slot_capacity( $tour_id, $time ) {
			$tour_max = (int) get_field( 'halong_tour_max_guests', $tour_id );
			if ( $tour_max <= 0 ) {
				$tour_max = 15;
			}

			$slots = get_field( 'halong_time_slots', $tour_id );
			if ( ! empty( $slots ) && is_array( $slots ) ) {
				foreach ( $slots as $slot ) {
					$slot_time = isset( $slot['slot_time'] ) ? trim( $slot['slot_time'] ) : '';
					if ( $slot_time === trim( $time ) ) {
						$cap = isset( $slot['slot_capacity'] ) ? (int) $slot['slot_capacity'] : 0;
						return $cap > 0 ? $cap : $tour_max;
					}
				}
			}

			return $tour_max;
		}

		// -------------------------------------------------------------------------
		// check_capacity()
		// -------------------------------------------------------------------------

		/**
		 * AJAX handler: check whether a time slot still has room.
		 *
		 * POST params: tour_id, date (d/m/Y), time, guests
		 */
		public function check_capacity() {
			$this->verify_nonce();
			$this->check_booking_enabled();

			// Sanitize inputs
			$tour_id = absint( $_POST['tour_id'] ?? 0 );
			$date    = sanitize_text_field( wp_unslash( $_POST['date'] ?? '' ) );
			$time    = sanitize_text_field( wp_unslash( $_POST['time'] ?? '' ) );
			$guests  = absint( $_POST['guests'] ?? 0 );

			// Basic validation
			if ( ! $tour_id || ! $date || ! $time || $guests < 1 ) {
				wp_send_json_error(
					[ 'message' => __( 'Vui lòng cung cấp đầy đủ thông tin.', 'halong-tour' ) ],
					400
				);
			}

			$slot_capacity = $this->get_slot_capacity( $tour_id, $time );
			$booked_count  = Halong_Booking_CPT::get_booked_count( $tour_id, $date, $time );
			$remaining     = $slot_capacity - $booked_count;

			if ( $booked_count + $guests > $slot_capacity ) {
				wp_send_json_error(
					[
						'message'   => sprintf(
							/* translators: %d: remaining seats */
							__( 'Slot đã hết chỗ. Còn lại: %d chỗ.', 'halong-tour' ),
							max( 0, $remaining )
						),
						'remaining' => max( 0, $remaining ),
					],
					400
				);
			}

			wp_send_json_success(
				[
					'available' => true,
					'remaining' => $remaining - $guests,
				]
			);
		}

		// -------------------------------------------------------------------------
		// create_booking()
		// -------------------------------------------------------------------------

		/**
		 * AJAX handler: create a new tour booking.
		 *
		 * POST params: tour_id, date, time, adults, children, customer_name,
		 *              customer_phone, customer_email, customer_note,
		 *              vat_requested, vat_company_name, vat_tax_code, vat_address
		 */
		public function create_booking() {
			$this->verify_nonce();

			// ---- Sanitize all inputs ------------------------------------------------
			$tour_id          = absint( $_POST['tour_id']          ?? 0 );
			$date             = sanitize_text_field( wp_unslash( $_POST['date']             ?? '' ) );
			$time             = sanitize_text_field( wp_unslash( $_POST['time']             ?? '' ) );
			$adults           = absint( $_POST['adults']           ?? 0 );
			$children         = absint( $_POST['children']         ?? 0 );
			$customer_name    = sanitize_text_field( wp_unslash( $_POST['customer_name']    ?? '' ) );
			$customer_phone   = sanitize_text_field( wp_unslash( $_POST['customer_phone']   ?? '' ) );
			$customer_email   = sanitize_email( wp_unslash( $_POST['customer_email']        ?? '' ) );
			$customer_note    = sanitize_textarea_field( wp_unslash( $_POST['customer_note'] ?? '' ) );
			$vat_raw          = strtolower( (string) ( $_POST['vat_requested'] ?? '' ) );
			$vat_requested    = in_array( $vat_raw, [ '1', 'true', 'yes' ], true );
			$vat_company_name = sanitize_text_field( wp_unslash( $_POST['vat_company_name'] ?? '' ) );
			$vat_tax_code     = sanitize_text_field( wp_unslash( $_POST['vat_tax_code']     ?? '' ) );
			$vat_address      = sanitize_textarea_field( wp_unslash( $_POST['vat_address']  ?? '' ) );

			// ---- Validate required fields -------------------------------------------
			$errors = [];

			if ( ! $tour_id ) {
				$errors[] = __( 'Tour không hợp lệ.', 'halong-tour' );
			}
			if ( ! $date ) {
				$errors[] = __( 'Vui lòng chọn ngày đi.', 'halong-tour' );
			}
			if ( ! $time ) {
				$errors[] = __( 'Vui lòng chọn giờ khởi hành.', 'halong-tour' );
			}
			if ( $adults < 1 ) {
				$errors[] = __( 'Phải có ít nhất 1 người lớn.', 'halong-tour' );
			}
			if ( ! $customer_name ) {
				$errors[] = __( 'Vui lòng nhập họ tên liên hệ.', 'halong-tour' );
			}
			if ( ! $customer_phone ) {
				$errors[] = __( 'Vui lòng nhập số điện thoại.', 'halong-tour' );
			}
			if ( ! $customer_email ) {
				$errors[] = __( 'Vui lòng nhập địa chỉ email.', 'halong-tour' );
			} elseif ( ! is_email( $customer_email ) ) {
				$errors[] = __( 'Địa chỉ email không đúng định dạng.', 'halong-tour' );
			}

			if ( ! empty( $errors ) ) {
				wp_send_json_error( [ 'message' => implode( ' ', $errors ) ], 400 );
			}

			// ---- Booking system toggle ----------------------------------------------
			$this->check_booking_enabled();

			// ---- Capacity check -----------------------------------------------------
			$slot_capacity = $this->get_slot_capacity( $tour_id, $time );
			$total_guests  = $adults + $children;
			$booked_count  = Halong_Booking_CPT::get_booked_count( $tour_id, $date, $time );

			if ( $booked_count + $total_guests > $slot_capacity ) {
				$remaining = max( 0, $slot_capacity - $booked_count );
				wp_send_json_error(
					[
						'message'   => sprintf(
							/* translators: %d: remaining seats */
							__( 'Slot không đủ chỗ. Còn lại: %d chỗ.', 'halong-tour' ),
							$remaining
						),
						'remaining' => $remaining,
					],
					400
				);
			}

			// ---- Generate booking code ----------------------------------------------
			$booking_code = Halong_Booking_CPT::generate_booking_code();

			// ---- Calculate total price ----------------------------------------------
			$adult_price = (int) get_field( 'halong_adult_price', $tour_id );
			if ( $adult_price <= 0 ) {
				$adult_price = 450000;
			}

			$child_price = (int) get_field( 'halong_child_price', $tour_id );
			if ( $child_price <= 0 ) {
				$child_price = 225000;
			}

			$total_price = ( $adults * $adult_price ) + ( $children * $child_price );

			// ---- Create WP post -----------------------------------------------------
			$post_id = wp_insert_post(
				[
					'post_type'   => 'tour_booking',
					'post_status' => 'publish',
					'post_title'  => $booking_code . ' - ' . $customer_name,
				],
				true
			);

			if ( is_wp_error( $post_id ) ) {
				wp_send_json_error(
					[ 'message' => __( 'Không thể tạo đơn đặt tour. Vui lòng thử lại.', 'halong-tour' ) ],
					500
				);
			}

			// ---- Save all meta fields -----------------------------------------------
			update_post_meta( $post_id, 'booking_code',         $booking_code );
			update_post_meta( $post_id, 'booking_tour_id',      $tour_id );
			// Save using SCF field name so Tour post_object field resolves correctly in admin
			if ( function_exists( 'update_field' ) ) {
				update_field( 'booking_tour', $tour_id, $post_id );
			} else {
				update_post_meta( $post_id, 'booking_tour', $tour_id );
			}
			update_post_meta( $post_id, 'booking_date',         $date );
			update_post_meta( $post_id, 'booking_time',         $time );
			update_post_meta( $post_id, 'booking_adults',       $adults );
			update_post_meta( $post_id, 'booking_children',     $children );
			update_post_meta( $post_id, 'booking_total_guests', $total_guests );
			update_post_meta( $post_id, 'booking_adult_price',  $adult_price );
			update_post_meta( $post_id, 'booking_child_price',  $child_price );
			update_post_meta( $post_id, 'booking_total_price',  $total_price );
			update_post_meta( $post_id, 'customer_name',        $customer_name );
			update_post_meta( $post_id, 'customer_phone',       $customer_phone );
			update_post_meta( $post_id, 'customer_email',       $customer_email );
			update_post_meta( $post_id, 'customer_note',        $customer_note );
			update_post_meta( $post_id, 'vat_requested',        $vat_requested ? '1' : '0' );

			if ( $vat_requested ) {
				update_post_meta( $post_id, 'vat_company_name', $vat_company_name );
				update_post_meta( $post_id, 'vat_tax_code',     $vat_tax_code );
				update_post_meta( $post_id, 'vat_address',      $vat_address );
			}

			// ---- Booking status and expiry ------------------------------------------
			update_post_meta( $post_id, 'booking_status', 'pending_payment' );

			$expire_hours = (int) get_option( 'halong_booking_expire_hours', 24 );
			if ( $expire_hours <= 0 ) {
				$expire_hours = 24;
			}
			$expire_at = gmdate( 'Y-m-d H:i:s', time() + ( $expire_hours * HOUR_IN_SECONDS ) );
			update_post_meta( $post_id, 'booking_expired_at', $expire_at );

			// ---- Audit log ----------------------------------------------------------
			Halong_Audit_Log::log(
				$post_id,
				'booking_created',
				'',
				'pending_payment',
				sprintf( 'Đặt tour mới: %s', $booking_code )
			);

			// ---- Fire action --------------------------------------------------------
			do_action( 'halong_booking_created', $post_id );

			// ---- Build VietQR URL ---------------------------------------------------
			$bank_bin  = get_field( 'halong_bank_bin',     'option' ) ?: '970422';
			$account   = get_field( 'halong_bank_account', 'option' ) ?: '';
			$acct_name = get_field( 'halong_bank_name',    'option' ) ?: 'HALONG RUM';

			$payment_desc = Halong_Booking_CPT::get_payment_description( $booking_code );

			$qr_url    = sprintf(
				'https://img.vietqr.io/image/%s-%s-compact2.png?amount=%d&addInfo=%s&accountName=%s',
				rawurlencode( $bank_bin ),
				rawurlencode( $account ),
				$total_price,
				rawurlencode( $payment_desc ),
				rawurlencode( $acct_name )
			);

			// ---- Return success -----------------------------------------------------
			wp_send_json_success(
				[
					'booking_code' => $booking_code,
					'payment_desc' => $payment_desc,
					'total_price'  => $total_price,
					'qr_url'       => $qr_url,
					'expire_at'    => $expire_at,
					'redirect_url' => home_url( '/payment/?code=' . $booking_code ),
				]
			);
		}

		// -------------------------------------------------------------------------
		// lookup_tax_code()
		// -------------------------------------------------------------------------

		/**
		 * AJAX handler: look up a Vietnamese business tax code via VietQR API.
		 *
		 * POST params: tax_code
		 */
		public function lookup_tax_code() {
			$this->verify_nonce();

			// Sanitize: digits and dash only
			$raw_tax_code = wp_unslash( $_POST['tax_code'] ?? '' );
			$tax_code     = preg_replace( '/[^0-9-]/', '', $raw_tax_code );

			// Validate length
			$len = strlen( $tax_code );
			if ( $len < 10 || $len > 14 ) {
				wp_send_json_error(
					[ 'message' => __( 'Mã số thuế không đúng định dạng (10–14 ký tự số).', 'halong-tour' ) ],
					400
				);
			}

			// ---- Call VietQR API from server side -----------------------------------
			$url      = 'https://api.vietqr.io/v2/business/' . $tax_code;
			$response = wp_remote_get(
				$url,
				[
					'timeout' => 10,
					'headers' => [ 'Accept' => 'application/json' ],
				]
			);

			if ( is_wp_error( $response ) ) {
				wp_send_json_error(
					[ 'message' => __( 'Không thể tra cứu MST. Vui lòng thử lại sau.', 'halong-tour' ) ],
					502
				);
			}

			$http_code = wp_remote_retrieve_response_code( $response );
			if ( 200 !== (int) $http_code ) {
				wp_send_json_error(
					[ 'message' => __( 'Không thể tra cứu MST. Vui lòng thử lại sau.', 'halong-tour' ) ],
					502
				);
			}

			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );

			if (
				empty( $data )
				|| ! isset( $data['code'] )
				|| '00' !== $data['code']
				|| empty( $data['data'] )
			) {
				wp_send_json_error(
					[ 'message' => __( 'Mã số thuế không hợp lệ hoặc không tồn tại.', 'halong-tour' ) ],
					404
				);
			}

			$biz     = $data['data'];
			$name    = isset( $biz['name'] )    ? sanitize_text_field( $biz['name'] )    : '';
			$address = isset( $biz['address'] ) ? sanitize_text_field( $biz['address'] ) : '';

			if ( ! $name ) {
				wp_send_json_error(
					[ 'message' => __( 'Mã số thuế không hợp lệ hoặc không tồn tại.', 'halong-tour' ) ],
					404
				);
			}

			wp_send_json_success(
				[
					'company_name' => $name,
					'address'      => $address,
				]
			);
		}

		// -------------------------------------------------------------------------
		// expire_pending_bookings()
		// -------------------------------------------------------------------------

		/**
		 * WP-Cron callback: mark expired pending bookings as 'expired'.
		 *
		 * No nonce required — called only by the scheduler.
		 *
		 * @return int Number of bookings expired.
		 */
		public function expire_pending_bookings() {
			global $wpdb;

			$now = current_time( 'mysql', true ); // UTC

			// Find all pending_payment bookings whose expiry timestamp has passed.
			// Using $wpdb->prepare() for all raw queries.
			// phpcs:disable WordPress.DB.DirectDatabaseQuery
			$expired_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT p.ID
					FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->postmeta} pm_status
						ON pm_status.post_id = p.ID
						AND pm_status.meta_key = 'booking_status'
						AND pm_status.meta_value = %s
					INNER JOIN {$wpdb->postmeta} pm_expiry
						ON pm_expiry.post_id = p.ID
						AND pm_expiry.meta_key = 'booking_expired_at'
						AND pm_expiry.meta_value < %s
					WHERE p.post_type = 'tour_booking'
					AND p.post_status = 'publish'",
					'pending_payment',
					$now
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery

			$count = 0;

			if ( ! empty( $expired_ids ) ) {
				foreach ( $expired_ids as $id ) {
					$id = (int) $id;

					update_post_meta( $id, 'booking_status', 'expired' );

					Halong_Audit_Log::log(
						$id,
						'booking_expired',
						'pending_payment',
						'expired',
						'Tự động hết hạn bởi WP-Cron'
					);

					++$count;
				}
			}

			return $count;
		}

	} // end class Halong_Ajax_Handler

} // end class_exists guard
