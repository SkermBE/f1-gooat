export function adminActions() {
    const buttons = document.querySelectorAll('.js-admin-action');
    const messageEl = document.querySelector('#adminMessage');

    if (!buttons.length) return;

    function showMessage(text, isError = false) {
        messageEl.textContent = text;
        messageEl.className = `text-sm ${isError ? 'text-primary' : 'text-f1-success'}`;
        messageEl.classList.remove('hidden');
    }

    buttons.forEach(button => {
        button.addEventListener('click', async () => {
            const url = button.dataset.url;
            const csrf = button.dataset.csrf;
            const label = button.textContent.trim();

            // Disable all buttons during request
            buttons.forEach(b => {
                b.disabled = true;
                b.classList.add('opacity-50', 'pointer-events-none');
            });
            showMessage(`${label}...`);

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'Accept': 'application/json',
                    },
                    body: new URLSearchParams({
                        'CRAFT_CSRF_TOKEN': csrf,
                    }),
                });

                const data = await response.json();

                if (data.success) {
                    showMessage(data.message);
                } else {
                    showMessage(data.error || 'Something went wrong.', true);
                }
            } catch (error) {
                showMessage('Network error. Please try again.', true);
            } finally {
                buttons.forEach(b => {
                    b.disabled = false;
                    b.classList.remove('opacity-50', 'pointer-events-none');
                });
            }
        });
    });
}