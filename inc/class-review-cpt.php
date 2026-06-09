<?php
/**
 * Halong_Review_CPT
 *
 * Đăng ký Custom Post Type `tour_review` và các trường SCF liên quan.
 * Quản lý cột admin cho danh sách đánh giá.
 *
 * @package HaLong_Tour
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Halong_Review_CPT {

    /**
     * Post type slug.
     *
     * @var string
     */
    const POST_TYPE = 'tour_review';

    /**
     * SCF field group key.
     *
     * @var string
     */
    const FIELD_GROUP_KEY = 'group_halong_review';

    /**
     * Khởi tạo và đăng ký hooks.
     */
    public function __construct() {
        add_action( 'init', array( $this, 'register_post_type' ) );
        add_action( 'acf/init', array( $this, 'register_scf_fields' ) );

        // Admin columns.
        add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'add_admin_columns' ) );
        add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'render_admin_column' ), 10, 2 );
        add_filter( 'manage_edit-' . self::POST_TYPE . '_sortable_columns', array( $this, 'sortable_columns' ) );
    }

    /**
     * Đăng ký Custom Post Type `tour_review`.
     */
    public function register_post_type() {
        $labels = array(
            'name'                  => _x( 'Đánh giá', 'post type general name', 'halong-tour' ),
            'singular_name'         => _x( 'Đánh giá', 'post type singular name', 'halong-tour' ),
            'menu_name'             => __( 'Đánh giá', 'halong-tour' ),
            'name_admin_bar'        => __( 'Đánh giá', 'halong-tour' ),
            'add_new'               => __( 'Thêm mới', 'halong-tour' ),
            'add_new_item'          => __( 'Thêm đánh giá mới', 'halong-tour' ),
            'new_item'              => __( 'Đánh giá mới', 'halong-tour' ),
            'edit_item'             => __( 'Sửa đánh giá', 'halong-tour' ),
            'view_item'             => __( 'Xem đánh giá', 'halong-tour' ),
            'all_items'             => __( 'Tất cả đánh giá', 'halong-tour' ),
            'search_items'          => __( 'Tìm kiếm đánh giá', 'halong-tour' ),
            'parent_item_colon'     => __( 'Đánh giá cha:', 'halong-tour' ),
            'not_found'             => __( 'Không tìm thấy đánh giá.', 'halong-tour' ),
            'not_found_in_trash'    => __( 'Không có đánh giá trong thùng rác.', 'halong-tour' ),
            'featured_image'        => __( 'Ảnh đại diện', 'halong-tour' ),
            'set_featured_image'    => __( 'Đặt ảnh đại diện', 'halong-tour' ),
            'remove_featured_image' => __( 'Xóa ảnh đại diện', 'halong-tour' ),
            'use_featured_image'    => __( 'Dùng làm ảnh đại diện', 'halong-tour' ),
            'archives'              => __( 'Lưu trữ đánh giá', 'halong-tour' ),
            'attributes'            => __( 'Thuộc tính đánh giá', 'halong-tour' ),
            'insert_into_item'      => __( 'Chèn vào đánh giá', 'halong-tour' ),
            'uploaded_to_this_item' => __( 'Đã tải lên mục này', 'halong-tour' ),
            'filter_items_list'     => __( 'Lọc danh sách đánh giá', 'halong-tour' ),
            'items_list_navigation' => __( 'Điều hướng danh sách đánh giá', 'halong-tour' ),
            'items_list'            => __( 'Danh sách đánh giá', 'halong-tour' ),
        );

        $args = array(
            'labels'             => $labels,
            'description'        => __( 'Đánh giá tour của khách hàng.', 'halong-tour' ),
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
            'menu_position'      => 6,
            'menu_icon'          => 'dashicons-star-filled',
            'supports'           => array( 'title', 'custom-fields' ),
        );

        register_post_type( self::POST_TYPE, $args );
    }

    /**
     * Đăng ký nhóm trường SCF / ACF cho `tour_review`.
     */
    public function register_scf_fields() {
        if ( ! function_exists( 'acf_add_local_field_group' ) ) {
            return;
        }

        acf_add_local_field_group( array(
            'key'                   => self::FIELD_GROUP_KEY,
            'title'                 => 'Thông tin đánh giá',
            'fields'                => array(

                // ── Tab: Thông tin đánh giá ────────────────────────────────
                array(
                    'key'       => 'field_review_tab_info',
                    'label'     => 'Thông tin đánh giá',
                    'name'      => '',
                    'type'      => 'tab',
                    'placement' => 'top',
                    'endpoint'  => 0,
                ),

                // Tour liên kết
                array(
                    'key'           => 'field_review_tour',
                    'label'         => 'Tour liên kết',
                    'name'          => 'review_tour',
                    'type'          => 'post_object',
                    'post_type'     => array( 'tour' ),
                    'taxonomy'      => array(),
                    'allow_null'    => 0,
                    'multiple'      => 0,
                    'return_format' => 'id',
                    'ui'            => 1,
                    'instructions'  => 'Chọn tour mà khách đã đánh giá.',
                    'required'      => 1,
                ),

                // Đánh giá sao
                array(
                    'key'           => 'field_review_rating',
                    'label'         => 'Đánh giá (1-5)',
                    'name'          => 'review_rating',
                    'type'          => 'number',
                    'min'           => 1,
                    'max'           => 5,
                    'step'          => 1,
                    'default_value' => 5,
                    'instructions'  => 'Số sao từ 1 (tệ nhất) đến 5 (xuất sắc).',
                    'required'      => 1,
                ),

                // Tên khách hàng
                array(
                    'key'          => 'field_review_reviewer_name',
                    'label'        => 'Tên khách hàng',
                    'name'         => 'review_reviewer_name',
                    'type'         => 'text',
                    'instructions' => 'Họ tên đầy đủ của người đánh giá.',
                    'required'     => 1,
                    'maxlength'    => 150,
                ),

                // Ngày tham gia
                array(
                    'key'            => 'field_review_join_date',
                    'label'          => 'Ngày tham gia',
                    'name'           => 'review_join_date',
                    'type'           => 'date_picker',
                    'display_format' => 'd/m/Y',
                    'return_format'  => 'd/m/Y',
                    'first_day'      => 1,
                    'instructions'   => 'Ngày khách tham gia tour.',
                    'required'       => 0,
                ),

                // Nội dung đánh giá
                array(
                    'key'          => 'field_review_content',
                    'label'        => 'Nội dung đánh giá',
                    'name'         => 'review_content',
                    'type'         => 'textarea',
                    'rows'         => 5,
                    'new_lines'    => 'br',
                    'instructions' => 'Nhận xét chi tiết của khách hàng.',
                    'required'     => 1,
                ),

                // Đã xác thực
                array(
                    'key'           => 'field_review_verified',
                    'label'         => 'Đã xác thực',
                    'name'          => 'review_verified',
                    'type'          => 'true_false',
                    'message'       => 'Đánh dấu đánh giá này là đã xác thực',
                    'default_value' => 0,
                    'ui'            => 1,
                    'ui_on_text'    => 'Có',
                    'ui_off_text'   => 'Không',
                    'instructions'  => 'Bật nếu đã xác minh khách hàng thực sự đã tham gia tour.',
                    'required'      => 0,
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
            'description' => 'Các trường thông tin cho đánh giá tour.',
        ) );
    }

    /**
     * Thêm cột tùy chỉnh vào danh sách bài viết.
     *
     * @param array $columns Cột mặc định.
     * @return array Cột đã chỉnh sửa.
     */
    public function add_admin_columns( $columns ) {
        unset( $columns['date'] );

        return array(
            'cb'                   => $columns['cb'],
            'title'                => __( 'Tiêu đề', 'halong-tour' ),
            'review_tour'          => __( 'Tour', 'halong-tour' ),
            'review_reviewer_name' => __( 'Người đánh giá', 'halong-tour' ),
            'review_rating'        => __( 'Đánh giá', 'halong-tour' ),
            'review_verified'      => __( 'Đã xác thực', 'halong-tour' ),
            'review_join_date'     => __( 'Ngày tham gia', 'halong-tour' ),
            'date'                 => __( 'Ngày tạo', 'halong-tour' ),
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

            case 'review_tour':
                $tour_id = get_post_meta( $post_id, 'review_tour', true );
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

            case 'review_reviewer_name':
                $name = get_post_meta( $post_id, 'review_reviewer_name', true );
                echo $name ? esc_html( $name ) : '<span style="color:#999;">—</span>';
                break;

            case 'review_rating':
                $rating = (int) get_post_meta( $post_id, 'review_rating', true );
                if ( $rating >= 1 && $rating <= 5 ) {
                    $stars = str_repeat( '★', $rating ) . str_repeat( '☆', 5 - $rating );
                    printf(
                        '<span style="color:#f5a623;font-size:16px;" title="%d/5">%s</span>',
                        esc_attr( $rating ),
                        esc_html( $stars )
                    );
                } else {
                    echo '<span style="color:#999;">—</span>';
                }
                break;

            case 'review_verified':
                $verified = get_post_meta( $post_id, 'review_verified', true );
                if ( $verified ) {
                    printf(
                        '<span style="color:#46b450;font-size:18px;" title="%s">&#10003;</span>',
                        esc_attr__( 'Đã xác thực', 'halong-tour' )
                    );
                } else {
                    printf(
                        '<span style="color:#dc3232;font-size:18px;" title="%s">&#10007;</span>',
                        esc_attr__( 'Chưa xác thực', 'halong-tour' )
                    );
                }
                break;

            case 'review_join_date':
                $date = get_post_meta( $post_id, 'review_join_date', true );
                echo $date ? esc_html( $date ) : '<span style="color:#999;">—</span>';
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
        $columns['review_rating']    = 'review_rating';
        $columns['review_join_date'] = 'review_join_date';
        return $columns;
    }
}
