const sidebar = document.querySelector('#app-sidebar');
const backdrop = document.querySelector('[data-sidebar-backdrop]');
const openButton = document.querySelector('[data-sidebar-open]');
const closeButton = document.querySelector('[data-sidebar-close]');

const setSidebar = (open) => {
    if (!sidebar || !backdrop || !openButton) return;
    document.body.classList.toggle('sidebar-open', open);
    sidebar.setAttribute('aria-hidden', String(!open));
    openButton.setAttribute('aria-expanded', String(open));
    if (open) closeButton?.focus();
};

openButton?.addEventListener('click', () => setSidebar(true));
closeButton?.addEventListener('click', () => setSidebar(false));
backdrop?.addEventListener('click', () => setSidebar(false));
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && document.body.classList.contains('sidebar-open')) {
        setSidebar(false);
        openButton?.focus();
    }
});

const autoOpenDialog = document.querySelector('[data-auto-open-dialog]');
if (autoOpenDialog instanceof HTMLDialogElement && !autoOpenDialog.open) {
    autoOpenDialog.showModal();
}

document.querySelector('[data-sync-form]')?.addEventListener('submit', (event) => {
    const button = event.currentTarget.querySelector('[data-sync-button]');
    if (!button || button.disabled) return;
    button.disabled = true;
    button.textContent = '↻ En cola...';
});

document.querySelectorAll('[data-label-open]').forEach((button) => {
    button.addEventListener('click', () => {
        const dialog = document.getElementById(button.dataset.labelOpen);
        if (!dialog) return;
        updateLabelCalculation(dialog, dialog.dataset.batchAdjusted !== 'true');
        dialog.showModal();
    });
});

document.querySelectorAll('[data-dialog-close]').forEach((button) => {
    button.addEventListener('click', () => button.closest('dialog')?.close());
});

document.querySelectorAll('[data-label-dialog]').forEach((dialog) => {
    setRecommendedLabelType(dialog);
    restoreLabelAdjustment(dialog);
    dialog.querySelector('[data-label-type]')?.addEventListener('change', () => {
        markLabelAdjustmentDirty(dialog);
        updateLabelCalculation(dialog, true);
    });
    dialog.querySelector('[data-units-per-label]')?.addEventListener('input', () => {
        markLabelAdjustmentDirty(dialog);
        updateLabelCalculation(dialog, true);
    });
    dialog.querySelector('[data-label-count]')?.addEventListener('input', () => {
        markLabelAdjustmentDirty(dialog);
        updateLabelCalculation(dialog, false);
    });
    dialog.querySelector('[data-save-label-adjustment]')?.addEventListener('click', () => saveLabelAdjustment(dialog));
    dialog.querySelector('[data-print-labels]')?.addEventListener('click', () => printLabels(dialog));
});

document.querySelector('[data-print-all-labels]')?.addEventListener('click', printAllOrderLabels);

function updateLabelCalculation(dialog, resetCount) {
    const quantity = Number(dialog.dataset.quantity);
    const standalone = dialog.dataset.standalone === 'true';
    const type = dialog.querySelector('[data-label-type]').value;
    const unitsInput = dialog.querySelector('[data-units-per-label]');
    const countInput = dialog.querySelector('[data-label-count]');
    const expectedUnits = Number(
        type === 'bulk'
            ? dialog.dataset.bulk
            : dialog.dataset.fractioned
    );

    if (!unitsInput.value || unitsInput.dataset.lastType !== type) {
        unitsInput.value = expectedUnits > 0 ? expectedUnits : '';
        unitsInput.dataset.lastType = type;
    }

    const units = Number(unitsInput.value);
    const alert = dialog.querySelector('[data-quantity-alert]');
    if (!Number.isFinite(units) || units < 1) {
        countInput.value = '';
        alert.hidden = false;
        alert.innerHTML = '<strong>Falta indicar las unidades para esta presentación.</strong><br>Ingresá manualmente las unidades por etiqueta para continuar.';
        dialog.querySelector('[data-label-help]').textContent = 'Esta cantidad todavía no está configurada. Podés escribirla solamente para esta impresión.';
        dialog.querySelector('[data-preview-units]').textContent = '— UNIDADES';
        dialog.querySelector('[data-preview-type]').textContent = type === 'bulk' ? 'GRANEL' : 'FRACCIONADO';
        return;
    }

    const exact = standalone || quantity % units === 0;
    const fractioned = type === 'fractioned';
    const calculatedCount = standalone ? 1 : Math.max(1, Math.ceil(quantity / units));
    if (resetCount || !countInput.value) countInput.value = calculatedCount;

    const remainder = quantity % units;
    alert.hidden = exact;
    const fullBoxes = Math.floor(quantity / units);
    const partialDescription = fullBoxes > 0
        ? `${fullBoxes} ${fullBoxes === 1 ? 'caja completa' : 'cajas completas'} y 1 caja parcial de ${remainder.toLocaleString('es-AR')} unidades`
        : `1 caja parcial de ${remainder.toLocaleString('es-AR')} unidades`;
    alert.innerHTML = exact
        ? ''
        : fractioned
        ? `<strong>La cantidad pedida no es múltiplo de esta presentación fraccionada.</strong><br>Podés ajustar las unidades manualmente o imprimir la etiqueta igualmente.`
        : `<strong>La cantidad pedida no completa exactamente las cajas de granel.</strong><br>Se prepararán ${partialDescription}. Podés imprimir igualmente.`;

    dialog.querySelector('[data-label-help]').textContent = standalone
        ? 'Indicá cuántas cajas o etiquetas necesitás. Podés modificar tanto las unidades como el total antes de imprimir.'
        : fractioned
        ? `Se proponen ${calculatedCount} ${calculatedCount === 1 ? 'etiqueta fraccionada' : 'etiquetas fraccionadas'} de ${units.toLocaleString('es-AR')} unidades. Podés cambiar la cantidad manualmente.`
        : remainder
        ? `Se proponen ${calculatedCount} etiquetas: ${partialDescription}. La última etiqueta llevará la cantidad parcial real.`
        : `Se proponen ${calculatedCount} etiquetas de granel (${units.toLocaleString('es-AR')} unidades por etiqueta).`;
    dialog.querySelector('[data-preview-units]').textContent = `${units.toLocaleString('es-AR')} UNIDADES`;
    dialog.querySelector('[data-preview-type]').textContent = type === 'bulk' ? 'GRANEL' : 'FRACCIONADO';
}

