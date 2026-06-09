<?php
/**
 * Template for displaying a single Tour with the Booking SPA.
 * Nav/header and footer are handled by Elementor Pro Theme Builder.
 *
 * @package halong-tour
 */

if (!defined('ABSPATH')) {
	exit;
}

get_header();

// ─── Tour data ───────────────────────────────────────────────────────────────
$post_id = get_queried_object_id();
$tour_title = get_the_title($post_id);
$adult_price = get_field('halong_adult_price', $post_id) ?: 450000;
$child_price = get_field('halong_child_price', $post_id) ?: 225000;
$duration = get_field('halong_tour_duration', $post_id) ?: '60 - 90 phút';
$languages = get_field('halong_tour_languages', $post_id) ?: 'Việt, Anh';
$max_guests = (int) (get_field('halong_tour_max_guests', $post_id) ?: 15);
$hero_image = get_field('halong_hero_image', $post_id);
if (!$hero_image) {
    $hero_image = get_the_post_thumbnail_url($post_id, 'large');
}
if (!$hero_image) {
    $hero_image = 'https://images.unsplash.com/photo-1596484552834-6a58f850e0a1?q=80&w=2070&auto=format&fit=crop';
}
$intro_text = get_field('halong_intro_text', $post_id);
$highlights = get_field('halong_highlights', $post_id);
$itinerary = get_field('halong_itinerary', $post_id);
$notes = get_field('halong_notes', $post_id);
$policies = get_field('halong_policies', $post_id);
$children_enabled = (bool) get_field('halong_enable_children', 'option');
$bank_name = Halong_Theme_Settings::get_option('halong_bank_name', 'CÔNG TY TNHH HALONG RUM');
$bank_account = Halong_Theme_Settings::get_option('halong_bank_account', '');
$bank_account_display = $bank_account ? implode(' ', str_split(preg_replace('/\s+/', '', $bank_account), 4)) : '—';

// ─── Price formatter ─────────────────────────────────────────────────────────
if (!function_exists('halong_format_price')) {
	function halong_format_price($amount)
	{
		return number_format((int) $amount, 0, ',', '.');
	}
}

// ─── Star renderer (uses ph-fill ph-star like original) ──────────────────────
if (!function_exists('halong_render_stars')) {
	function halong_render_stars($rating)
	{
		$rating = max(1, min(5, (int) $rating));
		$html = '<div class="flex gap-0.5 text-brand-accent text-[13px]">';
		for ($i = 1; $i <= 5; $i++) {
			$html .= $i <= $rating
				? '<i class="ph-fill ph-star"></i>'
				: '<i class="ph ph-star" style="opacity:0.3;"></i>';
		}
		$html .= '</div>';
		return $html;
	}
}

// ─── Reviews queries ─────────────────────────────────────────────────────────
$reviews_query = new WP_Query(array(
	'post_type' => 'tour_review',
	'posts_per_page' => 3,
	'orderby' => 'date',
	'order' => 'DESC',
	'meta_query' => array(array('key' => 'review_tour', 'value' => $post_id)),
));

$all_reviews_query = new WP_Query(array(
	'post_type' => 'tour_review',
	'posts_per_page' => 20,
	'orderby' => 'date',
	'order' => 'DESC',
	'meta_query' => array(array('key' => 'review_tour', 'value' => $post_id)),
));

// ─── Time slots ──────────────────────────────────────────────────────────────
$time_slots = Halong_Tour_CPT::get_time_slots($post_id);
?>

<!-- ═══════════════════════════════════════════════════════════════
	 AGE VERIFICATION MODAL
	 ═══════════════════════════════════════════════════════════════ -->
<div id="ageVerifyOverlay"
	class="hidden fixed inset-0 z-[200] bg-brand-black/95 backdrop-blur-sm flex items-center justify-center p-6">
	<div class="bg-brand-section border border-brand-green/30 p-10 max-w-sm w-full text-center shadow-2xl">
		<div class="w-16 h-16 border border-brand-accent/40 flex items-center justify-center mx-auto mb-6">
			<span class="font-serif text-brand-accent text-xl tracking-widest">HLR</span>
		</div>
		<h2 class="font-serif text-brand-cream text-2xl font-light mb-3">Xác nhận Độ tuổi</h2>
		<p class="text-[13px] text-brand-body font-light leading-[1.8] mb-8">
			Theo quy định pháp luật Việt Nam, dịch vụ tại HaLong Rum chỉ dành cho người từ <strong
				class="text-brand-cream">đủ 18 tuổi</strong> trở lên.
		</p>
		<div class="space-y-3">
			<button onclick="hlrConfirmAge()"
				class="w-full bg-brand-accent text-brand-black text-[12px] font-semibold uppercase tracking-h2 py-3 hover:bg-brand-cream transition-all">
				Tôi đủ 18 tuổi — Xác nhận
			</button>
			<button onclick="hlrRejectAge()"
				class="w-full border border-brand-body/30 text-brand-body text-[11px] uppercase tracking-label py-3 hover:border-brand-body transition-all">
				Tôi chưa đủ tuổi
			</button>
		</div>
		<p class="text-[10px] text-brand-body/50 uppercase tracking-label mt-6">Uống rượu có trách nhiệm</p>
	</div>
</div>

<!-- ═══════════════════════════════════════════════════════════════
	 VIEW 1 — TOUR DETAIL
	 ═══════════════════════════════════════════════════════════════ -->
