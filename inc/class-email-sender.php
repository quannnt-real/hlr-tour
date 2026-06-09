<?php
/**
 * HaLong Tour - Email Sender Class
 *
 * Handles all transactional emails for the booking system:
 * - Customer pending email (after booking created)
 * - Customer confirmed email (after admin confirms)
 * - Admin notification emails
 *
 * @package HaLongTour
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Halong_Email_Sender' ) ) :

class Halong_Email_Sender {

	/**
	 * Constructor - register action hooks.
	 */
	public function __construct() {
		add_action( 'halong_booking_confirmed', [ $this, 'send_confirmed_emails' ] );
		add_action( 'halong_booking_created',   [ $this, 'send_pending_emails' ] );
	}

	// =========================================================================
	// HELPER METHODS
	// =========================================================================

	/**
	 * Gather all booking meta for a given post ID.
	 *
	 * @param  int   $post_id  Booking post ID.
	 * @return array           Associative array of booking data.
	 */
	public function get_booking_data( $post_id ) {
		// Meta keys must match what class-ajax-handler.php saves via update_post_meta()
		$tour_id = (int) get_post_meta( $post_id, 'booking_tour_id', true );

		return [
			'booking_code'  => get_post_meta( $post_id, 'booking_code',         true ),
			'tour_id'       => $tour_id,
			'tour_title'    => $tour_id ? get_the_title( $tour_id ) : '',
			'date'          => get_post_meta( $post_id, 'booking_date',          true ),
			'time'          => get_post_meta( $post_id, 'booking_time',          true ),
			'adults'        => (int) get_post_meta( $post_id, 'booking_adults',        true ),
			'children'      => (int) get_post_meta( $post_id, 'booking_children',     true ),
			'total_guests'  => (int) get_post_meta( $post_id, 'booking_total_guests',  true ),
			'total_price'   => (float) get_post_meta( $post_id, 'booking_total_price', true ),
			'customer'      => [
				'name'  => get_post_meta( $post_id, 'customer_name',  true ),
				'phone' => get_post_meta( $post_id, 'customer_phone', true ),
				'email' => get_post_meta( $post_id, 'customer_email', true ),
				'note'  => get_post_meta( $post_id, 'customer_note',  true ),
			],
			'vat'           => [
				'requested'    => (bool) get_post_meta( $post_id, 'vat_requested',    true ),
				'company_name' => get_post_meta( $post_id, 'vat_company_name', true ),
				'tax_code'     => get_post_meta( $post_id, 'vat_tax_code',     true ),
				'address'      => get_post_meta( $post_id, 'vat_address',      true ),
			],
		];
	}

	/**
	 * Format a numeric amount as Vietnamese Dong.
	 *
	 * @param  float|int $amount
	 * @return string
	 */
	public function format_price( $amount ) {
		return number_format( $amount, 0, ',', '.' ) . ' ₫';
	}

	/**
	 * Get admin notification email addresses from SCF option.
	 *
	 * @return array
	 */
	public function get_admin_emails() {
		$raw = function_exists( 'get_field' )
			? get_field( 'halong_admin_emails', 'option' )
			: get_option( 'halong_admin_emails', '' );

		if ( ! empty( $raw ) ) {
			$emails = array_filter(
				array_map( 'trim', explode( "\n", $raw ) )
			);

			if ( ! empty( $emails ) ) {
				return array_values( $emails );
			}
		}

		return [ get_option( 'admin_email' ) ];
	}

	/**
	 * Build the wp_mail() headers array.
	 *
	 * @return array
	 */
	public function get_from_headers() {
		$from_name = function_exists( 'get_field' )
			? get_field( 'halong_from_name', 'option' )
			: get_option( 'halong_from_name', '' );

		$from_email = function_exists( 'get_field' )
			? get_field( 'halong_from_email', 'option' )
			: get_option( 'halong_from_email', '' );

		if ( empty( $from_name ) ) {
			$from_name = 'HaLong Rum';
		}

		if ( empty( $from_email ) ) {
			$from_email = get_option( 'admin_email' );
		}

		return [
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: %s <%s>', $from_name, $from_email ),
		];
	}

	// =========================================================================
	// STATIC SEND UTILITY
	// =========================================================================

	/**
	 * Send a single email and log the result.
	 *
	 * @param  string|array $to         Recipient(s).
	 * @param  string       $subject    Email subject.
	 * @param  string       $html_body  Full HTML body.
	 * @param  int          $booking_id Optional booking post ID for logging.
	 * @return bool
	 */
	public static function send( $to, $subject, $html_body, $booking_id = 0 ) {
		$instance = new self();
		$headers  = $instance->get_from_headers();

		$sent = wp_mail( $to, $subject, $html_body, $headers );

		if ( class_exists( 'Halong_Audit_Log' ) ) {
			$recipient = is_array( $to ) ? implode( ', ', $to ) : $to;
			Halong_Audit_Log::log_email( $booking_id, $subject, $recipient, $sent );
		}

		return $sent;
	}

	// =========================================================================
	// PENDING EMAILS (booking created)
	// =========================================================================

	/**
	 * Send notification emails when a new booking is created.
	 *
	 * @param int $post_id Booking post ID.
	 */
	public function send_pending_emails( $post_id ) {
		$enable = function_exists( 'get_field' )
			? get_field( 'halong_enable_email', 'option' )
			: get_option( 'halong_enable_email', true );

		if ( ! $enable ) {
			return;
		}

		$data    = $this->get_booking_data( $post_id );
		$headers = $this->get_from_headers();

		// --- Admin email ---
		$admin_subject = sprintf(
			'[NEW BOOKING] HLR Tour - %s %s - %s (%d khách) - CẦN XÁC NHẬN',
			$data['date'],
			$data['time'],
			$data['customer']['name'],
			$data['total_guests']
		);
		$admin_body   = $this->build_admin_pending_html( $data, $post_id );
		$admin_emails = $this->get_admin_emails();

		$admin_sent = wp_mail( $admin_emails, $admin_subject, $admin_body, $headers );

		if ( class_exists( 'Halong_Audit_Log' ) ) {
			Halong_Audit_Log::log_email(
				$post_id,
				$admin_subject,
				implode( ', ', $admin_emails ),
				$admin_sent
			);
		}

		// --- Customer email ---
		$customer_email = $data['customer']['email'];
		if ( ! empty( $customer_email ) ) {
			$customer_subject = sprintf(
				'HaLong Rum - Đặt tour thành công! Mã vé: %s',
				$data['booking_code']
			);
			$customer_body = $this->build_customer_pending_html( $data );
			$customer_sent = wp_mail( $customer_email, $customer_subject, $customer_body, $headers );

			if ( class_exists( 'Halong_Audit_Log' ) ) {
				Halong_Audit_Log::log_email(
					$post_id,
					$customer_subject,
					$customer_email,
					$customer_sent
				);
			}
		}
	}

	// =========================================================================
	// CONFIRMED EMAILS (admin confirmed booking)
	// =========================================================================

	/**
	 * Send confirmation emails when an admin confirms a booking.
	 *
	 * @param int $post_id Booking post ID.
	 */
	public function send_confirmed_emails( $post_id ) {
		$enable = function_exists( 'get_field' )
			? get_field( 'halong_enable_email', 'option' )
			: get_option( 'halong_enable_email', true );

		if ( ! $enable ) {
			return;
		}

		$data    = $this->get_booking_data( $post_id );
		$headers = $this->get_from_headers();

		// --- Customer confirmed email ---
		$customer_email = $data['customer']['email'];
		if ( ! empty( $customer_email ) ) {
			$customer_subject = sprintf(
				'🎉 HaLong Rum - Vé Tour đã xác nhận! Mã: %s',
				$data['booking_code']
			);
			$customer_body = $this->build_customer_confirmed_html( $data );
			$customer_sent = wp_mail( $customer_email, $customer_subject, $customer_body, $headers );

			if ( class_exists( 'Halong_Audit_Log' ) ) {
				Halong_Audit_Log::log_email(
					$post_id,
					$customer_subject,
					$customer_email,
					$customer_sent
				);
			}
		}

		// --- Admin/receptionist confirmed email ---
		$admin_subject = sprintf(
			'[CONFIRMED] Đoàn khách %s - %s %s - %d người',
			$data['customer']['name'],
			$data['date'],
			$data['time'],
			$data['total_guests']
		);
		$admin_body   = $this->build_admin_confirmed_html( $data );
		$admin_emails = $this->get_admin_emails();

		$admin_sent = wp_mail( $admin_emails, $admin_subject, $admin_body, $headers );

		if ( class_exists( 'Halong_Audit_Log' ) ) {
			Halong_Audit_Log::log_email(
				$post_id,
				$admin_subject,
				implode( ', ', $admin_emails ),
				$admin_sent
			);
		}
	}

	// =========================================================================
	// HTML TEMPLATE BUILDERS
	// =========================================================================

	/**
	 * Shared email wrapper — generates the full HTML document with header/footer.
	 *
	 * @param  string $content   Inner HTML content.
	 * @param  string $preheader Short preview text (hidden from body).
	 * @return string            Complete HTML email string.
	 */
	private function wrap_email( $content, $preheader = '' ) {
		$hotline = function_exists( 'get_field' )
			? get_field( 'halong_hotline', 'option' )
			: get_option( 'halong_hotline', '1900 xxxx' );

		$site_url = home_url();

		ob_start();
		?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title>HaLong Rum</title>
</head>
<body style="margin:0;padding:0;background-color:#0d1f0a;font-family:Arial,Helvetica,sans-serif;">
<?php if ( $preheader ) : ?>
<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;"><?php echo esc_html( $preheader ); ?>&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;</div>
<?php endif; ?>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#0d1f0a;">
  <tr>
    <td align="center" style="padding:20px 10px;">
      <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="max-width:600px;width:100%;background-color:#1E2B1A;border-radius:12px;overflow:hidden;">

        <!-- HEADER -->
        <tr>
          <td align="center" style="background-color:#0d1f0a;padding:30px 20px;border-bottom:2px solid #C6A96B;">
            <a href="<?php echo esc_url( $site_url ); ?>" style="text-decoration:none;">
              <div style="font-size:28px;font-weight:bold;color:#C6A96B;letter-spacing:3px;font-family:Georgia,serif;">HaLong Rum</div>
              <div style="font-size:12px;color:#F0EBE1;letter-spacing:2px;margin-top:4px;opacity:0.8;">DISTILLERY &amp; TASTING TOUR</div>
            </a>
          </td>
        </tr>

        <!-- CONTENT -->
        <tr>
          <td style="padding:30px 30px 10px 30px;">
            <?php echo $content; // Already escaped within each builder. ?>
          </td>
        </tr>

        <!-- FOOTER -->
        <tr>
          <td style="padding:20px 30px 30px 30px;border-top:1px solid #2d4a28;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
              <tr>
                <td style="padding-top:20px;">
                  <p style="margin:0 0 6px 0;color:#C6A96B;font-size:13px;font-weight:bold;">Hotline hỗ trợ: <?php echo esc_html( $hotline ); ?></p>
                  <p style="margin:0 0 6px 0;color:#F0EBE1;font-size:12px;opacity:0.7;">Nhà máy HaLong Rum, Khu Công Nghiệp Cái Lân, TP Hạ Long, Quảng Ninh</p>
                  <p style="margin:0;color:#F0EBE1;font-size:11px;opacity:0.5;">Email này được gửi tự động từ hệ thống đặt tour HaLong Rum. Vui lòng không reply trực tiếp email này.</p>
                </td>
              </tr>
            </table>
          </td>
        </tr>

      </table>
    </td>
  </tr>
</table>
</body>
</html>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render a two-column info row (label + value) for booking detail tables.
	 *
	 * @param  string $label
	 * @param  string $value
	 * @return string HTML <tr> string.
	 */
	private function info_row( $label, $value ) {
		return sprintf(
			'<tr>
				<td style="padding:8px 12px;color:#C6A96B;font-size:13px;font-weight:bold;white-space:nowrap;vertical-align:top;width:40%%;">%s</td>
				<td style="padding:8px 12px;color:#F0EBE1;font-size:13px;vertical-align:top;">%s</td>
			</tr>',
			esc_html( $label ),
			esc_html( $value )
		);
	}

	/**
	 * Build VAT information block HTML. Returns empty string when VAT not requested.
	 *
	 * @param  array $vat  VAT sub-array from booking data.
	 * @return string
	 */
	private function vat_block( $vat ) {
		if ( empty( $vat['requested'] ) ) {
			return '';
		}

		return '
		<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
		       style="background-color:#0d1f0a;border:1px solid #C6A96B;border-radius:8px;margin-top:20px;">
		  <tr>
		    <td style="padding:14px 16px;">
		      <p style="margin:0 0 10px 0;color:#C6A96B;font-size:14px;font-weight:bold;letter-spacing:1px;">&#128196; XUẤT HÓA ĐƠN VAT</p>
		      <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
		        ' . $this->info_row( 'Tên công ty:', $vat['company_name'] ) . '
		        ' . $this->info_row( 'Mã số thuế:', $vat['tax_code'] ) . '
		        ' . $this->info_row( 'Địa chỉ:', $vat['address'] ) . '
		      </table>
		      <p style="margin:10px 0 0 0;color:#F0EBE1;font-size:12px;opacity:0.7;">Hóa đơn VAT sẽ được gửi trong vòng 3–5 ngày làm việc sau khi tour kết thúc.</p>
		    </td>
		  </tr>
		</table>';
	}

	// =========================================================================
	// TEMPLATE: CUSTOMER PENDING
	// =========================================================================

	/**
	 * Build HTML body for customer pending email (booking created, awaiting payment).
	 *
	 * @param  array $data Booking data from get_booking_data().
	 * @return string      Complete HTML email document.
	 */
	private function build_customer_pending_html( $data ) {
		$payment_hours = (int) apply_filters( 'halong_payment_deadline_hours', 24 );

		$bank_bin     = function_exists( 'get_field' ) ? get_field( 'halong_bank_bin',     'option' ) : '970422';
		$bank_account = function_exists( 'get_field' ) ? get_field( 'halong_bank_account', 'option' ) : '';
		$bank_holder  = function_exists( 'get_field' ) ? get_field( 'halong_bank_name',    'option' ) : 'HALONG RUM';

		$bank_name = 'Ngân hàng';
		if ( function_exists( 'halong_get_bank_details_by_bin' ) ) {
			$bank_details = halong_get_bank_details_by_bin( $bank_bin );
			if ( $bank_details ) {
				$bank_name = $bank_details['shortName'] . ' - ' . $bank_details['name'];
			}
		}

		ob_start();
		?>
<!-- Greeting -->
<p style="margin:0 0 6px 0;color:#C6A96B;font-size:16px;font-weight:bold;">Xin chào <?php echo esc_html( $data['customer']['name'] ); ?>,</p>
<p style="margin:0 0 20px 0;color:#F0EBE1;font-size:14px;line-height:1.6;">
  Cảm ơn bạn đã đặt tour tại <strong style="color:#C6A96B;">HaLong Rum Distillery</strong>!<br>
  Đơn đặt tour của bạn đã được ghi nhận. Vui lòng hoàn tất thanh toán trong vòng
  <strong style="color:#C6A96B;"><?php echo $payment_hours; ?> giờ</strong>
  để giữ chỗ.
</p>

<!-- Booking info box -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
       style="background-color:#0d1f0a;border:1px solid #C6A96B;border-radius:8px;margin-bottom:20px;">
  <tr>
    <td style="padding:14px 16px;">
      <p style="margin:0 0 10px 0;color:#C6A96B;font-size:14px;font-weight:bold;letter-spacing:1px;">&#127914; THÔNG TIN ĐẶT TOUR</p>
      <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
        <?php
        echo $this->info_row( 'Mã vé:',       $data['booking_code'] );
        echo $this->info_row( 'Tour:',         $data['tour_title'] );
        echo $this->info_row( 'Ngày:',         $data['date'] );
        echo $this->info_row( 'Giờ:',          $data['time'] );
        echo $this->info_row( 'Số người lớn:', (string) $data['adults'] );
        echo $this->info_row( 'Trẻ em:',       (string) $data['children'] );
        echo $this->info_row( 'Tổng khách:',   (string) $data['total_guests'] . ' khách' );
        echo $this->info_row( 'Tổng tiền:',    $this->format_price( $data['total_price'] ) );
        ?>
      </table>
    </td>
  </tr>
</table>

<!-- Payment instructions -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
       style="background-color:#1a3016;border:1px solid #2d5a28;border-radius:8px;margin-bottom:20px;">
  <tr>
    <td style="padding:14px 16px;">
      <p style="margin:0 0 10px 0;color:#C6A96B;font-size:14px;font-weight:bold;letter-spacing:1px;">&#127968; THÔNG TIN CHUYỂN KHOẢN</p>
      <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
        <?php
        echo $this->info_row( 'Ngân hàng:',      $bank_name );
        echo $this->info_row( 'Số tài khoản:',   $bank_account );
        echo $this->info_row( 'Chủ tài khoản:',  $bank_holder );
        $payment_desc = class_exists( 'Halong_Booking_CPT' )
            ? Halong_Booking_CPT::get_payment_description( $data['booking_code'] )
            : 'TOUR ' . $data['booking_code'];
        echo $this->info_row( 'Nội dung CK:',    $payment_desc );
        echo $this->info_row( 'Số tiền:',         $this->format_price( $data['total_price'] ) );
        ?>
      </table>
      <p style="margin:10px 0 0 0;color:#F0EBE1;font-size:12px;opacity:0.7;">
        &#9888; Vui lòng ghi đúng nội dung chuyển khoản để hệ thống xác nhận tự động.
      </p>
    </td>
  </tr>
</table>

<?php echo $this->vat_block( $data['vat'] ); ?>

<!-- Notes -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
       style="margin-top:20px;">
  <tr>
    <td style="padding:14px 16px;background-color:#1a1a0d;border-left:3px solid #C6A96B;border-radius:0 6px 6px 0;">
      <p style="margin:0 0 8px 0;color:#C6A96B;font-size:13px;font-weight:bold;">&#128204; LƯU Ý QUAN TRỌNG</p>
      <ul style="margin:0;padding-left:16px;color:#F0EBE1;font-size:13px;line-height:1.8;">
        <li>Đơn chưa được xác nhận cho đến khi thanh toán được ghi nhận.</li>
        <li>Sau khi chuyển khoản, vui lòng gửi ảnh biên lai qua hotline để được xác nhận nhanh.</li>
        <li>Hủy tour trước 48 giờ được hoàn 100% — sau thời gian này, vé không được hoàn tiền.</li>
        <li>Mang giày kín mũi khi tham quan nhà máy (bắt buộc).</li>
      </ul>
    </td>
  </tr>
</table>
		<?php
		$content = ob_get_clean();
		return $this->wrap_email( $content, 'Đơn đặt tour của bạn đã được ghi nhận - Mã vé: ' . $data['booking_code'] );
	}

	// =========================================================================
	// TEMPLATE: CUSTOMER CONFIRMED
	// =========================================================================

	/**
	 * Build HTML body for customer confirmed email.
	 *
	 * @param  array $data Booking data from get_booking_data().
	 * @return string      Complete HTML email document.
	 */
	private function build_customer_confirmed_html( $data ) {
		ob_start();
		?>
<!-- Congratulations banner -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
       style="background-color:#162b12;border:1px solid #C6A96B;border-radius:8px;margin-bottom:24px;">
  <tr>
    <td align="center" style="padding:20px;">
      <p style="margin:0;font-size:36px;">&#127881;</p>
      <p style="margin:8px 0 4px 0;color:#C6A96B;font-size:20px;font-weight:bold;letter-spacing:1px;">VÉ TOUR ĐÃ XÁC NHẬN!</p>
      <p style="margin:0;color:#F0EBE1;font-size:14px;">Xin chào <strong><?php echo esc_html( $data['customer']['name'] ); ?></strong>, chúng tôi rất vui được đón bạn!</p>
    </td>
  </tr>
</table>

<!-- Booking details -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
       style="background-color:#0d1f0a;border:1px solid #C6A96B;border-radius:8px;margin-bottom:20px;">
  <tr>
    <td style="padding:14px 16px;">
      <p style="margin:0 0 10px 0;color:#C6A96B;font-size:14px;font-weight:bold;letter-spacing:1px;">&#127914; CHI TIẾT VÉ TOUR</p>
      <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
        <?php
        echo $this->info_row( 'Mã vé:',          $data['booking_code'] );
        echo $this->info_row( 'Tour:',            $data['tour_title'] );
        echo $this->info_row( 'Ngày tham quan:',  $data['date'] );
        echo $this->info_row( 'Giờ bắt đầu:',    $data['time'] );
        echo $this->info_row( 'Số người lớn:',    (string) $data['adults'] );
        echo $this->info_row( 'Trẻ em:',          (string) $data['children'] );
        echo $this->info_row( 'Tổng khách:',      (string) $data['total_guests'] . ' khách' );
        echo $this->info_row( 'Tổng tiền:',       $this->format_price( $data['total_price'] ) );
        ?>
      </table>
    </td>
  </tr>
</table>

<!-- Address -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
       style="background-color:#1a3016;border-radius:8px;margin-bottom:20px;">
  <tr>
    <td style="padding:14px 16px;">
      <p style="margin:0 0 6px 0;color:#C6A96B;font-size:14px;font-weight:bold;">&#128205; ĐỊA ĐIỂM TỔ CHỨC</p>
      <p style="margin:0;color:#F0EBE1;font-size:14px;line-height:1.6;">
        Nhà máy HaLong Rum<br>
        Khu Công Nghiệp Cái Lân, TP Hạ Long, Quảng Ninh
      </p>
    </td>
  </tr>
</table>

<?php echo $this->vat_block( $data['vat'] ); ?>

<!-- Important notes -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
       style="margin-top:20px;">
  <tr>
    <td style="padding:14px 16px;background-color:#1a1a0d;border-left:3px solid #C6A96B;border-radius:0 6px 6px 0;">
      <p style="margin:0 0 8px 0;color:#C6A96B;font-size:13px;font-weight:bold;">&#128204; LƯU Ý TRƯỚC KHI ĐẾN</p>
      <ul style="margin:0;padding-left:16px;color:#F0EBE1;font-size:13px;line-height:1.8;">
        <li>Vui lòng <strong>đến trước 10 phút</strong> để làm thủ tục check-in.</li>
        <li><strong>Bắt buộc mang giày kín mũi</strong> khi tham quan nhà máy sản xuất.</li>
        <li>Khu vực trải nghiệm nếm thử <strong>không phù hợp cho trẻ em dưới 18 tuổi</strong>.</li>
        <li>Khách hàng sẽ được tặng <strong>1 ly tasting khắc logo</strong> miễn phí.</li>
        <li>Chụp ảnh tự do trong khu vực được phép — không chụp dây chuyền sản xuất.</li>
      </ul>
    </td>
  </tr>
</table>

<!-- Contact -->
<p style="margin:24px 0 0 0;color:#F0EBE1;font-size:13px;line-height:1.6;text-align:center;">
  Có thắc mắc? Liên hệ ngay với chúng tôi qua hotline hoặc email.<br>
  Chúng tôi rất mong được gặp bạn! &#127867;
</p>
		<?php
		$content = ob_get_clean();
		return $this->wrap_email( $content, 'Tour của bạn đã được xác nhận - Mã vé: ' . $data['booking_code'] );
	}

	// =========================================================================
	// TEMPLATE: ADMIN PENDING
	// =========================================================================

	/**
	 * Build HTML admin notification for a new (pending) booking.
	 *
	 * @param  array $data    Booking data from get_booking_data().
	 * @param  int   $post_id Booking post ID (used to generate edit link).
	 * @return string         Complete HTML email document.
	 */
	private function build_admin_pending_html( $data, $post_id ) {
		$edit_link = admin_url( 'post.php?post=' . (int) $post_id . '&action=edit' );

		ob_start();
		?>
<!-- Alert header -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
       style="background-color:#5a1a00;border:1px solid #ff6b35;border-radius:8px;margin-bottom:20px;">
  <tr>
    <td align="center" style="padding:16px;">
      <p style="margin:0;color:#ff6b35;font-size:20px;font-weight:bold;letter-spacing:1px;">&#128680; ĐƠN ĐẶT TOUR MỚI — CẦN XÁC NHẬN</p>
      <p style="margin:6px 0 0 0;color:#F0EBE1;font-size:13px;">Vui lòng xác nhận thanh toán và xác nhận đơn trong hệ thống.</p>
    </td>
  </tr>
</table>

<!-- Booking table -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
       style="background-color:#0d1f0a;border:1px solid #C6A96B;border-radius:8px;margin-bottom:20px;">
  <tr>
    <td style="padding:14px 16px;">
      <p style="margin:0 0 10px 0;color:#C6A96B;font-size:14px;font-weight:bold;letter-spacing:1px;">THÔNG TIN ĐƠN ĐẶT</p>
      <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
        <?php
        echo $this->info_row( 'Mã đơn:',      $data['booking_code'] );
        echo $this->info_row( 'Tour:',         $data['tour_title'] );
        echo $this->info_row( 'Khách hàng:',  $data['customer']['name'] );
        echo $this->info_row( 'Điện thoại:',  $data['customer']['phone'] );
        echo $this->info_row( 'Email:',        $data['customer']['email'] );
        echo $this->info_row( 'Ngày tour:',   $data['date'] );
        echo $this->info_row( 'Giờ:',         $data['time'] );
        echo $this->info_row( 'Người lớn:',   (string) $data['adults'] );
        echo $this->info_row( 'Trẻ em:',      (string) $data['children'] );
        echo $this->info_row( 'Tổng khách:',  (string) $data['total_guests'] . ' khách' );
        echo $this->info_row( 'Tổng tiền:',   $this->format_price( $data['total_price'] ) );
        if ( ! empty( $data['customer']['note'] ) ) {
            echo $this->info_row( 'Ghi chú KH:', $data['customer']['note'] );
        }
        ?>
      </table>
    </td>
  </tr>
</table>

<?php echo $this->vat_block( $data['vat'] ); ?>

<!-- Action required -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
       style="background-color:#1a2a0a;border:1px solid #4a7a2a;border-radius:8px;margin-top:20px;margin-bottom:20px;">
  <tr>
    <td style="padding:14px 16px;">
      <p style="margin:0 0 8px 0;color:#C6A96B;font-size:13px;font-weight:bold;">&#9989; HÀNH ĐỘNG CẦN THỰC HIỆN</p>
      <ul style="margin:0;padding-left:16px;color:#F0EBE1;font-size:13px;line-height:1.8;">
        <li>Kiểm tra thanh toán từ khách (chuyển khoản ngân hàng / ảnh biên lai qua hotline).</li>
        <li>Vào hệ thống xác nhận đơn để kích hoạt email xác nhận gửi khách.</li>
        <li>Chuẩn bị <strong style="color:#C6A96B;"><?php echo (int) $data['total_guests']; ?> ly tasting khắc logo</strong> cho đoàn.</li>
      </ul>
    </td>
  </tr>
</table>

<!-- Admin link button -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
  <tr>
    <td align="center" style="padding:10px 0 20px 0;">
      <a href="<?php echo esc_url( $edit_link ); ?>"
         style="display:inline-block;background-color:#C6A96B;color:#0d1f0a;text-decoration:none;font-weight:bold;font-size:14px;padding:12px 28px;border-radius:6px;letter-spacing:1px;">
        &#128196; XEM &amp; XÁC NHẬN ĐƠN
      </a>
    </td>
  </tr>
</table>
		<?php
		$content = ob_get_clean();
		return $this->wrap_email( $content, 'Đơn mới #' . $data['booking_code'] . ' cần xác nhận' );
	}

	// =========================================================================
	// TEMPLATE: ADMIN CONFIRMED (to receptionist/guide)
	// =========================================================================

	/**
	 * Build HTML admin confirmed email sent to receptionist/tour guide.
	 *
	 * @param  array $data Booking data from get_booking_data().
	 * @return string      Complete HTML email document.
	 */
	private function build_admin_confirmed_html( $data ) {
		ob_start();
		?>
<!-- Confirmed banner -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
       style="background-color:#1a3016;border:1px solid #4aaa4a;border-radius:8px;margin-bottom:20px;">
  <tr>
    <td align="center" style="padding:16px;">
      <p style="margin:0;color:#4aaa4a;font-size:20px;font-weight:bold;letter-spacing:1px;">&#10004; ĐOÀN KHÁCH ĐÃ XÁC NHẬN</p>
      <p style="margin:6px 0 0 0;color:#F0EBE1;font-size:13px;">Đơn đã được xác nhận — chuẩn bị đón khách.</p>
    </td>
  </tr>
</table>

<!-- Booking details for guide -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
       style="background-color:#0d1f0a;border:1px solid #C6A96B;border-radius:8px;margin-bottom:20px;">
  <tr>
    <td style="padding:14px 16px;">
      <p style="margin:0 0 10px 0;color:#C6A96B;font-size:14px;font-weight:bold;letter-spacing:1px;">THÔNG TIN ĐOÀN KHÁCH</p>
      <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
        <?php
        echo $this->info_row( 'Mã đơn:',      $data['booking_code'] );
        echo $this->info_row( 'Khách hàng:',  $data['customer']['name'] );
        echo $this->info_row( 'Điện thoại:',  $data['customer']['phone'] );
        echo $this->info_row( 'Tour:',         $data['tour_title'] );
        echo $this->info_row( 'Ngày:',         $data['date'] );
        echo $this->info_row( 'Giờ:',          $data['time'] );
        echo $this->info_row( 'Người lớn:',   (string) $data['adults'] );
        echo $this->info_row( 'Trẻ em:',      (string) $data['children'] );
        echo $this->info_row( 'Tổng khách:',  (string) $data['total_guests'] . ' người' );
        ?>
      </table>
    </td>
  </tr>
</table>

<?php if ( ! empty( $data['customer']['note'] ) ) : ?>
<!-- Special notes from customer -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
       style="background-color:#1a1a0d;border-left:3px solid #ff6b35;border-radius:0 6px 6px 0;margin-bottom:20px;">
  <tr>
    <td style="padding:12px 16px;">
      <p style="margin:0 0 6px 0;color:#ff6b35;font-size:13px;font-weight:bold;">&#128221; GHI CHÚ ĐẶC BIỆT TỪ KHÁCH</p>
      <p style="margin:0;color:#F0EBE1;font-size:13px;line-height:1.6;"><?php echo esc_html( $data['customer']['note'] ); ?></p>
    </td>
  </tr>
</table>
<?php endif; ?>

<?php echo $this->vat_block( $data['vat'] ); ?>

<!-- Gift prep reminder -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
       style="background-color:#1a2a0a;border:1px solid #4a7a2a;border-radius:8px;margin-top:20px;">
  <tr>
    <td style="padding:14px 16px;">
      <p style="margin:0 0 8px 0;color:#C6A96B;font-size:13px;font-weight:bold;">&#127867; CHUẨN BỊ ĐÓN ĐOÀN</p>
      <ul style="margin:0;padding-left:16px;color:#F0EBE1;font-size:13px;line-height:1.8;">
        <li>Chuẩn bị <strong style="color:#C6A96B;"><?php echo (int) $data['total_guests']; ?> ly tasting khắc logo</strong>.</li>
        <li>Kiểm tra khu vực tasting tour đã sẵn sàng trước giờ đón khách.</li>
        <li>Xác nhận hướng dẫn viên / nhân viên tiếp đón cho khung giờ <strong style="color:#C6A96B;"><?php echo esc_html( $data['time'] ); ?></strong>.</li>
        <?php if ( $data['children'] > 0 ) : ?>
        <li>Đoàn có <strong style="color:#ff6b35;"><?php echo (int) $data['children']; ?> trẻ em</strong> — chuẩn bị khu vực an toàn / hoạt động phù hợp.</li>
        <?php endif; ?>
      </ul>
    </td>
  </tr>
</table>
		<?php
		$content = ob_get_clean();
		return $this->wrap_email(
			$content,
			'Xác nhận đoàn: ' . $data['customer']['name'] . ' - ' . $data['date'] . ' ' . $data['time']
		);
	}

} // end class Halong_Email_Sender

endif; // end class_exists check
