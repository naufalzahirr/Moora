const sidebar = document.querySelector('#sidebar');
const menuToggle = document.querySelector('[data-menu-toggle]');
const menuBreakpoint = window.matchMedia('(max-width: 780px)');

const setMenuOpen = (open, restoreFocus = false) => {
    if (!sidebar || !menuToggle) return;
    sidebar.classList.toggle('open', open);
    document.body.classList.toggle('menu-open', open);
    menuToggle.setAttribute('aria-expanded', String(open));
    menuToggle.setAttribute('aria-label', open ? 'Tutup menu' : 'Buka menu');
    if (menuBreakpoint.matches) sidebar.inert = !open;
    else sidebar.inert = false;
    if (open) sidebar.querySelector('a')?.focus();
    else if (restoreFocus) menuToggle.focus();
};

menuToggle?.addEventListener('click', () => setMenuOpen(!sidebar?.classList.contains('open'), true));
document.querySelector('[data-menu-close]')?.addEventListener('click', () => setMenuOpen(false, true));
menuBreakpoint.addEventListener('change', () => setMenuOpen(false));
setMenuOpen(false);

const dialogTriggers = new WeakMap();
document.querySelectorAll('[data-dialog-open]').forEach((button) => {
    button.addEventListener('click', () => {
        const dialog = document.getElementById(button.dataset.dialogOpen);
        if (!(dialog instanceof HTMLDialogElement)) return;
        dialogTriggers.set(dialog, button);
        document.querySelectorAll(`[data-dialog-open="${button.dataset.dialogOpen}"]`).forEach((trigger) => trigger.setAttribute('aria-expanded', 'true'));
        dialog.showModal();
    });
});
document.querySelectorAll('[data-dialog-close]').forEach((button) => {
    button.addEventListener('click', () => button.closest('dialog')?.close());
});
document.querySelectorAll('dialog').forEach((dialog) => {
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) dialog.close();
    });
    dialog.addEventListener('close', () => {
        document.querySelectorAll(`[data-dialog-open="${dialog.id}"]`).forEach((trigger) => trigger.setAttribute('aria-expanded', 'false'));
        dialogTriggers.get(dialog)?.focus();
    });
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && sidebar?.classList.contains('open')) setMenuOpen(false, true);
});

const confirmationDialog = document.querySelector('#confirmation-dialog');
let pendingConfirmationForm = null;
let pendingConfirmationSubmitter = null;
document.querySelectorAll('form').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (form.dataset.confirmed === 'true') {
            delete form.dataset.confirmed;
            return;
        }
        const source = event.submitter?.hasAttribute('data-confirm') ? event.submitter : form;
        if (!source.hasAttribute('data-confirm')) return;
        event.preventDefault();
        if (!(confirmationDialog instanceof HTMLDialogElement)) return;
        pendingConfirmationForm = form;
        pendingConfirmationSubmitter = event.submitter;
        confirmationDialog.querySelector('#confirmation-dialog-title').textContent = source.dataset.confirmTitle || 'Konfirmasi tindakan';
        confirmationDialog.querySelector('#confirmation-dialog-message').textContent = source.dataset.confirm;
        confirmationDialog.querySelector('[data-confirmation-accept]').textContent = source.dataset.confirmAccept || 'Lanjutkan';
        confirmationDialog.showModal();
    });
});
document.querySelectorAll('[data-confirmation-cancel]').forEach((button) => {
    button.addEventListener('click', () => confirmationDialog?.close());
});
document.querySelector('[data-confirmation-accept]')?.addEventListener('click', () => {
    const form = pendingConfirmationForm;
    const submitter = pendingConfirmationSubmitter;
    confirmationDialog?.close();
    pendingConfirmationForm = null;
    pendingConfirmationSubmitter = null;
    if (!form) return;
    form.dataset.confirmed = 'true';
    form.requestSubmit(submitter ?? undefined);
});
confirmationDialog?.addEventListener('close', () => {
    pendingConfirmationForm = null;
    pendingConfirmationSubmitter = null;
});

