<?php
/**
 * Halong_Audit_Log
 *
 * Quản lý nhật ký thay đổi (audit log) và nhật ký email cho hệ thống đặt tour.
 * Tạo và duy trì hai bảng DB: wp_halong_audit_log và wp_halong_email_log.
 *
 * Cách dùng trong functions.php:
 *   register_activation_hook( __FILE__, array( 'Halong_Audit_Log', 'create_tables' ) );
 *   new Halong_Audit_Log();
 *
 * @package HaLong_Tour
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Halong_Audit_Log {

    /**
     * Phiên bản schema DB. Tăng khi thay đổi cấu trúc bảng.
     *
     * @var string
     */
    const DB_VERSION = '1.0';

    /**
     * Option key lưu phiên bản DB đã cài.
     *
     * @var string
     */
    const DB_VERSION_OPTION = 'halong_db_version';

    /**
     * Khởi tạo: kiểm tra phiên bản DB và đăng ký admin page.
     */
    public function __construct() {
        add_action( 'init', array( $this, 'maybe_create_tables' ) );
        add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
        add_action( 'admin_notices', array( $this, 'show_locked_notice' ) );
    }

    // =========================================================================
    // TABLE MANAGEMENT
    // =========================================================================

    /**
     * Tạo hoặc cập nhật cấu trúc bảng DB bằng dbDelta.
     * Gọi từ register_activation_hook và khi phiên bản DB không khớp.
     */
    public static function create_tables() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        // ── Bảng audit log ────────────────────────────────────────────────────
        $audit_table = $wpdb->prefix . 'halong_audit_log';
        $sql_audit   = "CREATE TABLE {$audit_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            booking_id bigint(20) NOT NULL,
            booking_code varchar(20) NOT NULL DEFAULT '',
            action varchar(100) NOT NULL,
            old_value text,
            new_value text,
            note text,
            user_id bigint(20) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY booking_id (booking_id),
            KEY booking_code (booking_code)
        ) {$charset_collate};";

        // ── Bảng email log ────────────────────────────────────────────────────
        $email_table = $wpdb->prefix . 'halong_email_log';
        $sql_email   = "CREATE TABLE {$email_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            booking_id bigint(20) NOT NULL,
            recipient varchar(255) NOT NULL,
            subject varchar(255) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'sent',
            error_message text,
            sent_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY booking_id (booking_id)
        ) {$charset_collate};";

        dbDelta( $sql_audit );
        dbDelta( $sql_email );

        update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
    }

    /**
     * Kiểm tra phiên bản DB; nếu chưa khớp thì chạy create_tables.
     * Hook vào 'init'.
     */
    public function maybe_create_tables() {
        $installed_version = get_option( self::DB_VERSION_OPTION, '' );
        if ( $installed_version !== self::DB_VERSION ) {
            self::create_tables();
        }
    }

    // =========================================================================
    // LOGGING
    // =========================================================================

    /**
     * Ghi một mục vào bảng audit log.
     *
     * @param int          $booking_id ID của booking.
     * @param string       $action     Mô tả hành động (ví dụ: 'status_changed').
     * @param mixed        $old_value  Giá trị cũ (array/object sẽ tự động JSON-encode).
     * @param mixed        $new_value  Giá trị mới.
     * @param string       $note       Ghi chú thêm.
     * @param int|null     $user_id    ID người dùng (null = lấy user hiện tại).
     * @return int|false   Số hàng đã chèn hoặc false nếu lỗi.
     */
    public static function log(
        $booking_id,
        $action,
        $old_value = '',
        $new_value = '',
        $note = '',
        $user_id = null
    ) {
        global $wpdb;

        if ( null === $user_id ) {
            $user_id = get_current_user_id();
        }

        // JSON-encode giá trị phức tạp.
        if ( is_array( $old_value ) || is_object( $old_value ) ) {
            $old_value = wp_json_encode( $old_value );
        }
        if ( is_array( $new_value ) || is_object( $new_value ) ) {
            $new_value = wp_json_encode( $new_value );
        }

        // Lấy booking_code.
        $booking_code = (string) get_post_meta( (int) $booking_id, 'booking_code', true );

        $table = $wpdb->prefix . 'halong_audit_log';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
        $result = $wpdb->insert(
            $table,
            array(
                'booking_id'   => (int) $booking_id,
                'booking_code' => $booking_code,
                'action'       => sanitize_text_field( $action ),
                'old_value'    => (string) $old_value,
                'new_value'    => (string) $new_value,
                'note'         => sanitize_textarea_field( $note ),
                'user_id'      => (int) $user_id,
                'created_at'   => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery

        return $result;
    }

    /**
     * Ghi một mục vào bảng email log.
     *
     * @param int    $booking_id ID của booking.
     * @param string $recipient  Địa chỉ email người nhận.
     * @param string $subject    Tiêu đề email.
     * @param string $status     Trạng thái gửi ('sent', 'failed', v.v.).
     * @param string $error      Thông báo lỗi (nếu có).
     * @return int|false Số hàng đã chèn hoặc false nếu lỗi.
     */
    public static function log_email(
        $booking_id,
        $recipient,
        $subject,
        $status = 'sent',
        $error = ''
    ) {
        global $wpdb;

        $table = $wpdb->prefix . 'halong_email_log';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
        $result = $wpdb->insert(
            $table,
            array(
                'booking_id'    => (int) $booking_id,
                'recipient'     => sanitize_email( $recipient ),
                'subject'       => sanitize_text_field( $subject ),
                'status'        => sanitize_key( $status ),
                'error_message' => sanitize_textarea_field( $error ),
                'sent_at'       => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%s' )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery

        return $result;
    }

    // =========================================================================
    // QUERIES
    // =========================================================================

    /**
     * Lấy toàn bộ audit log của một booking, mới nhất trước.
     *
     * @param int $booking_id ID của booking.
     * @return array Mảng các hàng log.
     */
    public static function get_booking_log( $booking_id ) {
        global $wpdb;

        $table = $wpdb->prefix . 'halong_audit_log';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE booking_id = %d ORDER BY created_at DESC",
                (int) $booking_id
            ),
            ARRAY_A
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        return $results ? $results : array();
    }

    /**
     * Lấy toàn bộ email log của một booking, mới nhất trước.
     *
     * @param int $booking_id ID của booking.
     * @return array Mảng các hàng email log.
     */
    public static function get_email_log( $booking_id ) {
        global $wpdb;

        $table = $wpdb->prefix . 'halong_email_log';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE booking_id = %d ORDER BY sent_at DESC",
                (int) $booking_id
            ),
            ARRAY_A
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        return $results ? $results : array();
    }

    // =========================================================================
    // ADMIN PAGE
    // =========================================================================

    /**
     * Đăng ký trang admin "Nhật ký hệ thống" dưới menu tour_booking.
     */
    public function register_admin_page() {
        add_submenu_page(
            'edit.php?post_type=tour_booking',
            __( 'Nhật ký hệ thống', 'halong-tour' ),
            __( 'Nhật ký', 'halong-tour' ),
            'manage_options',
            'halong-audit-log',
            array( $this, 'render_admin_page' )
        );
    }

    /**
     * Render trang admin audit log.
     * Hỗ trợ lọc theo booking_id qua ?booking_id=XXX.
     */
    public function render_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Bạn không có quyền truy cập trang này.', 'halong-tour' ) );
        }

        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $booking_id = isset( $_GET['booking_id'] ) ? (int) $_GET['booking_id'] : 0;
        $tab        = isset( $_GET['log_tab'] ) ? sanitize_key( $_GET['log_tab'] ) : 'audit';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        $audit_logs = array();
        $email_logs = array();
        $booking    = null;

        if ( $booking_id ) {
            $booking    = get_post( $booking_id );
            $audit_logs = self::get_booking_log( $booking_id );
            $email_logs = self::get_email_log( $booking_id );
        }

        $page_url      = admin_url( 'edit.php?post_type=tour_booking&page=halong-audit-log' );
        $audit_tab_url = add_query_arg( array( 'booking_id' => $booking_id, 'log_tab' => 'audit' ), $page_url );
        $email_tab_url = add_query_arg( array( 'booking_id' => $booking_id, 'log_tab' => 'email' ), $page_url );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Nhật ký hệ thống', 'halong-tour' ); ?></h1>

            <!-- Bộ lọc theo booking ID -->
            <form method="get" action="<?php echo esc_url( $page_url ); ?>" style="margin:16px 0;">
                <input type="hidden" name="post_type" value="tour_booking">
                <input type="hidden" name="page" value="halong-audit-log">
                <label for="halong_booking_id_filter">
                    <strong><?php esc_html_e( 'Lọc theo ID đặt tour:', 'halong-tour' ); ?></strong>
                </label>
                <input
                    type="number"
                    id="halong_booking_id_filter"
                    name="booking_id"
                    value="<?php echo esc_attr( $booking_id ); ?>"
                    placeholder="<?php esc_attr_e( 'Nhập booking ID...', 'halong-tour' ); ?>"
                    style="width:160px;"
                >
                <?php submit_button( __( 'Xem nhật ký', 'halong-tour' ), 'secondary', 'submit', false ); ?>

                <?php if ( $booking_id ) : ?>
                    <a href="<?php echo esc_url( $page_url ); ?>" class="button">
                        <?php esc_html_e( 'Xóa bộ lọc', 'halong-tour' ); ?>
                    </a>
                <?php endif; ?>
            </form>

            <?php if ( $booking_id ) : ?>

                <?php if ( $booking ) : ?>
                    <?php
                    $booking_code = get_post_meta( $booking_id, 'booking_code', true );
                    $customer     = get_post_meta( $booking_id, 'customer_name', true );
                    ?>
                    <div class="notice notice-info inline">
                        <p>
                            <?php esc_html_e( 'Đơn đặt:', 'halong-tour' ); ?>
                            <strong><a href="<?php echo esc_url( get_edit_post_link( $booking_id ) ); ?>">#<?php echo esc_html( $booking_id ); ?></a></strong>
                            <?php if ( $booking_code ) : ?>
                                &mdash; <code><?php echo esc_html( $booking_code ); ?></code>
                            <?php endif; ?>
                            <?php if ( $customer ) : ?>
                                &mdash; <?php echo esc_html( $customer ); ?>
                            <?php endif; ?>
                        </p>
                    </div>
                <?php else : ?>
                    <div class="notice notice-warning inline">
                        <p><?php esc_html_e( 'Không tìm thấy đơn đặt với ID này.', 'halong-tour' ); ?></p>
                    </div>
                <?php endif; ?>

                <!-- Tabs -->
                <h2 class="nav-tab-wrapper">
                    <a href="<?php echo esc_url( $audit_tab_url ); ?>"
                       class="nav-tab <?php echo 'audit' === $tab ? 'nav-tab-active' : ''; ?>">
                        <?php
                        printf(
                            /* translators: %d: log count */
                            esc_html__( 'Nhật ký thay đổi (%d)', 'halong-tour' ),
                            count( $audit_logs )
                        );
                        ?>
                    </a>
                    <a href="<?php echo esc_url( $email_tab_url ); ?>"
                       class="nav-tab <?php echo 'email' === $tab ? 'nav-tab-active' : ''; ?>">
                        <?php
                        printf(
                            /* translators: %d: log count */
                            esc_html__( 'Nhật ký email (%d)', 'halong-tour' ),
                            count( $email_logs )
                        );
                        ?>
                    </a>
                </h2>

                <?php if ( 'audit' === $tab ) : ?>
                    <?php $this->render_audit_table( $audit_logs ); ?>
                <?php else : ?>
                    <?php $this->render_email_table( $email_logs ); ?>
                <?php endif; ?>

            <?php else : ?>
                <p><?php esc_html_e( 'Nhập ID đặt tour để xem nhật ký.', 'halong-tour' ); ?></p>

                <?php $this->render_recent_audit_logs(); ?>

            <?php endif; ?>
        </div><!-- .wrap -->
        <?php
    }

    /**
     * Render bảng audit log.
     *
     * @param array $logs Mảng log.
     */
    protected function render_audit_table( $logs ) {
        if ( empty( $logs ) ) {
            echo '<p>' . esc_html__( 'Không có nhật ký thay đổi nào.', 'halong-tour' ) . '</p>';
            return;
        }
        ?>
        <table class="wp-list-table widefat fixed striped" style="margin-top:16px;">
            <thead>
                <tr>
                    <th style="width:60px;"><?php esc_html_e( 'ID', 'halong-tour' ); ?></th>
                    <th style="width:160px;"><?php esc_html_e( 'Thời gian', 'halong-tour' ); ?></th>
                    <th style="width:160px;"><?php esc_html_e( 'Hành động', 'halong-tour' ); ?></th>
                    <th><?php esc_html_e( 'Giá trị cũ', 'halong-tour' ); ?></th>
                    <th><?php esc_html_e( 'Giá trị mới', 'halong-tour' ); ?></th>
                    <th><?php esc_html_e( 'Ghi chú', 'halong-tour' ); ?></th>
                    <th style="width:120px;"><?php esc_html_e( 'Người thực hiện', 'halong-tour' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $logs as $log ) : ?>
                    <tr>
                        <td><?php echo esc_html( $log['id'] ); ?></td>
                        <td><?php echo esc_html( $log['created_at'] ); ?></td>
                        <td><code><?php echo esc_html( $log['action'] ); ?></code></td>
                        <td>
                            <?php if ( ! empty( $log['old_value'] ) ) : ?>
                                <span style="color:#dc3232;"><?php echo esc_html( $log['old_value'] ); ?></span>
                            <?php else : ?>
                                <span style="color:#aaa;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ( ! empty( $log['new_value'] ) ) : ?>
                                <span style="color:#46b450;"><?php echo esc_html( $log['new_value'] ); ?></span>
                            <?php else : ?>
                                <span style="color:#aaa;">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html( $log['note'] ); ?></td>
                        <td>
                            <?php
                            $user_id = (int) $log['user_id'];
                            if ( $user_id ) {
                                $user = get_user_by( 'id', $user_id );
                                echo $user
                                    ? esc_html( $user->display_name )
                                    : esc_html( sprintf( '#%d', $user_id ) );
                            } else {
                                echo '<span style="color:#aaa;">' . esc_html__( 'Hệ thống', 'halong-tour' ) . '</span>';
                            }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Render bảng email log.
     *
     * @param array $logs Mảng email log.
     */
    protected function render_email_table( $logs ) {
        if ( empty( $logs ) ) {
            echo '<p>' . esc_html__( 'Không có nhật ký email nào.', 'halong-tour' ) . '</p>';
            return;
        }
        ?>
        <table class="wp-list-table widefat fixed striped" style="margin-top:16px;">
            <thead>
                <tr>
                    <th style="width:60px;"><?php esc_html_e( 'ID', 'halong-tour' ); ?></th>
                    <th style="width:160px;"><?php esc_html_e( 'Thời gian', 'halong-tour' ); ?></th>
                    <th><?php esc_html_e( 'Người nhận', 'halong-tour' ); ?></th>
                    <th><?php esc_html_e( 'Tiêu đề email', 'halong-tour' ); ?></th>
                    <th style="width:100px;"><?php esc_html_e( 'Trạng thái', 'halong-tour' ); ?></th>
                    <th><?php esc_html_e( 'Lỗi', 'halong-tour' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $logs as $log ) : ?>
                    <?php
                    $status_color = 'sent' === $log['status'] ? '#46b450' : '#dc3232';
                    $status_label = 'sent' === $log['status']
                        ? __( 'Đã gửi', 'halong-tour' )
                        : ucfirst( $log['status'] );
                    ?>
                    <tr>
                        <td><?php echo esc_html( $log['id'] ); ?></td>
                        <td><?php echo esc_html( $log['sent_at'] ); ?></td>
                        <td><?php echo esc_html( $log['recipient'] ); ?></td>
                        <td><?php echo esc_html( $log['subject'] ); ?></td>
                        <td>
                            <span style="color:<?php echo esc_attr( $status_color ); ?>;font-weight:bold;">
                                <?php echo esc_html( $status_label ); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ( ! empty( $log['error_message'] ) ) : ?>
                                <span style="color:#dc3232;"><?php echo esc_html( $log['error_message'] ); ?></span>
                            <?php else : ?>
                                <span style="color:#aaa;">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Render 50 mục audit log gần nhất (tổng hợp, không lọc booking).
     * Hiển thị trên trang audit log khi chưa lọc.
     */
    protected function render_recent_audit_logs() {
        global $wpdb;

        $table = $wpdb->prefix . 'halong_audit_log';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $logs = $wpdb->get_results(
            "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 50",
            ARRAY_A
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        if ( empty( $logs ) ) {
            echo '<p>' . esc_html__( 'Chưa có nhật ký nào trong hệ thống.', 'halong-tour' ) . '</p>';
            return;
        }

        echo '<h2>' . esc_html__( '50 mục gần nhất', 'halong-tour' ) . '</h2>';
        ?>
        <table class="wp-list-table widefat fixed striped" style="margin-top:8px;">
            <thead>
                <tr>
                    <th style="width:60px;"><?php esc_html_e( 'ID', 'halong-tour' ); ?></th>
                    <th style="width:100px;"><?php esc_html_e( 'Booking', 'halong-tour' ); ?></th>
                    <th style="width:120px;"><?php esc_html_e( 'Mã booking', 'halong-tour' ); ?></th>
                    <th style="width:160px;"><?php esc_html_e( 'Thời gian', 'halong-tour' ); ?></th>
                    <th style="width:160px;"><?php esc_html_e( 'Hành động', 'halong-tour' ); ?></th>
                    <th><?php esc_html_e( 'Giá trị cũ → Mới', 'halong-tour' ); ?></th>
                    <th><?php esc_html_e( 'Ghi chú', 'halong-tour' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $logs as $log ) :
                    $page_url   = admin_url( 'edit.php?post_type=tour_booking&page=halong-audit-log' );
                    $filter_url = add_query_arg( 'booking_id', $log['booking_id'], $page_url );
                    ?>
                    <tr>
                        <td><?php echo esc_html( $log['id'] ); ?></td>
                        <td>
                            <a href="<?php echo esc_url( $filter_url ); ?>">
                                #<?php echo esc_html( $log['booking_id'] ); ?>
                            </a>
                        </td>
                        <td>
                            <?php if ( $log['booking_code'] ) : ?>
                                <code><?php echo esc_html( $log['booking_code'] ); ?></code>
                            <?php else : ?>
                                <span style="color:#aaa;">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html( $log['created_at'] ); ?></td>
                        <td><code><?php echo esc_html( $log['action'] ); ?></code></td>
                        <td>
                            <?php if ( $log['old_value'] || $log['new_value'] ) : ?>
                                <span style="color:#dc3232;"><?php echo esc_html( $log['old_value'] ); ?></span>
                                &nbsp;&rarr;&nbsp;
                                <span style="color:#46b450;"><?php echo esc_html( $log['new_value'] ); ?></span>
                            <?php else : ?>
                                <span style="color:#aaa;">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html( $log['note'] ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    // =========================================================================
    // ADMIN NOTICES
    // =========================================================================

    /**
     * Hiển thị thông báo khi đơn bị khóa (không cho lưu).
     */
    public function show_locked_notice() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        if ( ! isset( $_GET['halong_locked'] ) || '1' !== $_GET['halong_locked'] ) {
            return;
        }
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        ?>
        <div class="notice notice-error is-dismissible">
            <p>
                <strong><?php esc_html_e( 'Không thể lưu:', 'halong-tour' ); ?></strong>
                <?php esc_html_e( 'Đơn đặt tour này đang bị khóa. Chỉ quản trị viên (manage_options) mới có thể thực hiện thay đổi.', 'halong-tour' ); ?>
            </p>
        </div>
        <?php
    }
}
