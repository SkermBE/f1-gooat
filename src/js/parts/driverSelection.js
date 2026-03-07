import { gsap } from 'gsap';

// --- Status Toast (success / error) ---

function showToast(message, type = 'info', duration = 3000) {
    const toast = document.getElementById('status-toast');
    const text = document.getElementById('status-toast-text');
    if (!toast || !text) return;

    const typeClasses = {
        success: ['bg-green-600', 'text-white'],
        error:   ['bg-red-600',   'text-white'],
        info:    ['bg-[#1E1E2E]', 'text-slate-200', 'border', 'border-[#2D2D3F]'],
    };

    // Reset type classes
    toast.className = 'fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-[9999] px-5 py-3 rounded-lg text-sm font-semibold shadow-xl pointer-events-none';
    (typeClasses[type] || typeClasses.info).forEach(cls => toast.classList.add(cls));

    text.textContent = message;

    gsap.fromTo(toast,
        { autoAlpha: 0, scale: 0.85 },
        { autoAlpha: 1, scale: 1, duration: 0.25, ease: 'back.out(1.7)' }
    );

    if (duration > 0) {
        gsap.to(toast, {
            autoAlpha: 0,
            scale: 0.85,
            duration: 0.2,
            ease: 'power2.in',
            delay: duration / 1000,
        });
    }
}

// --- Confirm Modal ---

function showConfirmModal(driverCode, onConfirm, onCancel) {
    const overlay = document.getElementById('confirm-overlay');
    const modal   = document.getElementById('confirm-modal');
    const text    = document.getElementById('confirm-modal-text');
    const confirmBtn = document.getElementById('confirm-modal-confirm');
    const cancelBtn  = document.getElementById('confirm-modal-cancel');

    text.textContent = `Pick ${driverCode} for P10?`;

    // Make visible before animating (gsap uses autoAlpha)
    gsap.set([overlay, modal], { autoAlpha: 0 });
    overlay.classList.remove('hidden');
    modal.classList.remove('hidden');

    // Animate overlay fade + modal pop
    gsap.to(overlay, { autoAlpha: 1, duration: 0.25, ease: 'power2.out' });
    gsap.fromTo(modal,
        { autoAlpha: 0, scale: 0.85, y: 12 },
        { autoAlpha: 1, scale: 1,    y: 0, duration: 0.3, ease: 'back.out(1.7)' }
    );

    // Clone buttons to clear old listeners
    const newConfirm = confirmBtn.cloneNode(true);
    const newCancel  = cancelBtn.cloneNode(true);
    confirmBtn.replaceWith(newConfirm);
    cancelBtn.replaceWith(newCancel);

    function close(action) {
        overlay.removeEventListener('click', onOverlayClick);
        gsap.to(overlay, { autoAlpha: 0, duration: 0.2 });
        gsap.to(modal, {
            autoAlpha: 0,
            scale: 0.9,
            y: 8,
            duration: 0.2,
            ease: 'power2.in',
            onComplete: () => {
                overlay.classList.add('hidden');
                modal.classList.add('hidden');
                action();
            },
        });
    }

    function onOverlayClick() { close(onCancel); }

    newConfirm.addEventListener('click', (e) => { e.stopPropagation(); close(onConfirm); });
    newCancel.addEventListener('click',  (e) => { e.stopPropagation(); close(onCancel);  });
    overlay.addEventListener('click', onOverlayClick);
}

function hideConfirmModal() {
    const overlay = document.getElementById('confirm-overlay');
    const modal   = document.getElementById('confirm-modal');
    if (overlay) overlay.classList.add('hidden');
    if (modal)   modal.classList.add('hidden');
}

// --- Driver Selection ---

export function driverSelection(gridElement) {
    const raceId      = gridElement.dataset.raceId;
    const csrfToken   = gridElement.dataset.csrf;
    const submitUrl   = gridElement.dataset.submitUrl;
    const isPlayerTurn = gridElement.dataset.isPlayerTurn === '1';

    if (!isPlayerTurn) return;

    const cards = gridElement.querySelectorAll('.F1DriverCard:not(.is-disabled)');
    let activeCard = null;

    function resetSelection() {
        hideConfirmModal();
        if (activeCard) activeCard.classList.remove('is-confirming');
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
                    raceId,
                    driverId,
                }),
            });

            const data = await response.json();

            if (data.success) {
                showToast(`${driverCode} confirmed!`, 'success');
                setTimeout(() => window.location.reload(), 1500);
            } else {
                showToast(data.error || 'Failed to submit prediction', 'error');
                cards.forEach(c => c.classList.remove('is-disabled'));
                card.classList.remove('is-selected');
            }
        } catch {
            showToast('Network error. Please try again.', 'error');
            cards.forEach(c => c.classList.remove('is-disabled'));
            card.classList.remove('is-selected');
        }
    }

    cards.forEach(card => {
        card.addEventListener('click', () => {
            const driverId   = card.dataset.driverId;
            const driverCode = card.querySelector('.js-driver-name')?.textContent?.trim();

            if (activeCard && activeCard !== card) resetSelection();
            if (activeCard === card) return;

            activeCard = card;
            card.classList.add('is-confirming');

            // Small pulse on the clicked card
            gsap.fromTo(card, { scale: 0.97 }, { scale: 1, duration: 0.25, ease: 'back.out(2)' });

            showConfirmModal(
                driverCode,
                () => {
                    activeCard = null;
                    submitPick(card, driverId, driverCode);
                },
                () => {
                    card.classList.remove('is-confirming');
                    activeCard = null;
                }
            );
        });
    });
}