document.querySelectorAll('[data-flash]').forEach((flash) => {
    const dismiss = () => {
        flash.style.transition = 'opacity .2s ease, transform .2s ease';
        flash.style.opacity = '0';
        flash.style.transform = 'translateY(-8px)';
        window.setTimeout(() => flash.remove(), 220);
    };
    flash.querySelector('[data-flash-close]')?.addEventListener('click', dismiss);
    if (flash.classList.contains('error')) return;
    window.setTimeout(() => {
        dismiss();
    }, 4500);
});

document.querySelectorAll('[data-file-input]').forEach((input) => {
    input.addEventListener('change', () => {
        const target = document.querySelector(`[data-file-name="${input.dataset.fileInput}"]`);
        if (target) target.textContent = input.files?.[0]?.name ?? 'Belum ada berkas dipilih';
    });
});

document.querySelectorAll('[data-auto-submit]').forEach((input) => {
    input.addEventListener('change', () => input.form?.requestSubmit());
});

document.querySelectorAll('[data-select-all]').forEach((toggle) => {
    const form = toggle.closest('table')?.querySelector('[data-bulk-item]')?.form
        ?? document.querySelector('#bulk-action-form');
    const items = form ? [...document.querySelectorAll(`[data-bulk-item][form="${form.id}"]`)] : [];
    const summary = document.querySelector('[data-bulk-selection]');
    const allInput = form?.querySelector('[data-bulk-all]');
    const refreshSelection = () => {
        const selected = items.filter((item) => item.checked).length;
        const available = items.filter((item) => !item.disabled).length;
        toggle.checked = available > 0 && selected === available;
        toggle.indeterminate = selected > 0 && selected < available;
        if (summary) summary.textContent = selected ? `${selected} barang dipilih` : 'Belum ada barang dipilih';
    };
    toggle.addEventListener('change', () => {
        items.filter((item) => !item.disabled).forEach((item) => { item.checked = toggle.checked; });
        refreshSelection();
    });
    items.forEach((item) => item.addEventListener('change', refreshSelection));
    form?.querySelectorAll('button[type="submit"]').forEach((button) => {
        button.addEventListener('click', () => {
            if (allInput) allInput.disabled = !button.hasAttribute('data-bulk-submit-all');
        });
    });
    refreshSelection();
});

document.querySelectorAll('[data-quantity-product-select]').forEach((select) => {
    const target = document.querySelector(select.dataset.quantityProductSelect);
    if (!(target instanceof HTMLInputElement)) return;
    const syncQuantityStep = () => {
        const option = select.options[select.selectedIndex];
        const step = option?.dataset.quantityStep;
        if (!step) return;
        target.step = step;
        target.placeholder = step === '1' ? 'Contoh: 24' : 'Contoh: 2,50';
    };
    select.addEventListener('change', syncQuantityStep);
    syncQuantityStep();
});

document.querySelectorAll('[data-unit-input]').forEach((unitInput) => {
    const form = unitInput.closest('form');
    const quantities = form ? [...form.querySelectorAll('[data-unit-quantity]')] : [];
    const wholeUnits = ['pcs', 'pc', 'unit', 'pack', 'pak', 'box', 'dus', 'botol', 'kaleng'];
    const syncUnitStep = () => {
        const whole = wholeUnits.includes(unitInput.value.trim().toLowerCase());
        quantities.forEach((input) => {
            input.step = whole ? '1' : '0.01';
            if (input.hasAttribute('data-unit-positive')) input.min = whole ? '1' : '0.01';
        });
    };
    unitInput.addEventListener('input', syncUnitStep);
    syncUnitStep();
});

document.querySelectorAll('[data-debounced-submit]').forEach((input) => {
    const initialValue = input.value.trim();
    let debounce;
    input.addEventListener('input', () => {
        window.clearTimeout(debounce);
        debounce = window.setTimeout(() => {
            if (input.value.trim() !== initialValue) input.form?.requestSubmit();
        }, 450);
    });
});

document.querySelectorAll('[data-date-range]').forEach((form) => {
    const start = form.querySelector('input[name="start_date"]');
    const end = form.querySelector('input[name="end_date"]');
    if (!start || !end) return;

    const syncDateRange = () => {
        end.min = start.value;
        if (start.value && (!end.value || end.value < start.value)) end.value = start.value;
    };
    start.addEventListener('change', syncDateRange);
    syncDateRange();
});

