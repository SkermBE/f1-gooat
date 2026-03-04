function getToastContainer() {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.style.cssText = 'position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;display:flex;flex-direction:column;gap:0.5rem;pointer-events:auto;';
        document.body.appendChild(container);
    }
    return container;
}

function showToast(message, type = 'info', duration = 3000) {
    const container = getToastContainer();

    const colors = {
        success: 'background:#16a34a;color:#fff;',
        error: 'background:#E10600;color:#fff;',
        info: 'background:#1E1E2E;color:#E0E0E0;border:1px solid #2D2D3F;',
    };

    const toast = document.createElement('div');
    toast.style.cssText = `${colors[type] || colors.info}padding:0.75rem 1.25rem;border-radius:0.5rem;font-size:0.875rem;font-weight:600;box-shadow:0 4px 12px rgba(0,0,0,0.3);transform:translateX(120%);transition:transform 0.3s ease;`;
    toast.textContent = message;
    container.appendChild(toast);

    requestAnimationFrame(() => { toast.style.transform = 'translateX(0)'; });

    if (duration > 0) {
        setTimeout(() => dismissToast(toast), duration);
    }

    return toast;
}

function dismissToast(toast) {
    if (!toast || !toast.parentNode) return;
    toast.style.transform = 'translateX(120%)';
    setTimeout(() => toast.remove(), 300);
}

function showConfirmToast(driverCode, onConfirm, onCancel) {
    const container = getToastContainer();

    const toast = document.createElement('div');
    toast.style.cssText = 'background:#1E1E2E;color:#E0E0E0;border:1px solid #2D2D3F;padding:1rem 1.25rem;border-radius:0.5rem;font-size:0.875rem;box-shadow:0 4px 16px rgba(0,0,0,0.4);transform:translateX(120%);transition:transform 0.3s ease;min-width:220px;';

    const text = document.createElement('div');
    text.style.cssText = 'font-weight:700;margin-bottom:0.75rem;font-size:0.9375rem;';
    text.textContent = `Pick ${driverCode} for P10?`;

    const buttons = document.createElement('div');
    buttons.style.cssText = 'display:flex;gap:0.5rem;';

    const confirmBtn = document.createElement('button');
    confirmBtn.textContent = 'Lock it in';
    confirmBtn.style.cssText = 'flex:1;background:#16a34a;color:#fff;border:none;padding:0.5rem 0.75rem;border-radius:0.375rem;font-weight:700;font-size:0.8125rem;cursor:pointer;transition:opacity 0.15s;';
    confirmBtn.onmouseenter = () => { confirmBtn.style.opacity = '0.85'; };
    confirmBtn.onmouseleave = () => { confirmBtn.style.opacity = '1'; };

    const cancelBtn = document.createElement('button');
    cancelBtn.textContent = 'Cancel';
    cancelBtn.style.cssText = 'flex:1;background:transparent;color:#8B8B9E;border:1px solid #2D2D3F;padding:0.5rem 0.75rem;border-radius:0.375rem;font-weight:600;font-size:0.8125rem;cursor:pointer;transition:opacity 0.15s;';
    cancelBtn.onmouseenter = () => { cancelBtn.style.opacity = '0.7'; };
    cancelBtn.onmouseleave = () => { cancelBtn.style.opacity = '1'; };

    confirmBtn.addEventListener('click', () => {
        dismissToast(toast);
        onConfirm();
    });

    cancelBtn.addEventListener('click', () => {
        dismissToast(toast);
        onCancel();
    });

    buttons.appendChild(confirmBtn);
    buttons.appendChild(cancelBtn);
    toast.appendChild(text);
    toast.appendChild(buttons);
    container.appendChild(toast);

    requestAnimationFrame(() => { toast.style.transform = 'translateX(0)'; });

    return toast;
}

export function driverSelection(gridElement) {
    const raceId = gridElement.dataset.raceId;
    const csrfToken = gridElement.dataset.csrf;
    const submitUrl = gridElement.dataset.submitUrl;
    const isPlayerTurn = gridElement.dataset.isPlayerTurn === '1';

    if (!isPlayerTurn) return;

    const cards = gridElement.querySelectorAll('.F1DriverCard:not(.is-disabled)');
    let activeToast = null;
    let activeCard = null;

    function resetSelection() {
        if (activeToast) dismissToast(activeToast);
        if (activeCard) activeCard.classList.remove('is-confirming');
        activeToast = null;
        activeCard = null;
    }

    async function submitPick(card, driverId, driverCode) {
        cards.forEach(c => c.classList.add('is-disabled'));
        card.classList.add('is-selected');
        card.classList.remove('is-confirming');

        try {
            const response = await fetch(submitUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'Accept': 'application/json',
                },
                body: new URLSearchParams({
                    'CRAFT_CSRF_TOKEN': csrfToken,
                    raceId: raceId,
                    driverId: driverId,
                }),
            });

            const data = await response.json();

            if (data.success) {
                showToast(`${driverCode} locked in!`, 'success');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showToast(data.error || 'Failed to submit prediction', 'error');
                cards.forEach(c => c.classList.remove('is-disabled'));
                card.classList.remove('is-selected');
            }
        } catch (error) {
            showToast('Network error. Please try again.', 'error');
            cards.forEach(c => c.classList.remove('is-disabled'));
            card.classList.remove('is-selected');
        }
    }

    cards.forEach(card => {
        card.addEventListener('click', () => {
            const driverId = card.dataset.driverId;
            const driverCode = card.querySelector('.font-black')?.textContent?.trim();

            // If clicking a different card, reset previous
            if (activeCard && activeCard !== card) {
                resetSelection();
            }

            // If this card is already pending, ignore (toast buttons handle it)
            if (activeCard === card) return;

            activeCard = card;
            card.classList.add('is-confirming');

            activeToast = showConfirmToast(
                driverCode,
                () => {
                    activeToast = null;
                    activeCard = null;
                    submitPick(card, driverId, driverCode);
                },
                () => {
                    card.classList.remove('is-confirming');
                    activeToast = null;
                    activeCard = null;
                }
            );
        });
    });
}
