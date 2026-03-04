export function driverSelection(gridElement) {
    const raceId = gridElement.dataset.raceId;
    const csrfToken = gridElement.dataset.csrf;
    const submitUrl = gridElement.dataset.submitUrl;
    const isPlayerTurn = gridElement.dataset.isPlayerTurn === '1';

    if (!isPlayerTurn) return;

    const cards = gridElement.querySelectorAll('.F1DriverCard:not(.is-disabled)');

    cards.forEach(card => {
        card.addEventListener('click', async () => {
            const driverId = card.dataset.driverId;
            const driverCode = card.querySelector('.font-black')?.textContent?.trim();

            if (!confirm(`Select ${driverCode} for P10?`)) return;

            // Disable all cards during submission
            cards.forEach(c => c.classList.add('is-disabled'));
            card.classList.add('is-selected');

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
                    window.location.reload();
                } else {
                    alert(data.error || 'Failed to submit prediction');
                    cards.forEach(c => c.classList.remove('is-disabled'));
                    card.classList.remove('is-selected');
                }
            } catch (error) {
                alert('Network error. Please try again.');
                cards.forEach(c => c.classList.remove('is-disabled'));
                card.classList.remove('is-selected');
            }
        });
    });
}