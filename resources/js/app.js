const sidebar = document.querySelector('#app-sidebar');
const backdrop = document.querySelector('[data-sidebar-backdrop]');
const openButton = document.querySelector('[data-sidebar-open]');
const closeButton = document.querySelector('[data-sidebar-close]');

document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const input = document.getElementById(button.dataset.passwordToggle);
        if (!(input instanceof HTMLInputElement)) return;
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        button.setAttribute('aria-label', show ? 'Ocultar contraseña' : 'Mostrar contraseña');
        button.setAttribute('title', show ? 'Ocultar contraseña' : 'Mostrar contraseña');
        button.setAttribute('aria-pressed', String(show));
        input.focus({ preventScroll: true });
        input.setSelectionRange(input.value.length, input.value.length);
    });
});

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
        dialog.dataset.unitsManuallyEdited = 'false';
        dialog.dataset.allowOverage = 'false';
        updateLabelCalculation(dialog, true);
    });
    dialog.querySelector('[data-units-per-label]')?.addEventListener('input', () => {
        markLabelAdjustmentDirty(dialog);
        dialog.dataset.unitsManuallyEdited = 'true';
        updateLabelCalculation(dialog, true);
    });
    dialog.querySelector('[data-label-count]')?.addEventListener('input', () => {
        markLabelAdjustmentDirty(dialog);
        updateLabelCalculation(dialog, false, true);
    });
    dialog.querySelector('[data-save-label-adjustment]')?.addEventListener('click', () => saveLabelAdjustment(dialog));
    dialog.querySelector('[data-print-labels]')?.addEventListener('click', () => printLabels(dialog));
});

document.querySelector('[data-print-all-labels]')?.addEventListener('click', printAllOrderLabels);

