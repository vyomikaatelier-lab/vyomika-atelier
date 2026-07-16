(function () {
    const MM_PER_FT = 304.8;
    const CM_PER_FT = 30.48;

    function toFeet(unit, lenVal, lenIn, hgtVal, hgtIn) {
        let lengthFt, heightFt;
        if (unit === 'ft-in') {
            lengthFt = (parseFloat(lenVal) || 0) + (parseFloat(lenIn) || 0) / 12;
            heightFt = (parseFloat(hgtVal) || 0) + (parseFloat(hgtIn) || 0) / 12;
        } else if (unit === 'mm') {
            lengthFt = (parseFloat(lenVal) || 0) / MM_PER_FT;
            heightFt = (parseFloat(hgtVal) || 0) / MM_PER_FT;
        } else {
            lengthFt = (parseFloat(lenVal) || 0) / CM_PER_FT;
            heightFt = (parseFloat(hgtVal) || 0) / CM_PER_FT;
        }
        return { lengthFt, heightFt };
    }

    function formatINR(amount) {
        return '₹' + Math.round(amount).toLocaleString('en-IN');
    }

    function getCalcState(calc) {
        const unit = calc.querySelector('.va-unit-btn.active')?.dataset.unit || 'ft-in';
        let lengthFt, heightFt, dimLabel;

        if (unit === 'ft-in') {
            const lf = calc.querySelector('.va-len-ft')?.value;
            const li = calc.querySelector('.va-len-in')?.value;
            const hf = calc.querySelector('.va-hgt-ft')?.value;
            const hi = calc.querySelector('.va-hgt-in')?.value;
            const r = toFeet(unit, lf, li, hf, hi);
            lengthFt = r.lengthFt;
            heightFt = r.heightFt;
            dimLabel = `${lf || 0}'${li || 0}" × ${hf || 0}'${hi || 0}"`;
        } else if (unit === 'mm') {
            const l = calc.querySelector('.va-len-mm')?.value;
            const h = calc.querySelector('.va-hgt-mm')?.value;
            const r = toFeet(unit, l, 0, h, 0);
            lengthFt = r.lengthFt;
            heightFt = r.heightFt;
            dimLabel = `${l || 0} mm × ${h || 0} mm`;
        } else {
            const l = calc.querySelector('.va-len-cm')?.value;
            const h = calc.querySelector('.va-hgt-cm')?.value;
            const r = toFeet(unit, l, 0, h, 0);
            lengthFt = r.lengthFt;
            heightFt = r.heightFt;
            dimLabel = `${l || 0} cm × ${h || 0} cm`;
        }

        const sqft = lengthFt * heightFt;
        const rate = parseFloat(calc.dataset.rate) || 1800;
        const price = sqft * rate;

        return { unit, sqft, price, dimLabel };
    }

    function updateCalc(calc) {
        const state = getCalcState(calc);
        const areaEl = calc.querySelector('.va-area-display');
        const priceEl = calc.querySelector('.va-price-display');
        if (areaEl) areaEl.textContent = state.sqft.toFixed(2) + ' sq ft';
        if (priceEl) priceEl.textContent = formatINR(state.price);
        return state;
    }

    function openOrderModal(modal) {
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        const overlay = document.getElementById('am-overlay');
        if (overlay) overlay.classList.add('is-open');
        document.body.style.overflow = 'hidden';
    }

    function closeOrderModal(modal) {
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
        const overlay = document.getElementById('am-overlay');
        const cartOpen = document.getElementById('am-cart-drawer')?.classList.contains('is-open');
        const qvOpen = document.getElementById('am-quickview')?.classList.contains('is-open');
        if (overlay && !cartOpen && !qvOpen) overlay.classList.remove('is-open');
        if (!cartOpen && !qvOpen) document.body.style.overflow = '';
    }

    function bindCalculator(calc) {
        if (calc.dataset.bound === '1') return;
        calc.dataset.bound = '1';
        const unitBtns = calc.querySelectorAll('.va-unit-btn');
        unitBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                const unit = btn.dataset.unit;
                unitBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                calc.querySelectorAll('.va-dim-group').forEach(g => g.classList.add('hidden'));
                if (unit === 'ft-in') calc.querySelectorAll('.va-dim-ft-in').forEach(g => g.classList.remove('hidden'));
                if (unit === 'mm') calc.querySelectorAll('.va-dim-mm').forEach(g => g.classList.remove('hidden'));
                if (unit === 'cm') calc.querySelectorAll('.va-dim-cm').forEach(g => g.classList.remove('hidden'));
                updateCalc(calc);
            });
        });

        calc.querySelectorAll('input').forEach(function (input) {
            input.addEventListener('input', () => updateCalc(calc));
        });

        updateCalc(calc);

        const orderBtn = calc.querySelector('.va-order-btn');
        orderBtn?.addEventListener('click', function () {
            const state = updateCalc(calc);
            const modal = document.getElementById('va-order-modal');
            if (!modal) return;

            const serviceSlug = orderBtn.dataset.serviceSlug || '';
            const designSlug = orderBtn.dataset.designSlug || '';
            const serviceName = orderBtn.dataset.serviceName || 'Service';
            const activeFinish = document.querySelector('[data-pdp-finish] [data-finish-slug].is-active');
            const finishName = activeFinish?.dataset.finishName || '';

            const typeInput = document.getElementById('am-popup-type');
            const titleEl = document.getElementById('am-popup-form-title');
            const subtitleEl = modal.querySelector('[data-popup-subtitle]');
            const orderSummary = modal.querySelector('[data-popup-order-summary]');
            const messageField = document.getElementById('va-modal-message');

            if (typeInput) typeInput.value = 'order_now';
            if (titleEl) titleEl.textContent = 'Complete your order';
            if (subtitleEl) subtitleEl.hidden = true;
            if (orderSummary) orderSummary.hidden = false;
            if (messageField) messageField.placeholder = 'Installation address, timeline, finish preference…';

            document.getElementById('va-modal-service-slug').value = serviceSlug;
            document.getElementById('va-modal-design-slug').value = designSlug;
            document.getElementById('va-modal-price').value = Math.round(state.price);
            document.getElementById('va-modal-dimensions').value = state.dimLabel;
            document.getElementById('va-modal-unit').value = state.unit;
            const finishField = document.getElementById('va-modal-finish');
            if (finishField) finishField.value = finishName;
            document.getElementById('va-modal-dim-display').textContent = state.dimLabel + ' (' + state.sqft.toFixed(2) + ' sq ft)';
            document.getElementById('va-modal-price-display').textContent = formatINR(state.price);
            document.getElementById('va-modal-subject').value = serviceName + ' — Order Request';
            document.querySelector('.va-modal-service-label').textContent = serviceName;
            const productField = document.getElementById('va-modal-product');
            if (productField) {
                productField.value = serviceName + (finishName ? ' · ' + finishName : '');
            }

            openOrderModal(modal);
        });
    }

    function initCalculators() {
        document.querySelectorAll('.va-calculator').forEach(bindCalculator);
    }

    initCalculators();
    document.addEventListener('am-content-ready', initCalculators);
    document.addEventListener('am-recalc-calculators', () => {
        document.querySelectorAll('.va-calculator').forEach(updateCalc);
    });

    const modal = document.getElementById('va-order-modal');
    if (modal) {
        modal.querySelectorAll('[data-close-popup-form], [data-close-modal]').forEach(function (el) {
            el.addEventListener('click', function () {
                closeOrderModal(modal);
            });
        });
    }
})();
