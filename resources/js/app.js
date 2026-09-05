import { parseCurrency } from './number-format.js';

const sidebar = document.querySelector('#sidebar');
const menuToggle = document.querySelector('[data-menu-toggle]');
const workspace = document.querySelector('.workspace');
const menuBreakpoint = window.matchMedia('(max-width: 780px)');

const setMenuOpen = (open, restoreFocus = false) => {
    if (!sidebar || !menuToggle) return;
    sidebar.classList.toggle('open', open);
    if (workspace) workspace.inert = open && menuBreakpoint.matches;
    document.body.classList.toggle('menu-open', open);
    menuToggle.setAttribute('aria-expanded', String(open));
    menuToggle.setAttribute('aria-label', open ? 'Tutup menu' : 'Buka menu');
    if (menuBreakpoint.matches) sidebar.inert = !open;
    else sidebar.inert = false;
    if (open) sidebar.querySelector('a')?.focus();
    else if (restoreFocus) menuToggle.focus();
};

menuToggle?.addEventListener('click', () => setMenuOpen(!sidebar?.classList.contains('open'), true));
document.querySelectorAll('[data-menu-close]').forEach((button) => button.addEventListener('click', () => setMenuOpen(false, true)));
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
let confirmationAction = null;
let confirmationCancel = null;
let confirmationTrigger = null;
let navigationApproved = false;
const initialValues = new WeakMap();
const restoredForms = new WeakSet();
const trackedForms = [...document.querySelectorAll('[data-unsaved-form]')];
const editableFields = (form) => [...form.elements].filter((field) =>
    field.matches('input:not([type="hidden"]):not([type="submit"]), select, textarea')
    && !field.disabled && !field.readOnly && !field.hasAttribute('data-bulk-item'));
const fieldValue = (field) => field.type === 'checkbox' || field.type === 'radio'
    ? String(field.checked)
    : (field.type === 'file' ? [...field.files].map((file) => `${file.name}:${file.size}`).join('|') : field.value);
const fieldDirty = (field) => initialValues.has(field) && initialValues.get(field) !== fieldValue(field);
const formDirty = (form) => restoredForms.has(form) || editableFields(form).some(fieldDirty);
const unsavedOutside = (form) => trackedForms.some((other) => other !== form && formDirty(other));
const selectedRestockRows = (form) => [...form.querySelectorAll('[data-restock-row]')]
    .filter((row) => row.querySelector('[data-bulk-item]:checked:not(:disabled)'));

const askConfirmation = ({ title = 'Konfirmasi tindakan', message, accept = 'Lanjutkan', action, cancel }) => {
    if (!(confirmationDialog instanceof HTMLDialogElement)) return;
    confirmationAction = action;
    confirmationCancel = cancel;
    confirmationTrigger = document.activeElement;
    confirmationDialog.querySelector('#confirmation-dialog-title').textContent = title;
    confirmationDialog.querySelector('#confirmation-dialog-message').textContent = message;
    confirmationDialog.querySelector('[data-confirmation-accept]').textContent = accept;
    confirmationDialog.showModal();
};
document.querySelectorAll('[data-confirmation-cancel]').forEach((button) => {
    button.addEventListener('click', () => confirmationDialog?.close());
});
document.querySelector('[data-confirmation-accept]')?.addEventListener('click', () => {
    const action = confirmationAction;
    confirmationCancel = null;
    confirmationAction = null;
    confirmationDialog.close();
    action?.();
});
confirmationDialog?.addEventListener('close', () => {
    confirmationCancel?.();
    confirmationAction = null;
    confirmationCancel = null;
    confirmationTrigger?.focus();
});

const validateRestockSelection = (form, status) => {
    const rows = selectedRestockRows(form);
    if (!rows.length) {
        form.querySelector('[data-bulk-selection]').textContent = 'Pilih setidaknya satu barang.';
        form.querySelector('[data-select-all]')?.focus();
        return false;
    }
    for (const row of rows) {
        const input = row.querySelector('[data-restock-quantity]');
        input.setCustomValidity('');
        if (status === 'skipped') continue;
        input.required = true;
        if (['approved', 'proposed'].includes(status) && Number(input.value) <= 0) {
            input.setCustomValidity('Isi jumlah lebih dari 0 untuk memesan, atau pilih Tidak Dipesan.');
        }
        if (!input.reportValidity()) return false;
    }
    return true;
};

