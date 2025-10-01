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

    // Copy password buttons in table
    const copyButtons = document.querySelectorAll('.copy-btn');
    if (copyButtons && copyButtons.length) {
        const copyText = async (text) => {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                await navigator.clipboard.writeText(text);
                return true;
            }
            // Fallback: use a hidden textarea
            try {
                const ta = document.createElement('textarea');
                ta.value = text;
                ta.setAttribute('readonly', '');
                ta.style.position = 'absolute';
                ta.style.left = '-9999px';
                document.body.appendChild(ta);
                ta.select();
                const ok = document.execCommand('copy');
                document.body.removeChild(ta);
                return ok;
            } catch (_) {
                return false;
            }
        };

        const giveFeedback = (btn, ok) => {
            const prevTitle = btn.getAttribute('title') || '';
            btn.classList.add(ok ? 'copied' : 'copy-error');
            btn.setAttribute('title', ok ? 'Copiado' : 'Error al copiar');
            // Optionally announce via aria-live in future; for now, quick visual state
            setTimeout(() => {
                btn.classList.remove('copied', 'copy-error');
                btn.setAttribute('title', prevTitle || 'Copiar');
            }, 1500);
        };

        copyButtons.forEach(btn => {
            btn.addEventListener('click', async () => {
                const text = btn.getAttribute('data-password') || '';
                const ok = await copyText(text);
                giveFeedback(btn, ok);
                if (!ok) {
                    alert('No se pudo copiar la contraseña.');
                }
            });
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

    // ===== introducir.php enhancements (CSP-compliant, no inline JS) =====
    const introducirForm = document.getElementById('form-introducir');
    if (introducirForm) {
        // Format link on submit (ensure protocol)
        introducirForm.addEventListener('submit', function() {
            const linkInput = document.getElementById('enlace');
            if (linkInput) {
                const val = (linkInput.value || '').trim();
                if (val && !/^https?:\/\//i.test(val)) {
                    linkInput.value = 'https://' + val;
                }
            }
        });

        // Toggle password visibility
        const btnToggle = document.getElementById('btn-toggle-password');
        const pwdInput = document.getElementById('password');
        if (btnToggle && pwdInput) {
            btnToggle.addEventListener('click', function() {
                const isHidden = pwdInput.type === 'password';
                pwdInput.type = isHidden ? 'text' : 'password';
                // Update button label for better UX
                btnToggle.textContent = isHidden ? 'Ocultar' : 'Mostrar';
            });
        }

        // Paste password from clipboard
        const btnPaste = document.getElementById('btn-paste-password');
        if (btnPaste && pwdInput && navigator.clipboard && navigator.clipboard.readText) {
            btnPaste.addEventListener('click', function() {
                navigator.clipboard.readText()
                    .then(text => { pwdInput.value = text; })
                    .catch(() => {
                        alert('No se pudo pegar la contraseña. Asegúrate de que el portapapeles tenga texto.');
                    });
            });
        }

        // Assignees helpers (Asignar a todos / Quitar todos)
        const list = document.querySelector('.assignees-list');
        const btnAll = document.getElementById('assign-all');
        const btnNone = document.getElementById('assign-none');
        if (list && btnAll && btnNone) {
            btnAll.addEventListener('click', () => {
                list.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = true);
            });
            btnNone.addEventListener('click', () => {
                list.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
            });
        }
    }
});
