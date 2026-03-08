export function refetchResults(button) {
    const messageEl = document.querySelector('.js-refetch-message');
    const icon = button.querySelector('.js-refetch-icon');

    button.addEventListener('click', async () => {
        const url = button.dataset.url;
        const csrf = button.dataset.csrf;

        button.disabled = true;
        button.classList.add('opacity-50', 'pointer-events-none');
        if (icon) icon.classList.add('animate-spin');

        messageEl.textContent = 'Fetching results...';
        messageEl.className = 'js-refetch-message text-xs text-slate-500';
        messageEl.classList.remove('hidden');

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
                messageEl.textContent = 'Results queued for update. Reloading...';
                messageEl.className = 'js-refetch-message text-xs text-emerald-600';
                setTimeout(() => window.location.reload(), 2000);
            } else {
                messageEl.textContent = data.error || 'Something went wrong.';
                messageEl.className = 'js-refetch-message text-xs text-red-500';
                button.disabled = false;
                button.classList.remove('opacity-50', 'pointer-events-none');
                if (icon) icon.classList.remove('animate-spin');
            }
        } catch (error) {
            messageEl.textContent = 'Network error. Please try again.';
            messageEl.className = 'js-refetch-message text-xs text-red-500';
            button.disabled = false;
            button.classList.remove('opacity-50', 'pointer-events-none');
            if (icon) icon.classList.remove('animate-spin');
        }
    });
}