<div id="view-tour-detail" class="view-section active">

	<!-- HERO -->
	<section class="relative h-[60vh] min-h-[500px] w-full flex items-end pb-16">
		<div class="absolute inset-0 z-0">
			<img src="<?php echo esc_url($hero_image); ?>" alt="<?php echo esc_attr($tour_title); ?>"
				class="w-full h-full object-cover object-center" style="filter:brightness(0.45);" />
			<div class="absolute inset-0 bg-gradient-to-t from-brand-black via-brand-black/60 to-transparent"></div>
		</div>
		<div class="relative z-10 max-w-7xl mx-auto px-6 w-full mt-20">
			<div class="max-w-3xl">
				<span class="block text-brand-accent text-[11px] uppercase tracking-label font-medium mb-4">HaLong Rum
					Distillery</span>
				<h1
					class="font-serif text-brand-cream text-4xl md:text-5xl lg:text-6xl font-light tracking-wide leading-tight mb-6">
					<?php echo esc_html($tour_title); ?>
				</h1>
				<div class="flex flex-wrap items-center gap-6 text-[14px] font-light">
					<div class="flex items-center gap-2"><i
							class="ph ph-clock text-brand-accent text-xl"></i><span><?php echo esc_html($duration); ?></span>
					</div>
					<div class="flex items-center gap-2"><i class="ph ph-users text-brand-accent text-xl"></i><span>Tối
							đa: <?php echo esc_html($max_guests); ?> người</span></div>
					<div class="flex items-center gap-2"><i
							class="ph ph-translate text-brand-accent text-xl"></i><span><?php echo esc_html($languages); ?></span>
					</div>
					<div class="flex items-center gap-2"><i class="ph ph-map-pin text-brand-accent text-xl"></i><span>Hạ
							Long, Quảng Ninh</span></div>
				</div>
			</div>
		</div>
	</section>

	<!-- CONTENT + BOOKING WIDGET -->
	<section class="max-w-7xl mx-auto px-6 py-16">
		<div class="grid grid-cols-1 lg:grid-cols-12 gap-12 relative">

			<!-- LEFT COLUMN: Accordions -->
			<div class="lg:col-span-8 space-y-6">

				<!-- 1. Giới thiệu Tour -->
				<div class="border-b border-brand-section pb-4">
					<button type="button" class="w-full flex justify-between items-center py-4 group cursor-pointer"
						onclick="toggleAccordion('acc-intro', this)">
						<h2
							class="font-sans text-brand-cream text-xl uppercase tracking-h2 group-hover:text-brand-accent transition-colors">
							Giới thiệu Tour
						</h2>
						<i
							class="ph ph-caret-up text-brand-accent text-xl transition-transform duration-300 transform rotate-0"></i>
					</button>
					<div id="acc-intro" class="accordion-content opacity-100" style="max-height:500px;">
						<div class="pb-6 space-y-4">
							<?php if ($intro_text): ?>
								<div class="text-[14px] leading-[1.8] font-light hlr-wysiwyg">
									<?php echo wp_kses_post($intro_text); ?></div>
							<?php else: ?>
								<p class="text-[14px] leading-[1.8] font-light">Bước vào không gian tĩnh lặng mang đậm dấu
									ấn di sản, nơi nghệ thuật chưng cất thủ công hòa quyện cùng thiên nhiên vùng đất Hạ
									Long. Chuyến tham quan nhà máy HaLong Rum không chỉ là một hành trình tìm hiểu quy trình
									sản xuất, mà là một trải nghiệm đánh thức các giác quan.</p>
								<p class="text-[14px] leading-[1.8] font-light">Từ những thùng gỗ sồi ủ mình trong bóng tối,
									đến giọt rượu Amber Gold lấp lánh phản chiếu ánh sáng. Khách hàng sẽ được tận mắt chứng
									kiến sự tỉ mỉ trong từng khâu lên men, chưng cất, và cuối cùng là phần thử nếm tại quầy
									bar riêng tư của chúng tôi.</p>
							<?php endif; ?>
						</div>
					</div>
				</div>

				<!-- 2. Điểm nhấn Trải nghiệm -->
				<div class="border-b border-brand-section pb-4">
					<button type="button" class="w-full flex justify-between items-center py-4 group cursor-pointer"
						onclick="toggleAccordion('acc-highlights', this)">
						<h2
							class="font-sans text-brand-cream text-xl uppercase tracking-h2 group-hover:text-brand-accent transition-colors">
							Điểm nhấn Trải nghiệm
						</h2>
						<i
							class="ph ph-caret-up text-brand-accent text-xl transition-transform duration-300 transform rotate-0"></i>
					</button>
					<div id="acc-highlights" class="accordion-content opacity-100" style="max-height:500px;">
						<div class="pb-6">
							<ul class="grid grid-cols-1 md:grid-cols-2 gap-4">
								<?php if ($highlights && is_array($highlights)): ?>
									<?php foreach ($highlights as $hl): ?>
										<?php if (!empty($hl['highlight_text'])): ?>
											<li class="flex items-start gap-3">
												<i class="ph ph-check text-brand-accent text-lg mt-1"></i>
												<span
													class="text-[14px] leading-[1.8]"><?php echo esc_html($hl['highlight_text']); ?></span>
											</li>
										<?php endif; ?>
									<?php endforeach; ?>
								<?php else: ?>
									<li class="flex items-start gap-3"><i
											class="ph ph-check text-brand-accent text-lg mt-1"></i><span
											class="text-[14px] leading-[1.8]">Tham quan khu vực ủ rượu và nồi chưng cất
											đồng.</span></li>
									<li class="flex items-start gap-3"><i
											class="ph ph-check text-brand-accent text-lg mt-1"></i><span
											class="text-[14px] leading-[1.8]">Trò chuyện cùng Master Distiller của HaLong
											Rum.</span></li>
									<li class="flex items-start gap-3"><i
											class="ph ph-check text-brand-accent text-lg mt-1"></i><span
											class="text-[14px] leading-[1.8]">Nếm thử 3 dòng sản phẩm Premium độc
											quyền.</span></li>
									<li class="flex items-start gap-3"><i
											class="ph ph-check text-brand-accent text-lg mt-1"></i><span
											class="text-[14px] leading-[1.8]">Quà tặng: 01 ly Tasting khắc logo HaLong
											Rum.</span></li>
								<?php endif; ?>
							</ul>
						</div>
					</div>
				</div>

				<!-- 3. Lịch trình dự kiến -->
				<div class="border-b border-brand-section pb-4">
					<button type="button" class="w-full flex justify-between items-center py-4 group cursor-pointer"
						onclick="toggleAccordion('acc-itinerary', this)">
						<h2
							class="font-sans text-brand-cream text-xl uppercase tracking-h2 group-hover:text-brand-accent transition-colors">
							Lịch trình dự kiến
						</h2>
						<i
							class="ph ph-caret-up text-brand-accent text-xl transition-transform duration-300 transform rotate-0"></i>
					</button>
					<div id="acc-itinerary" class="accordion-content opacity-100" style="max-height:800px;">
						<div class="pb-6 pt-4">
							<div
								class="space-y-8 relative before:absolute before:inset-0 before:ml-2 before:-translate-x-px md:before:mx-auto md:before:translate-x-0 before:h-full before:w-0.5 before:bg-gradient-to-b before:from-brand-accent before:via-brand-section before:to-transparent">
								<?php if ($itinerary && is_array($itinerary)): ?>
									<?php foreach ($itinerary as $i => $step): ?>
										<div
											class="relative flex items-center justify-between md:justify-normal <?php echo $i % 2 === 0 ? 'md:odd:flex-row-reverse' : ''; ?> group is-active">
											<div
												class="flex items-center justify-center w-5 h-5 rounded-full bg-brand-black border-2 border-brand-accent shadow shrink-0 md:order-1 z-10">
											</div>
											<div
												class="w-[calc(100%-2.5rem)] md:w-[calc(50%-1.25rem)] p-4 rounded bg-brand-section border border-transparent hover:border-brand-green transition-all">
												<?php if (!empty($step['itinerary_duration'])): ?>
													<span
														class="font-sans text-brand-accent text-[11px] uppercase tracking-label font-medium mb-1 block"><?php echo esc_html($step['itinerary_duration']); ?></span>
												<?php endif; ?>
												<?php if (!empty($step['itinerary_title'])): ?>
													<h3 class="font-serif text-brand-cream text-lg mb-2">
														<?php echo esc_html($step['itinerary_title']); ?></h3>
												<?php endif; ?>
												<?php if (!empty($step['itinerary_desc'])): ?>
													<p class="text-[14px] leading-[1.8] font-light text-brand-body/80">
														<?php echo esc_html($step['itinerary_desc']); ?></p>
												<?php endif; ?>
											</div>
										</div>
									<?php endforeach; ?>
								<?php else: ?>
									<div class="relative flex items-center justify-between group is-active">
										<div
											class="flex items-center justify-center w-5 h-5 rounded-full bg-brand-black border-2 border-brand-accent shadow shrink-0 md:order-1 z-10">
										</div>
										<div
											class="w-[calc(100%-2.5rem)] md:w-[calc(50%-1.25rem)] p-4 rounded bg-brand-section border border-transparent hover:border-brand-green transition-all">
											<span
												class="font-sans text-brand-accent text-[11px] uppercase tracking-label font-medium mb-1 block">15
												Phút đầu</span>
											<h3 class="font-serif text-brand-cream text-lg mb-2">Đón khách & Câu chuyện Di
												sản</h3>
											<p class="text-[14px] leading-[1.8] font-light text-brand-body/80">Thưởng thức
												welcome drink, lắng nghe câu chuyện sáng lập thương hiệu và khát vọng đưa
												hương vị địa phương ra thế giới.</p>
										</div>
									</div>
									<div class="relative flex items-center justify-between group is-active">
										<div
											class="flex items-center justify-center w-5 h-5 rounded-full bg-brand-black border-2 border-brand-section shadow shrink-0 md:order-1 z-10">
										</div>
										<div
											class="w-[calc(100%-2.5rem)] md:w-[calc(50%-1.25rem)] p-4 rounded bg-brand-section border border-transparent hover:border-brand-green transition-all">
											<span
												class="font-sans text-brand-accent text-[11px] uppercase tracking-label font-medium mb-1 block">30
												Phút tiếp theo</span>
											<h3 class="font-serif text-brand-cream text-lg mb-2">Nghệ thuật Chưng cất</h3>
											<p class="text-[14px] leading-[1.8] font-light text-brand-body/80">Di chuyển qua
												khu vực lên men và phòng ủ sồi. Cảm nhận sự tĩnh lặng và mùi hương gỗ thoang
												thoảng trong không gian.</p>
										</div>
									</div>
									<div class="relative flex items-center justify-between group is-active">
										<div
											class="flex items-center justify-center w-5 h-5 rounded-full bg-brand-black border-2 border-brand-section shadow shrink-0 md:order-1 z-10">
										</div>
										<div
											class="w-[calc(100%-2.5rem)] md:w-[calc(50%-1.25rem)] p-4 rounded bg-brand-section border border-transparent hover:border-brand-green transition-all">
											<span
												class="font-sans text-brand-accent text-[11px] uppercase tracking-label font-medium mb-1 block">45
												Phút cuối</span>
											<h3 class="font-serif text-brand-cream text-lg mb-2">Tasting & Cửa hàng</h3>
											<p class="text-[14px] leading-[1.8] font-light text-brand-body/80">Trải nghiệm
												tasting dưới sự hướng dẫn của chuyên gia. Mua sắm các phiên bản giới hạn tại
												cửa hàng nội khu.</p>
										</div>
									</div>
								<?php endif; ?>
							</div>
						</div>
					</div>
				</div>

				<!-- 4. Đánh giá khách hàng -->
				<div class="border-b border-brand-section pb-4">
					<button type="button" class="w-full flex justify-between items-center py-4 group cursor-pointer"
						onclick="toggleAccordion('acc-reviews', this)">
						<div class="flex items-center gap-4">
							<h2
								class="font-sans text-brand-cream text-xl uppercase tracking-h2 group-hover:text-brand-accent transition-colors">
								Đánh giá khách hàng
							</h2>
							<?php if ($reviews_query->have_posts()): ?>
								<div class="hidden md:flex items-center gap-1.5 bg-brand-green/20 px-3 py-1 rounded">
									<i class="ph-fill ph-star text-brand-accent text-sm"></i>
									<span
										class="text-brand-accent text-[13px] font-medium tracking-wide"><?php echo esc_html($reviews_query->found_posts); ?>
										đánh giá</span>
								</div>
							<?php endif; ?>
						</div>
						<i
							class="ph ph-caret-up text-brand-accent text-xl transition-transform duration-300 transform rotate-0"></i>
					</button>
					<div id="acc-reviews" class="accordion-content opacity-100" style="max-height:800px;">
						<div class="pb-6 pt-2">
							<?php if ($reviews_query->have_posts()): ?>
								<div class="space-y-4">
									<?php while ($reviews_query->have_posts()):
										$reviews_query->the_post(); ?>
										<?php
										$rev_rating = (int) get_post_meta(get_the_ID(), 'review_rating', true) ?: 5;
										$rev_name = get_post_meta(get_the_ID(), 'review_reviewer_name', true) ?: 'Khách hàng';
										$rev_content = get_post_meta(get_the_ID(), 'review_content', true);
										$rev_date = get_post_meta(get_the_ID(), 'review_join_date', true);
										$rev_verified = get_post_meta(get_the_ID(), 'review_verified', true);
										?>
										<div
											class="bg-brand-black/50 p-5 rounded border border-brand-green/20 hover:border-brand-green/50 transition-colors">
											<div class="flex justify-between items-start mb-3">
												<div>
													<div class="flex items-center gap-2">
														<h4 class="text-brand-cream font-medium text-[14px]">
															<?php echo esc_html($rev_name); ?></h4>
														<?php if ($rev_verified): ?>
															<span
																class="flex items-center gap-1 text-[9px] text-brand-accent border border-brand-accent/50 px-1.5 py-0.5 rounded uppercase tracking-wider">
																<i class="ph-fill ph-seal-check"></i> Đã xác thực
															</span>
														<?php endif; ?>
													</div>
													<?php if ($rev_date): ?><span class="text-brand-body/60 text-[11px]">Tham
															gia ngày: <?php echo esc_html($rev_date); ?></span><?php endif; ?>
												</div>
												<?php echo halong_render_stars($rev_rating); // phpcs:ignore WordPress.Security.EscapeOutput ?>
											</div>
											<?php if ($rev_content): ?>
												<p class="text-[13px] text-brand-body font-light leading-[1.7]">
													"<?php echo esc_html($rev_content); ?>"</p>
											<?php endif; ?>
										</div>
									<?php endwhile; ?>
									<?php wp_reset_postdata(); ?>
								</div>
							<?php else: ?>
								<div class="py-8 text-center">
									<i class="ph ph-chat-circle text-brand-accent/20 text-5xl block mb-3"></i>
									<p class="text-[13px] text-brand-body font-light">Chưa có đánh giá nào cho tour này.</p>
								</div>
							<?php endif; ?>

							<div class="mt-6 text-center">
								<button onclick="openReviewsModal()"
									class="text-brand-accent text-[12px] uppercase tracking-label font-medium hover:text-brand-cream transition-colors flex items-center justify-center gap-2 mx-auto">
									Xem thêm đánh giá <i class="ph ph-arrow-right"></i>
								</button>
							</div>
						</div>
					</div>
				</div>

				<!-- 5. Lưu ý quan trọng -->
				<div class="border-b border-brand-section pb-4">
					<button type="button" class="w-full flex justify-between items-center py-4 group cursor-pointer"
						onclick="toggleAccordion('acc-notes', this)">
						<h2
							class="font-sans text-brand-cream text-xl uppercase tracking-h2 group-hover:text-brand-accent transition-colors">
							Lưu ý quan trọng
						</h2>
						<i
							class="ph ph-caret-up text-brand-accent text-xl transition-transform duration-300 transform rotate-0"></i>
					</button>
					<div id="acc-notes" class="accordion-content opacity-100" style="max-height:500px;">
						<div class="pb-6">
							<ul class="space-y-4 text-[14px] leading-[1.8] font-light">
								<?php if ($notes && is_array($notes)): ?>
									<?php foreach ($notes as $note): ?>
										<?php if (!empty($note['note_title']) || !empty($note['note_content'])): ?>
											<li class="flex items-start gap-3">
												<i class="ph ph-info text-brand-accent text-xl mt-0.5 shrink-0"></i>
												<span>
													<?php if (!empty($note['note_title'])): ?><strong
															class="text-brand-cream font-medium"><?php echo esc_html($note['note_title']); ?>:</strong>
													<?php endif; ?>
													<?php echo esc_html($note['note_content']); ?>
												</span>
											</li>
										<?php endif; ?>
									<?php endforeach; ?>
								<?php else: ?>
									<li class="flex items-start gap-3"><i
											class="ph ph-info text-brand-accent text-xl mt-0.5 shrink-0"></i><span><strong
												class="text-brand-cream font-medium">Trang phục:</strong> Vui lòng mặc trang
											phục lịch sự và mang giày bệt, kín mũi để đảm bảo an toàn tối đa khi di chuyển
											trong khu vực sản xuất.</span></li>
									<li class="flex items-start gap-3"><i
											class="ph ph-info text-brand-accent text-xl mt-0.5 shrink-0"></i><span><strong
												class="text-brand-cream font-medium">Thời gian:</strong> Quý khách vui lòng
											có mặt tại sảnh lễ tân trước giờ bắt đầu tour từ 10 - 15 phút để làm thủ tục và
											nhận Welcome Drink.</span></li>
									<li class="flex items-start gap-3"><i
											class="ph ph-info text-brand-accent text-xl mt-0.5 shrink-0"></i><span><strong
												class="text-brand-cream font-medium">Độ tuổi:</strong> Khách dưới 18 tuổi có
											thể tham gia tour nhưng không được phép vào khu vực Tasting.</span></li>
									<li class="flex items-start gap-3"><i
											class="ph ph-info text-brand-accent text-xl mt-0.5 shrink-0"></i><span><strong
												class="text-brand-cream font-medium">Quy định chung:</strong> Không mang
											theo đồ ăn, thức uống bên ngoài và không mang theo thú cưng vào khuôn viên nhà
											máy.</span></li>
								<?php endif; ?>
							</ul>
						</div>
					</div>
				</div>

				<!-- 6. Chính sách & Hủy vé -->
				<div class="border-b border-brand-section pb-4">
					<button type="button" class="w-full flex justify-between items-center py-4 group cursor-pointer"
						onclick="toggleAccordion('acc-policies', this)">
						<h2
							class="font-sans text-brand-cream text-xl uppercase tracking-h2 group-hover:text-brand-accent transition-colors">
							Chính sách & Hủy vé
						</h2>
						<i
							class="ph ph-caret-up text-brand-accent text-xl transition-transform duration-300 transform rotate-0"></i>
					</button>
					<div id="acc-policies" class="accordion-content opacity-100" style="max-height:500px;">
						<div class="pb-6">
							<ul class="space-y-4 text-[14px] leading-[1.8] font-light">
								<?php if ($policies && is_array($policies)): ?>
									<?php foreach ($policies as $policy): ?>
										<?php if (!empty($policy['policy_title']) || !empty($policy['policy_content'])): ?>
											<li class="flex items-start gap-3">
												<i class="ph ph-shield-check text-brand-accent text-xl mt-0.5 shrink-0"></i>
												<span>
													<?php if (!empty($policy['policy_title'])): ?><strong
															class="text-brand-cream font-medium"><?php echo esc_html($policy['policy_title']); ?>:</strong>
													<?php endif; ?>
													<?php echo esc_html($policy['policy_content']); ?>
												</span>
											</li>
										<?php endif; ?>
									<?php endforeach; ?>
								<?php else: ?>
									<li class="flex items-start gap-3"><i
											class="ph ph-shield-check text-brand-accent text-xl mt-0.5 shrink-0"></i><span><strong
												class="text-brand-cream font-medium">Chính sách hủy vé:</strong> Hoàn tiền
											100% nếu quý khách thông báo hủy trước 24 giờ so với giờ bắt đầu tour. Các
											trường hợp hủy trong vòng 24 giờ sẽ không được hỗ trợ hoàn tiền.</span></li>
									<li class="flex items-start gap-3"><i
											class="ph ph-shield-check text-brand-accent text-xl mt-0.5 shrink-0"></i><span><strong
												class="text-brand-cream font-medium">Dời ngày tham quan:</strong> Hỗ trợ
											thay đổi ngày hoặc khung giờ 01 lần (miễn phí) với điều kiện báo trước tối thiểu
											12 giờ.</span></li>
									<li class="flex items-start gap-3"><i
											class="ph ph-shield-check text-brand-accent text-xl mt-0.5 shrink-0"></i><span><strong
												class="text-brand-cream font-medium">Quyền từ chối phục vụ:</strong> HaLong
											Rum có quyền từ chối phục vụ những khách có dấu hiệu say xỉn hoặc không tuân thủ
											quy định an toàn.</span></li>
								<?php endif; ?>
							</ul>
						</div>
					</div>
				</div>

			</div><!-- /left col -->

			<!-- RIGHT COLUMN: Sticky Booking Widget -->
			<div class="lg:col-span-4">
				<div class="sticky top-28 bg-brand-section p-8 border border-brand-green/30 shadow-2xl">

					<!-- Price header -->
					<div class="mb-6 border-b border-brand-green/20 pb-6">
						<span class="block text-brand-body text-[14px] mb-1">Giá từ</span>
						<div class="flex items-baseline gap-2">
							<span
								class="font-serif text-brand-accent text-4xl"><?php echo esc_html(halong_format_price($adult_price)); ?></span>
							<span class="text-brand-cream">VND / khách</span>
						</div>
					</div>

					<?php if (empty($time_slots)): ?>
						<!-- Admin notice: no slots configured -->
						<div
							class="bg-yellow-500/10 border border-yellow-500/30 p-3 rounded mb-5 flex gap-2 items-start text-[12px]">
							<i class="ph ph-warning text-yellow-400 text-base mt-0.5 shrink-0"></i>
							<span class="text-yellow-300 leading-relaxed">
								Chưa có khung giờ nào được cấu hình.
								<?php if (current_user_can('manage_options')): ?>
									<a href="<?php echo esc_url(get_edit_post_link($post_id)); ?>" class="underline">→ Thêm
										khung giờ</a>
								<?php endif; ?>
							</span>
						</div>
					<?php endif; ?>

					<div id="bookingForm" class="space-y-6">

						<!-- 1. Calendar -->
						<div>
							<label class="block text-[11px] font-medium uppercase tracking-label text-brand-cream mb-3">
								1. Chọn ngày tham quan
							</label>
							<div
								class="flex items-center justify-between bg-brand-black p-3 border border-brand-green/30 rounded-t">
								<button type="button" onclick="changeMonth(-1)"
									class="p-1 hover:text-brand-accent text-brand-body transition-colors">
									<i class="ph ph-caret-left text-lg"></i>
								</button>
								<span id="calendarMonthYear"
									class="text-[12px] font-medium uppercase tracking-label text-brand-cream"></span>
								<button type="button" onclick="changeMonth(1)"
									class="p-1 hover:text-brand-accent text-brand-body transition-colors">
									<i class="ph ph-caret-right text-lg"></i>
								</button>
							</div>
							<div class="border-x border-b border-brand-green/30 bg-brand-black/50 p-3 rounded-b">
								<div
									class="grid grid-cols-7 gap-1 text-center text-[10px] uppercase tracking-label text-brand-body/60 mb-2">
									<?php foreach (array('CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7') as $dl): ?>
										<div><?php echo esc_html($dl); ?></div>
									<?php endforeach; ?>
								</div>
								<div id="calendarDays" class="grid grid-cols-7 gap-1 text-[13px]"></div>
							</div>
						</div>

						<!-- 2. Time slots -->
						<div id="timeSlotWrapper" class="hidden">
							<label class="block text-[11px] font-medium uppercase tracking-label text-brand-cream mb-3">
								2. Khung giờ khả dụng
								<span id="selectedDateDisplay" class="text-brand-accent normal-case italic ml-1"></span>
							</label>
							<div id="timeGrid" class="grid grid-cols-2 gap-3"></div>
						</div>

						<!-- 3. Guests -->
						<div>
							<label class="block text-[11px] font-medium uppercase tracking-label text-brand-cream mb-2">
								3. Số lượng khách
							</label>

							<!-- Adults -->
							<p class="text-[11px] text-brand-body mb-1 uppercase tracking-label">Người lớn</p>
							<div class="flex items-center justify-between input-dark p-2 mb-1">
								<button type="button" onclick="updateAdults(-1)"
									class="w-8 h-8 flex items-center justify-center hover:bg-brand-green/20 text-brand-cream transition-colors">−</button>
								<input type="number" id="adultCount" value="1" min="1"
									max="<?php echo esc_attr($max_guests); ?>"
									class="w-12 text-center bg-transparent border-none text-brand-cream font-medium text-[16px] focus:outline-none"
									readonly>
								<button type="button" onclick="updateAdults(1)"
									class="w-8 h-8 flex items-center justify-center hover:bg-brand-green/20 text-brand-cream transition-colors">+</button>
							</div>
							<p class="text-[11px] text-brand-body text-right mb-3">
								<span
									id="adultPriceDisplay"><?php echo esc_html(halong_format_price($adult_price)); ?>
									₫</span> / người
							</p>

							<!-- Children (conditional) -->
							<div id="childrenCountRow" class="hidden">
								<p class="text-[11px] text-brand-body mb-1 uppercase tracking-label">Trẻ em</p>
								<div class="flex items-center justify-between input-dark p-2 mb-1">
									<button type="button" onclick="updateChildren(-1)"
										class="w-8 h-8 flex items-center justify-center hover:bg-brand-green/20 text-brand-cream transition-colors">−</button>
									<input type="number" id="childCount" value="0" min="0"
										max="<?php echo esc_attr($max_guests); ?>"
										class="w-12 text-center bg-transparent border-none text-brand-cream font-medium text-[16px] focus:outline-none"
										readonly>
									<button type="button" onclick="updateChildren(1)"
										class="w-8 h-8 flex items-center justify-center hover:bg-brand-green/20 text-brand-cream transition-colors">+</button>
								</div>
								<p class="text-[11px] text-brand-body text-right mb-3">
									<span
										id="childPriceDisplay"><?php echo esc_html(halong_format_price($child_price)); ?>
										₫</span> / trẻ em
								</p>
							</div>

							<p class="text-[11px] text-brand-body italic">* Tối đa
								<?php echo esc_html($max_guests); ?> khách / đơn.</p>
						</div>

						<!-- Booking summary -->
						<div id="bookingSummary" class="bg-brand-black/50 p-4 border border-brand-accent/20 hidden">
							<p class="text-[12px] text-brand-cream mb-1 font-medium">Chi tiết vé:</p>
							<p class="text-[13px] text-brand-body font-light">
								<i class="ph ph-calendar-blank inline-block mr-1"></i>
								<span id="summaryDateTime">-</span>
							</p>
							<p class="text-[13px] text-brand-body font-light">
								<i class="ph ph-users inline-block mr-1"></i>
								<span id="summaryGuests">1</span>
							</p>
						</div>

						<!-- Total & Submit -->
						<div class="pt-6 mt-6 border-t border-brand-green/20">
							<div class="flex justify-between items-end mb-6">
								<span class="text-[14px] font-light text-brand-body">Tổng cộng:</span>
								<span id="totalPrice"
									class="font-serif text-brand-cream text-2xl"><?php echo esc_html(halong_format_price($adult_price)); ?>
									₫</span>
							</div>
							<button type="button" id="submitBtn" onclick="goToCheckout()"
								class="w-full bg-brand-accent text-brand-black text-[13px] font-semibold uppercase tracking-h2 py-4 hover:bg-brand-cream transition-all duration-300 opacity-50 cursor-not-allowed"
								<?php echo empty($time_slots) ? 'disabled title="Chưa có khung giờ"' : 'disabled'; ?>>
								Vui lòng chọn Ngày &amp; Giờ
							</button>
						</div>

					</div><!-- /bookingForm -->
				</div>
			</div><!-- /right col -->

		</div>
	</section>

