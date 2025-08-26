document.addEventListener('DOMContentLoaded', function() {
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';
    // Handle delete buttons
    const deleteButtons = document.querySelectorAll('.delete-btn');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const passwordId = this.getAttribute('data-id');

            // Confirm deletion
            if (confirm('¿Estás seguro de que deseas eliminar esta contraseña?')) {
                // Send a request to delete the password
                fetch('delete-password.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-Token': csrfToken,
                    },
                    body: `id=${encodeURIComponent(passwordId)}&csrf_token=${encodeURIComponent(csrfToken)}`
                })
                .then(response => response.text())
                .then(data => {
                    if (data === 'success') {
                        // Reload the page to reflect the changes
                        location.reload();
                    } else {
                        alert('Error al eliminar la contraseña.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
            }
        });
    });

    // Handle clear search button
    const clearSearchButton = document.getElementById('clear-search');
    if (clearSearchButton) {
        clearSearchButton.addEventListener('click', function() {
            window.location.href = 'ver-passwords.php';
        });
    }

    // Auto-submit search with debounce
    const searchForm = document.querySelector('form.search-form');
    const searchInput = document.getElementById('search');
    if (searchForm && searchInput) {
        // Focus input for quicker UX and keep caret at end
        try {
            searchInput.focus();
            const val = searchInput.value;
            // Place caret at the end to avoid jumping to start on reload
            if (typeof searchInput.setSelectionRange === 'function') {
                // setSelectionRange may require a tick after focus
                setTimeout(() => {
                    try { searchInput.setSelectionRange(val.length, val.length); } catch (_) {}
                }, 0);
            }
        } catch (e) {}

        const debounce = (fn, delay = 250) => {
            let t;
            return (...args) => {
                clearTimeout(t);
                t = setTimeout(() => fn.apply(null, args), delay);
            };
        };

        const submitForm = () => {
            // Use native submit to preserve GET semantics
            searchForm.requestSubmit ? searchForm.requestSubmit() : searchForm.submit();
        };

        const debouncedSubmit = debounce(submitForm, 250);

        // Submit when typing stops
        searchInput.addEventListener('input', () => {
            // If field cleared entirely, navigate to base to remove query param
            if (!searchInput.value.trim()) {
                // Avoid submitting empty query repeatedly; just go to base URL
                window.location.href = 'ver-passwords.php';
                return;
            }
            debouncedSubmit();
        });

        // Pressing Enter submits immediately (default). Escape clears
        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                e.preventDefault();
                window.location.href = 'ver-passwords.php';
            }
        });
    }
});
