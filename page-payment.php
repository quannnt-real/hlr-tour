<?php
/**
 * Template Name: HaLong Tour Payment Page
 *
 * Page template for displaying payment instructions and success screen.
 *
 * @package HaLong_Tour
 */

get_header();

$booking_code = isset( $_GET['code'] ) ? strtoupper( sanitize_key( $_GET['code'] ) ) : '';
$is_success_param = isset( $_GET['success'] ) && $_GET['success'] === '1';

$booking_id = 0;
if ( ! empty( $booking_code ) ) {
    $bookings = get_posts( array(
        'post_type'      => 'tour_booking',
        'posts_per_page' => 1,
        'meta_key'       => 'booking_code',
        'meta_value'     => $booking_code,
        'fields'         => 'ids',
    ) );
    if ( ! empty( $bookings ) ) {
        $booking_id = $bookings[0];
    }
}

if ( ! $booking_id ) {
    ?>
    <div class="view-section pt-32 pb-20 text-center min-h-[50vh] flex flex-col items-center justify-center">
        <div class="max-w-md mx-auto px-6">
            <i class="ph ph-warning-circle text-brand-accent text-6xl mb-4 block"></i>
            <h1 class="font-serif text-brand-cream text-2xl mb-4">Không tìm thấy đơn đặt tour</h1>
            <p class="text-brand-body text-sm font-light mb-8">Mã đặt chỗ của bạn không tồn tại hoặc đã xảy ra lỗi trong hệ thống.</p>
            <a href="<?php echo esc_url( home_url() ); ?>" class="border border-brand-accent text-brand-accent hover:bg-brand-accent hover:text-brand-black text-[11px] uppercase tracking-label font-medium px-8 py-3 transition-colors">
                Quay lại Trang Chủ
            </a>
        </div>
    </div>
    <?php
    get_footer();
    exit;
}

$tour_id = (int) get_post_meta( $booking_id, 'booking_tour_id', true );
$tour_title = $tour_id ? get_the_title( $tour_id ) : 'Vé tham quan HaLong Rum';
$date = get_post_meta( $booking_id, 'booking_date', true );
$time = get_post_meta( $booking_id, 'booking_time', true );
$adults = (int) get_post_meta( $booking_id, 'booking_adults', true );
$children = (int) get_post_meta( $booking_id, 'booking_children', true );
$total_guests = (int) get_post_meta( $booking_id, 'booking_total_guests', true );
$total_price = (float) get_post_meta( $booking_id, 'booking_total_price', true );
$status = get_post_meta( $booking_id, 'booking_status', true );
$expired_at = get_post_meta( $booking_id, 'booking_expired_at', true );
$customer_name = get_post_meta( $booking_id, 'customer_name', true );
$customer_email = get_post_meta( $booking_id, 'customer_email', true );

// Format price function
function halong_pay_format_price( $amount ) {
    return number_format( $amount, 0, ',', '.' ) . ' ₫';
}

// Format time function
function halong_pay_format_time( $time_str ) {
    if ( empty( $time_str ) ) return '';
    $time = strtotime( $time_str );
    if ( ! $time ) return $time_str;
    return date( 'h:i A', $time );
}

$show_success = $is_success_param || ( $status === 'confirmed' );
$show_expired = ( $status === 'expired' || $status === 'cancelled' ) && !$is_success_param;

