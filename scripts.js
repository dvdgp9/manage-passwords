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

    // ===== Admin Users: Modal-based create/edit =====
    const initAdminUsersModal = () => {
        const modal = document.getElementById('modal-user');
        const adminForm = document.getElementById('form-admin-user');
        const btnNewUser = document.getElementById('btn-new-user');
        if (!modal || !adminForm || !btnNewUser) return;

        const modalTitle = document.getElementById('modal-user-title');
        const formAction = document.getElementById('form-action');
        const formId = document.getElementById('form-id');
        const nombreInput = adminForm.querySelector('#nombre');
        const apellidosInput = adminForm.querySelector('#apellidos');
        const emailInput = adminForm.querySelector('#email');
        const roleSelect = adminForm.querySelector('#role');
        const pwdInput = adminForm.querySelector('#password');
        const confirmInput = adminForm.querySelector('#confirm_password');
        const passwordLabel = document.getElementById('password-label');
        const deptsContainer = document.getElementById('user-depts-container');
        const submitBtn = document.getElementById('btn-submit-user');

        const openModal = () => modal.classList.remove('hidden');
        const closeModal = () => {
            modal.classList.add('hidden');
            adminForm.reset();
        };

        const openCreate = () => {
            modalTitle.textContent = 'Nuevo usuario';
            formAction.value = 'create';
            formId.value = '';
            if (nombreInput) nombreInput.value = '';
            if (apellidosInput) apellidosInput.value = '';
            emailInput.value = '';
            roleSelect.value = 'editor';
            pwdInput.value = '';
            confirmInput.value = '';
            pwdInput.required = true;
            confirmInput.required = true;
            passwordLabel.textContent = 'Contraseña';
            if (deptsContainer) {
                deptsContainer.style.display = 'block';
                deptsContainer.querySelectorAll('input[type="checkbox"]').forEach(cb => { cb.checked = false; });
            }
            submitBtn.innerHTML = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg> Crear usuario`;
            openModal();
            emailInput.focus();
        };

        const openEdit = async (id, email, role, nombre, apellidos) => {
            modalTitle.textContent = 'Editar usuario';
            formAction.value = 'update';
            formId.value = String(id || '');
            if (nombreInput) nombreInput.value = nombre || '';
            if (apellidosInput) apellidosInput.value = apellidos || '';
            emailInput.value = email || '';
            roleSelect.value = role || 'editor';
            pwdInput.value = '';
            confirmInput.value = '';
            pwdInput.required = false;
            confirmInput.required = false;
            passwordLabel.textContent = 'Nueva contraseña (opcional)';
            submitBtn.innerHTML = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg> Guardar cambios`;
            
            // Show departments container for editing
            if (deptsContainer) {
                deptsContainer.style.display = 'block';
                // Fetch user's current departments and check them
                try {
                    const res = await fetch(`api-departments.php?action=user_departments&user_id=${id}`);
                    const data = await res.json();
                    if (data.success) {
                        const userDeptIds = (data.departments || []).map(d => d.id);
                        deptsContainer.querySelectorAll('input[type="checkbox"]').forEach(cb => {
                            cb.checked = userDeptIds.includes(parseInt(cb.value));
                        });
                    }
                } catch (e) {
                    console.error('Error loading user departments:', e);
                }
            }
            
            openModal();
            emailInput.focus();
        };

        // Open create modal
        btnNewUser.addEventListener('click', (e) => { e.preventDefault(); openCreate(); });

        // Edit buttons in table
        document.querySelectorAll('.tabla-usuarios a.modify-btn[data-id]').forEach(a => {
            a.addEventListener('click', (e) => {
                e.preventDefault();
                openEdit(
                    a.getAttribute('data-id'),
                    a.getAttribute('data-email'),
                    a.getAttribute('data-role'),
                    a.getAttribute('data-nombre'),
                    a.getAttribute('data-apellidos')
                );
            });
        });

        // Close modal buttons
        modal.querySelectorAll('[data-close-modal]').forEach(btn => {
            btn.addEventListener('click', closeModal);
        });

        // Close on backdrop click
        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeModal();
        });

        // Form validation on submit
        adminForm.addEventListener('submit', (e) => {
            const emailVal = (emailInput.value || '').trim();
            const isCreate = formAction.value === 'create';
            const pwdVal = pwdInput.value || '';
            const confirmVal = confirmInput.value || '';
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            let errors = [];
            if (!emailRegex.test(emailVal)) errors.push('Introduce un email válido');
            if (isCreate && pwdVal.length < 8) errors.push('La contraseña debe tener al menos 8 caracteres');
            if (pwdVal && pwdVal !== confirmVal) errors.push('Las contraseñas no coinciden');
            if (!isCreate && pwdVal && pwdVal.length < 8) errors.push('La contraseña debe tener al menos 8 caracteres');
            
            if (errors.length > 0) {
                e.preventDefault();
                alert(errors.join('\n'));
            }
        });
    };

    // Initialize Admin Users modal
    initAdminUsersModal();

    // Toggle password visibility - event delegation on document body
    document.body.addEventListener('click', function(e) {
        var btn = e.target;
        // Check if clicked element is one of the toggle buttons
        if (btn.id !== 'btn-toggle-password' && btn.id !== 'btn-toggle-confirm') {
            return;
        }
        e.preventDefault();
        var group = btn.parentElement;
        if (!group || !group.classList.contains('password-group')) {
            return;
        }
        var input = group.querySelector('input');
        if (!input) return;
        if (input.type === 'password') {
            input.type = 'text';
            btn.textContent = 'Ocultar';
        } else {
            input.type = 'password';
            btn.textContent = 'Mostrar';
        }
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

    // Toggle password visibility in table
    const togglePasswordButtons = document.querySelectorAll('.toggle-password-btn');
    if (togglePasswordButtons && togglePasswordButtons.length) {
        const eyeIconSVG = `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
            <circle cx="12" cy="12" r="3"></circle>
        </svg>`;
        
        const eyeOffIconSVG = `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
            <line x1="1" y1="1" x2="23" y2="23"></line>
        </svg>`;

        togglePasswordButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                const cell = btn.closest('.password-cell');
                if (!cell) return;
                
                const passwordText = cell.querySelector('.password-text');
                if (!passwordText) return;
                
                const realPassword = passwordText.getAttribute('data-password') || '';
                const isHidden = passwordText.textContent === '••••••••';
                
                if (isHidden) {
                    // Mostrar contraseña
                    passwordText.textContent = realPassword;
                    btn.innerHTML = eyeOffIconSVG;
                    btn.setAttribute('title', 'Ocultar contraseña');
                    btn.setAttribute('aria-label', 'Ocultar contraseña');
                    btn.classList.add('showing');
                } else {
                    // Ocultar contraseña
                    passwordText.textContent = '••••••••';
                    btn.innerHTML = eyeIconSVG;
                    btn.setAttribute('title', 'Mostrar contraseña');
                    btn.setAttribute('aria-label', 'Mostrar contraseña');
                    btn.classList.remove('showing');
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

        // Búsqueda automática con debounce más largo para no molestar al escribir.
        const debounce = (fn, delay = 800) => {
            let t;
            return (...args) => {
                clearTimeout(t);
                t = setTimeout(() => fn.apply(null, args), delay);
            };
        };

        const submitForm = () => {
            // Usamos submit nativo para mantener la semántica GET
            if (!searchInput.value.trim()) {
                // Si está vacío, volvemos a la vista base sin parámetros
                window.location.href = 'ver-passwords.php';
                return;
            }
            searchForm.requestSubmit ? searchForm.requestSubmit() : searchForm.submit();
        };

        const debouncedSubmit = debounce(submitForm, 800);

        // Lanzar búsqueda cuando el usuario deje de escribir un momento
        searchInput.addEventListener('input', () => {
            debouncedSubmit();
        });

        // Escape limpia y vuelve a la vista base rápidamente
        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                e.preventDefault();
                window.location.href = 'ver-passwords.php';
            }
        });
    }

    // ===== Form enhancements (introducir.php, edit-password.php) =====
    // Format link on submit (ensure protocol) - universal for both forms
    const passwordForms = document.querySelectorAll('#form-introducir, #form-edit-password');
    passwordForms.forEach(form => {
        form.addEventListener('submit', function() {
            const linkInput = document.getElementById('enlace');
            if (linkInput) {
                const val = (linkInput.value || '').trim();
                if (val && !/^https?:\/\//i.test(val)) {
                    linkInput.value = 'https://' + val;
                }
            }
        });
    });

    // Paste password from clipboard - universal
    const btnPaste = document.getElementById('btn-paste-password');
    const pasteTarget = document.getElementById('password');
    if (btnPaste && pasteTarget && navigator.clipboard && navigator.clipboard.readText) {
        btnPaste.addEventListener('click', function() {
            navigator.clipboard.readText()
                .then(text => { pasteTarget.value = text; })
                .catch(() => {
                    alert('No se pudo pegar la contraseña. Asegúrate de que el portapapeles tenga texto.');
                });
        });
    }

    // Assignees helpers (Asignar a todos / Quitar todos) - for introducir.php and edit-password.php
    const introducirForm = document.getElementById('form-introducir');
    const editPasswordForm = document.getElementById('form-edit-password');
    
    if (introducirForm || editPasswordForm) {
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

        // Departments helpers
        const deptList = document.getElementById('departments-list');
        const btnAllDepts = document.getElementById('assign-all-depts');
        const btnNoneDepts = document.getElementById('assign-none-depts');
        if (deptList && btnAllDepts && btnNoneDepts) {
            btnAllDepts.addEventListener('click', () => {
                deptList.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = true);
            });
            btnNoneDepts.addEventListener('click', () => {
                deptList.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
            });
        }
    }

    // ======= Departments Management (admin-users.php) =======
    const departmentsSection = document.getElementById('departments-tbody');
    if (departmentsSection) {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const deptModal = document.getElementById('modal-department');
        const deptForm = document.getElementById('form-department');
        const deptTbody = document.getElementById('departments-tbody');
        const modal = document.getElementById('modal-assign-users');
        
        let currentDepartmentId = null;
        let allUsers = [];

        // Cargar todos los usuarios para el modal
        async function loadAllUsers() {
            try {
                // Reutilizar la tabla de usuarios ya cargada en la página
                const userRows = document.querySelectorAll('.tabla-usuarios tbody tr');
                allUsers = [];
                userRows.forEach(row => {
                    const cells = row.querySelectorAll('td');
                    if (cells.length >= 3) {
                        const id = parseInt(cells[0].textContent.trim());
                        const email = cells[1].textContent.trim();
                        const role = cells[2].textContent.trim();
                        if (id > 0) {
                            allUsers.push({ id, email, role });
                        }
                    }
                });
            } catch (e) {
                console.error('Error cargando usuarios:', e);
            }
        }

        // Cargar departamentos
        async function loadDepartments() {
            try {
                const res = await fetch('api-departments.php?action=list');
                const data = await res.json();
                
                if (!data.success) {
                    throw new Error(data.error || 'Error al cargar departamentos');
                }

                const departments = data.departments || [];
                
                if (departments.length === 0) {
                    deptTbody.innerHTML = '<tr><td colspan="5">No hay departamentos creados</td></tr>';
                    return;
                }

                deptTbody.innerHTML = departments.map(dept => {
                    const createdDate = dept.created_at ? new Date(dept.created_at).toLocaleDateString() : '—';
                    const description = dept.description || '—';
                    
                    return `
                        <tr>
                            <td><strong>${escapeHtml(dept.name)}</strong></td>
                            <td>${escapeHtml(description)}</td>
                            <td><span class="dept-badge">${dept.user_count} usuario${dept.user_count !== 1 ? 's' : ''}</span></td>
                            <td>${createdDate}</td>
                            <td>
                                <div class="button-container">
                                    <button class="modify-btn" data-action="assign" data-id="${dept.id}" data-name="${escapeHtml(dept.name)}" title="Asignar usuarios" aria-label="Asignar usuarios">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                            <circle cx="9" cy="7" r="4"></circle>
                                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                        </svg>
                                    </button>
                                    <button class="modify-btn" data-action="edit" data-id="${dept.id}" data-name="${escapeHtml(dept.name)}" data-description="${escapeHtml(description)}" title="Editar" aria-label="Editar">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M12 20h9"/>
                                            <path d="M16.5 3.5a2.121 2.121 0 1 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/>
                                        </svg>
                                    </button>
                                    <button class="delete-btn" data-action="delete" data-id="${dept.id}" data-name="${escapeHtml(dept.name)}" title="Eliminar" aria-label="Eliminar">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M3 6h18"/>
                                            <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                                            <path d="M10 11v6"/>
                                            <path d="M14 11v6"/>
                                            <path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `;
                }).join('');

                // Añadir event listeners a los botones
                deptTbody.querySelectorAll('[data-action]').forEach(btn => {
                    btn.addEventListener('click', handleDepartmentAction);
                });

            } catch (e) {
                console.error('Error al cargar departamentos:', e);
                deptTbody.innerHTML = '<tr><td colspan="5">Error al cargar departamentos</td></tr>';
            }
        }

        // Manejar acciones de departamento (editar, eliminar, asignar)
        async function handleDepartmentAction(e) {
            const btn = e.currentTarget;
            const action = btn.dataset.action;
            const id = parseInt(btn.dataset.id);
            const name = btn.dataset.name;

            if (action === 'edit') {
                // Editar departamento
                document.getElementById('modal-dept-form-title').textContent = 'Editar departamento';
                document.getElementById('dept-id').value = id;
                document.getElementById('dept-name').value = name;
                document.getElementById('dept-description').value = btn.dataset.description === '—' ? '' : btn.dataset.description;
                deptModal.classList.remove('hidden');
                document.getElementById('dept-name').focus();

            } else if (action === 'delete') {
                // Eliminar departamento
                if (!confirm(`¿Seguro que deseas eliminar el departamento "${name}"?\n\nEsto también eliminará todas las asignaciones de usuarios y accesos a contraseñas de este departamento.`)) {
                    return;
                }

                try {
                    const formData = new FormData();
                    formData.append('csrf_token', csrf);
                    formData.append('action', 'delete');
                    formData.append('id', id);

                    const res = await fetch('api-departments.php', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await res.json();
                    
                    if (!data.success) {
                        throw new Error(data.error || 'Error al eliminar');
                    }

                    alert(data.message || 'Departamento eliminado');
                    loadDepartments();
                    // Recargar página para actualizar badges de usuarios
                    window.location.reload();

                } catch (e) {
                    alert('Error: ' + e.message);
                }

            } else if (action === 'assign') {
                // Asignar usuarios
                await openAssignModal(id, name);
            }
        }

        // Abrir modal de asignación
        async function openAssignModal(deptId, deptName) {
            currentDepartmentId = deptId;
            document.getElementById('modal-dept-title').textContent = `Asignar usuarios a "${deptName}"`;
            
            // Cargar usuarios asignados al departamento
            try {
                const res = await fetch(`api-departments.php?action=get&id=${deptId}`);
                const data = await res.json();
                
                if (!data.success) {
                    throw new Error(data.error || 'Error al cargar datos');
                }

                const assignedUserIds = (data.users || []).map(u => u.id);
                
                // Renderizar lista de usuarios
                const usersList = document.getElementById('modal-users-list');
                usersList.innerHTML = allUsers.map(user => {
                    const checked = assignedUserIds.includes(user.id) ? 'checked' : '';
                    return `
                        <label class="assignee-item">
                            <input type="checkbox" name="user_ids[]" value="${user.id}" ${checked}>
                            <span class="assignee-email">${escapeHtml(user.email)}</span>
                            <span class="assignee-role">${escapeHtml(user.role)}</span>
                        </label>
                    `;
                }).join('');

                modal.classList.remove('hidden');

            } catch (e) {
                alert('Error: ' + e.message);
            }
        }

        // Cerrar modal
        function closeModal() {
            modal.classList.add('hidden');
            currentDepartmentId = null;
        }

        // Guardar asignaciones
        async function saveAssignments() {
            if (!currentDepartmentId) return;

            const checkboxes = modal.querySelectorAll('input[name="user_ids[]"]:checked');
            const userIds = Array.from(checkboxes).map(cb => cb.value);

            try {
                const formData = new FormData();
                formData.append('csrf_token', csrf);
                formData.append('action', 'assign_users');
                formData.append('department_id', currentDepartmentId);
                userIds.forEach(id => formData.append('user_ids[]', id));

                const res = await fetch('api-departments.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await res.json();
                
                if (!data.success) {
                    throw new Error(data.error || 'Error al guardar');
                }

                alert(data.message || 'Asignaciones guardadas');
                closeModal();
                loadDepartments();
                // Recargar página para actualizar badges de usuarios
                window.location.reload();

            } catch (e) {
                alert('Error: ' + e.message);
            }
        }

        // Botón nuevo departamento
        document.getElementById('btn-new-department')?.addEventListener('click', () => {
            document.getElementById('modal-dept-form-title').textContent = 'Nuevo departamento';
            document.getElementById('dept-id').value = '';
            deptForm.reset();
            deptModal.classList.remove('hidden');
            document.getElementById('dept-name').focus();
        });

        // Close modal buttons for department modal
        deptModal?.querySelectorAll('[data-close-modal]').forEach(btn => {
            btn.addEventListener('click', () => {
                deptModal.classList.add('hidden');
                deptForm.reset();
            });
        });

        // Close on backdrop click
        deptModal?.addEventListener('click', (e) => {
            if (e.target === deptModal) {
                deptModal.classList.add('hidden');
                deptForm.reset();
            }
        });

        // Submit formulario departamento
        deptForm?.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const id = document.getElementById('dept-id').value;
            const name = document.getElementById('dept-name').value.trim();
            const description = document.getElementById('dept-description').value.trim();

            if (!name) {
                alert('El nombre es obligatorio');
                return;
            }

            try {
                const formData = new FormData();
                formData.append('csrf_token', csrf);
                formData.append('action', id ? 'update' : 'create');
                formData.append('name', name);
                formData.append('description', description);
                if (id) formData.append('id', id);

                const res = await fetch('api-departments.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await res.json();
                
                if (!data.success) {
                    throw new Error(data.error || 'Error al guardar');
                }

                alert(data.message || 'Guardado correctamente');
                deptModal.classList.add('hidden');
                deptForm.reset();
                loadDepartments();

            } catch (e) {
                alert('Error: ' + e.message);
            }
        });

        // Modal - botones
        document.getElementById('btn-close-modal')?.addEventListener('click', closeModal);
        document.getElementById('btn-cancel-modal')?.addEventListener('click', closeModal);
        document.getElementById('btn-save-assignments')?.addEventListener('click', saveAssignments);

        // Modal - seleccionar/quitar todos
        document.getElementById('assign-all-users')?.addEventListener('click', () => {
            modal.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = true);
        });
        document.getElementById('assign-none-users')?.addEventListener('click', () => {
            modal.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
        });

        // Cerrar modal al hacer click fuera
        modal?.addEventListener('click', (e) => {
            if (e.target === modal) closeModal();
        });

        // Helper para escapar HTML
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Inicializar
        loadAllUsers();
        loadDepartments();
    }

    // ======= Compact list filter (reusable) =======
    // Normalize text: remove accents and convert to lowercase
    function normalizeText(str) {
        return (str || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '');
    }

    // Filters .assignee-item or .checkbox-item based on text content
    function initListFilters() {
        document.querySelectorAll('.list-filter__input').forEach(input => {
            const listContainer = input.closest('.assignees-panel, .form-group')?.querySelector('.assignees-list, .checkbox-grid');
            if (!listContainer) return;

            // Create no-results message if not exists
            let noResults = listContainer.querySelector('.filter-no-results');
            if (!noResults) {
                noResults = document.createElement('div');
                noResults.className = 'filter-no-results';
                noResults.textContent = 'No se encontraron resultados';
                noResults.style.display = 'none';
                listContainer.appendChild(noResults);
            }

            input.addEventListener('input', () => {
                const query = normalizeText(input.value.trim());
                const items = listContainer.querySelectorAll('.assignee-item, .checkbox-item');
                let visibleCount = 0;

                items.forEach(item => {
                    const text = normalizeText(item.textContent);
                    const matches = query === '' || text.includes(query);
                    item.classList.toggle('filter-hidden', !matches);
                    if (matches) visibleCount++;
                });

                noResults.style.display = (visibleCount === 0 && query !== '') ? 'block' : 'none';
            });

            // Clear filter when modal closes (reset)
            const modal = input.closest('.modal');
            if (modal) {
                const observer = new MutationObserver(() => {
                    if (modal.classList.contains('hidden')) {
                        input.value = '';
                        input.dispatchEvent(new Event('input'));
                    }
                });
                observer.observe(modal, { attributes: true, attributeFilter: ['class'] });
            }
        });
    }

    // Initialize filters on DOM ready
    initListFilters();

    // Re-init filters when modal content is dynamically loaded (for admin assign users)
    const modalUsersList = document.getElementById('modal-users-list');
    if (modalUsersList) {
        const observer = new MutationObserver(() => {
            // Re-attach filter after list is rebuilt
            const filterInput = document.querySelector('#modal-assign-users .list-filter__input');
            if (filterInput) {
                filterInput.value = '';
                filterInput.dispatchEvent(new Event('input'));
            }
        });
        observer.observe(modalUsersList, { childList: true });
    }
});