document.querySelectorAll('[data-criteria-form]').forEach((form) => {
    const rows = [...form.querySelectorAll('[data-criterion-row]')];
    const totalOutput = form.querySelector('[data-weight-total]');
    const guidance = form.querySelector('[data-weight-guidance]');
    const summary = form.querySelector('[data-criteria-summary]');
    const status = form.querySelector('[data-criteria-status]');
    const submit = document.querySelector(`[form="${form.id}"][data-criteria-submit]`);

    const refreshCriteria = () => {
        const activeRows = rows.filter((row) => row.querySelector('[data-criterion-active]')?.checked);
        const total = activeRows.reduce((sum, row) => sum + (Number(row.querySelector('[data-criterion-weight]')?.value) || 0), 0);
        const types = activeRows.map((row) => row.querySelector('[data-criterion-type]')?.value);
        const totalValid = Math.abs(total - 1) < 0.000001;
        const directionsValid = types.includes('benefit') && types.includes('cost');
        const valid = totalValid && directionsValid;

        if (totalOutput) totalOutput.textContent = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(total * 100);
        if (guidance) {
            guidance.textContent = !totalValid
                ? 'Sesuaikan bobot kriteria aktif hingga tepat 100%.'
                : (!directionsValid ? 'Aktifkan sedikitnya satu kriteria benefit dan satu cost.' : 'Konfigurasi siap disimpan dan digunakan untuk perhitungan.');
        }
        [summary, status].forEach((element) => {
            element?.classList.toggle('success', valid);
            element?.classList.toggle('green', valid);
            element?.classList.toggle('error', !valid);
            element?.classList.toggle('red', !valid);
        });
        if (status) status.textContent = valid ? 'Siap digunakan' : 'Periksa konfigurasi';
        if (submit) submit.disabled = !valid;
    };

    form.addEventListener('input', refreshCriteria);
    form.addEventListener('change', refreshCriteria);
    refreshCriteria();
});

document.querySelectorAll('[data-dataset-form]').forEach((form) => {
    const rows = [...form.querySelectorAll('[data-dataset-row]')];
    const summary = form.querySelector('[data-complete-summary]');

    const hasValidValue = (input) => {
        const value = input.hasAttribute('data-currency-input')
            ? input.value.replace(/[^0-9]/g, '')
            : input.value;

        return value.trim() !== ''
            && Number.isFinite(Number(value))
            && Number(value) >= Number(input.min || 0);
    };
    const refreshDataset = () => {
        let complete = 0;
        rows.forEach((row) => {
            const valid = [...row.querySelectorAll('[data-required-value]')].every(hasValidValue);
            const status = row.querySelector('[data-row-status]');
            status?.classList.toggle('green', valid);
            status?.classList.toggle('gray', !valid);
            if (status) status.textContent = valid ? 'Valid' : 'Lengkapi data';
            if (valid) complete++;
        });
        if (summary) {
            const total = Number(summary.dataset.total || rows.length);
            summary.textContent = `${complete} dari ${total} baris lengkap`;
            summary.classList.toggle('green', complete === total);
            summary.classList.toggle('gray', complete !== total);
        }
    };

    form.addEventListener('input', refreshDataset);
    refreshDataset();
});

const formatCurrencyPreview = (input) => {
    const preview = input.parentElement?.querySelector('[data-currency-preview]');
    if (!preview) return;
    const digits = input.value.replace(/[^0-9]/g, '');
    const value = Number(digits);
    preview.textContent = digits !== '' && Number.isFinite(value)
        ? `Rp${new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(value)}`
        : 'Rp—';
};
document.querySelectorAll('[data-currency-input]').forEach((input) => {
    input.addEventListener('input', () => {
        const digits = input.value.replace(/[^0-9]/g, '');
        input.value = digits === '' ? '' : new Intl.NumberFormat('id-ID').format(Number(digits));
        formatCurrencyPreview(input);
    });
    formatCurrencyPreview(input);
});

document.querySelector('[data-print-page]')?.addEventListener('click', () => window.print());