function printLabels(dialog) {
    const unitsInput = dialog.querySelector('[data-units-per-label]');
    const countInput = dialog.querySelector('[data-label-count]');
    const units = Number(unitsInput.value);
    const count = Number(countInput.value);
    if (!Number.isFinite(units) || units < 1) {
        updateLabelCalculation(dialog, false);
        unitsInput.focus();
        return;
    }
    if (!Number.isFinite(count) || count < 1) {
        countInput.focus();
        return;
    }
    const quantity = Number(dialog.dataset.quantity);
    const type = dialog.querySelector('[data-label-type]').value;
    const standalone = dialog.dataset.standalone === 'true';
    const size = dialog.querySelector('[data-label-size]')?.value || '80x50';
    const area = document.querySelector('#label-print-area');
    if (!area) return;

    area.innerHTML = Array.from({ length: count }, (_, index) => {
        const assigned = standalone
            ? units
            : type === 'fractioned'
            ? units
            : Math.max(0, Math.min(units, quantity - (index * units)));
        return labelMarkup(dialog.dataset, type, assigned, index + 1, count, standalone);
    }).join('');

    dialog.close();
    openPrintDialog(area, size);
}

function printAllOrderLabels() {
    const dialogs = Array.from(document.querySelectorAll('[data-label-dialog]:not([data-standalone="true"])'));
    const area = document.querySelector('#label-print-area');
    if (!area || dialogs.length === 0) return;

    area.innerHTML = dialogs.map((dialog) => {
        const quantity = Number(dialog.dataset.quantity);
        const bulk = Number(dialog.dataset.bulk);
        const fractioned = Number(dialog.dataset.fractioned);
        const recommendation = recommendedPackaging(quantity, bulk, fractioned);
        const adjusted = dialog.dataset.batchAdjusted === 'true';
        const selectedType = dialog.querySelector('[data-label-type]')?.value;
        const selectedUnits = Number(dialog.querySelector('[data-units-per-label]')?.value);
        const selectedCount = Number(dialog.querySelector('[data-label-count]')?.value);
        const type = adjusted ? selectedType : recommendation.type;
        const units = adjusted && selectedUnits > 0 ? selectedUnits : recommendation.units;
        const count = adjusted && selectedCount > 0
            ? Math.floor(selectedCount)
            : (type === 'unconfigured' ? 1 : Math.max(1, Math.ceil(quantity / units)));

        return Array.from({ length: count }, (_, index) => {
            const assigned = type === 'bulk'
                ? Math.max(0, Math.min(units, quantity - (index * units)))
                : (type === 'fractioned' ? units : quantity);
            return labelMarkup(dialog.dataset, type, assigned, index + 1, count, false);
        }).join('');
    }).join('');

    const size = document.querySelector('[data-print-all-size]')?.value || '80x50';
    openPrintDialog(area, size);
}

function labelAdjustmentKey(dialog) {
    return `iron-label-adjustment:${dialog.dataset.order || 'product'}:${dialog.dataset.itemId || dialog.dataset.code || 'unknown'}`;
}

function recommendedPackaging(quantity, bulk, fractioned) {
    const bulkExact = bulk > 0 && quantity % bulk === 0;
    const fractionedExact = fractioned > 0 && quantity % fractioned === 0;
    if (bulkExact) return { type: 'bulk', units: bulk };
    if (fractionedExact) return { type: 'fractioned', units: fractioned };
    if (bulk > 0) return { type: 'bulk', units: bulk };
    if (fractioned > 0) return { type: 'fractioned', units: fractioned };
    return { type: 'unconfigured', units: quantity };
}

