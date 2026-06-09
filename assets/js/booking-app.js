/**
 * HaLong Tour - Booking App JS
 * Requires: BookingConfig (injected via wp_localize_script)
 *
 * BookingConfig = {
 *   ajaxUrl: string,
 *   nonce: string,
 *   price: { adult: number, child: number },
 *   childrenEnabled: bool,
 *   timeSlots: [{ slot_time: string, slot_capacity: number }],
 *   tourId: number,
 *   features: { qr_enabled: bool, email_enabled: bool },
 *   ageVerifyRedirect: string,
 *   tourMaxGuests: number
 * }
 */

(function() {
    'use strict';

    // ===========================
    // AGE VERIFICATION (18+)
    // ===========================
    function initAgeVerification() {
        if (localStorage.getItem('hlr_age_verified') === '1') return;
        const overlay = document.getElementById('ageVerifyOverlay');
        if (overlay) overlay.classList.remove('hidden');
    }

    window.hlrConfirmAge = function() {
        localStorage.setItem('hlr_age_verified', '1');
        const overlay = document.getElementById('ageVerifyOverlay');
        if (overlay) overlay.classList.add('hidden');
    };

    window.hlrRejectAge = function() {
        const redirect = (BookingConfig && BookingConfig.ageVerifyRedirect)
            ? BookingConfig.ageVerifyRedirect
            : 'https://halongrum.com';
        window.location.href = redirect;
    };

    // ===========================
    // VIEW MANAGEMENT (SPA)
    // ===========================
    function showView(viewId) {
        document.querySelectorAll('.view-section').forEach(el => el.classList.remove('active'));
        const target = document.getElementById(viewId);
        if (target) {
            target.classList.add('active');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    }

    window.goHome = function() { showView('view-tour-detail'); };

    // ===========================
    // MODAL: REVIEWS
    // ===========================
    window.openReviewsModal = function() {
        const modal = document.getElementById('reviewsModal');
        if (modal) {
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }
    };

    window.closeReviewsModal = function() {
        const modal = document.getElementById('reviewsModal');
        if (modal) {
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }
    };

    // ===========================
    // GLOBAL STATE
    // ===========================
    const state = {
        selectedDate: null,       // Date object
        selectedDateStr: '',      // "d/m/Y"
        selectedTime: null,       // slot object
        adults: 1,
        children: 0,
        totalPrice: 0,
        bookingCode: '',
        bookingData: {},
        customerData: {},
        isSubmitting: false,
    };

    // ===========================
    // PRICE CALC
    // ===========================
    function getAdultPrice() {
        return (BookingConfig && BookingConfig.price && BookingConfig.price.adult) ? BookingConfig.price.adult : 450000;
    }

    function getChildPrice() {
        return (BookingConfig && BookingConfig.price && BookingConfig.price.child) ? BookingConfig.price.child : 225000;
    }

    function calcTotal() {
        state.totalPrice = (state.adults * getAdultPrice()) + (state.children * getChildPrice());
        return state.totalPrice;
    }

    function formatMoney(amount) {
        return new Intl.NumberFormat('vi-VN').format(amount) + ' ₫';
    }

    // ===========================
    // ACCORDION
    // ===========================
    window.toggleAccordion = function(id, btnEl) {
        const content = document.getElementById(id);
        if (!content) return;
        const icon = btnEl ? btnEl.querySelector('i') : null;
        const isOpen = content.style.maxHeight && content.style.maxHeight !== '0px';
        if (isOpen) {
            content.style.maxHeight = '0px';
            content.style.opacity = '0';
            if (icon) { icon.classList.remove('rotate-0'); icon.classList.add('rotate-180'); }
        } else {
            content.style.maxHeight = content.scrollHeight + 'px';
            content.style.opacity = '1';
            if (icon) { icon.classList.remove('rotate-180'); icon.classList.add('rotate-0'); }
        }
    };

    // ===========================
    // CALENDAR
    // ===========================
    const monthNames = ['Tháng 1','Tháng 2','Tháng 3','Tháng 4','Tháng 5','Tháng 6','Tháng 7','Tháng 8','Tháng 9','Tháng 10','Tháng 11','Tháng 12'];
    let calMonth = new Date().getMonth();
    let calYear = new Date().getFullYear();

    function renderCalendar() {
        const monthYearEl = document.getElementById('calendarMonthYear');
        const daysEl = document.getElementById('calendarDays');
        if (!monthYearEl || !daysEl) return;

        monthYearEl.textContent = monthNames[calMonth] + ' ' + calYear;
        daysEl.innerHTML = '';

        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const firstDay = new Date(calYear, calMonth, 1).getDay();
        const daysInMonth = new Date(calYear, calMonth + 1, 0).getDate();

        for (let i = 0; i < firstDay; i++) {
            daysEl.innerHTML += '<div></div>';
        }

        for (let d = 1; d <= daysInMonth; d++) {
            const cellDate = new Date(calYear, calMonth, d);
            const isToday = cellDate.getTime() === today.getTime();
            const isDisabled = cellDate <= today;
            const isSelected = state.selectedDate && cellDate.getTime() === state.selectedDate.getTime();

            let cls = 'calendar-day';
            if (isSelected) cls += ' selected';
            else if (isToday) cls += ' today disabled';
            else if (isDisabled) cls += ' disabled';

            const click = isDisabled ? '' : `onclick="hlrSelectDate(${d})"`;
            daysEl.innerHTML += `<div class="${cls}" ${click}>${d}</div>`;
        }
    }

    window.changeMonth = function(dir) {
        calMonth += dir;
        if (calMonth < 0) { calMonth = 11; calYear--; }
        else if (calMonth > 11) { calMonth = 0; calYear++; }
        renderCalendar();
    };

    window.hlrSelectDate = function(day) {
        state.selectedDate = new Date(calYear, calMonth, day);
        state.selectedTime = null;
        // Format d/m/Y
        state.selectedDateStr = day + '/' + (calMonth + 1) + '/' + calYear;

        renderCalendar();
        renderTimeSlots();
        updateSummary();
        checkCheckoutReady();

        const wrapper = document.getElementById('timeSlotWrapper');
        if (wrapper) wrapper.classList.remove('hidden');
        const dateDisplay = document.getElementById('selectedDateDisplay');
        if (dateDisplay) dateDisplay.textContent = '(' + state.selectedDateStr + ')';
    };

    // ===========================
    // TIME SLOTS
    // ===========================
    function getTimeSlots() {
        if (BookingConfig && BookingConfig.timeSlots) {
            return BookingConfig.timeSlots;
        }
        return [];
    }

    function renderTimeSlots() {
        const grid = document.getElementById('timeGrid');
        if (!grid) return;
        grid.innerHTML = '';
        getTimeSlots().forEach(slot => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'time-slot';
            btn.textContent = formatTime(slot.slot_time);
            btn.dataset.slotTime = slot.slot_time;
            btn.dataset.slotCapacity = slot.slot_capacity;
            btn.onclick = function() { hlrSelectTime(slot, this); };
            grid.appendChild(btn);
        });
    }

    window.hlrSelectTime = function(slot, btnEl) {
        state.selectedTime = slot;
        document.querySelectorAll('.time-slot').forEach(b => b.classList.remove('selected'));
        if (btnEl) btnEl.classList.add('selected');
        updateSummary();
        checkCheckoutReady();
    };

    // ===========================
    // GUEST COUNTER
    // ===========================
    window.updateAdults = function(delta) {
        const max = (BookingConfig && BookingConfig.tourMaxGuests) ? BookingConfig.tourMaxGuests : 15;
        const newVal = state.adults + delta;
        if (newVal >= 1 && (newVal + state.children) <= max) {
            state.adults = newVal;
            const el = document.getElementById('adultCount');
            if (el) el.value = state.adults;
            updateSummary();
        }
    };

    window.updateChildren = function(delta) {
        const max = (BookingConfig && BookingConfig.tourMaxGuests) ? BookingConfig.tourMaxGuests : 15;
        const newVal = state.children + delta;
        if (newVal >= 0 && (state.adults + newVal) <= max) {
            state.children = newVal;
            const el = document.getElementById('childCount');
            if (el) el.value = state.children;
            updateSummary();
        }
    };

    // Legacy single-counter fallback
    window.updateGuest = function(delta) { window.updateAdults(delta); };

    function updateSummary() {
        calcTotal();
        const totalEl = document.getElementById('totalPrice');
        if (totalEl) totalEl.textContent = formatMoney(state.totalPrice);

        const adultPriceEl = document.getElementById('adultPriceDisplay');
        if (adultPriceEl) adultPriceEl.textContent = formatMoney(getAdultPrice());
        const childPriceEl = document.getElementById('childPriceDisplay');
        if (childPriceEl) childPriceEl.textContent = formatMoney(getChildPrice());

        if (state.selectedDate && state.selectedTime) {
            const summary = document.getElementById('bookingSummary');
            if (summary) summary.classList.remove('hidden');
            const dtEl = document.getElementById('summaryDateTime');
            if (dtEl) dtEl.textContent = formatTime(state.selectedTime.slot_time) + ' - ' + state.selectedDateStr;
            const gEl = document.getElementById('summaryGuests');
            if (gEl) gEl.textContent = state.adults + (state.children > 0 ? (' người lớn, ' + state.children + ' trẻ em') : ' khách');
        }
    }

    function checkCheckoutReady() {
        const btn = document.getElementById('submitBtn');
        if (!btn) return;
        if (state.selectedDate && state.selectedTime) {
            btn.removeAttribute('disabled');
            btn.classList.remove('opacity-50', 'cursor-not-allowed');
            btn.textContent = 'Đặt ngay';
        } else {
            btn.setAttribute('disabled', 'true');
            btn.classList.add('opacity-50', 'cursor-not-allowed');
            btn.textContent = 'Vui lòng chọn Ngày & Giờ';
        }
    }

    // ===========================
    // GO TO CHECKOUT
    // ===========================
    window.goToCheckout = function() {
        if (!state.selectedDate || !state.selectedTime) return;

        // Populate checkout summary
        setText('chkDate', state.selectedDateStr);
        setText('chkTime', formatTime(state.selectedTime.slot_time));
        setText('chkGuests', state.adults + (state.children > 0 ? (' người lớn + ' + state.children + ' trẻ em') : ' người'));
        setText('chkSubtotal', formatMoney(state.totalPrice));
        setText('chkTotal', formatMoney(state.totalPrice));

        showView('view-checkout');
    };

    // ===========================
    // VAT TOGGLE & TAX LOOKUP
    // ===========================
    window.toggleVatForm = function() {
        const checked = document.getElementById('reqVatToggle') && document.getElementById('reqVatToggle').checked;
        const form = document.getElementById('vatFormSection');
        if (!form) return;
        if (checked) {
            form.classList.remove('hidden');
            form.querySelectorAll('input').forEach(i => i.setAttribute('required', 'true'));
        } else {
            form.classList.add('hidden');
            form.querySelectorAll('input').forEach(i => i.removeAttribute('required'));
        }
    };

    // Auto-lookup tax code on blur
    function initTaxLookup() {
        const taxInput = document.getElementById('vatTaxCode');
        if (!taxInput) return;
        taxInput.addEventListener('blur', function() {
            const code = this.value.trim();
            if (code.length < 10) return;
            lookupTaxCode(code);
        });
    }

    function lookupTaxCode(taxCode) {
        const companyInput = document.getElementById('vatCompanyName');
        const addressInput = document.getElementById('vatAddress');
        const indicator = document.getElementById('vatLookupStatus');
        if (!companyInput || !addressInput) return;

        if (indicator) indicator.textContent = 'Đang tra cứu...';
        companyInput.classList.add('vat-loading');
        addressInput.classList.add('vat-loading');

        const data = new FormData();
        data.append('action', 'halong_lookup_tax');
        data.append('nonce', BookingConfig.nonce);
        data.append('tax_code', taxCode);

        fetch(BookingConfig.ajaxUrl, { method: 'POST', body: data })
            .then(r => r.json())
            .then(res => {
                companyInput.classList.remove('vat-loading');
                addressInput.classList.remove('vat-loading');
                if (res.success) {
                    companyInput.value = res.data.company_name || '';
                    addressInput.value = res.data.address || '';
                    companyInput.setAttribute('readonly', 'true');
                    addressInput.setAttribute('readonly', 'true');
                    if (indicator) indicator.innerHTML = '<span class="vat-success-badge"><i class="ph ph-check-circle"></i> Đã xác thực từ Cổng thông tin Thuế</span>';
                } else {
                    if (indicator) indicator.innerHTML = '<span style="color:#fca5a5;font-size:12px;">' + (res.data && res.data.message ? res.data.message : 'Không tìm thấy MST') + '</span>';
                }
            })
            .catch(() => {
                companyInput.classList.remove('vat-loading');
                addressInput.classList.remove('vat-loading');
                if (indicator) indicator.innerHTML = '<span style="color:#fca5a5;font-size:12px;">Lỗi kết nối, vui lòng nhập thủ công</span>';
            });
    }

    // ===========================
    // PROCESS CHECKOUT -> AJAX CREATE BOOKING
    // ===========================
    window.processCheckout = function(e) {
        if (e) e.preventDefault();
        if (state.isSubmitting) return;

        const name = document.getElementById('cusName') ? document.getElementById('cusName').value.trim() : '';
        const phone = document.getElementById('cusPhone') ? document.getElementById('cusPhone').value.trim() : '';
        const email = document.getElementById('cusEmail') ? document.getElementById('cusEmail').value.trim() : '';
        const note = document.getElementById('cusNote') ? document.getElementById('cusNote').value.trim() : '';
        const vatRequested = document.getElementById('reqVatToggle') ? document.getElementById('reqVatToggle').checked : false;
        const vatCompany = vatRequested && document.getElementById('vatCompanyName') ? document.getElementById('vatCompanyName').value.trim() : '';
        const vatTax = vatRequested && document.getElementById('vatTaxCode') ? document.getElementById('vatTaxCode').value.trim() : '';
        const vatAddr = vatRequested && document.getElementById('vatAddress') ? document.getElementById('vatAddress').value.trim() : '';

        // Store customer data
        state.customerData = { name, phone, email, note, vatRequested, vatCompany, vatTax, vatAddr };

        const submitBtn = document.querySelector('#checkoutDataForm button[type="submit"]');
        setSubmitting(true, submitBtn);

        const data = new FormData();
        data.append('action', 'halong_create_booking');
        data.append('nonce', BookingConfig.nonce);
        data.append('tour_id', BookingConfig.tourId || 0);
        data.append('date', state.selectedDateStr);
        data.append('time', state.selectedTime ? state.selectedTime.slot_time : '');
        data.append('adults', state.adults);
        data.append('children', state.children);
        data.append('customer_name', name);
        data.append('customer_phone', phone);
        data.append('customer_email', email);
        data.append('customer_note', note);
        data.append('vat_requested', vatRequested ? '1' : '0');
        data.append('vat_company_name', vatCompany);
        data.append('vat_tax_code', vatTax);
        data.append('vat_address', vatAddr);

        fetch(BookingConfig.ajaxUrl, { method: 'POST', body: data })
            .then(r => r.json())
            .then(res => {
                setSubmitting(false, submitBtn);
                if (res.success) {
                    window.location.href = res.data.redirect_url;
                } else {
                    showFormError(res.data && res.data.message ? res.data.message : 'Đã xảy ra lỗi. Vui lòng thử lại.');
                }
            })
            .catch(() => {
                setSubmitting(false, submitBtn);
                showFormError('Lỗi kết nối. Vui lòng kiểm tra mạng và thử lại.');
            });
    };



    // ===========================
    // UTILITIES
    // ===========================
    function formatTime(timeStr) {
        if (!timeStr) return '';
        const parts = timeStr.split(':');
        if (parts.length < 2) return timeStr;
        let hour = parseInt(parts[0], 10);
        const minute = parts[1].padStart(2, '0');
        const ampm = hour >= 12 ? 'PM' : 'AM';
        hour = hour % 12;
        hour = hour ? hour : 12;
        const hourStr = String(hour).padStart(2, '0');
        return `${hourStr}:${minute} ${ampm}`;
    }

    function setText(id, text) {
        const el = document.getElementById(id);
        if (el) el.textContent = text;
    }

    function setSubmitting(isLoading, btn) {
        state.isSubmitting = isLoading;
        if (!btn) return;
        if (isLoading) {
            btn.disabled = true;
            btn.innerHTML = '<span class="hlr-spinner"></span> Đang xử lý...';
        } else {
            btn.disabled = false;
            btn.innerHTML = 'Xác nhận & Tiến hành Thanh toán';
        }
    }

    function showFormError(msg) {
        let alert = document.getElementById('checkoutFormAlert');
        if (!alert) {
            alert = document.createElement('div');
            alert.id = 'checkoutFormAlert';
            alert.className = 'hlr-alert error';
            const form = document.getElementById('checkoutDataForm');
            if (form) form.insertAdjacentElement('afterend', alert);
        }
        alert.textContent = msg;
        alert.style.display = 'block';
        setTimeout(() => { if (alert) alert.style.display = 'none'; }, 6000);
    }

    // ===========================
    // CHILDREN TOGGLE (Admin feature flag)
    // ===========================
    function initChildrenToggle() {
        const childRow = document.getElementById('childrenCountRow');
        if (!childRow) return;
        if (BookingConfig && BookingConfig.childrenEnabled) {
            childRow.classList.remove('hidden');
        } else {
            childRow.classList.add('hidden');
            state.children = 0;
        }
    }

    // ===========================
    // INIT
    // ===========================
    document.addEventListener('DOMContentLoaded', function() {
        // Accordion init
        document.querySelectorAll('.accordion-content').forEach(acc => {
            acc.style.maxHeight = acc.scrollHeight + 'px';
        });

        // Calendar
        renderCalendar();

        // Children toggle based on feature flag
        initChildrenToggle();

        // Age verification
        initAgeVerification();

        // VAT Tax lookup
        initTaxLookup();

        // Close reviews modal on outside click
        const reviewsModal = document.getElementById('reviewsModal');
        if (reviewsModal) {
            reviewsModal.addEventListener('click', function(e) {
                if (e.target === this) closeReviewsModal();
            });
        }

        // Init price display
        updateSummary();
    });

})();