function updateLabelCalculation(dialog, resetCount, preserveEmptyCount = false) {
    const quantity = Number(dialog.dataset.quantity);
    const standalone = dialog.dataset.standalone === 'true';
    const type = dialog.querySelector('[data-label-type]').value;
    const unitsInput = dialog.querySelector('[data-units-per-label]');
    const countInput = dialog.querySelector('[data-label-count]');
    const expectedUnits = Number(
        type === 'order'
            ? quantity
            : type === 'bulk'
            ? dialog.dataset.bulk
            : dialog.dataset.fractioned
    );

    if (unitsInput.dataset.lastType !== type) {
        const suggestedUnits = !standalone && quantity > 0 && expectedUnits > quantity
            ? quantity
            : expectedUnits;
        unitsInput.value = suggestedUnits > 0 ? suggestedUnits : '';
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
        dialog.querySelector('[data-preview-type]').textContent = type === 'order' ? 'PEDIDO' : (type === 'bulk' ? 'GRANEL' : 'FRACCIONADO');
        return;
    }

    const packagingUnits = expectedUnits > 0 ? expectedUnits : units;
    const exact = standalone || type === 'order' || quantity % packagingUnits === 0;
    const fractioned = type === 'fractioned';
    const calculatedCount = standalone ? 1 : Math.max(1, Math.ceil(quantity / units));
    if (resetCount || (!countInput.value && !preserveEmptyCount)) countInput.value = calculatedCount;

    const remainder = quantity % packagingUnits;
    const exceedsOrder = !standalone && units > quantity;
    alert.hidden = exact && !exceedsOrder;
    const fullBoxes = Math.floor(quantity / packagingUnits);
    const partialDescription = fullBoxes > 0
        ? `${fullBoxes} ${fullBoxes === 1 ? 'caja completa' : 'cajas completas'} y 1 caja parcial de ${remainder.toLocaleString('es-AR')} unidades`
        : `1 caja parcial de ${remainder.toLocaleString('es-AR')} unidades`;
    alert.innerHTML = exceedsOrder
        ? `<strong>La etiqueta supera la cantidad pedida.</strong><br>La orden pide ${quantity.toLocaleString('es-AR')} unidades y se imprimirán ${units.toLocaleString('es-AR')}. Podés continuar si se completará la caja.`
        : exact
        ? ''
        : fractioned
        ? `<strong>La cantidad pedida no es múltiplo de esta presentación fraccionada.</strong><br>Podés ajustar las unidades manualmente o imprimir la etiqueta igualmente.`
        : `<strong>La cantidad pedida no completa exactamente las cajas de granel.</strong><br>Se prepararán ${partialDescription}. Podés imprimir igualmente.`;

    dialog.querySelector('[data-label-help]').textContent = standalone
        ? 'Indicá cuántas cajas o etiquetas necesitás. Podés modificar tanto las unidades como el total antes de imprimir.'
        : type === 'order'
        ? `Se imprimirá 1 etiqueta con la cantidad exacta pedida: ${quantity.toLocaleString('es-AR')} unidades.`
        : fractioned
        ? `Se proponen ${calculatedCount} ${calculatedCount === 1 ? 'etiqueta fraccionada' : 'etiquetas fraccionadas'} de ${units.toLocaleString('es-AR')} unidades. Podés cambiar la cantidad manualmente.`
        : remainder
        ? `La presentación de ${packagingUnits.toLocaleString('es-AR')} no cierra con el pedido. Se proponen ${calculatedCount} etiquetas y la cantidad a imprimir puede ajustarse manualmente.`
        : `Se proponen ${calculatedCount} etiquetas de granel (${units.toLocaleString('es-AR')} unidades por etiqueta).`;
    dialog.querySelector('[data-preview-units]').textContent = `${units.toLocaleString('es-AR')} UNIDADES`;
    dialog.querySelector('[data-preview-type]').textContent = type === 'order' ? 'PEDIDO' : (type === 'bulk' ? 'GRANEL' : 'FRACCIONADO');
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
    const allowOverage = dialog.dataset.unitsManuallyEdited === 'true' || dialog.dataset.allowOverage === 'true';
    const size = dialog.querySelector('[data-label-size]')?.value || '80x50';
    const area = document.querySelector('#label-print-area');
    if (!area) return;

    area.innerHTML = Array.from({ length: count }, (_, index) => {
        const assigned = standalone
            ? units
            : type === 'fractioned'
            ? units
            : allowOverage
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
        const recommendation = recommendedPackaging(quantity, bulk, fractioned, dialog.dataset.exactOrder === 'true');
        let savedAdjustment = null;
        try {
            savedAdjustment = JSON.parse(dialog.dataset.savedAdjustment || 'null');
        } catch (_) {
            savedAdjustment = null;
        }
        const currentAdjusted = dialog.dataset.batchAdjusted === 'true';
        const selectedType = dialog.querySelector('[data-label-type]')?.value;
        const selectedUnits = Number(dialog.querySelector('[data-units-per-label]')?.value);
        const selectedCount = Number(dialog.querySelector('[data-label-count]')?.value);
        const savedUnits = Number(savedAdjustment?.units);
        const savedCount = Number(savedAdjustment?.count);
        const allowOverage = currentAdjusted
            ? dialog.dataset.allowOverage === 'true'
            : savedAdjustment?.allowOverage === true;
        const type = currentAdjusted
            ? selectedType
            : (savedAdjustment?.type || recommendation.type);
        const units = currentAdjusted && selectedUnits > 0
            ? selectedUnits
            : (savedUnits > 0 ? savedUnits : recommendation.units);
        const count = currentAdjusted && selectedCount > 0
            ? Math.floor(selectedCount)
            : (savedCount > 0
                ? Math.floor(savedCount)
                : (type === 'unconfigured' ? 1 : Math.max(1, Math.ceil(quantity / units))));

        return Array.from({ length: count }, (_, index) => {
            const assigned = type === 'bulk'
                ? (allowOverage ? units : Math.max(0, Math.min(units, quantity - (index * units))))
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

function recommendedPackaging(quantity, bulk, fractioned, exactOrder = false) {
    if (exactOrder && quantity > 0) return { type: 'order', units: quantity };
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
        dialog.dataset.exactOrder === 'true',
    );
    if (recommendation.type === 'unconfigured') return;
    const typeInput = dialog.querySelector('[data-label-type]');
    const unitsInput = dialog.querySelector('[data-units-per-label]');
    typeInput.value = recommendation.type;
    unitsInput.value = recommendation.units > Number(dialog.dataset.quantity)
        ? Number(dialog.dataset.quantity)
        : recommendation.units;
    unitsInput.dataset.lastType = recommendation.type;
    dialog.dataset.unitsManuallyEdited = 'false';
    dialog.dataset.allowOverage = 'false';
}

function markLabelAdjustmentDirty(dialog) {
    if (dialog.dataset.standalone === 'true') return;
    dialog.dataset.batchAdjusted = 'false';
    const button = dialog.querySelector('[data-save-label-adjustment]');
    if (button) button.textContent = 'Guardar ajuste';
}

async function saveLabelAdjustment(dialog) {
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

    const adjustment = {
        type,
        units,
        count: Math.floor(count),
        allowOverage: dialog.dataset.unitsManuallyEdited === 'true',
    };
    const button = dialog.querySelector('[data-save-label-adjustment]');
    if (button) {
        button.disabled = true;
        button.textContent = 'Guardando...';
    }
    if (dialog.dataset.saveUrl) {
        try {
            const response = await saveSharedAdjustmentWithRetry(dialog, adjustment, button);
            if (response.redirected || !response.headers.get('content-type')?.includes('application/json')) {
                throw new Error('La sesión venció. Actualizá la página e iniciá sesión nuevamente.');
            }
            const payload = await response.json();
            Object.assign(adjustment, payload.adjustment || {});
            dialog.dataset.savedAdjustment = JSON.stringify(adjustment);
        } catch (error) {
            if (button) {
                button.disabled = false;
                button.textContent = 'Reintentar guardado';
            }
            const alert = dialog.querySelector('[data-quantity-alert]');
            if (alert) {
                alert.hidden = false;
                alert.innerHTML = `<strong>No se pudo compartir el ajuste.</strong><br>${escapeHtml(error.message || 'Revisá la conexión e intentá guardarlo nuevamente.')}`;
            }
            return;
        }
    }
    dialog.dataset.batchAdjusted = 'true';
    dialog.dataset.allowOverage = String(adjustment.allowOverage);
    try {
        sessionStorage.setItem(labelAdjustmentKey(dialog), JSON.stringify(adjustment));
    } catch (_) {
        // It still remains saved in the current page when storage is unavailable.
    }
    if (button) {
        button.disabled = false;
        button.textContent = '✓ Ajuste compartido';
    }
    reflectLabelAdjustment(dialog, adjustment);
    dialog.close();
}

async function saveSharedAdjustmentWithRetry(dialog, adjustment, button) {
    const retryableStatuses = new Set([408, 425, 429]);
    let lastError;

    for (let attempt = 1; attempt <= 3; attempt += 1) {
        if (button) button.textContent = attempt === 1 ? 'Guardando...' : `Reintentando (${attempt}/3)...`;
        try {
            const response = await fetch(dialog.dataset.saveUrl, {
                method: 'PUT',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({
                    type: adjustment.type,
                    units: adjustment.units,
                    count: adjustment.count,
                    allow_overage: adjustment.allowOverage,
                    concept_id: dialog.dataset.conceptId || null,
                    code: dialog.dataset.code || null,
                    description: dialog.dataset.description || null,
                    line_index: Number(dialog.dataset.lineIndex),
                }),
            });

            if (response.ok) return response;
            if (response.status === 401 || response.status === 419) {
                throw new Error('La sesión venció. Actualizá la página e iniciá sesión nuevamente.');
            }
            if (!retryableStatuses.has(response.status) && response.status < 500) {
                throw new Error(`El servidor rechazó el ajuste (HTTP ${response.status}).`);
            }
            lastError = new Error(`Error temporal del servidor (HTTP ${response.status}).`);
        } catch (error) {
            if (error.message.includes('sesión venció') || error.message.includes('rechazó')) throw error;
            lastError = error;
        }

        if (attempt < 3) await new Promise((resolve) => window.setTimeout(resolve, attempt * 700));
    }

    throw lastError || new Error('Revisá la conexión e intentá guardarlo nuevamente.');
}

function reflectLabelAdjustment(dialog, adjustment) {
    const itemId = dialog.dataset.itemId;
    if (!itemId) return;
    const total = document.querySelector(`[data-item-box-total="${itemId}"]`);
    const quantity = document.querySelector(`[data-item-quantity="${itemId}"]`);
    if (!total) return;

    const count = Math.floor(Number(adjustment.count));
    const units = Number(adjustment.units);
    const type = adjustment.type === 'order' ? 'a pedido' : (adjustment.type === 'bulk' ? 'granel' : 'fraccionada');
    const typeLabel = type === 'granel' || type === 'a pedido' ? type : (count === 1 ? type : 'fraccionadas');
    const adjustedBy = adjustment.adjustedBy ? ` por ${escapeHtml(adjustment.adjustedBy)}` : '';
    total.innerHTML = `<strong>${count.toLocaleString('es-AR')}</strong><small>${count === 1 ? 'caja' : 'cajas'} ${typeLabel}</small><em class="packaging-adjusted" title="Ajustado${adjustedBy}">Ajustado · ${units.toLocaleString('es-AR')} u</em>`;
    quantity?.classList.remove('quantity-review');
    quantity?.classList.add('quantity-adjusted');
    if (quantity) quantity.title = `Presentación ajustada${adjustment.adjustedBy ? ` por ${adjustment.adjustedBy}` : ''}: ${count} ${count === 1 ? 'etiqueta' : 'etiquetas'} de ${units.toLocaleString('es-AR')} unidades`;
}

function restoreLabelAdjustment(dialog) {
    if (dialog.dataset.standalone === 'true') return;
    let adjustment = null;
    try {
        adjustment = dialog.dataset.savedAdjustment
            ? JSON.parse(dialog.dataset.savedAdjustment)
            : JSON.parse(sessionStorage.getItem(labelAdjustmentKey(dialog)) || 'null');
    } catch (_) { adjustment = null; }
    if (!adjustment || !['bulk', 'fractioned', 'order'].includes(adjustment.type)) return;
    if (!(Number(adjustment.units) > 0) || !(Number(adjustment.count) > 0)) return;

    dialog.querySelector('[data-label-type]').value = adjustment.type;
    dialog.querySelector('[data-units-per-label]').value = adjustment.units;
    dialog.querySelector('[data-label-count]').value = Math.floor(adjustment.count);
    dialog.querySelector('[data-units-per-label]').dataset.lastType = adjustment.type;
    dialog.dataset.batchAdjusted = 'true';
    dialog.dataset.allowOverage = String(adjustment.allowOverage === true);
    dialog.dataset.unitsManuallyEdited = String(adjustment.allowOverage === true);
    const button = dialog.querySelector('[data-save-label-adjustment]');
    if (button) button.textContent = dialog.dataset.savedAdjustment ? '✓ Ajuste compartido' : '✓ Ajuste guardado';
    updateLabelCalculation(dialog, false);
    reflectLabelAdjustment(dialog, adjustment);
}

function labelMarkup(data, type, assigned, position, total, standalone) {
    const customer = standalone ? '' : `<div class="thermal-customer">${escapeHtml(data.customer.toUpperCase())}</div>`;
    const reference = standalone ? '' : ` · OV ${escapeHtml(data.order)}`;
    const typeLabel = type === 'bulk' ? 'GRANEL' : (type === 'fractioned' ? 'FRACCIONADO' : 'PEDIDO');

    return `<article class="printed-label">
        ${customer}
        <div class="thermal-product">
            <strong>${formatLabelDescription(data.description)}</strong>
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

function formatLabelDescription(value = '') {
    return escapeHtml(value).replace(/(\d+)\s+[xX]\s+(\d+)/g, '$1&nbsp;X&nbsp;$2');
}