document.querySelectorAll('form').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (form.dataset.submitting === 'true') {
            event.preventDefault();
            return;
        }
        const submitter = event.submitter;
        if (form.hasAttribute('data-restock-form') && !validateRestockSelection(form, submitter?.value || 'pending')) {
            event.preventDefault();
            return;
        }
        if (form.dataset.confirmed !== 'true') {
            const source = submitter?.hasAttribute('data-confirm') ? submitter : form;
            let message = source.dataset.confirm || '';
            if (form.hasAttribute('data-restock-form') && submitter?.value === 'pending'
                && selectedRestockRows(form).some((row) => ['approved', 'skipped'].includes(row.dataset.actionStatus))) {
                message = 'Simpan pilihan sebagai draft? Keputusan Owner pada barang terpilih akan dibuka kembali untuk ditinjau.';
            }
            const unselectedEdits = form.hasAttribute('data-restock-form') && [...form.querySelectorAll('[data-restock-row]')]
                .some((row) => !row.querySelector('[data-bulk-item]:checked') && [...row.querySelectorAll('input')].some(fieldDirty));
            if (source.hasAttribute('data-confirm-selection')) {
                const rows = selectedRestockRows(form);
                message += `\n\n${rows.length} barang dipilih:\n` + rows.map((row) =>
                    `• ${row.dataset.productName}: ${row.querySelector('[data-restock-quantity]').value} ${row.dataset.unit}`
                ).join('\n');
            }
            if (unsavedOutside(form) || unselectedEdits) {
                message += '\n\nAda perubahan lain yang belum disimpan. Melanjutkan akan meninggalkan perubahan tersebut.';
            }
            if (message.trim()) {
                event.preventDefault();
                askConfirmation({
                    title: source.dataset.confirmTitle || 'Tinjau tindakan', message: message.trim(),
                    accept: source.dataset.confirmAccept || 'Lanjutkan',
                    action: () => { form.dataset.confirmed = 'true'; form.requestSubmit(submitter ?? undefined); },
                    cancel: () => { if (form.querySelector('[data-auto-submit]')) form.reset(); },
                });
                return;
            }
        }
        delete form.dataset.confirmed;
        navigationApproved = true;
        if (form.method.toLowerCase() !== 'get') {
            form.dataset.submitting = 'true';
            form.setAttribute('aria-busy', 'true');
            if (submitter) {
                submitter.dataset.originalLabel = submitter.textContent;
                submitter.textContent = 'Menyimpan…';
            }
        }
    });
});

