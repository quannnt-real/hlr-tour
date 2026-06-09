<?php
/**
 * Theme Settings - SCF Options Page
 *
 * @package HaLong_Tour
 */

if ( ! class_exists( 'Halong_Theme_Settings' ) ) {

	/**
	 * Class Halong_Theme_Settings
	 *
	 * Registers an ACF/SCF options page and field groups for the HaLong Tour theme.
	 */
	class Halong_Theme_Settings {

		/**
		 * Constructor. Registers hooks.
		 */
		public function __construct() {
			add_action( 'acf/init', array( $this, 'register_options_page' ) );
			add_action( 'acf/init', array( $this, 'register_fields' ) );
		}

		/**
		 * Register the ACF/SCF options page.
		 */
		public function register_options_page() {
			if ( ! function_exists( 'acf_add_options_page' ) ) {
				return;
			}

			acf_add_options_page(
				array(
					'page_title'  => 'Cài đặt HaLong Tour',
					'menu_title'  => 'Cài đặt HaLong Tour',
					'menu_slug'   => 'halong-settings',
					'capability'  => 'manage_options',
					'icon_url'    => 'dashicons-tickets-alt',
					'redirect'    => false,
					'autoload'    => true,
				)
			);
		}

		/**
		 * Register ACF/SCF field groups for the options page.
		 */
		public function register_fields() {
			if ( ! function_exists( 'acf_add_local_field_group' ) ) {
				return;
			}

			acf_add_local_field_group(
				array(
					'key'      => 'group_halong_settings',
					'title'    => 'Cài đặt HaLong Tour',
					'fields'   => array(

						// ── Tab 1: Ngân hàng & QR ──────────────────────────────────────
						array(
							'key'   => 'field_halong_tab_bank',
							'label' => 'Ngân hàng & QR',
							'name'  => '',
							'type'  => 'tab',
						),
						array(
							'key'           => 'field_halong_bank_bin',
							'label'         => 'Bank BIN / Bank ID (VietQR)',
							'name'          => 'halong_bank_bin',
							'type'          => 'text',
							'default_value' => '970422',
						),
						array(
							'key'           => 'field_halong_bank_account',
							'label'         => 'Số tài khoản',
							'name'          => 'halong_bank_account',
							'type'          => 'text',
							'default_value' => '',
						),
						array(
							'key'           => 'field_halong_bank_name',
							'label'         => 'Tên chủ tài khoản',
							'name'          => 'halong_bank_name',
							'type'          => 'text',
							'default_value' => 'CONG TY HALONG RUM',
						),
						array(
							'key'           => 'field_halong_bank_template',
							'label'         => 'Template nội dung CK',
							'name'          => 'halong_bank_template',
							'type'          => 'text',
							'default_value' => '{booking_code}',
							'instructions'  => 'Sử dụng {booking_code} để chèn mã đặt tour vào nội dung chuyển khoản.',
						),

						// ── Tab 2: Thông báo Email ──────────────────────────────────────
						array(
							'key'   => 'field_halong_tab_email',
							'label' => 'Thông báo Email',
							'name'  => '',
							'type'  => 'tab',
						),
						array(
							'key'           => 'field_halong_admin_emails',
							'label'         => 'Email nhận thông báo admin',
							'name'          => 'halong_admin_emails',
							'type'          => 'textarea',
							'default_value' => 'admin@halongrum.com',
							'instructions'  => 'Mỗi dòng 1 email.',
							'rows'          => 4,
						),
						array(
							'key'           => 'field_halong_from_email',
							'label'         => 'Email gửi đi',
							'name'          => 'halong_from_email',
							'type'          => 'text',
							'default_value' => 'reservations@halongrum.com',
						),
						array(
							'key'           => 'field_halong_from_name',
							'label'         => 'Tên người gửi',
							'name'          => 'halong_from_name',
							'type'          => 'text',
							'default_value' => 'HaLong Rum',
						),
						array(
							'key'           => 'field_halong_booking_expire_hours',
							'label'         => 'Số giờ trước khi đơn Pending hết hạn',
							'name'          => 'halong_booking_expire_hours',
							'type'          => 'number',
							'default_value' => 24,
							'min'           => 1,
							'max'           => 72,
						),

						// ── Tab 3: Tính năng (Feature Toggles) ─────────────────────────
						array(
							'key'   => 'field_halong_tab_features',
							'label' => 'Tính năng (Feature Toggles)',
							'name'  => '',
							'type'  => 'tab',
						),
						array(
							'key'           => 'field_halong_enable_booking',
							'label'         => 'Bật Form Đặt Tour',
							'name'          => 'halong_enable_booking',
							'type'          => 'true_false',
							'default_value' => 1,
							'ui'            => 1,
						),
						array(
							'key'           => 'field_halong_enable_qr',
							'label'         => 'Bật thanh toán QR VietQR',
							'name'          => 'halong_enable_qr',
							'type'          => 'true_false',
							'default_value' => 1,
							'ui'            => 1,
						),
						array(
							'key'           => 'field_halong_enable_email',
							'label'         => 'Bật gửi Email tự động',
							'name'          => 'halong_enable_email',
							'type'          => 'true_false',
							'default_value' => 1,
							'ui'            => 1,
						),
						array(
							'key'           => 'field_halong_enable_audit_log',
							'label'         => 'Bật Audit Log',
							'name'          => 'halong_enable_audit_log',
							'type'          => 'true_false',
							'default_value' => 1,
							'ui'            => 1,
						),
						array(
							'key'           => 'field_halong_enable_children',
							'label'         => 'Bật tùy chọn khách Trẻ em',
							'name'          => 'halong_enable_children',
							'type'          => 'true_false',
							'default_value' => 0,
							'ui'            => 1,
						),
						array(
							'key'           => 'field_halong_age_verify_redirect',
							'label'         => 'URL chuyển hướng khi từ chối xác nhận tuổi 18+',
							'name'          => 'halong_age_verify_redirect',
							'type'          => 'url',
							'default_value' => 'https://halongrum.com',
						),
					),
					'location' => array(
						array(
							array(
								'param'    => 'options_page',
								'operator' => '==',
								'value'    => 'halong-settings',
							),
						),
					),
					'active' => true,
				)
			);
		}

		/**
		 * Retrieve a theme option value with an optional fallback default.
		 *
		 * @param string $key     ACF field name.
		 * @param mixed  $default Fallback value when the field is empty.
		 * @return mixed
		 */
		public static function get_option( $key, $default = '' ) {
			if ( ! function_exists( 'get_field' ) ) {
				return $default;
			}

			$value = get_field( $key, 'option' );

			if ( $value === null || $value === false || $value === '' ) {
				return $default;
			}

			return $value;
		}
	}
}