if ( $show_expired ) {
    ?>
    <div class="view-section pt-32 pb-20 text-center min-h-[50vh] flex flex-col items-center justify-center">
        <div class="max-w-md mx-auto px-6">
            <i class="ph ph-x-circle text-red-500 text-6xl mb-4 block"></i>
            <h1 class="font-serif text-brand-cream text-2xl mb-4">Đơn đặt tour đã hết hạn hoặc bị hủy</h1>
            <p class="text-brand-body text-sm font-light mb-8">Đơn đặt tour số <strong><?php echo esc_html( $booking_code ); ?></strong> đã quá hạn thanh toán 24h hoặc đã bị hủy trên hệ thống.</p>
            <a href="<?php echo esc_url( home_url() ); ?>" class="border border-brand-accent text-brand-accent hover:bg-brand-accent hover:text-brand-black text-[11px] uppercase tracking-label font-medium px-8 py-3 transition-colors">
                Quay lại Trang Chủ
            </a>
        </div>
    </div>
    <?php
} elseif ( $show_success ) {
    ?>
    <div id="view-success" class="view-section active pt-32 pb-20">
        <div class="max-w-3xl mx-auto px-6">
            <!-- Success header -->
            <div class="text-center mb-16">
                <div class="w-20 h-20 mx-auto bg-brand-green/20 rounded-full flex items-center justify-center mb-6">
                    <i class="ph ph-check-circle text-5xl text-brand-accent"></i>
                </div>
                <h1 class="font-serif text-brand-cream text-3xl md:text-5xl font-light tracking-wide mb-4">Đặt Tour Thành Công!</h1>
                <p class="text-[15px] font-light text-brand-body max-w-xl mx-auto">
                    Cảm ơn <span class="text-brand-accent font-medium"><?php echo esc_html( $customer_name ); ?></span> đã chọn HaLong Rum.<br>
                    Mã đặt chỗ của bạn là <strong class="text-brand-cream"><?php echo esc_html( $booking_code ); ?></strong>.<br>
                    Chúng tôi đã gửi thông tin chi tiết qua email.
                </p>
            </div>

            <!-- Booking summary -->
            <div class="bg-brand-section border border-brand-green/20 p-8 mb-8 space-y-4">
                <h2 class="font-sans text-brand-cream text-lg uppercase tracking-h2 mb-6 border-b border-brand-green/20 pb-4">Chi tiết đặt chỗ</h2>
                <div class="grid grid-cols-2 gap-4 text-[14px] font-light">
                    <div class="text-brand-body">Ngày tham quan</div>
                    <div class="text-brand-cream font-medium text-right"><?php echo esc_html( $date ); ?></div>
                    <div class="text-brand-body">Giờ khởi hành</div>
                    <div class="text-brand-cream font-medium text-right"><?php echo esc_html( halong_pay_format_time( $time ) ); ?></div>
                    <div class="text-brand-body">Số khách</div>
                    <div class="text-brand-cream font-medium text-right">
                        <?php 
                        echo $adults . ' người lớn';
                        if ( $children > 0 ) {
                            echo ', ' . $children . ' trẻ em';
                        }
                        ?>
                    </div>
                    <div class="text-brand-body border-t border-brand-green/20 pt-4">Tổng thanh toán</div>
                    <div class="font-serif text-brand-accent text-2xl text-right border-t border-brand-green/20 pt-4"><?php echo esc_html( halong_pay_format_price( $total_price ) ); ?></div>
                </div>
            </div>

            <!-- Email confirmation notice -->
            <div class="bg-brand-green/10 border border-brand-green/20 p-5 rounded mb-8 flex gap-3 items-start text-[13px] font-light">
                <i class="ph ph-envelope text-brand-accent text-xl shrink-0 mt-0.5"></i>
                <div class="text-brand-body">
                    Email xác nhận đã được gửi tới <span class="text-brand-accent font-medium"><?php echo esc_html( $customer_email ); ?></span>.<br>
                    Vui lòng kiểm tra hộp thư (kể cả mục Spam) để xem hướng dẫn tham quan.
                </div>
            </div>

            <!-- What to bring -->
            <div class="space-y-3 mb-12">
                <p class="text-[11px] uppercase tracking-label text-brand-body mb-3">Lưu ý khi đến</p>
                <div class="flex items-start gap-3 text-[13px] font-light text-brand-body">
                    <i class="ph ph-clock text-brand-accent shrink-0 mt-0.5"></i>
                    <span>Vui lòng có mặt <strong class="text-brand-cream">trước 10 phút</strong> so với giờ khởi hành.</span>
                </div>
                <div class="flex items-start gap-3 text-[13px] font-light text-brand-body">
                    <i class="ph ph-identification-card text-brand-accent shrink-0 mt-0.5"></i>
                    <span>Mang theo <strong class="text-brand-cream">CMND/CCCD</strong> để xác nhận độ tuổi (18+).</span>
                </div>
                <div class="flex items-start gap-3 text-[13px] font-light text-brand-body">
                    <i class="ph ph-ticket text-brand-accent shrink-0 mt-0.5"></i>
                    <span>Xuất trình <strong class="text-brand-cream">mã đặt chỗ</strong> hoặc email xác nhận khi vào cửa.</span>
                </div>
            </div>

            <!-- Action buttons -->
            <div class="flex flex-wrap gap-4 justify-center">
                <a href="<?php echo esc_url( home_url() ); ?>" class="border border-brand-accent text-brand-accent hover:bg-brand-accent hover:text-brand-black text-[11px] uppercase tracking-label font-medium px-8 py-3 transition-colors inline-flex items-center gap-2">
                    <i class="ph ph-house"></i> Về Trang Chủ
                </a>
                <button onclick="window.print()" class="border border-brand-body/30 text-brand-body hover:border-brand-body text-[11px] uppercase tracking-label px-8 py-3 transition-colors inline-flex items-center gap-2">
                    <i class="ph ph-printer"></i> In xác nhận
                </button>
            </div>
        </div>
    </div>
    <?php
} else {
    // Show Payment layout (pending_payment)
    $bank_bin  = get_field( 'halong_bank_bin',     'option' ) ?: '970422';
    $account   = get_field( 'halong_bank_account', 'option' ) ?: '';
    $acct_name = get_field( 'halong_bank_name',    'option' ) ?: 'HALONG RUM';
    $bank_short_name = 'Ngân hàng';
    if ( function_exists( 'halong_get_bank_details_by_bin' ) ) {
        $bank_details = halong_get_bank_details_by_bin( $bank_bin );
        if ( $bank_details ) {
            $bank_short_name = $bank_details['shortName'];
        }
    }
    $bank_account_display = $account ? implode( ' ', str_split( preg_replace( '/\s+/', '', $account ), 4 ) ) : '—';
    
    $payment_desc = class_exists( 'Halong_Booking_CPT' ) 
        ? Halong_Booking_CPT::get_payment_description( $booking_code )
        : $booking_code;

    $qr_url    = sprintf(
        'https://img.vietqr.io/image/%s-%s-compact2.png?amount=%d&addInfo=%s&accountName=%s',
        rawurlencode( $bank_bin ),
        rawurlencode( $account ),
        $total_price,
        rawurlencode( $payment_desc ),
        rawurlencode( $acct_name )
    );

    $expire_iso = '';
    if ( $expired_at ) {
        $expire_iso = date( 'c', strtotime( $expired_at ) );
    }
    ?>
    <div id="view-payment" class="view-section active pt-32 pb-20">
        <div class="max-w-4xl mx-auto px-6">
            <div class="text-center mb-10">
                <span class="block text-brand-accent text-[11px] uppercase tracking-label font-medium mb-2">Bước cuối cùng</span>
                <h1 class="font-serif text-brand-cream text-3xl md:text-4xl font-light tracking-wide mb-4">Thanh Toán Đơn Hàng</h1>
                <p class="text-[14px] font-light text-brand-body">Vui lòng quét mã QR dưới đây bằng App Ngân hàng để hoàn tất.</p>
                <?php if ( $expire_iso ) : ?>
                    <div class="mt-4 inline-flex items-center gap-2 bg-brand-green/10 border border-brand-green/20 px-4 py-2 rounded text-[12px] text-brand-body">
                        <i class="ph ph-timer text-brand-accent"></i>
                        Đơn hết hạn sau: <span id="expireCountdown" data-expire-at="<?php echo esc_attr( $expire_iso ); ?>" class="text-brand-accent font-semibold font-mono ml-1 min-w-[60px] inline-block">--:--:--</span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="bg-brand-section border border-brand-green/30 rounded-lg shadow-2xl overflow-hidden flex flex-col md:flex-row">
                <!-- Left: QR -->
                <div class="w-full md:w-1/2 bg-brand-black p-10 flex flex-col items-center justify-center border-b md:border-b-0 md:border-r border-brand-green/20">
                    <div class="bg-white p-4 rounded-xl mb-6 shadow-lg">
                        <img id="qrCodeImg" src="<?php echo esc_url( $qr_url ); ?>" alt="Mã QR Thanh Toán" class="w-56 h-56 object-contain">
                    </div>
                    <div class="flex items-center gap-2 text-brand-accent">
                        <i class="ph ph-scan text-xl"></i>
                        <span class="text-[12px] uppercase tracking-label font-medium">Quét mã để thanh toán</span>
                    </div>
                </div>

                <!-- Right: Info -->
                <div class="w-full md:w-1/2 p-10 flex flex-col justify-between">
                    <div class="space-y-6">
                        <div>
                            <p class="text-[11px] uppercase tracking-label text-brand-body mb-1">Số tiền thanh toán</p>
                            <p class="font-serif text-brand-accent text-4xl"><?php echo esc_html( halong_pay_format_price( $total_price ) ); ?></p>
                        </div>

                        <div class="space-y-3 pt-4 border-t border-brand-green/20">
                            <div>
                                <p class="text-[11px] uppercase tracking-label text-brand-body mb-1">Mã Đặt Chỗ (Nội dung CK)</p>
                                <div class="bg-[#121A10] p-4 border border-brand-green/30 flex justify-between items-center rounded">
                                    <span class="font-mono text-brand-cream text-xl tracking-widest font-bold"><?php echo esc_html( $payment_desc ); ?></span>
                                    <button type="button" id="copyCodeBtn" data-copy-text="<?php echo esc_attr( $payment_desc ); ?>" class="text-brand-accent hover:text-brand-cream transition-colors" title="Copy mã">
                                        <i class="ph ph-copy text-2xl"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Bank info -->
                        <div class="space-y-2 pt-4 border-t border-brand-green/20 text-[13px] text-brand-body">
                            <p class="text-[11px] uppercase tracking-label text-brand-body mb-2">Thông tin tài khoản</p>
                            <div class="flex justify-between">
                                <span>Ngân hàng</span>
                                <span class="text-brand-cream font-medium text-right"><?php echo esc_html( $bank_short_name ); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span>Số tài khoản</span>
                                <span class="font-mono text-brand-cream font-medium"><?php echo esc_html( $bank_account_display ); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span>Chủ tài khoản</span>
                                <span class="text-brand-cream font-medium text-right"><?php echo esc_html( $acct_name ); ?></span>
                            </div>
                        </div>

                        <div class="bg-brand-green/10 border border-brand-green/20 p-4 rounded text-[12px] text-brand-body font-light">
                            <p>Lưu ý: Mã đặt chỗ của bạn <strong class="text-brand-cream">bắt buộc</strong> phải có trong nội dung chuyển khoản để hệ thống tự động xác nhận.</p>
                        </div>
                    </div>

                    <div class="mt-8">
                        <a href="<?php echo esc_url( add_query_arg( 'success', '1' ) ); ?>" class="w-full bg-brand-accent text-brand-black text-[13px] font-semibold uppercase tracking-h2 py-4 hover:bg-brand-cream transition-all duration-300 flex items-center justify-center gap-2">
                            Tôi Đã Thanh Toán <i class="ph ph-arrow-right text-lg"></i>
                        </a>
                        <p class="text-center text-[11px] text-brand-body mt-4 italic">Sau khi xác nhận, chúng tôi sẽ kiểm tra và gửi email xác nhận trong vòng 15 phút.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Copy button
        const copyBtn = document.getElementById('copyCodeBtn');
        if (copyBtn) {
            copyBtn.onclick = function() {
                const copyText = this.getAttribute('data-copy-text');
                navigator.clipboard.writeText(copyText).then(() => {
                    copyBtn.classList.add('copied');
                    setTimeout(() => copyBtn.classList.remove('copied'), 2000);
                });
            };
        }

        // Countdown countdown
        const expireEl = document.getElementById('expireCountdown');
        if (expireEl) {
            const expireTime = new Date(expireEl.getAttribute('data-expire-at')).getTime();
            const tick = () => {
                const remaining = expireTime - Date.now();
                if (remaining <= 0) {
                    expireEl.textContent = 'Đơn hàng đã hết hạn';
                    return;
                }
                const h = Math.floor(remaining / 3600000);
                const m = Math.floor((remaining % 3600000) / 60000);
                const s = Math.floor((remaining % 60000) / 1000);
                expireEl.textContent = `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
                setTimeout(tick, 1000);
            };
            tick();
        }
    });
    </script>
    <style>
    #copyCodeBtn.copied {
        color: #4ade80 !important;
    }
    </style>
    <?php
}

get_footer();
