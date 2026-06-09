<?php
/**
 * Halong_Booking_CPT
 *
 * Đăng ký Custom Post Type `tour_booking`, custom post statuses,
 * các trường SCF, cột admin, metabox "Khóa đơn" và business logic hooks.
 *
 * @package HaLong_Tour
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Halong_Booking_CPT {

    /**
     * Post type slug.
     *
     * @var string
     */
    const POST_TYPE = 'tour_booking';

    /**
     * SCF field group key.
     *
     * @var string
     */
    const FIELD_GROUP_KEY = 'group_halong_booking';

    /**
     * Các trạng thái đơn hàng tùy chỉnh.
     *
     * @var array
     */
    protected static $statuses = array(
        'pending_payment' => 'Chờ thanh toán',
        'confirmed'       => 'Đã xác nhận',
        'expired'         => 'Hết hạn',
        'cancelled'       => 'Đã hủy',
    );

    /**
     * Khởi tạo và đăng ký hooks.
     */
    public function __construct() {
        add_action( 'init', array( $this, 'register_post_statuses' ) );
        add_action( 'init', array( $this, 'register_post_type' ) );
        add_action( 'acf/init', array( $this, 'register_scf_fields' ) );

        // Business logic on save.
        add_action( 'save_post_' . self::POST_TYPE, array( $this, 'on_save_post' ), 20, 3 );

        // Admin columns.
        add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'add_admin_columns' ) );
        add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'render_admin_column' ), 10, 2 );
        add_filter( 'manage_edit-' . self::POST_TYPE . '_sortable_columns', array( $this, 'sortable_columns' ) );

        // "Khóa đơn" metabox.
        add_action( 'add_meta_boxes', array( $this, 'add_lock_metabox' ) );
        add_action( 'admin_post_halong_lock_booking', array( $this, 'handle_lock_booking' ) );

        // Show custom statuses in post list dropdown.
        add_action( 'admin_footer-edit.php', array( $this, 'append_statuses_to_bulk_dropdown' ) );
        add_action( 'admin_footer-post.php', array( $this, 'append_statuses_to_post_edit_dropdown' ) );
    }

    // =========================================================================
    // REGISTER: Post Statuses
    // =========================================================================

    /**
     * Đăng ký các trạng thái tùy chỉnh cho tour_booking.
     */
    public function register_post_statuses() {
        register_post_status( 'pending_payment', array(
            'label'                     => _x( 'Chờ thanh toán', 'post status', 'halong-tour' ),
            'public'                    => false,
            'internal'                  => false,
            'protected'                 => true,
            'private'                   => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            /* translators: %s: count */
            'label_count'               => _n_noop(
                'Chờ thanh toán <span class="count">(%s)</span>',
                'Chờ thanh toán <span class="count">(%s)</span>',
                'halong-tour'
            ),
        ) );

        register_post_status( 'confirmed', array(
            'label'                     => _x( 'Đã xác nhận', 'post status', 'halong-tour' ),
            'public'                    => false,
            'internal'                  => false,
            'protected'                 => true,
            'private'                   => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop(
                'Đã xác nhận <span class="count">(%s)</span>',
                'Đã xác nhận <span class="count">(%s)</span>',
                'halong-tour'
            ),
        ) );

        register_post_status( 'expired', array(
            'label'                     => _x( 'Hết hạn', 'post status', 'halong-tour' ),
            'public'                    => false,
            'internal'                  => false,
            'protected'                 => true,
            'private'                   => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop(
                'Hết hạn <span class="count">(%s)</span>',
                'Hết hạn <span class="count">(%s)</span>',
                'halong-tour'
            ),
        ) );

        register_post_status( 'cancelled', array(
            'label'                     => _x( 'Đã hủy', 'post status', 'halong-tour' ),
            'public'                    => false,
            'internal'                  => false,
            'protected'                 => true,
            'private'                   => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop(
                'Đã hủy <span class="count">(%s)</span>',
                'Đã hủy <span class="count">(%s)</span>',
                'halong-tour'
            ),
        ) );
    }

    // =========================================================================
    // REGISTER: CPT
    // =========================================================================

    /**
     * Đăng ký Custom Post Type `tour_booking`.
     */
    public function register_post_type() {
        $labels = array(
            'name'                  => _x( 'Đơn đặt tour', 'post type general name', 'halong-tour' ),
            'singular_name'         => _x( 'Đơn đặt tour', 'post type singular name', 'halong-tour' ),
            'menu_name'             => __( 'Đơn đặt tour', 'halong-tour' ),
            'name_admin_bar'        => __( 'Đơn đặt tour', 'halong-tour' ),
            'add_new'               => __( 'Thêm mới', 'halong-tour' ),
            'add_new_item'          => __( 'Thêm đơn mới', 'halong-tour' ),
            'new_item'              => __( 'Đơn mới', 'halong-tour' ),
            'edit_item'             => __( 'Sửa đơn đặt tour', 'halong-tour' ),
            'view_item'             => __( 'Xem đơn đặt tour', 'halong-tour' ),
            'all_items'             => __( 'Tất cả đơn đặt', 'halong-tour' ),
            'search_items'          => __( 'Tìm kiếm đơn đặt', 'halong-tour' ),
            'parent_item_colon'     => __( 'Đơn cha:', 'halong-tour' ),
            'not_found'             => __( 'Không tìm thấy đơn đặt.', 'halong-tour' ),
            'not_found_in_trash'    => __( 'Không có đơn đặt trong thùng rác.', 'halong-tour' ),
            'featured_image'        => __( 'Ảnh đại diện', 'halong-tour' ),
            'set_featured_image'    => __( 'Đặt ảnh đại diện', 'halong-tour' ),
            'remove_featured_image' => __( 'Xóa ảnh đại diện', 'halong-tour' ),
            'use_featured_image'    => __( 'Dùng làm ảnh đại diện', 'halong-tour' ),
            'archives'              => __( 'Lưu trữ đơn đặt', 'halong-tour' ),
            'attributes'            => __( 'Thuộc tính đơn đặt', 'halong-tour' ),
            'insert_into_item'      => __( 'Chèn vào đơn đặt', 'halong-tour' ),
            'uploaded_to_this_item' => __( 'Đã tải lên mục này', 'halong-tour' ),
            'filter_items_list'     => __( 'Lọc danh sách đơn đặt', 'halong-tour' ),
            'items_list_navigation' => __( 'Điều hướng danh sách đơn đặt', 'halong-tour' ),
            'items_list'            => __( 'Danh sách đơn đặt', 'halong-tour' ),
        );

        $args = array(
            'labels'             => $labels,
            'description'        => __( 'Quản lý đơn đặt tour của khách hàng.', 'halong-tour' ),
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_in_nav_menus'  => false,
            'show_in_admin_bar'  => true,
            'show_in_rest'       => false,
            'query_var'          => false,
            'rewrite'            => false,
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => 7,
            'menu_icon'          => 'dashicons-clipboard',
            'supports'           => array( 'title', 'custom-fields' ),
        );

        register_post_type( self::POST_TYPE, $args );
    }

    // =========================================================================
    // REGISTER: SCF Fields
    // =========================================================================

    /**
     * Đăng ký nhóm trường SCF / ACF cho `tour_booking`.
     */
    public function register_scf_fields() {
        if ( ! function_exists( 'acf_add_local_field_group' ) ) {
            return;
        }

        acf_add_local_field_group( array(
            'key'    => self::FIELD_GROUP_KEY,
            'title'  => 'Thông tin đặt tour',
            'fields' => array(

                // ── Tab 1: Thông tin đặt tour ──────────────────────────────
                array(
                    'key'       => 'field_booking_tab_info',
                    'label'     => 'Thông tin đặt tour',
                    'name'      => '',
                    'type'      => 'tab',
                    'placement' => 'top',
                    'endpoint'  => 0,
                ),

                array(
                    'key'          => 'field_booking_code',
                    'label'        => 'Mã booking',
                    'name'         => 'booking_code',
                    'type'         => 'text',
                    'instructions' => 'Mã định danh dạng HLR-XXXXXX (tự động tạo, không chỉnh sửa).',
                    'required'     => 0,
                    'readonly'     => 1,
                    'wrapper'      => array( 'class' => 'halong-readonly-field' ),
                ),

                array(
                    'key'           => 'field_booking_tour',
                    'label'         => 'Tour',
                    'name'          => 'booking_tour',
                    'type'          => 'post_object',
                    'post_type'     => array( 'tour' ),
                    'taxonomy'      => array(),
                    'allow_null'    => 0,
                    'multiple'      => 0,
                    'return_format' => 'id',
                    'ui'            => 1,
                    'instructions'  => 'Chọn tour khách muốn đặt.',
                    'required'      => 1,
                ),

                array(
                    'key'          => 'field_booking_date',
                    'label'        => 'Ngày tham quan',
                    'name'         => 'booking_date',
                    'type'         => 'text',
                    'instructions' => 'Định dạng d/m/Y. Ví dụ: 25/12/2025.',
                    'required'     => 1,
                    'placeholder'  => 'dd/mm/yyyy',
                ),

                array(
                    'key'          => 'field_booking_time',
                    'label'        => 'Khung giờ',
                    'name'         => 'booking_time',
                    'type'         => 'text',
                    'instructions' => 'Khung giờ tham quan. Ví dụ: 09:00 - 12:00.',
                    'required'     => 1,
                    'placeholder'  => '09:00 - 12:00',
                ),

                array(
                    'key'           => 'field_booking_adults',
                    'label'         => 'Số người lớn',
                    'name'          => 'booking_adults',
                    'type'          => 'number',
                    'default_value' => 1,
                    'min'           => 0,
                    'step'          => 1,
                    'instructions'  => 'Số khách người lớn (>= 12 tuổi).',
                    'required'      => 0,
                ),

                array(
                    'key'           => 'field_booking_children',
                    'label'         => 'Số trẻ em',
                    'name'          => 'booking_children',
                    'type'          => 'number',
                    'default_value' => 0,
                    'min'           => 0,
                    'step'          => 1,
                    'instructions'  => 'Số khách trẻ em (< 12 tuổi).',
                    'required'      => 0,
                ),

                array(
                    'key'          => 'field_booking_total_guests',
                    'label'        => 'Tổng khách',
                    'name'         => 'booking_total_guests',
                    'type'         => 'number',
                    'min'          => 0,
                    'step'         => 1,
                    'instructions' => 'Tổng số khách (tự động tính, không chỉnh sửa thủ công).',
                    'required'     => 0,
                    'readonly'     => 1,
                    'wrapper'      => array( 'class' => 'halong-readonly-field' ),
                ),

                array(
                    'key'          => 'field_booking_total_price',
                    'label'        => 'Tổng tiền (VND)',
                    'name'         => 'booking_total_price',
                    'type'         => 'number',
                    'min'          => 0,
                    'step'         => 1000,
                    'instructions' => 'Tổng số tiền của đơn đặt (VND).',
                    'required'     => 0,
                    'prepend'      => '₫',
                ),

                // ── Tab 2: Khách hàng ──────────────────────────────────────
                array(
                    'key'       => 'field_booking_tab_customer',
                    'label'     => 'Khách hàng',
                    'name'      => '',
                    'type'      => 'tab',
                    'placement' => 'top',
                    'endpoint'  => 0,
                ),

                array(
                    'key'          => 'field_customer_name',
                    'label'        => 'Họ tên',
                    'name'         => 'customer_name',
                    'type'         => 'text',
                    'instructions' => 'Họ và tên đầy đủ của người đặt tour.',
                    'required'     => 1,
                    'maxlength'    => 200,
                ),

                array(
                    'key'          => 'field_customer_phone',
                    'label'        => 'SĐT',
                    'name'         => 'customer_phone',
                    'type'         => 'text',
                    'instructions' => 'Số điện thoại liên lạc.',
                    'required'     => 1,
                    'maxlength'    => 20,
                    'placeholder'  => '0912345678',
                ),

                array(
                    'key'          => 'field_customer_email',
                    'label'        => 'Email',
                    'name'         => 'customer_email',
                    'type'         => 'text',
                    'instructions' => 'Địa chỉ email nhận xác nhận booking.',
                    'required'     => 0,
                    'placeholder'  => 'email@example.com',
                ),

                array(
                    'key'          => 'field_customer_note',
                    'label'        => 'Ghi chú đặc biệt',
                    'name'         => 'customer_note',
                    'type'         => 'textarea',
                    'rows'         => 4,
                    'new_lines'    => 'br',
                    'instructions' => 'Yêu cầu hoặc lưu ý đặc biệt từ khách.',
                    'required'     => 0,
                ),

                // ── Tab 3: Hóa đơn VAT ────────────────────────────────────
                array(
                    'key'       => 'field_booking_tab_vat',
                    'label'     => 'Hóa đơn VAT',
                    'name'      => '',
                    'type'      => 'tab',
                    'placement' => 'top',
                    'endpoint'  => 0,
                ),

                array(
                    'key'           => 'field_vat_requested',
                    'label'         => 'Yêu cầu VAT',
                    'name'          => 'vat_requested',
                    'type'          => 'true_false',
                    'message'       => 'Khách yêu cầu xuất hóa đơn VAT',
                    'default_value' => 0,
                    'ui'            => 1,
                    'ui_on_text'    => 'Có',
                    'ui_off_text'   => 'Không',
                    'instructions'  => 'Bật nếu khách muốn xuất hóa đơn VAT.',
                    'required'      => 0,
                ),

                array(
                    'key'               => 'field_vat_company_name',
                    'label'             => 'Tên công ty',
                    'name'              => 'vat_company_name',
                    'type'              => 'text',
                    'instructions'      => 'Tên công ty trên hóa đơn VAT.',
                    'required'          => 0,
                    'conditional_logic' => array(
                        array(
                            array(
                                'field'    => 'field_vat_requested',
                                'operator' => '==',
                                'value'    => '1',
                            ),
                        ),
                    ),
                ),

                array(
                    'key'               => 'field_vat_tax_code',
                    'label'             => 'Mã số thuế',
                    'name'              => 'vat_tax_code',
                    'type'              => 'text',
                    'instructions'      => 'Mã số thuế của công ty.',
                    'required'          => 0,
                    'conditional_logic' => array(
                        array(
                            array(
                                'field'    => 'field_vat_requested',
                                'operator' => '==',
                                'value'    => '1',
                            ),
                        ),
                    ),
                ),

                array(
                    'key'               => 'field_vat_address',
                    'label'             => 'Địa chỉ công ty',
                    'name'              => 'vat_address',
                    'type'              => 'text',
                    'instructions'      => 'Địa chỉ công ty ghi trên hóa đơn VAT.',
                    'required'          => 0,
                    'conditional_logic' => array(
                        array(
                            array(
                                'field'    => 'field_vat_requested',
                                'operator' => '==',
                                'value'    => '1',
                            ),
                        ),
                    ),
                ),

                // ── Tab 4: Quản lý ────────────────────────────────────────
                array(
                    'key'       => 'field_booking_tab_management',
                    'label'     => 'Quản lý',
                    'name'      => '',
                    'type'      => 'tab',
                    'placement' => 'top',
                    'endpoint'  => 0,
                ),

                array(
                    'key'           => 'field_booking_status',
                    'label'         => 'Trạng thái',
                    'name'          => 'booking_status',
                    'type'          => 'select',
                    'choices'       => array(
                        'pending_payment' => 'Chờ thanh toán',
                        'confirmed'       => 'Đã xác nhận',
                        'expired'         => 'Hết hạn',
                        'cancelled'       => 'Đã hủy',
                    ),
                    'default_value' => 'pending_payment',
                    'allow_null'    => 0,
                    'multiple'      => 0,
                    'ui'            => 1,
                    'return_format' => 'value',
                    'instructions'  => 'Trạng thái hiện tại của đơn đặt tour.',
                    'required'      => 1,
                ),

                array(
                    'key'           => 'field_booking_locked',
                    'label'         => 'Khóa đơn',
                    'name'          => 'booking_locked',
                    'type'          => 'true_false',
                    'message'       => 'Không cho phép chỉnh sửa đơn này (chỉ admin mới mở được)',
                    'default_value' => 0,
                    'ui'            => 1,
                    'ui_on_text'    => 'Khóa',
                    'ui_off_text'   => 'Mở',
                    'instructions'  => 'Khi bật, chỉ người dùng có quyền manage_options mới có thể lưu thay đổi.',
                    'required'      => 0,
                ),

                array(
                    'key'            => 'field_booking_paid_at',
                    'label'          => 'Thời gian xác nhận thanh toán',
                    'name'           => 'booking_paid_at',
                    'type'           => 'date_time_picker',
                    'display_format' => 'd/m/Y H:i',
                    'return_format'  => 'd/m/Y H:i',
                    'first_day'      => 1,
                    'instructions'   => 'Thời điểm xác nhận thanh toán thành công.',
                    'required'       => 0,
                ),

                array(
                    'key'            => 'field_booking_expired_at',
                    'label'          => 'Thời gian hết hạn',
                    'name'           => 'booking_expired_at',
                    'type'           => 'date_time_picker',
                    'display_format' => 'd/m/Y H:i',
                    'return_format'  => 'd/m/Y H:i',
                    'first_day'      => 1,
                    'instructions'   => 'Thời điểm đơn đặt hết hạn (nếu không thanh toán).',
                    'required'       => 0,
                ),

            ),
            'location'              => array(
                array(
                    array(
                        'param'    => 'post_type',
                        'operator' => '==',
                        'value'    => self::POST_TYPE,
                    ),
                ),
            ),
            'menu_order'            => 0,
            'position'              => 'normal',
            'style'                 => 'default',
            'label_placement'       => 'top',
            'instruction_placement' => 'label',
            'hide_on_screen'        => array(
                'permalink',
                'the_content',
                'excerpt',
                'discussion',
                'comments',
                'revisions',
                'slug',
                'author',
                'format',
                'page_attributes',
                'featured_image',
                'categories',
                'tags',
                'send-trackbacks',
            ),
            'active'      => true,
            'description' => 'Các trường quản lý đơn đặt tour.',
        ) );
    }

    // =========================================================================
    // HOOKS: save_post
    // =========================================================================

    /**
     * Hook vào lưu bài viết:
     * 1. Chặn lưu nếu đơn bị khóa và người dùng không có quyền manage_options.
     * 2. Khi booking_status chuyển sang "confirmed", bắn action halong_booking_confirmed.
     * 3. Tự động tính booking_total_guests.
     * 4. Tự động tạo booking_code nếu chưa có.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     * @param bool    $update  True nếu đang cập nhật.
     */
    public function on_save_post( $post_id, $post, $update ) {
        // Bỏ qua autosave, revision, trash.
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( wp_is_post_revision( $post_id ) ) {
            return;
        }
        if ( 'trash' === $post->post_status ) {
            return;
        }

        // Kiểm tra nonce ACF nếu có.
        if ( isset( $_POST['acf_nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['acf_nonce'] ) ), 'input' ) ) {
            return;
        }

        // ── 1. Kiểm tra khóa đơn ────────────────────────────────────────────
        $is_locked = (bool) get_post_meta( $post_id, 'booking_locked', true );
        if ( $is_locked && ! current_user_can( 'manage_options' ) ) {
            // Ngăn lưu bằng cách set lại nội dung cũ. Hiển thị thông báo.
            remove_action( 'save_post_' . self::POST_TYPE, array( $this, 'on_save_post' ), 20 );
            wp_update_post( array(
                'ID'          => $post_id,
                'post_status' => $post->post_status,
            ) );
            add_action( 'save_post_' . self::POST_TYPE, array( $this, 'on_save_post' ), 20 );

            add_filter( 'redirect_post_location', function ( $location ) {
                return add_query_arg( 'halong_locked', '1', $location );
            } );
            return;
        }

        // ── 2. Tự động tạo booking_code nếu chưa có ─────────────────────────
        $existing_code = get_post_meta( $post_id, 'booking_code', true );
        if ( empty( $existing_code ) ) {
            $new_code = self::generate_booking_code();
            update_post_meta( $post_id, 'booking_code', $new_code );
        }

        // ── 3. Tính booking_total_guests ─────────────────────────────────────
        $adults   = (int) get_post_meta( $post_id, 'booking_adults', true );
        $children = (int) get_post_meta( $post_id, 'booking_children', true );
        update_post_meta( $post_id, 'booking_total_guests', $adults + $children );

        // ── 4. Phát hiện chuyển sang "confirmed" ─────────────────────────────
        $new_status = get_post_meta( $post_id, 'booking_status', true );
        $old_status = get_post_meta( $post_id, '_booking_status_previous', true );

        if ( 'confirmed' === $new_status && 'confirmed' !== $old_status ) {
            /**
             * Fires khi booking chuyển sang trạng thái "confirmed".
             *
             * @param int $post_id ID của booking.
             */
            do_action( 'halong_booking_confirmed', $post_id );
        }

        // Lưu trạng thái hiện tại để so sánh lần sau.
        update_post_meta( $post_id, '_booking_status_previous', $new_status );
    }

    // =========================================================================
    // METABOX: Khóa đơn
    // =========================================================================

    /**
     * Thêm metabox "Khóa đơn" vào trang chỉnh sửa tour_booking.
     */
    public function add_lock_metabox() {
        add_meta_box(
            'halong_booking_lock',
            __( 'Khóa đơn', 'halong-tour' ),
            array( $this, 'render_lock_metabox' ),
            self::POST_TYPE,
            'side',
            'high'
        );
    }

    /**
     * Render nội dung metabox "Khóa đơn".
     *
     * @param WP_Post $post Post object.
     */
    public function render_lock_metabox( $post ) {
        $is_locked    = (bool) get_post_meta( $post->ID, 'booking_locked', true );
        $booking_code = get_post_meta( $post->ID, 'booking_code', true );
        $nonce        = wp_create_nonce( 'halong_lock_booking_' . $post->ID );
        $action_url   = admin_url( 'admin-post.php' );

        $button_label = $is_locked
            ? __( 'Mở khóa đơn', 'halong-tour' )
            : __( 'Khóa đơn', 'halong-tour' );
        $button_class = $is_locked ? 'button-secondary' : 'button-primary';
        $status_text  = $is_locked
            ? '<span style="color:#dc3232;font-weight:bold;">' . esc_html__( 'Đang bị khóa', 'halong-tour' ) . '</span>'
            : '<span style="color:#46b450;">' . esc_html__( 'Không bị khóa', 'halong-tour' ) . '</span>';
        ?>
        <p>
            <?php esc_html_e( 'Trạng thái:', 'halong-tour' ); ?>
            <?php echo $status_text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped above. ?>
        </p>
        <?php if ( $booking_code ) : ?>
            <p>
                <strong><?php esc_html_e( 'Mã booking:', 'halong-tour' ); ?></strong>
                <code><?php echo esc_html( $booking_code ); ?></code>
            </p>
        <?php endif; ?>

        <?php if ( current_user_can( 'manage_options' ) ) : ?>
            <form method="post" action="<?php echo esc_url( $action_url ); ?>">
                <input type="hidden" name="action" value="halong_lock_booking">
                <input type="hidden" name="post_id" value="<?php echo esc_attr( $post->ID ); ?>">
                <input type="hidden" name="lock_action" value="<?php echo $is_locked ? 'unlock' : 'lock'; ?>">
                <?php wp_nonce_field( 'halong_lock_booking_' . $post->ID, '_halong_lock_nonce' ); ?>
                <p>
                    <button type="submit" class="button <?php echo esc_attr( $button_class ); ?>">
                        <?php echo esc_html( $button_label ); ?>
                    </button>
                </p>
            </form>
        <?php else : ?>
            <p><em><?php esc_html_e( 'Bạn không có quyền thay đổi trạng thái khóa.', 'halong-tour' ); ?></em></p>
        <?php endif;
    }

    /**
     * Xử lý form khóa/mở khóa đơn.
     */
    public function handle_lock_booking() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Bạn không có quyền thực hiện hành động này.', 'halong-tour' ) );
        }

        $post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
        if ( ! $post_id ) {
            wp_die( esc_html__( 'ID đơn không hợp lệ.', 'halong-tour' ) );
        }

        check_admin_referer( 'halong_lock_booking_' . $post_id, '_halong_lock_nonce' );

        $lock_action = isset( $_POST['lock_action'] ) ? sanitize_key( $_POST['lock_action'] ) : '';
        $new_locked  = ( 'lock' === $lock_action ) ? 1 : 0;

        update_post_meta( $post_id, 'booking_locked', $new_locked );

        // Ghi audit log nếu class tồn tại.
        if ( class_exists( 'Halong_Audit_Log' ) ) {
            $old = $new_locked ? 0 : 1;
            Halong_Audit_Log::log(
                $post_id,
                'lock_changed',
                (string) $old,
                (string) $new_locked,
                'lock' === $lock_action ? 'Khóa đơn' : 'Mở khóa đơn'
            );
        }

        wp_redirect( get_edit_post_link( $post_id, 'url' ) );
        exit;
    }

    // =========================================================================
    // ADMIN COLUMNS
    // =========================================================================

    /**
     * Thêm cột tùy chỉnh vào danh sách bài viết.
     *
     * @param array $columns Cột mặc định.
     * @return array Cột đã chỉnh sửa.
     */
    public function add_admin_columns( $columns ) {
        unset( $columns['date'] );

        return array(
            'cb'              => $columns['cb'],
            'title'           => __( 'Tiêu đề', 'halong-tour' ),
            'booking_code'    => __( 'Mã booking', 'halong-tour' ),
            'booking_tour'    => __( 'Tour', 'halong-tour' ),
            'booking_datetime'=> __( 'Ngày / Giờ', 'halong-tour' ),
            'booking_guests'  => __( 'Số khách', 'halong-tour' ),
            'booking_total'   => __( 'Tổng tiền', 'halong-tour' ),
            'booking_status'  => __( 'Trạng thái', 'halong-tour' ),
            'booking_locked'  => __( 'Khóa', 'halong-tour' ),
            'date'            => __( 'Ngày tạo', 'halong-tour' ),
        );
    }

    /**
     * Render nội dung cột tùy chỉnh.
     *
     * @param string $column  Slug cột.
     * @param int    $post_id ID bài viết.
     */
    public function render_admin_column( $column, $post_id ) {
        switch ( $column ) {

            case 'booking_code':
                $code = get_post_meta( $post_id, 'booking_code', true );
                echo $code
                    ? '<code>' . esc_html( $code ) . '</code>'
                    : '<span style="color:#999;">—</span>';
                break;

            case 'booking_tour':
                $tour_id = get_post_meta( $post_id, 'booking_tour', true );
                if ( $tour_id ) {
                    $tour = get_post( (int) $tour_id );
                    if ( $tour ) {
                        printf(
                            '<a href="%s">%s</a>',
                            esc_url( get_edit_post_link( $tour_id ) ),
                            esc_html( $tour->post_title )
                        );
                        break;
                    }
                }
                echo '<span style="color:#999;">—</span>';
                break;

            case 'booking_datetime':
                $date = get_post_meta( $post_id, 'booking_date', true );
                $time = get_post_meta( $post_id, 'booking_time', true );
                if ( $date || $time ) {
                    echo esc_html( $date ) . ( $time ? '<br><small>' . esc_html( $time ) . '</small>' : '' );
                } else {
                    echo '<span style="color:#999;">—</span>';
                }
                break;

            case 'booking_guests':
                $total = get_post_meta( $post_id, 'booking_total_guests', true );
                $adults   = (int) get_post_meta( $post_id, 'booking_adults', true );
                $children = (int) get_post_meta( $post_id, 'booking_children', true );
                if ( '' !== $total ) {
                    printf(
                        '<strong>%d</strong><br><small>%s: %d | %s: %d</small>',
                        (int) $total,
                        esc_html__( 'Lớn', 'halong-tour' ),
                        $adults,
                        esc_html__( 'Trẻ', 'halong-tour' ),
                        $children
                    );
                } else {
                    echo '<span style="color:#999;">—</span>';
                }
                break;

            case 'booking_total':
                $price = get_post_meta( $post_id, 'booking_total_price', true );
                if ( '' !== $price ) {
                    echo '<strong>' . esc_html( number_format( (float) $price, 0, ',', '.' ) ) . ' ₫</strong>';
                } else {
                    echo '<span style="color:#999;">—</span>';
                }
                break;

            case 'booking_status':
                $status = get_post_meta( $post_id, 'booking_status', true );
                $colors = array(
                    'pending_payment' => '#f0ad00',
                    'confirmed'       => '#46b450',
                    'expired'         => '#777',
                    'cancelled'       => '#dc3232',
                );
                $label  = isset( self::$statuses[ $status ] ) ? self::$statuses[ $status ] : $status;
                $color  = isset( $colors[ $status ] ) ? $colors[ $status ] : '#888';
                printf(
                    '<span style="background:%s;color:#fff;padding:2px 8px;border-radius:3px;font-size:11px;font-weight:600;">%s</span>',
                    esc_attr( $color ),
                    esc_html( $label )
                );
                break;

            case 'booking_locked':
                $locked = get_post_meta( $post_id, 'booking_locked', true );
                if ( $locked ) {
                    printf(
                        '<span style="color:#dc3232;font-size:18px;" title="%s">&#128274;</span>',
                        esc_attr__( 'Đơn bị khóa', 'halong-tour' )
                    );
                } else {
                    printf(
                        '<span style="color:#aaa;font-size:18px;" title="%s">&#128275;</span>',
                        esc_attr__( 'Đơn không bị khóa', 'halong-tour' )
                    );
                }
                break;
        }
    }

    /**
     * Khai báo các cột có thể sắp xếp.
     *
     * @param array $columns Cột hiện tại.
     * @return array Cột có thể sắp xếp.
     */
    public function sortable_columns( $columns ) {
        $columns['booking_code']   = 'booking_code';
        $columns['booking_status'] = 'booking_status';
        $columns['booking_total']  = 'booking_total_price';
        return $columns;
    }

    // =========================================================================
    // ADMIN: Status dropdowns in list & post edit screen
    // =========================================================================

    /**
     * Thêm custom statuses vào dropdown bulk action / filter trên edit.php.
     */
    public function append_statuses_to_bulk_dropdown() {
        global $post_type;
        if ( self::POST_TYPE !== $post_type ) {
            return;
        }
        ?>
        <script>
        jQuery(document).ready(function ($) {
            var statuses = <?php echo wp_json_encode( self::$statuses ); ?>;
            $.each(statuses, function (val, label) {
                $('select[name="_status"]').append(
                    $('<option>').val(val).text(label)
                );
            });
        });
        </script>
        <?php
    }

    /**
     * Thêm custom statuses vào dropdown trạng thái trên post.php.
     */
    public function append_statuses_to_post_edit_dropdown() {
        global $post;
        if ( ! $post || self::POST_TYPE !== $post->post_type ) {
            return;
        }
        ?>
        <script>
        jQuery(document).ready(function ($) {
            var statuses = <?php echo wp_json_encode( self::$statuses ); ?>;
            $.each(statuses, function (val, label) {
                $('select#post_status').append(
                    $('<option>').val(val).text(label)
                );
            });
            // Set selected nếu post đang có custom status.
            var currentStatus = '<?php echo esc_js( $post->post_status ); ?>';
            if (statuses[currentStatus]) {
                $('select#post_status').val(currentStatus);
                $('#post-status-display').text(statuses[currentStatus]);
            }
        });
        </script>
        <?php
    }

    // =========================================================================
    // STATIC UTILITIES
    // =========================================================================

    /**
     * Tạo mã booking duy nhất dạng HLRXXXXXX.
     * Loại bỏ các ký tự dễ nhầm lẫn (0, O, I, 1).
     *
     * @return string Mã booking chưa tồn tại trong DB.
     */
    public static function generate_booking_code() {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // exclude ambiguous chars
        do {
            $code = 'HLR';
            for ( $i = 0; $i < 6; $i++ ) {
                $code .= $chars[ random_int( 0, strlen( $chars ) - 1 ) ];
            }
            $existing = get_posts( array(
                'post_type'      => self::POST_TYPE,
                'meta_key'       => 'booking_code',
                'meta_value'     => $code,
                'posts_per_page' => 1,
                'fields'         => 'ids',
            ) );
        } while ( ! empty( $existing ) );

        return $code;
    }

    /**
     * Lấy nội dung chuyển khoản hợp lệ từ template cấu hình.
     * Tự động loại bỏ ký tự đặc biệt để khớp hoàn toàn với ngân hàng.
     *
     * @param string $booking_code  Mã đặt chỗ.
     * @return string Nội dung chuyển khoản viết hoa, không ký tự đặc biệt.
     */
    public static function get_payment_description( $booking_code ) {
        $template = function_exists( 'get_field' )
            ? get_field( 'halong_bank_template', 'option' )
            : '';
        if ( empty( $template ) ) {
            $template = '{booking_code}';
        }

        // Thay thế các placeholder
        $desc = str_replace( '{booking_code}', $booking_code, $template );

        // Loại bỏ ký tự đặc biệt trong toàn chuỗi
        $desc = preg_replace( '/[^A-Za-z0-9 ]/', '', $desc );
        $desc = preg_replace( '/\s+/', ' ', $desc );

        return strtoupper( trim( $desc ) );
    }


    /**
     * Lấy tổng số khách đã đặt cho một tour vào một ngày và khung giờ cụ thể.
     * Chỉ tính các đơn có trạng thái pending_payment hoặc confirmed.
     *
     * @param int    $tour_id  ID của tour.
     * @param string $date     Ngày tham quan (định dạng d/m/Y).
     * @param string $time     Khung giờ.
     * @return int Tổng số khách đã đặt.
     */
    public static function get_booked_count( $tour_id, $date, $time ) {
        $args = array(
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => array(
                'relation' => 'AND',
                array(
                    'key'     => 'booking_tour',
                    'value'   => (int) $tour_id,
                    'compare' => '=',
                    'type'    => 'NUMERIC',
                ),
                array(
                    'key'     => 'booking_date',
                    'value'   => sanitize_text_field( $date ),
                    'compare' => '=',
                ),
                array(
                    'key'     => 'booking_time',
                    'value'   => sanitize_text_field( $time ),
                    'compare' => '=',
                ),
                array(
                    'key'     => 'booking_status',
                    'value'   => array( 'pending_payment', 'confirmed' ),
                    'compare' => 'IN',
                ),
            ),
        );

        $booking_ids = get_posts( $args );

        if ( empty( $booking_ids ) ) {
            return 0;
        }

        $total = 0;
        foreach ( $booking_ids as $bid ) {
            $guests = (int) get_post_meta( $bid, 'booking_total_guests', true );
            $total += $guests;
        }

        return $total;
    }
}