</div><!-- /view-tour-detail -->

<!-- ═══════════════════════════════════════════════════════════════
	 VIEW 2 — CHECKOUT
	 ═══════════════════════════════════════════════════════════════ -->
<div id="view-checkout" class="view-section pt-32 pb-20">
	<div class="max-w-5xl mx-auto px-6">

		<button onclick="goHome()"
			class="flex items-center gap-2 text-[11px] uppercase tracking-label text-brand-accent hover:text-brand-cream transition-colors mb-8">
			<i class="ph ph-arrow-left"></i> Quay lại
		</button>

		<h1 class="font-serif text-brand-cream text-3xl md:text-4xl font-light tracking-wide mb-10">Hoàn tất Đặt Tour
		</h1>

		<div class="grid grid-cols-1 lg:grid-cols-12 gap-12">

			<!-- Left: Checkout Form -->
			<div class="lg:col-span-7 space-y-8">
				<form id="checkoutDataForm" onsubmit="processCheckout(event)" novalidate>

					<!-- Customer info -->
					<div class="bg-brand-section p-8 border border-brand-green/20 mb-8">
						<h2
							class="font-sans text-brand-cream text-lg uppercase tracking-h2 mb-6 border-b border-brand-green/20 pb-4">
							1. Thông tin khách hàng
						</h2>
						<div class="space-y-5">
							<div>
								<label
									class="block text-[11px] font-medium uppercase tracking-label text-brand-cream mb-2">Họ
									và Tên <span class="text-brand-accent">*</span></label>
								<input type="text" id="cusName" name="customer_name" required
									class="w-full input-dark p-3 text-[14px] font-light" placeholder="Vd: Nguyễn Văn A">
							</div>
							<div class="grid grid-cols-1 md:grid-cols-2 gap-5">
								<div>
									<label
										class="block text-[11px] font-medium uppercase tracking-label text-brand-cream mb-2">Số
										điện thoại <span class="text-brand-accent">*</span></label>
									<input type="tel" id="cusPhone" name="customer_phone" required
										class="w-full input-dark p-3 text-[14px] font-light" placeholder="09xxxxxxx">
								</div>
								<div>
									<label
										class="block text-[11px] font-medium uppercase tracking-label text-brand-cream mb-2">Email
										<span class="text-brand-accent">*</span></label>
									<input type="email" id="cusEmail" name="customer_email" required
										class="w-full input-dark p-3 text-[14px] font-light"
										placeholder="email@example.com">
								</div>
							</div>
							<div>
								<label
									class="block text-[11px] font-medium uppercase tracking-label text-brand-cream mb-2">Yêu
									cầu đặc biệt (Dị ứng, hỗ trợ đi lại...)</label>
								<textarea id="cusNote" name="customer_note"
									class="w-full input-dark p-3 text-[14px] font-light h-24 resize-none"
									placeholder="Không bắt buộc"></textarea>
							</div>

							<!-- VAT Request -->
							<div class="mt-8 border-t border-brand-green/20 pt-6">
								<label class="flex items-center gap-3 cursor-pointer group">
									<input type="checkbox" id="reqVatToggle" onchange="toggleVatForm()"
										class="w-4 h-4 accent-brand-accent bg-transparent border-brand-body rounded focus:ring-brand-accent">
									<span
										class="text-[14px] font-medium text-brand-cream group-hover:text-brand-accent transition-colors">Yêu
										cầu xuất hóa đơn VAT (Hóa đơn điện tử)</span>
								</label>
								<div id="vatFormSection"
									class="hidden mt-5 space-y-4 bg-[#121A10] p-5 border border-brand-green/30 rounded">
									<div>
										<label
											class="block text-[11px] font-medium uppercase tracking-label text-brand-cream mb-2">Mã
											số thuế <span class="text-brand-accent">*</span></label>
										<input type="text" id="vatTaxCode" name="vat_tax_code"
											class="w-full input-dark p-3 text-[14px] font-light"
											placeholder="Nhập MST — tự động tra cứu">
										<div id="vatLookupStatus" class="mt-1.5 min-h-[18px]"></div>
									</div>
									<div>
										<label
											class="block text-[11px] font-medium uppercase tracking-label text-brand-cream mb-2">Tên
											công ty <span class="text-brand-accent">*</span></label>
										<input type="text" id="vatCompanyName" name="vat_company_name"
											class="w-full input-dark p-3 text-[14px] font-light"
											placeholder="Tự động điền sau khi tra cứu MST">
									</div>
									<div>
										<label
											class="block text-[11px] font-medium uppercase tracking-label text-brand-cream mb-2">Địa
											chỉ công ty <span class="text-brand-accent">*</span></label>
										<input type="text" id="vatAddress" name="vat_address"
											class="w-full input-dark p-3 text-[14px] font-light"
											placeholder="Tự động điền sau khi tra cứu MST">
									</div>
									<p class="text-[11px] text-brand-body italic">* Thông tin hóa đơn sẽ được gửi kèm
										trong email xác nhận sau khi thanh toán.</p>
								</div>
							</div>
						</div>
					</div>

					<!-- Payment method -->
					<div class="bg-brand-section p-8 border border-brand-green/20">
						<h2
							class="font-sans text-brand-cream text-lg uppercase tracking-h2 mb-6 border-b border-brand-green/20 pb-4">
							2. Thanh toán
						</h2>
						<div class="space-y-4">
							<label
								class="flex items-center gap-4 input-dark p-4 cursor-pointer hover:border-brand-accent transition-colors">
								<input type="radio" name="payment" value="transfer"
									class="w-4 h-4 accent-brand-accent bg-transparent border-brand-body focus:ring-brand-accent"
									checked>
								<div class="flex items-center gap-3">
									<i class="ph ph-qr-code text-2xl text-brand-body"></i>
									<span class="text-[14px] font-light text-brand-cream">Quét mã QR / Chuyển
										khoản</span>
								</div>
							</label>
						</div>
						<div class="mt-8">
							<button type="submit"
								class="w-full bg-brand-accent text-brand-black text-[13px] font-semibold uppercase tracking-h2 py-4 hover:bg-brand-cream transition-all duration-300">
								Xác nhận &amp; Tiến hành Thanh toán
							</button>
							<p class="text-center text-[11px] text-brand-body mt-4 italic">Bằng việc thanh toán, bạn
								đồng ý với Điều khoản dịch vụ của HaLong Rum.</p>
						</div>
					</div>

					<div id="checkoutFormAlert" class="hlr-alert error hidden mt-4"></div>

				</form>
			</div>

			<!-- Right: Order Summary -->
			<div class="lg:col-span-5">
				<div class="sticky top-28 bg-brand-black p-8 border border-brand-accent/30 shadow-2xl">
					<h2 class="font-sans text-brand-cream text-lg uppercase tracking-h2 mb-6">Tóm tắt Đơn hàng</h2>

					<div class="flex gap-4 mb-6 pb-6 border-b border-brand-section">
						<img src="<?php echo esc_url($hero_image); ?>"
							class="w-20 h-20 object-cover border border-brand-green/30 shrink-0"
							alt="<?php echo esc_attr($tour_title); ?>">
						<div>
							<h3 class="font-serif text-brand-cream text-lg"><?php echo esc_html($tour_title); ?></h3>
							<p class="text-[12px] text-brand-body mt-1">Tour tham quan nhà máy HaLong Rum</p>
						</div>
					</div>

					<div class="space-y-4 text-[14px] font-light border-b border-brand-section pb-6 mb-6">
						<div class="flex justify-between text-brand-cream">
							<span>Ngày tham gia:</span>
							<span id="chkDate" class="font-medium">-</span>
						</div>
						<div class="flex justify-between text-brand-cream">
							<span>Khung giờ:</span>
							<span id="chkTime" class="font-medium">-</span>
						</div>
						<div class="flex justify-between text-brand-cream">
							<span>Số lượng khách:</span>
							<span id="chkGuests" class="font-medium">-</span>
						</div>
					</div>

					<div class="space-y-3 mb-6">
						<div class="flex justify-between text-[14px] text-brand-body">
							<span>Tạm tính</span>
							<span id="chkSubtotal">-</span>
						</div>
						<div class="flex justify-between text-[14px] text-brand-body">
							<span>Thuế &amp; Phí (10%)</span>
							<span>Đã bao gồm</span>
						</div>
					</div>

					<div class="flex justify-between items-end">
						<span class="text-[14px] font-light text-brand-body uppercase tracking-label">Tổng thanh
							toán</span>
						<span id="chkTotal" class="font-serif text-brand-accent text-3xl">-</span>
					</div>
				</div>
			</div>

		</div>
	</div>
