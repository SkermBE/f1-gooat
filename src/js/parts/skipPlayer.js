import { gsap } from 'gsap';

function showToast(message, type = 'info', duration = 3000) {
    const toast = document.getElementById('status-toast');
    const text = document.getElementById('status-toast-text');
    if (!toast || !text) return;

    const typeClasses = {
        success: ['bg-green-600', 'text-white'],
        error:   ['bg-red-600',   'text-white'],
        info:    ['bg-[#1E1E2E]', 'text-slate-200', 'border', 'border-[#2D2D3F]'],
    };

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

function showConfirmModal(message, onConfirm, onCancel) {
    const overlay = document.getElementById('confirm-overlay');
    const modal   = document.getElementById('confirm-modal');
    const text    = document.getElementById('confirm-modal-text');
    const confirmBtn = document.getElementById('confirm-modal-confirm');
    const cancelBtn  = document.getElementById('confirm-modal-cancel');

    text.textContent = message;

    gsap.set([overlay, modal], { autoAlpha: 0 });
    overlay.classList.remove('hidden');
    modal.classList.remove('hidden');

    gsap.to(overlay, { autoAlpha: 1, duration: 0.25, ease: 'power2.out' });
    gsap.fromTo(modal,
        { autoAlpha: 0, scale: 0.85, y: 12 },
        { autoAlpha: 1, scale: 1, y: 0, duration: 0.3, ease: 'back.out(1.7)' }
    );

    const newConfirm = confirmBtn.cloneNode(true);
    const newCancel  = cancelBtn.cloneNode(true);
    confirmBtn.replaceWith(newConfirm);
    cancelBtn.replaceWith(newCancel);

    function close(action) {
        overlay.removeEventListener('click', onOverlayClick);
        gsap.to(overlay, { autoAlpha: 0, duration: 0.2 });
        gsap.to(modal, {
            autoAlpha: 0, scale: 0.9, y: 8, duration: 0.2, ease: 'power2.in',
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

export function skipPlayer(button) {
    const raceId = button.dataset.raceId;
    const skipUrl = button.dataset.skipUrl;
    const csrfToken = button.dataset.csrf;
    const playerName = button.dataset.playerName;

    button.addEventListener('click', () => {
        showConfirmModal(
            `Skip ${playerName}'s turn? They will receive 0 points for this race.`,
            async () => {
                button.disabled = true;
                button.textContent = 'Skipping...';

                try {
                    const response = await fetch(skipUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'Accept': 'application/json',
                        },
                        body: new URLSearchParams({
                            'CRAFT_CSRF_TOKEN': csrfToken,
                            raceId,
                        }),
                    });

                    const data = await response.json();

                    if (data.success) {
                        showToast(`${data.skippedPlayer} has been skipped`, 'success');
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        showToast(data.error || 'Failed to skip player', 'error');
                        button.disabled = false;
                        button.textContent = `Skip ${playerName}`;
                    }
                } catch {
                    showToast('Network error. Please try again.', 'error');
                    button.disabled = false;
                    button.textContent = `Skip ${playerName}`;
                }
            },
            () => {} // cancel — do nothing
        );
    });
}
