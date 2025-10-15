document.addEventListener('DOMContentLoaded', function() {
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';
    // Handle password table delete buttons (only when data-id exists)
    const deleteButtons = document.querySelectorAll('.delete-btn');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            const passwordId = this.getAttribute('data-id');
            if (!passwordId) {
                // Not a password row (e.g., admin users delete button inside a form)
                return; // let the form submit normally
            }
            e.preventDefault();
            // Confirm deletion
            if (confirm('¿Estás seguro de que deseas eliminar esta contraseña?')) {
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

    // ===== Admin Users: create/edit form UX (initialized on load) =====
    const initAdminUsers = () => {
        const adminFormSection = document.getElementById('admin-user-form');
        const adminForm = document.getElementById('form-admin-user');
        const btnNewUser = document.getElementById('btn-new-user');
        if (!(adminFormSection && adminForm && btnNewUser)) return;
        const formTitle = document.getElementById('form-title');
        const formAction = document.getElementById('form-action');
        const formId = document.getElementById('form-id');
        const emailInput = document.getElementById('email');
        const roleSelect = document.getElementById('role');
        const pwdInput = document.getElementById('password');
        const pwdToggle = document.getElementById('btn-toggle-password');
        const confirmInput = document.getElementById('confirm_password');
        const confirmToggle = document.getElementById('btn-toggle-confirm');
        const btnCancel = document.getElementById('btn-cancel-form');
        const emailErr = document.getElementById('email-error');
        const pwdErr = document.getElementById('password-error');
        const confirmErr = document.getElementById('confirm-error');

        const showError = (el, msg) => {
            if (!el) return;
            el.textContent = msg || '';
            el.classList.toggle('hidden', !msg);
        };
        const hideErrors = () => {
            showError(emailErr, '');
            showError(pwdErr, '');
            showError(confirmErr, '');
        };
        const toggleField = (input, btn) => {
            if (!input || !btn) return;
            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            btn.textContent = isHidden ? 'Ocultar' : 'Mostrar';
        };
        const openCreate = () => {
            formTitle.textContent = 'Crear usuario';
            formAction.value = 'create';
            formId.value = '';
            emailInput.value = '';
            roleSelect.value = 'editor';
            pwdInput.value = '';
            confirmInput.value = '';
            pwdInput.required = true;
            confirmInput.required = true;
            hideErrors();
            adminFormSection.classList.remove('hidden');
        };
        const openEdit = (id, email, role) => {
            formTitle.textContent = 'Editar usuario';
            formAction.value = 'update';
            formId.value = String(id || '');
            emailInput.value = email || '';
            roleSelect.value = role || 'editor';
            pwdInput.value = '';
            confirmInput.value = '';
            pwdInput.required = false;
            confirmInput.required = false;
            hideErrors();
            adminFormSection.classList.remove('hidden');
        };
        const hideForm = () => { adminFormSection.classList.add('hidden'); };

        btnNewUser.addEventListener('click', (e) => { e.preventDefault(); openCreate(); });
        document.querySelectorAll('a.modify-btn[data-id]').forEach(a => {
            a.addEventListener('click', (e) => {
                e.preventDefault();
                openEdit(a.getAttribute('data-id'), a.getAttribute('data-email'), a.getAttribute('data-role'));
            });
        });
        if (pwdToggle) pwdToggle.addEventListener('click', () => toggleField(pwdInput, pwdToggle));
        if (confirmToggle) confirmToggle.addEventListener('click', () => toggleField(confirmInput, confirmToggle));
        if (btnCancel) btnCancel.addEventListener('click', (e) => {
            e.preventDefault();
            hideForm();
            if (window.location.search.includes('edit=')) {
                const url = new URL(window.location.href);
                url.searchParams.delete('edit');
                window.history.replaceState({}, '', url.toString());
            }
        });
        adminForm.addEventListener('submit', (e) => {
            hideErrors();
            let ok = true;
            const emailVal = (emailInput.value || '').trim();
            const isCreate = formAction.value === 'create';
            const pwdVal = pwdInput.value || '';
            const confirmVal = confirmInput.value || '';
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(emailVal)) { showError(emailErr, 'Introduce un email válido'); ok = false; }
            if (isCreate) {
                if (pwdVal.length < 8) { showError(pwdErr, 'La contraseña debe tener al menos 8 caracteres'); ok = false; }
                if (pwdVal !== confirmVal) { showError(confirmErr, 'Las contraseñas no coinciden'); ok = false; }
            } else if (pwdVal) {
                if (pwdVal.length < 8) { showError(pwdErr, 'La contraseña debe tener al menos 8 caracteres'); ok = false; }
                if (pwdVal !== confirmVal) { showError(confirmErr, 'Las contraseñas no coinciden'); ok = false; }
            }
            if (!ok) { e.preventDefault(); e.stopPropagation(); }
        });
    };

    // Initialize Admin Users form behaviors on load
    initAdminUsers();
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

    // Generic confirm for forms using data-confirm (CSP-friendly, no inline handlers)
    const confirmForms = document.querySelectorAll('form[data-confirm]');
    confirmForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const msg = form.getAttribute('data-confirm') || '¿Confirmar la acción?';
            if (!confirm(msg)) {
                e.preventDefault();
                e.stopPropagation();
            }
        });
    });

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

        const getCheckSvg = () => (
            `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M20 6L9 17l-5-5"></path>
            </svg>`
        );

        const giveFeedback = (btn, ok) => {
            const prevTitle = btn.getAttribute('title') || '';
            const prevAria = btn.getAttribute('aria-label') || '';
            const prevHtml = btn.innerHTML;
            btn.classList.add(ok ? 'copied' : 'copy-error');
            btn.setAttribute('title', ok ? 'Copiado' : 'Error al copiar');
            btn.setAttribute('aria-label', ok ? 'Copiado' : 'Error al copiar');
            if (ok) {
                // Swap icon to check for a short period
                btn.innerHTML = getCheckSvg();
            }
            // Optionally announce via aria-live in future; for now, quick visual state
            setTimeout(() => {
                btn.classList.remove('copied', 'copy-error');
                btn.setAttribute('title', prevTitle || 'Copiar');
                btn.setAttribute('aria-label', prevAria || 'Copiar contraseña');
                if (ok) {
                    // Restore original icon
                    btn.innerHTML = prevHtml;
                }
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