</div><!-- /view-checkout -->


<!-- ═══════════════════════════════════════════════════════════════
	 REVIEWS MODAL
	 ═══════════════════════════════════════════════════════════════ -->
<div id="reviewsModal"
	class="modal-overlay fixed inset-0 z-[100] bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
	<div
		class="modal-content bg-brand-section border border-brand-green/30 w-full max-w-2xl max-h-[85vh] rounded-lg shadow-2xl flex flex-col">

		<!-- Modal Header -->
		<div class="flex justify-between items-center p-6 border-b border-brand-green/20">
			<div>
				<h3 class="font-serif text-brand-cream text-2xl">Đánh giá khách hàng</h3>
				<div class="flex items-center gap-1.5 mt-2">
					<i class="ph-fill ph-star text-brand-accent text-sm"></i>
					<span class="text-brand-accent text-[14px] font-medium tracking-wide">Đánh giá thực tế</span>
					<span class="text-brand-body text-[13px] font-light ml-1">từ khách đã tham quan</span>
				</div>
			</div>
			<button onclick="closeReviewsModal()" class="text-brand-body hover:text-brand-accent transition-colors p-2">
				<i class="ph ph-x text-2xl"></i>
			</button>
		</div>

		<!-- Modal Body -->
		<div class="p-6 overflow-y-auto space-y-4">

			<?php if ($all_reviews_query->have_posts()): ?>

				<?php while ($all_reviews_query->have_posts()):
					$all_reviews_query->the_post(); ?>
					<?php
					$rev_rating = (int) get_post_meta(get_the_ID(), 'review_rating', true) ?: 5;
					$rev_name = get_post_meta(get_the_ID(), 'review_reviewer_name', true) ?: 'Khách hàng';
					$rev_content = get_post_meta(get_the_ID(), 'review_content', true);
					$rev_date = get_post_meta(get_the_ID(), 'review_join_date', true);
					$rev_verified = get_post_meta(get_the_ID(), 'review_verified', true);
					?>
					<div class="bg-brand-black/40 p-5 rounded border border-brand-green/20">
						<div class="flex justify-between items-start mb-3">
							<div>
								<div class="flex items-center gap-2">
									<h4 class="text-brand-cream font-medium text-[14px]"><?php echo esc_html($rev_name); ?>
									</h4>
									<?php if ($rev_verified): ?>
										<span
											class="flex items-center gap-1 text-[9px] text-brand-accent border border-brand-accent/50 px-1.5 py-0.5 rounded uppercase tracking-wider">
											<i class="ph-fill ph-seal-check"></i> Đã xác thực
										</span>
									<?php endif; ?>
								</div>
								<?php if ($rev_date): ?><span class="text-brand-body/60 text-[11px]">Tham gia ngày:
										<?php echo esc_html($rev_date); ?></span><?php endif; ?>
							</div>
							<?php echo halong_render_stars($rev_rating); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						</div>
						<?php if ($rev_content): ?>
							<p class="text-[13px] text-brand-body font-light leading-[1.7]">
								"<?php echo esc_html($rev_content); ?>"</p>
						<?php endif; ?>
					</div>
				<?php endwhile; ?>
				<?php wp_reset_postdata(); ?>

			<?php else: ?>

				<div class="py-12 text-center">
					<i class="ph ph-chat-circle text-brand-accent/20 text-6xl block mb-4"></i>
					<p class="text-[14px] text-brand-body font-light">Chưa có đánh giá nào cho tour này.</p>
					<p class="text-[12px] text-brand-body/60 mt-2">Hãy là người đầu tiên chia sẻ trải nghiệm của bạn!</p>
				</div>

			<?php endif; ?>

		</div>

		<!-- Modal Footer -->
		<div class="p-6 border-t border-brand-green/20 bg-[#121A10] rounded-b-lg">
			<p class="text-[11px] text-center text-brand-body/70 italic">Hệ thống chỉ cho phép khách hàng đã mua và hoàn
				thành chuyến đi để lại đánh giá thông qua Email xác thực.</p>
		</div>

	</div>
</div><!-- /reviewsModal -->

<!-- ─── Utility CSS ───────────────────────────────────────────────────────────── -->
<style>
	.hlr-wysiwyg p {
		margin: 0 0 12px;
	}

	.hlr-wysiwyg p:last-child {
		margin-bottom: 0;
	}

	.hlr-wysiwyg ul {
		padding-left: 20px;
		list-style: disc;
	}

	.hlr-wysiwyg li {
		margin-bottom: 6px;
	}

	.vat-success-badge {
		display: inline-flex;
		align-items: center;
		gap: 4px;
		color: #4ade80;
		font-size: 11px;
	}

	.vat-loading {
		opacity: 0.6;
	}

	@media print {

		#view-tour-detail,
		#view-checkout,
		#view-payment {
			display: none !important;
		}

		#view-success {
			display: block !important;
		}

		#ageVerifyOverlay,
		#reviewsModal {
			display: none !important;
		}
	}
</style>

<?php get_footer(); ?>