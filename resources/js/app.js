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
        updateLabelCalculation(dialog, true);
        dialog.showModal();
    });
});

document.querySelectorAll('[data-dialog-close]').forEach((button) => {
    button.addEventListener('click', () => button.closest('dialog')?.close());
});

document.querySelectorAll('[data-label-dialog]').forEach((dialog) => {
    dialog.querySelector('[data-label-type]')?.addEventListener('change', () => updateLabelCalculation(dialog, true));
    dialog.querySelector('[data-units-per-label]')?.addEventListener('input', () => updateLabelCalculation(dialog, true));
    dialog.querySelector('[data-label-count]')?.addEventListener('input', () => updateLabelCalculation(dialog, false));
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
    const calculatedCount = standalone ? 1 : (fractioned ? 1 : Math.ceil(quantity / units));
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
        ? 'Para fraccionado se propone una sola etiqueta. Podés cambiar la cantidad manualmente.'
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
    openPrintDialog(area);
}

function printAllOrderLabels() {
    const dialogs = Array.from(document.querySelectorAll('[data-label-dialog]:not([data-standalone="true"])'));
    const area = document.querySelector('#label-print-area');
    if (!area || dialogs.length === 0) return;

    area.innerHTML = dialogs.map((dialog) => {
        const quantity = Number(dialog.dataset.quantity);
        const bulk = Number(dialog.dataset.bulk);
        const fractioned = Number(dialog.dataset.fractioned);
        const type = bulk > 0 ? 'bulk' : (fractioned > 0 ? 'fractioned' : 'unconfigured');
        const units = bulk > 0 ? bulk : (fractioned > 0 ? fractioned : quantity);
        const count = type === 'bulk' ? Math.max(1, Math.ceil(quantity / units)) : 1;

        return Array.from({ length: count }, (_, index) => {
            const assigned = type === 'bulk'
                ? Math.max(0, Math.min(units, quantity - (index * units)))
                : (type === 'fractioned' ? units : quantity);
            return labelMarkup(dialog.dataset, type, assigned, index + 1, count, false);
        }).join('');
    }).join('');

    openPrintDialog(area);
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
            <div><strong>IRON<br>SCREW</strong><small>TORNILLOS AUTOPERFORANTES</small></div>
            <em>${typeLabel}${reference} · ${position}/${total}</em>
        </div>
    </article>`;
}

function openPrintDialog(area) {
    const images = Array.from(area.querySelectorAll('img'));
    Promise.all(images.map((image) => image.complete ? Promise.resolve() : new Promise((resolve) => {
        image.addEventListener('load', resolve, { once: true });
        image.addEventListener('error', resolve, { once: true });
    }))).then(() => window.print());
}

function escapeHtml(value = '') {
    const element = document.createElement('span');
    element.textContent = value;
    return element.innerHTML;
}
