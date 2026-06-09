<?php
/**
 * Tour Custom Post Type & SCF Fields
 *
 * @package HaLong_Tour
 */

if ( ! class_exists( 'Halong_Tour_CPT' ) ) {

	/**
	 * Class Halong_Tour_CPT
	 *
	 * Registers the 'tour' custom post type and its ACF/SCF field groups.
	 */
	class Halong_Tour_CPT {

		/**
		 * Constructor. Registers hooks.
		 */
		public function __construct() {
			add_action( 'init', array( $this, 'register_post_type' ), 0 );
			add_action( 'acf/init', array( $this, 'register_fields' ) );
		}

		/**
		 * Register the 'tour' custom post type.
		 */
		public function register_post_type() {
			$labels = array(
				'name'               => 'Tours',
				'singular_name'      => 'Tour',
				'add_new'            => 'Thêm tour mới',
				'add_new_item'       => 'Thêm tour mới',
				'edit_item'          => 'Sửa tour',
				'new_item'           => 'Tour mới',
				'view_item'          => 'Xem tour',
				'view_items'         => 'Xem danh sách tour',
				'search_items'       => 'Tìm kiếm tour',
				'not_found'          => 'Không tìm thấy tour nào',
				'not_found_in_trash' => 'Không có tour nào trong thùng rác',
				'all_items'          => 'Tất cả Tour',
				'menu_name'          => 'Tours',
				'name_admin_bar'     => 'Tour',
			);

			$args = array(
				'labels'        => $labels,
				'supports'      => array( 'title', 'editor', 'thumbnail', 'custom-fields', 'excerpt' ),
				'public'        => true,
				'has_archive'   => false,
				'rewrite'       => array( 'slug' => 'tour' ),
				'show_in_rest'  => true,
				'menu_icon'     => 'dashicons-location-alt',
				'menu_position' => 5,
			);

			register_post_type( 'tour', $args );
		}

		/**
		 * Register ACF/SCF field groups for the 'tour' post type.
		 */
		public function register_fields() {
			if ( ! function_exists( 'acf_add_local_field_group' ) ) {
				return;
			}

			acf_add_local_field_group(
				array(
					'key'    => 'group_halong_tour',
					'title'  => 'Thông tin Tour',
					'fields' => array(

						// ── Tab 1: Giá & Sức chứa ──────────────────────────────────────
						array(
							'key'   => 'field_halong_tour_tab_price',
							'label' => 'Giá & Sức chứa',
							'name'  => '',
							'type'  => 'tab',
						),
						array(
							'key'           => 'field_halong_adult_price',
							'label'         => 'Giá người lớn (VND)',
							'name'          => 'halong_adult_price',
							'type'          => 'number',
							'default_value' => 450000,
							'min'           => 0,
						),
						array(
							'key'           => 'field_halong_child_price',
							'label'         => 'Giá trẻ em (VND)',
							'name'          => 'halong_child_price',
							'type'          => 'number',
							'default_value' => 225000,
							'min'           => 0,
						),
						array(
							'key'           => 'field_halong_tour_duration',
							'label'         => 'Thời lượng',
							'name'          => 'halong_tour_duration',
							'type'          => 'text',
							'default_value' => '60 - 90 phút',
						),
						array(
							'key'           => 'field_halong_tour_languages',
							'label'         => 'Ngôn ngữ',
							'name'          => 'halong_tour_languages',
							'type'          => 'text',
							'default_value' => 'Việt, Anh',
						),
						array(
							'key'          => 'field_halong_time_slots',
							'label'        => 'Khung giờ & Sức chứa',
							'name'         => 'halong_time_slots',
							'type'         => 'repeater',
							'layout'       => 'table',
							'button_label' => 'Thêm khung giờ',
							'sub_fields'   => array(
								array(
									'key'            => 'field_halong_slot_time',
									'label'          => 'Giờ khởi hành',
									'name'           => 'slot_time',
									'type'           => 'time_picker',
									'display_format' => 'H:i',
									'return_format'  => 'H:i',
								),
								array(
									'key'           => 'field_halong_slot_capacity',
									'label'         => 'Sức chứa tối đa',
									'name'          => 'slot_capacity',
									'type'          => 'number',
									'default_value' => 15,
									'min'           => 1,
								),
							),
						),
						array(
							'key'           => 'field_halong_tour_max_guests',
							'label'         => 'Giới hạn khách/đơn',
							'name'          => 'halong_tour_max_guests',
							'type'          => 'number',
							'default_value' => 15,
							'min'           => 1,
						),

						// ── Tab 2: Nội dung Tour ────────────────────────────────────────
						array(
							'key'   => 'field_halong_tour_tab_content',
							'label' => 'Nội dung Tour',
							'name'  => '',
							'type'  => 'tab',
						),
						array(
							'key'          => 'field_halong_intro_text',
							'label'        => 'Giới thiệu Tour',
							'name'         => 'halong_intro_text',
							'type'         => 'wysiwyg',
							'toolbar'      => 'basic',
							'media_upload' => 0,
						),
						array(
							'key'          => 'field_halong_highlights',
							'label'        => 'Điểm nhấn',
							'name'         => 'halong_highlights',
							'type'         => 'repeater',
							'layout'       => 'block',
							'button_label' => 'Thêm điểm nhấn',
							'sub_fields'   => array(
								array(
									'key'           => 'field_halong_highlight_text',
									'label'         => 'Nội dung',
									'name'          => 'highlight_text',
									'type'          => 'text',
									'default_value' => '',
								),
							),
						),
						array(
							'key'          => 'field_halong_itinerary',
							'label'        => 'Lịch trình',
							'name'         => 'halong_itinerary',
							'type'         => 'repeater',
							'layout'       => 'block',
							'button_label' => 'Thêm mục lịch trình',
							'sub_fields'   => array(
								array(
									'key'           => 'field_halong_itinerary_duration',
									'label'         => 'Khoảng thời gian (VD: 15 Phút đầu)',
									'name'          => 'itinerary_duration',
									'type'          => 'text',
									'default_value' => '',
								),
								array(
									'key'           => 'field_halong_itinerary_title',
									'label'         => 'Tiêu đề',
									'name'          => 'itinerary_title',
									'type'          => 'text',
									'default_value' => '',
								),
								array(
									'key'           => 'field_halong_itinerary_desc',
									'label'         => 'Mô tả',
									'name'          => 'itinerary_desc',
									'type'          => 'textarea',
									'default_value' => '',
									'rows'          => 3,
								),
							),
						),
						array(
							'key'          => 'field_halong_notes',
							'label'        => 'Lưu ý quan trọng',
							'name'         => 'halong_notes',
							'type'         => 'repeater',
							'layout'       => 'block',
							'button_label' => 'Thêm lưu ý',
							'sub_fields'   => array(
								array(
									'key'           => 'field_halong_note_title',
									'label'         => 'Tiêu đề lưu ý',
									'name'          => 'note_title',
									'type'          => 'text',
									'default_value' => '',
								),
								array(
									'key'           => 'field_halong_note_content',
									'label'         => 'Nội dung',
									'name'          => 'note_content',
									'type'          => 'textarea',
									'default_value' => '',
									'rows'          => 3,
								),
							),
						),
						array(
							'key'          => 'field_halong_policies',
							'label'        => 'Chính sách',
							'name'         => 'halong_policies',
							'type'         => 'repeater',
							'layout'       => 'block',
							'button_label' => 'Thêm chính sách',
							'sub_fields'   => array(
								array(
									'key'           => 'field_halong_policy_title',
									'label'         => 'Tiêu đề',
									'name'          => 'policy_title',
									'type'          => 'text',
									'default_value' => '',
								),
								array(
									'key'           => 'field_halong_policy_content',
									'label'         => 'Nội dung',
									'name'          => 'policy_content',
									'type'          => 'textarea',
									'default_value' => '',
									'rows'          => 3,
								),
							),
						),

						// ── Tab 3: Media ────────────────────────────────────────────────
						array(
							'key'   => 'field_halong_tour_tab_media',
							'label' => 'Media',
							'name'  => '',
							'type'  => 'tab',
						),
						array(
							'key'           => 'field_halong_hero_image',
							'label'         => 'Ảnh Hero',
							'name'          => 'halong_hero_image',
							'type'          => 'image',
							'return_format' => 'url',
							'preview_size'  => 'medium',
							'library'       => 'all',
						),
					),
					'location' => array(
						array(
							array(
								'param'    => 'post_type',
								'operator' => '==',
								'value'    => 'tour',
							),
						),
					),
					'active' => true,
				)
			);
		}

		/**
		 * Get time slots for a given tour post.
		 * Returns empty array if no slots configured — callers must handle this case.
		 *
		 * @param int $post_id The tour post ID.
		 * @return array Array of slot arrays, each with keys slot_time, slot_capacity.
		 */
		public static function get_time_slots( $post_id ) {
			if ( ! function_exists( 'get_field' ) ) {
				return array();
			}

			$slots = get_field( 'halong_time_slots', $post_id );

			if ( empty( $slots ) || ! is_array( $slots ) ) {
				return array();
			}

			return $slots;
		}

		/**
		 * Check if a tour has time slots configured.
		 *
		 * @param int $post_id The tour post ID.
		 * @return bool True if slots exist.
		 */
		public static function has_time_slots( $post_id ) {
			$slots = self::get_time_slots( $post_id );
			return ! empty( $slots );
		}
	}
}