function setRecommendedLabelType(dialog) {
    if (dialog.dataset.standalone === 'true') return;
    const recommendation = recommendedPackaging(
        Number(dialog.dataset.quantity),
        Number(dialog.dataset.bulk),
        Number(dialog.dataset.fractioned),
    );
    if (recommendation.type === 'unconfigured') return;
    const typeInput = dialog.querySelector('[data-label-type]');
    const unitsInput = dialog.querySelector('[data-units-per-label]');
    typeInput.value = recommendation.type;
    unitsInput.value = recommendation.units;
    unitsInput.dataset.lastType = recommendation.type;
}

function markLabelAdjustmentDirty(dialog) {
    if (dialog.dataset.standalone === 'true') return;
    dialog.dataset.batchAdjusted = 'false';
    const button = dialog.querySelector('[data-save-label-adjustment]');
    if (button) button.textContent = 'Guardar ajuste';
}

function saveLabelAdjustment(dialog) {
    const type = dialog.querySelector('[data-label-type]')?.value;
    const unitsInput = dialog.querySelector('[data-units-per-label]');
    const countInput = dialog.querySelector('[data-label-count]');
    const units = Number(unitsInput?.value);
    const count = Number(countInput?.value);

    if (!Number.isFinite(units) || units < 1) {
        updateLabelCalculation(dialog, false);
        unitsInput?.focus();
        return;
    }
    if (!Number.isFinite(count) || count < 1) {
        countInput?.focus();
        return;
    }

    const adjustment = { type, units, count: Math.floor(count) };
    dialog.dataset.batchAdjusted = 'true';
    try {
        sessionStorage.setItem(labelAdjustmentKey(dialog), JSON.stringify(adjustment));
    } catch (_) {
        // It still remains saved in the current page when storage is unavailable.
    }
    const button = dialog.querySelector('[data-save-label-adjustment]');
    if (button) button.textContent = '✓ Ajuste guardado';
    dialog.close();
}

function restoreLabelAdjustment(dialog) {
    if (dialog.dataset.standalone === 'true') return;
    let adjustment = null;
    try {
        adjustment = JSON.parse(sessionStorage.getItem(labelAdjustmentKey(dialog)) || 'null');
    } catch (_) {
        adjustment = null;
    }
    if (!adjustment || !['bulk', 'fractioned'].includes(adjustment.type)) return;
    if (!(Number(adjustment.units) > 0) || !(Number(adjustment.count) > 0)) return;

    dialog.querySelector('[data-label-type]').value = adjustment.type;
    dialog.querySelector('[data-units-per-label]').value = adjustment.units;
    dialog.querySelector('[data-label-count]').value = Math.floor(adjustment.count);
    dialog.querySelector('[data-units-per-label]').dataset.lastType = adjustment.type;
    dialog.dataset.batchAdjusted = 'true';
    const button = dialog.querySelector('[data-save-label-adjustment]');
    if (button) button.textContent = '✓ Ajuste guardado';
    updateLabelCalculation(dialog, false);
}

function labelMarkup(data, type, assigned, position, total, standalone) {
    const customer = standalone ? '' : `<div class="thermal-customer">${escapeHtml(data.customer.toUpperCase())}</div>`;
    const reference = standalone ? '' : ` · OV ${escapeHtml(data.order)}`;
    const typeLabel = type === 'bulk' ? 'GRANEL' : (type === 'fractioned' ? 'FRACCIONADO' : 'PEDIDO');

    return `<article class="printed-label">
        ${customer}
        <div class="thermal-product">
            <strong>${escapeHtml(data.description)}</strong>
            <span>${escapeHtml(data.code)}</span>
            <b>${assigned.toLocaleString('es-AR')} UNIDADES</b>
        </div>
        <div class="thermal-brand">
            <img src="${escapeHtml(data.logo)}" alt="">
            <em>${typeLabel}${reference} · ${position}/${total}</em>
        </div>
    </article>`;
}

function openPrintDialog(area, size = '80x50') {
    const normalizedSize = size === '100x80' ? '100x80' : '80x50';
    document.documentElement.dataset.printLabelSize = normalizedSize;

    let pageStyle = document.querySelector('#label-page-size');
    if (!pageStyle) {
        pageStyle = document.createElement('style');
        pageStyle.id = 'label-page-size';
        document.head.appendChild(pageStyle);
    }
    pageStyle.textContent = normalizedSize === '100x80'
        ? '@page { size: 100mm 80mm; margin: 0; }'
        : '@page { size: 80mm 50mm; margin: 0; }';

    // Keep the browser print call in the original click event. Delaying it until
    // images load can make Chrome/Safari discard the user activation.
    window.print();
}

function escapeHtml(value = '') {
    const element = document.createElement('span');
    element.textContent = value;
    return element.innerHTML;
}
