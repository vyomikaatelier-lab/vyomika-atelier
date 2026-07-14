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

    document.querySelectorAll('.va-calculator').forEach(function (calc) {
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

            document.getElementById('va-modal-service-slug').value = serviceSlug;
            document.getElementById('va-modal-design-slug').value = designSlug;
            document.getElementById('va-modal-price').value = Math.round(state.price);
            document.getElementById('va-modal-dimensions').value = state.dimLabel;
            document.getElementById('va-modal-unit').value = state.unit;
            document.getElementById('va-modal-dim-display').textContent = state.dimLabel + ' (' + state.sqft.toFixed(2) + ' sq ft)';
            document.getElementById('va-modal-price-display').textContent = formatINR(state.price);
            document.getElementById('va-modal-subject').value = serviceName + ' — Order Request';
            document.querySelector('.va-modal-service-label').textContent = serviceName;

            modal.classList.add('open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('va-menu-open');
        });
    });

    const modal = document.getElementById('va-order-modal');
    if (modal) {
        modal.querySelectorAll('[data-close-modal]').forEach(function (el) {
            el.addEventListener('click', function () {
                modal.classList.remove('open');
                modal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('va-menu-open');
            });
        });
    }
})();