document.addEventListener('click', (event) => {
    const link = event.target.closest('a[href]');
    if (!link || link.target === '_blank' || link.hasAttribute('download') || event.metaKey || event.ctrlKey || event.shiftKey
        || (link.hash && link.pathname === window.location.pathname && link.search === window.location.search)) return;
    if (!trackedForms.some(formDirty)) return;
    event.preventDefault();
    askConfirmation({
        title: 'Perubahan belum disimpan',
        message: 'Ada isian yang belum disimpan. Tetap di halaman ini untuk menyimpannya, atau lanjutkan untuk meninggalkan perubahan.',
        accept: 'Tinggalkan Perubahan',
        action: () => { navigationApproved = true; window.location.assign(link.href); },
    });
});
window.addEventListener('beforeunload', (event) => {
    if (navigationApproved || !trackedForms.some(formDirty)) return;
    event.preventDefault();
    event.returnValue = '';
});
window.addEventListener('pageshow', () => {
    navigationApproved = false;
    document.querySelectorAll('[data-submitting]').forEach((form) => {
        delete form.dataset.submitting;
        form.removeAttribute('aria-busy');
    });
    document.querySelectorAll('[data-original-label]').forEach((button) => {
        button.textContent = button.dataset.originalLabel;
        delete button.dataset.originalLabel;
    });
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

document.querySelectorAll('[data-restock-form]').forEach((form) => {
    const toggle = form.querySelector('[data-select-all]');
    const items = [...form.querySelectorAll('[data-bulk-item]:not(:disabled)')];
    const refreshSelection = () => {
        const selected = items.filter((item) => item.checked).length;
        toggle.checked = items.length > 0 && selected === items.length;
        toggle.indeterminate = selected > 0 && selected < items.length;
        form.querySelector('[data-bulk-selection]').textContent = selected ? `${selected} barang dipilih` : 'Belum ada barang dipilih';
        form.querySelectorAll('[data-requires-selection]').forEach((button) => { button.disabled = selected === 0; });
    };
    toggle.addEventListener('change', () => {
        items.forEach((item) => { item.checked = toggle.checked; });
        refreshSelection();
    });
    items.forEach((item) => item.addEventListener('change', refreshSelection));
    form.querySelectorAll('[data-restock-row] input:not([type="checkbox"])').forEach((input) => {
        input.addEventListener('input', () => {
            input.setCustomValidity('');
            const checkbox = input.closest('[data-restock-row]').querySelector('[data-bulk-item]');
            if (!checkbox.disabled) checkbox.checked = true;
            refreshSelection();
        });
    });
    form.querySelectorAll('[data-use-suggestion]').forEach((button) => {
        button.addEventListener('click', () => {
            const input = button.closest('[data-restock-row]').querySelector('[data-restock-quantity]');
            input.value = String(Number(button.dataset.useSuggestion));
            input.dispatchEvent(new Event('input', { bubbles: true }));
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
        const totalValid = Math.abs(total - 100) < 0.000001;
        const directionsValid = types.includes('benefit') && types.includes('cost');
        const valid = totalValid && directionsValid;

        if (totalOutput) totalOutput.textContent = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(total);
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
            ? parseCurrency(input.value)
            : input.value;

        return value !== null && String(value).trim() !== ''
            && Number.isFinite(Number(value))
            && Number(value) >= Number(input.min || 0)
            && !input.validity.stepMismatch && !input.validity.badInput;
    };
    const refreshDataset = () => {
        let complete = Number(summary?.dataset.completeOutside || 0);
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
    const value = parseCurrency(input.value);
    preview.textContent = value !== null && value >= 0
        ? `Rp${new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(value)}`
        : (input.value.trim() === '' ? 'Belum diisi' : 'Gunakan nilai penjualan minimal 0');
};
document.querySelectorAll('[data-currency-input]').forEach((input) => {
    input.addEventListener('input', () => {
        formatCurrencyPreview(input);
    });
    formatCurrencyPreview(input);
});

trackedForms.forEach((form) => {
    editableFields(form).forEach((field) => initialValues.set(field, fieldValue(field)));
    const refresh = () => {
        const dirty = formDirty(form);
        form.querySelectorAll('[data-save-state]').forEach((output) => {
            output.textContent = dirty ? 'Ada perubahan belum disimpan' : '';
        });
    };
    form.addEventListener('input', refresh);
    form.addEventListener('change', refresh);
});

document.querySelectorAll('.field-error').forEach((error, index) => {
    const field = error.parentElement.querySelector('input:not([type="hidden"]), select, textarea');
    if (!field) return;
    error.id ||= `field-error-${index}`;
    field.setAttribute('aria-invalid', 'true');
    field.setAttribute('aria-describedby', error.id);
});
const invalidForm = document.body.dataset.invalidForm;
if (invalidForm) {
    const input = [...document.querySelectorAll('input[name="_form"]')].find((input) => input.value === invalidForm);
    if (input?.form?.hasAttribute('data-unsaved-form')) restoredForms.add(input.form);
    const dialog = input?.closest('dialog');
    if (dialog instanceof HTMLDialogElement) {
        const trigger = document.querySelector(`[data-dialog-open="${dialog.id}"]`);
        if (trigger) dialogTriggers.set(dialog, trigger);
        dialog.showModal();
    }
    const firstError = input?.form?.querySelector('[aria-invalid="true"]');
    firstError?.closest('details')?.setAttribute('open', '');
    firstError?.focus();
}

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Tab' || !menuBreakpoint.matches || !sidebar?.classList.contains('open')) return;
    const controls = [...sidebar.querySelectorAll('a, button')];
    const first = controls[0];
    const last = controls.at(-1);
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
});

document.querySelector('[data-print-page]')?.addEventListener('click', () => window.print());
document.querySelector('.nav-link.active')?.setAttribute('aria-current', 'page');

// Supply matching labels when operational tables stack into cards on a small screen.
document.querySelectorAll('.responsive-table').forEach((table) => {
    const headers = [...table.querySelectorAll('thead th')].map((header) => header.textContent.trim() || 'Aksi');
    table.querySelectorAll('tbody tr').forEach((row) => {
        [...row.cells].forEach((cell, index) => {
            if (cell.colSpan === 1) cell.dataset.label ||= headers[index] || '';
        });
    });
});
