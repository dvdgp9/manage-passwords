/**
 * Dashboard JavaScript - Arquitectura simple
 * Sin frameworks complejos, solo vanilla JS + AJAX
 */

// Estado global simple
const Dashboard = {
    stats: null,
    centers: [],
    admins: [],
    currentUser: null
};

// Inicializar dashboard cuando se carga la página
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Iniciando dashboard...');
    
    // Configurar event listeners
    setupEventListeners();
    
    // Cargar datos iniciales
    loadDashboardData();
});

// ====== Superadmin: Gestión de Administradores ======

const AdminAPI = {
    async list(params = {}) {
        const { search = '' } = params;
        const qs = search ? `?search=${encodeURIComponent(search)}` : '';
        const resp = await fetch(`api/superadmin/admins/list.php${qs}`);
        return resp.json();
    },
    async remove(id) {
        const resp = await fetch('api/superadmin/admins/delete.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        return resp.json();
    },
    async create({ username, password, role }) {
        const resp = await fetch('api/superadmin/admins/create.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username, password, role })
        });
        return resp.json();
    },
    async update({ id, role, new_password }) {
        const payload = { id };
        if (role) payload.role = role;
        if (new_password) payload.new_password = new_password;
        const resp = await fetch('api/superadmin/admins/update.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        return resp.json();
    },
    async centersList(admin_id) {
        const resp = await fetch(`api/superadmin/admins/centers_list.php?admin_id=${encodeURIComponent(admin_id)}`);
        return resp.json();
    },
    async centersSave(admin_id, center_ids) {
        const resp = await fetch('api/superadmin/admins/centers_save.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ admin_id, center_ids })
        });
        return resp.json();
    }
};

// ====== Toggle centers UI based on role selection ======
function updateCreateAdminCentersUI() {
    const roleSel = document.getElementById('adminRole');
    const wrapper = document.getElementById('createAdminCentersWrapper');
    const info = document.getElementById('createAdminCentersInfo');
    if (!roleSel || (!wrapper && !info)) return;
    const isSuper = String(roleSel.value) === 'superadmin';
    if (wrapper) wrapper.hidden = isSuper;
    if (info) info.hidden = !isSuper;
    if (!isSuper) {
        // Ensure list is loaded when switching from superadmin to admin
        loadCentersForCreateAdmin();
    }
}

async function updateEditAdminCentersUI(adminId) {
    const roleSel = document.getElementById('editAdminRole');
    const wrapper = document.getElementById('editAdminCentersWrapper');
    const info = document.getElementById('editAdminCentersInfo');
    if (!roleSel || (!wrapper && !info)) return Promise.resolve();
    const isSuper = String(roleSel.value) === 'superadmin';
    if (wrapper) wrapper.hidden = isSuper;
    if (info) info.hidden = !isSuper;
    if (!isSuper) {
        return loadCentersForEditAdmin(adminId);
    }
    return Promise.resolve();
}

// Generic filter for centers checkbox lists within modals
function filterCentersList(listId, noResultsId, term) {
    const container = document.getElementById(listId);
    const noRes = document.getElementById(noResultsId);
    if (!container) return;
    const t = (term || '').toLowerCase().trim();
    const items = Array.from(container.querySelectorAll('label.checkbox-inline'));
    let visible = 0;
    items.forEach(label => {
        const text = label.textContent.toLowerCase();
        const show = !t || text.includes(t);
        label.style.display = show ? 'flex' : 'none';
        if (show) visible++;
    });
    if (noRes) noRes.style.display = visible === 0 ? 'block' : 'none';
}

async function loadAdmins({ q = '', sort = 'created_at_desc' } = {}) {
    try {
        const data = await AdminAPI.list({ search: q });
        if (data.success) {
            let list = data.data || [];
            list = sortAdminsList(list, sort);
            Dashboard.admins = list;
            renderAdmins();
        } else {
            showNotification?.('Error al cargar administradores: ' + (data.error || 'Desconocido'), 'error');
            renderAdminsError();
        }
    } catch (err) {
        console.error(err);
        showNotification?.('Error de conexión al cargar administradores', 'error');
        renderAdminsError();
    }
}

// Filtrado de lista de centros en el modal por término de búsqueda
function filterManageCenters(term) {
    const container = document.getElementById('manageCentersList');
    const noRes = document.getElementById('manageCentersNoResults');
    if (!container) return;
    const t = (term || '').toLowerCase().trim();
    const items = Array.from(container.querySelectorAll('label.checkbox-inline'));
    let visible = 0;
    items.forEach(label => {
        const text = label.textContent.toLowerCase();
        const show = !t || text.includes(t);
        label.style.display = show ? 'flex' : 'none';
        if (show) visible++;
    });
    if (noRes) noRes.style.display = visible === 0 ? 'block' : 'none';
}

function sortAdminsList(items, sort) {
    const arr = [...items];
    switch (sort) {
        case 'created_at_asc':
            return arr.sort((a,b) => (a.created_at > b.created_at ? 1 : -1));
        case 'username_asc':
            return arr.sort((a,b) => String(a.username).localeCompare(String(b.username)));
        case 'username_desc':
            return arr.sort((a,b) => String(b.username).localeCompare(String(a.username)));
        case 'created_at_desc':
        default:
            return arr.sort((a,b) => (a.created_at < b.created_at ? 1 : -1));
    }
}

function renderAdmins() {
    const container = document.getElementById('admins-list');
    if (!container) return;
    const admins = Dashboard.admins || [];
    if (admins.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <div class="empty-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="48" height="48">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M17 20h5V4H2v16h5m10 0V10m0 10H7m10-10H7"/>
                    </svg>
                </div>
                <h3>No hay administradores</h3>
                <p>Crea tu primer administrador para comenzar</p>
            </div>
        `;
        return;
    }

    container.innerHTML = admins.map(a => `
        <div class="center-item" style="cursor: default;">
            <div class="center-main">
                <div class="center-header">
                    <h3 class="center-name">${escapeHtml(a.username)}</h3>
                    <span class="center-status ${a.role === 'superadmin' ? 'active' : ''}">${escapeHtml(a.role)}</span>
                </div>
                <div class="center-details">
                    <span class="center-address">
                        <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M6 8a4 4 0 118 0 4 4 0 01-8 0z"/>
                        </svg>
                        Creado: ${escapeHtml(a.created_at || '')}
                    </span>
                    <span class="center-stat">
                        <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
                        </svg>
                        ID: ${a.id}
                    </span>
                </div>
            </div>
            <div class="center-actions">
                <div class="dropdown" onclick="event.stopPropagation()">
                    <button class="more-btn" onclick="event.stopPropagation(); toggleDropdown('adm-${a.id}', this); return false;">
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M3 9.5a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zm5 0a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zm5 0a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3z"/>
                        </svg>
                    </button>
                    <div class="dropdown-menu" id="dropdown-adm-${a.id}" onclick="event.stopPropagation()">
                        <a href="#" onclick="event.preventDefault(); openEditAdmin(${a.id});">Editar</a>
                        <a href="#" onclick="event.preventDefault(); manageAdminCenters(${a.id});">Gestionar centros</a>
                        <a href="#" onclick="event.preventDefault(); confirmDeleteAdmin(${a.id});">Eliminar</a>
                    </div>
                </div>
            </div>
        </div>
    `).join('');
}

function renderAdminsError() {
    const container = document.getElementById('admins-list');
    if (!container) return;
    container.innerHTML = `
        <div class="error-card">
            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Error cargando administradores
        </div>
    `;
}

function confirmDeleteAdmin(id) {
    if (!confirm('¿Eliminar este administrador?')) return;
    AdminAPI.remove(id).then(result => {
        if (result.success) {
            showNotification?.('Administrador eliminado', 'success');
            Dashboard.admins = (Dashboard.admins || []).filter(a => String(a.id) !== String(id));
            renderAdmins();
        } else {
            showNotification?.(result.error || 'No se pudo eliminar', 'error');
        }
    }).catch(err => {
        console.error(err);
        showNotification?.('Error de conexión eliminando admin', 'error');
    });
}

// ====== Admins: Modales y formularios ======

function showCreateAdminModal() {
    const modal = document.getElementById('createAdminModal');
    if (modal) {
        modal.classList.add('show');
        setTimeout(() => {
            const first = modal.querySelector('input,select');
            if (first) first.focus();
        }, 50);
        // Init UI visibility and load list if needed
        updateCreateAdminCentersUI();
        // Bind change listener on role select to toggle UI
        const roleSel = document.getElementById('adminRole');
        if (roleSel && !roleSel._centersHooked) {
            roleSel.addEventListener('change', () => updateCreateAdminCentersUI());
            roleSel._centersHooked = true;
        }
    }
}

function closeCreateAdminModal() {
    const modal = document.getElementById('createAdminModal');
    if (modal) modal.classList.remove('show');
    // limpiar formulario
    const form = document.getElementById('createAdminForm');
    if (form) form.reset();
    clearFormErrors();
}

function openEditAdmin(id) {
    // Cerrar dropdowns antes de abrir modal
    document.querySelectorAll('.dropdown-menu').forEach(menu => {
        menu.classList.remove('show');
        menu.classList.remove('dropup');
        menu.style.top = '';
        menu.style.left = '';
        menu.style.right = '';
        menu.style.bottom = '';
    });
    const admin = (Dashboard.admins || []).find(a => String(a.id) === String(id));
    if (!admin) {
        showNotification?.('No se encontraron datos del administrador', 'error');
        return;
    }
    const idInput = document.getElementById('editAdminId');
    const userInput = document.getElementById('editAdminUsername');
    const roleSelect = document.getElementById('editAdminRole');
    const passInput = document.getElementById('editAdminNewPassword');
    if (idInput) idInput.value = admin.id;
    if (userInput) userInput.value = admin.username || '';
    if (roleSelect) roleSelect.value = admin.role || 'admin';
    if (passInput) passInput.value = '';
    // Toggle UI and load centers if needed (if superadmin UI is present)
    updateEditAdminCentersUI(id).finally(() => {
        showEditAdminModal();
    });
    // Bind change listener on role select to toggle UI in edit modal
    if (roleSelect && !roleSelect._centersHooked) {
        roleSelect.addEventListener('change', () => updateEditAdminCentersUI(id));
        roleSelect._centersHooked = true;
    }
}

function showEditAdminModal() {
    const modal = document.getElementById('editAdminModal');
    if (modal) {
        modal.classList.add('show');
        setTimeout(() => {
            const first = modal.querySelector('select');
            if (first) first.focus();
        }, 50);
    }
}

function closeEditAdminModal() {
    const modal = document.getElementById('editAdminModal');
    if (modal) modal.classList.remove('show');
    clearFormErrors();
    const form = document.getElementById('editAdminForm');
    if (form) form.reset();
}

// ====== Superadmin: Gestionar Centros asignados a Admin ======
async function manageAdminCenters(adminId) {
    // Cerrar cualquier dropdown abierto
    document.querySelectorAll('.dropdown-menu').forEach(menu => {
        menu.classList.remove('show');
        menu.classList.remove('dropup');
        menu.style.top = '';
        menu.style.left = '';
        menu.style.right = '';
        menu.style.bottom = '';
    });
    const admin = (Dashboard.admins || []).find(a => String(a.id) === String(adminId));
    if (!admin) {
        showNotification?.('Administrador no encontrado', 'error');
        return;
    }
    const title = document.getElementById('manageCentersTitle');
    if (title) title.textContent = `Centros de ${admin.username}`;
    const hiddenId = document.getElementById('manageCentersAdminId');
    if (hiddenId) hiddenId.value = adminId;
    const list = document.getElementById('manageCentersList');
    if (list) list.innerHTML = '<div class="custom-select-loading">Cargando centros...</div>';
    // Resetear búsqueda
    const search = document.getElementById('manageCentersSearch');
    if (search) {
        search.value = '';
    }
    showManageCentersModal();
    try {
        const res = await AdminAPI.centersList(adminId);
        if (!res.success) throw new Error(res.error || 'No se pudo cargar');
        renderManageCentersList(res.data || []);
        // Focus en búsqueda tras render
        if (search) search.focus();
    } catch (e) {
        console.error(e);
        if (list) list.innerHTML = '<div class="error-card">Error cargando centros</div>';
        showNotification?.('Error cargando centros', 'error');
    }
}

function renderManageCentersList(items) {
    const list = document.getElementById('manageCentersList');
    if (!list) return;
    if (!items.length) {
        list.innerHTML = '<div class="empty-state">No hay centros</div>';
        return;
    }
    list.innerHTML = items.map(c => `
        <label class="checkbox-inline" style="display:flex;align-items:center;gap:8px;padding:6px 0;">
            <input type="checkbox" class="mc-center" value="${c.id}" ${c.asignado ? 'checked' : ''}>
            <span>${escapeHtml(c.nombre)}</span>
            <span class="status-dot" style="margin-left:auto;display:inline-block;width:10px;height:10px;border-radius:50%;background-color:${c.activo ? '#16a34a' : '#ef4444'}" title="${c.activo ? 'Activo' : 'Inactivo'}"></span>
        </label>
    `).join('');
}

function showManageCentersModal() {
    const modal = document.getElementById('manageAdminCentersModal');
    if (modal) {
        modal.classList.add('show');
        setTimeout(() => {
            const first = modal.querySelector('input[type="checkbox"]');
            if (first) first.focus();
        }, 100);
    }
}

function closeManageCentersModal() {
    const modal = document.getElementById('manageAdminCentersModal');
    if (modal) modal.classList.remove('show');
    const list = document.getElementById('manageCentersList');
    if (list) list.innerHTML = '';
}

async function saveManageCenters(e) {
    e?.preventDefault?.();
    const adminId = parseInt(document.getElementById('manageCentersAdminId')?.value || '0', 10);
    if (!adminId) {
        showNotification?.('Admin inválido', 'error');
        return;
    }
    const checkboxes = Array.from(document.querySelectorAll('#manageCentersList .mc-center'));
    const selected = checkboxes.filter(ch => ch.checked).map(ch => parseInt(ch.value, 10)).filter(n => n > 0);
    const btn = document.getElementById('saveManageCentersBtn');
    btn?.classList.add('loading');
    if (btn) btn.disabled = true;
    try {
        const res = await AdminAPI.centersSave(adminId, selected);
        if (res.success) {
            showNotification?.('Asignaciones guardadas', 'success');
            closeManageCentersModal();
        } else {
            showNotification?.(res.error || 'No se pudo guardar', 'error');
        }
    } catch (err) {
        console.error(err);
        showNotification?.('Error de conexión guardando asignaciones', 'error');
    } finally {
        btn?.classList.remove('loading');
        if (btn) btn.disabled = false;
    }
}

// Mostrar modal de edición de centro
function showEditCenterModal() {
    const modal = document.getElementById('editCenterModal');
    if (modal) {
        modal.classList.add('show');
        setTimeout(() => {
            const firstInput = modal.querySelector('input[type="text"]');
            if (firstInput) firstInput.focus();
        }, 100);
    }
}

// Cerrar modal de edición de centro
function closeEditCenterModal() {
    const modal = document.getElementById('editCenterModal');
    if (modal) {
        modal.classList.remove('show');
    }
}

// ====== Helpers to load centers into Create/Edit Admin modals ======
async function loadCentersForCreateAdmin() {
    const list = document.getElementById('createAdminCentersList');
    if (!list) return; // not visible for non-superadmin
    try {
        // Use selector endpoint which returns active centers filtered by role (superadmin gets all)
        const resp = await fetch('api/centros/list_for_selector.php');
        const data = await resp.json();
        if (!data.success) throw new Error(data.message || 'Error cargando centros');
        renderCentersCheckboxes(list, data.centros || [], 'ca-center');
    } catch (e) {
        console.error(e);
        list.innerHTML = '<div class="error-card">Error cargando centros</div>';
    }
}

async function loadCentersForEditAdmin(adminId) {
    const list = document.getElementById('editAdminCentersList');
    if (!list) return; // not visible for non-superadmin
    try {
        const res = await AdminAPI.centersList(adminId);
        if (!res.success) throw new Error(res.error || 'Error cargando centros');
        // Map result to centros-like structure {id, nombre, asignado}
        const items = (res.data || []).map(c => ({ id: c.id, nombre: c.nombre, asignado: !!c.asignado }));
        renderCentersCheckboxes(list, items, 'ea-center');
    } catch (e) {
        console.error(e);
        list.innerHTML = '<div class="error-card">Error cargando centros</div>';
    }
}

function renderCentersCheckboxes(containerEl, items, checkboxClass) {
    if (!containerEl) return;
    if (!items.length) {
        containerEl.innerHTML = '<div class="empty-state">No hay centros</div>';
        return;
    }
    containerEl.innerHTML = items.map(c => `
        <label class="checkbox-inline">
            <input type="checkbox" class="${checkboxClass}" value="${c.id}" ${c.asignado ? 'checked' : ''}>
            <span>${escapeHtml(c.nombre)}</span>
        </label>
    `).join('');
}

/**
 * Configurar todos los event listeners
 */
function setupEventListeners() {
    // Dropdowns
    setupDropdowns();
    
    // Configurar modal de actividades
    setupActivityModal();
    
    // Configurar modal de participantes
    setupParticipantModal();
    
    // Búsqueda de centros
    const searchInput = document.getElementById('search-centers');
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            filterCenters(e.target.value);
        });
    }
    
    // Ordenación de centros
    const sortSelect = document.getElementById('sort-centers');
    if (sortSelect) {
        sortSelect.addEventListener('change', function(e) {
            sortCenters(e.target.value);
        });
    }

    // Búsqueda de administradores
    const searchAdmins = document.getElementById('search-admins');
    if (searchAdmins) {
        searchAdmins.addEventListener('input', function(e) {
            // Cargar con filtro rápido (debounce ligero)
            if (setupEventListeners._admTimer) clearTimeout(setupEventListeners._admTimer);
            setupEventListeners._admTimer = setTimeout(() => {
                loadAdmins({ q: e.target.value, sort: document.getElementById('sort-admins')?.value || 'created_at_desc' });
            }, 250);
        });
    }

    // Ordenación de administradores
    const sortAdmins = document.getElementById('sort-admins');
    if (sortAdmins) {
        sortAdmins.addEventListener('change', function(e) {
            loadAdmins({ q: document.getElementById('search-admins')?.value || '', sort: e.target.value });
        });
    }
    
    // Crear admin
    const createAdminForm = document.getElementById('createAdminForm');
    if (createAdminForm) {
        createAdminForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            clearFormErrors();
            const formData = new FormData(this);
            const data = Object.fromEntries(formData);
            // Validación básica
            if (!data.username || !data.username.trim()) {
                const el = document.getElementById('adminUsername-error');
                if (el) el.textContent = 'El usuario es obligatorio';
                return;
            }
            if (!data.password || String(data.password).length < 8) {
                const el = document.getElementById('adminPassword-error');
                if (el) el.textContent = 'Mínimo 8 caracteres';
                return;
            }
            if (!data.role) {
                const el = document.getElementById('adminRole-error');
                if (el) el.textContent = 'Seleccione un rol';
                return;
            }
            const btn = document.getElementById('createAdminBtn');
            btn?.classList.add('loading');
            if (btn) btn.disabled = true;
            try {
                const res = await AdminAPI.create({ username: data.username.trim(), password: String(data.password), role: data.role });
                if (res.success) {
                    const newId = res.data?.id;
                    // If role is NOT superadmin and centers UI exists, save selected centers
                    if (String(data.role) !== 'superadmin') {
                        const centersContainer = document.getElementById('createAdminCentersList');
                        if (newId && centersContainer) {
                            const selected = Array.from(centersContainer.querySelectorAll('input[type="checkbox"]:checked'))
                                .map(ch => parseInt(ch.value, 10))
                                .filter(n => n > 0);
                            try {
                                await AdminAPI.centersSave(newId, selected);
                            } catch (err) {
                                console.error(err);
                                showNotification?.('Admin creado, pero fallo guardando centros', 'warning');
                            }
                        }
                    }
                    showNotification?.('Administrador creado', 'success');
                    closeCreateAdminModal();
                    // recargar lista
                    const q = document.getElementById('search-admins')?.value || '';
                    const sort = document.getElementById('sort-admins')?.value || 'created_at_desc';
                    await loadAdmins({ q, sort });
                } else {
                    showNotification?.(res.error || 'No se pudo crear', 'error');
                    // errores de campo comunes
                    if (/usuario/i.test(res.error || '')) {
                        const el = document.getElementById('adminUsername-error');
                        if (el) el.textContent = res.error;
                    }
                }
            } catch (err) {
                console.error(err);
                showNotification?.('Error de conexión creando admin', 'error');
            } finally {
                btn?.classList.remove('loading');
                if (btn) btn.disabled = false;
            }
        });
    }

    // Editar admin
    const editAdminForm = document.getElementById('editAdminForm');
    if (editAdminForm) {
        editAdminForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            clearFormErrors();
            const formData = new FormData(this);
            const data = Object.fromEntries(formData);
            const id = parseInt(data.id, 10);
            if (!id) {
                showNotification?.('ID inválido', 'error');
                return;
            }
            const payload = { id };
            const role = data.role;
            const newPass = String(data.new_password || '').trim();
            if (role) payload.role = role;
            if (newPass) {
                if (newPass.length < 8) {
                    const el = document.getElementById('editAdminNewPassword-error');
                    if (el) el.textContent = 'Mínimo 8 caracteres';
                    return;
                }
                payload.new_password = newPass;
            }
            const btn = document.getElementById('saveEditAdminBtn');
            btn?.classList.add('loading');
            if (btn) btn.disabled = true;
            try {
                const res = await AdminAPI.update(payload);
                // Save centers assignments only if role is not superadmin and the UI exists
                if (String(data.role || '') !== 'superadmin') {
                    const centersContainer = document.getElementById('editAdminCentersList');
                    if (centersContainer) {
                        const selected = Array.from(centersContainer.querySelectorAll('input[type="checkbox"]:checked'))
                            .map(ch => parseInt(ch.value, 10))
                            .filter(n => n > 0);
                        try {
                            await AdminAPI.centersSave(id, selected);
                        } catch (err) {
                            console.error(err);
                            showNotification?.('Cambios aplicados, pero fallo guardando centros', 'warning');
                        }
                    }
                }
                if (res.success) {
                    showNotification?.('Administrador actualizado', 'success');
                    closeEditAdminModal();
                    // actualizar en memoria sin recargar
                    const idx = (Dashboard.admins || []).findIndex(a => String(a.id) === String(id));
                    if (idx >= 0) {
                        Dashboard.admins[idx] = { ...Dashboard.admins[idx], ...res.data };
                        renderAdmins();
                    } else {
                        // fallback: recargar
                        const q = document.getElementById('search-admins')?.value || '';
                        const sort = document.getElementById('sort-admins')?.value || 'created_at_desc';
                        await loadAdmins({ q, sort });
                    }
                } else {
                    showNotification?.(res.error || 'No se pudo actualizar', 'error');
                }
            } catch (err) {
                console.error(err);
                showNotification?.('Error de conexión actualizando admin', 'error');
            } finally {
                btn?.classList.remove('loading');
                if (btn) btn.disabled = false;
            }
        });
    }
    
    // Formulario crear centro
    const createCenterForm = document.getElementById('createCenterForm');
    if (createCenterForm) {
        createCenterForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // Limpiar errores previos
            clearFormErrors();
            
            // Obtener datos del formulario
            const formData = new FormData(this);
            const data = Object.fromEntries(formData);
            
            // Validación básica
            if (!data.nombre.trim()) {
                showFieldError('centerName', 'El nombre del centro es obligatorio');
                return;
            }
            
            if (!data.password || !data.password.trim()) {
                showFieldError('centerPassword', 'La contraseña del centro es obligatoria');
                return;
            }
            
            // Mostrar loading
            const submitBtn = document.getElementById('createCenterBtn');
            submitBtn.classList.add('loading');
            submitBtn.disabled = true;
            
            try {
                await createCenter(data);
            } finally {
                // Quitar loading
                submitBtn.classList.remove('loading');
                submitBtn.disabled = false;
            }
        });
    }
    
    // Formulario crear instalación
    const createInstallationForm = document.getElementById('createInstallationForm');
    if (createInstallationForm) {
        createInstallationForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // Limpiar errores previos
            clearFormErrors();
            
            // Obtener datos del formulario
            const formData = new FormData(this);
            const data = Object.fromEntries(formData);
            
            // Validación básica
            if (!data.centro_id) {
                showFieldError('installationCenter', 'Debe seleccionar un centro');
                return;
            }
            
            if (!data.nombre.trim()) {
                showFieldError('installationName', 'El nombre de la instalación es obligatorio');
                return;
            }
            
            // Mostrar loading
            const submitBtn = document.getElementById('createInstallationBtn');
            submitBtn.classList.add('loading');
            submitBtn.disabled = true;
            
            try {
                await createInstallation(data);
            } finally {
                // Quitar loading
                submitBtn.classList.remove('loading');
                submitBtn.disabled = false;
            }
        });
    }

    // Formulario editar centro
    const editCenterForm = document.getElementById('editCenterForm');
    if (editCenterForm) {
        editCenterForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            clearFormErrors();
            const formData = new FormData(this);
            const data = Object.fromEntries(formData);
            if (!data.nombre || !data.nombre.trim()) {
                showFieldError('editCenterName', 'El nombre del centro es obligatorio');
                return;
            }
            const submitBtn = document.getElementById('saveEditCenterBtn');
            submitBtn.classList.add('loading');
            submitBtn.disabled = true;
            try {
                await updateCenter(data);
            } finally {
                submitBtn.classList.remove('loading');
                submitBtn.disabled = false;
            }
        });
    }
}

/**
 * Configurar dropdowns
 */
function setupDropdowns() {
    // Dropdown "Añadir"
    const addBtn = document.getElementById('add-dropdown-btn');
    const addDropdown = document.getElementById('add-dropdown');
    
    if (addBtn && addDropdown) {
        addBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleDropdownElement(addDropdown);
        });
    }
    
    // Dropdown "Perfil"
    const profileBtn = document.getElementById('profile-dropdown-btn');
    const profileDropdown = document.getElementById('profile-dropdown');
    
    if (profileBtn && profileDropdown) {
        profileBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleDropdownElement(profileDropdown);
        });
    }
    
    // Cerrar dropdowns al hacer click fuera
    document.addEventListener('click', function() {
        closeAllDropdowns();
    });
}

/**
 * Toggle dropdown
 */
function toggleDropdownElement(dropdownEl) {
    // Cerrar otros dropdowns
    closeAllDropdowns();
    
    // Toggle el dropdown actual
    dropdownEl.classList.toggle('active');
}

/**
 * Cerrar todos los dropdowns
 */
function closeAllDropdowns() {
    const dropdowns = document.querySelectorAll('.dropdown-content');
    dropdowns.forEach(dropdown => {
        dropdown.classList.remove('active');
    });
}

/**
 * Cargar todos los datos del dashboard
 */
async function loadDashboardData() {
    console.log('📊 Cargando datos del dashboard...');
    
    try {
        // Cargar estadísticas y centros en paralelo
        await Promise.all([
            loadStats(),
            loadCenters()
        ]);
        
        console.log('✅ Datos cargados correctamente');
        
    } catch (error) {
        console.error('❌ Error cargando datos:', error);
        showError('Error al cargar los datos del dashboard');
    }
}

/**
 * Cargar estadísticas
 */
async function loadStats() {
    try {
        const response = await fetch('api/stats/dashboard.php');
        const data = await response.json();
        
        if (data.success) {
            Dashboard.stats = data.data;
            renderStats();
        } else {
            throw new Error(data.error || 'Error cargando estadísticas');
        }
        
    } catch (error) {
        console.error('Error cargando estadísticas:', error);
        showStatsError();
    }
}

/**
 * Cargar centros
 */
async function loadCenters() {
    try {
        // Usar el endpoint corregido
        const response = await fetch('api/centros/list_new.php');
        const data = await response.json();
        
        if (data.success) {
            Dashboard.centers = data.data || [];
            renderCenters();
        } else {
            throw new Error(data.error || 'Error cargando centros');
        }
        
    } catch (error) {
        console.error('Error cargando centros:', error);
        showCentersError();
    }
}

/**
 * Renderizar estadísticas
 */
function renderStats() {
    const statsGrid = document.getElementById('stats-grid');
    if (!statsGrid || !Dashboard.stats) return;
    
    const stats = Dashboard.stats;
    
    statsGrid.innerHTML = `
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-title">Centros</div>
                <svg class="stat-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
            </div>
            <div class="stat-value">${stats.total_centros || 0}</div>
            <div class="stat-change">Total activos</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-title">Instalaciones</div>
                <svg class="stat-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"/>
                </svg>
            </div>
            <div class="stat-value">${stats.total_instalaciones || 0}</div>
            <div class="stat-change">Total disponibles</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-title">Actividades</div>
                <svg class="stat-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div class="stat-value">${stats.total_actividades_activas || 0}</div>
            <div class="stat-change">Activas • ${stats.total_actividades_programadas || 0} programadas</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-title">% Asistencia</div>
                <svg class="stat-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
            </div>
            <div class="stat-value">${stats.porcentaje_asistencia || 0}%</div>
            <div class="stat-change">${stats.total_asistencias || 0} asistencias totales</div>
        </div>
    `;
}

/**
 * Renderizar centros
 */
function renderCenters() {
    const container = document.getElementById('centers-list');
    
    if (!Dashboard.centers || Dashboard.centers.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <div class="empty-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="48" height="48">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                </div>
                <h3>No hay centros disponibles</h3>
                <p>Comienza creando tu primer centro deportivo</p>
                <button class="btn btn-primary" onclick="showCreateCenterModal()">
                    + Crear primer centro
                </button>
            </div>
        `;
        return;
    }

    const centersHTML = Dashboard.centers.map(center => `
        <div class="center-item" onclick="goToCenter(${center.id})" style="cursor: pointer;">
            <div class="center-main">
                <div class="center-header">
                    <h3 class="center-name">${escapeHtml(center.nombre)}</h3>
                    <span class="center-status ${center.activo ? 'active' : 'inactive'}">${center.activo ? 'Activo' : 'Inactivo'}</span>
                </div>
                <div class="center-details">
                    <span class="center-address">
                        <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10zm0-7a3 3 0 1 1 0-6 3 3 0 0 1 0 6z"/>
                        </svg>
                        ${escapeHtml(center.direccion || 'Sin dirección')}
                    </span>
                    <span class="center-stat">
                        <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M6.5 14.5v-3.505c0-.245.25-.495.5-.495h2c.25 0 .5.25.5.495v3.5a.5.5 0 0 0 .5.5h4a.5.5 0 0 0 .5-.5v-7a.5.5 0 0 0-.146-.354L13 5.793V2.5a.5.5 0 0 0-.5-.5h-1a.5.5 0 0 0-.5.5v1.293L8.354 1.146a.5.5 0 0 0-.708 0l-6 6A.5.5 0 0 0 1.5 7.5v7a.5.5 0 0 0 .5.5z"/>
                        </svg>
                        ${center.total_instalaciones || 0} instalaciones
                    </span>
                    <span class="center-stat">
                        <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
                        </svg>
                        ${center.total_actividades || 0} actividades
                    </span>
                </div>
            </div>
            <div class="center-actions">
                <div class="dropdown" onclick="event.stopPropagation()">
                    <button class="more-btn" onclick="event.stopPropagation(); toggleDropdown(${center.id}, this); return false;">
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M3 9.5a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zm5 0a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zm5 0a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3z"/>
                        </svg>
                    </button>
                    <div class="dropdown-menu" id="dropdown-${center.id}" onclick="event.stopPropagation()">
                        <a href="#" onclick="event.preventDefault(); editCenter(${center.id});">Editar</a>
                        <a href="#" onclick="event.preventDefault(); toggleActiveCenter(${center.id}, ${center.activo ? 1 : 0});">${center.activo ? 'Desactivar' : 'Activar'}</a>
                        ${window.isSuperAdmin ? `<a href="#" class="dropdown-item-danger" onclick="event.preventDefault(); deleteCenter(${center.id}, '${escapeHtml(center.nombre || '')}');">Eliminar</a>` : ''}
                    </div>
                </div>
            </div>
        </div>
    `).join('');

    container.innerHTML = centersHTML;
}

/**
 * Mostrar error en estadísticas
 */
function showStatsError() {
    const statsGrid = document.getElementById('stats-grid');
    if (statsGrid) {
        statsGrid.innerHTML = `
            <div class="error-card">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Error cargando estadísticas
            </div>
        `;
    }
}

/**
 * Mostrar error en centros
 */
function showCentersError() {
    const centersGrid = document.getElementById('centers-grid');
    if (centersGrid) {
        centersGrid.innerHTML = `
            <div class="error-card">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Error cargando centros
            </div>
        `;
    }
}

/**
 * Filtrar centros por búsqueda
 */
function filterCenters(searchTerm) {
    const centerCards = document.querySelectorAll('.center-card');
    const term = searchTerm.toLowerCase();
    
    centerCards.forEach(card => {
        const name = card.querySelector('.center-name').textContent.toLowerCase();
        const address = card.querySelector('.center-address').textContent.toLowerCase();
        
        if (name.includes(term) || address.includes(term)) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

/**
 * Ordenar centros
 */
function sortCenters(sortBy) {
    // TODO: Implementar ordenación
    console.log('Ordenar centros por:', sortBy);
}

/**
 * Abrir modal (placeholder)
 */
function openModal(type) {
    console.log('Abrir modal para:', type);
    alert(`Modal para crear ${type} - En desarrollo`);
}

/**
 * Abrir panel de administradores (placeholder)
 */
function showAdminsPanel() {
    console.log('Abrir panel de administradores');
    const panel = document.getElementById('admins-panel');
    if (panel) {
        panel.style.display = 'block';
        // Cargar listado al abrir
        const q = document.getElementById('search-admins')?.value || '';
        const sort = document.getElementById('sort-admins')?.value || 'created_at_desc';
        loadAdmins({ q, sort });
        // Desplazar a la sección
        panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

/**
 * Ver centro (placeholder)
 */
function viewCenter(centerId) {
    console.log('Ver centro:', centerId);
    alert(`Ver detalles del centro ${centerId} - En desarrollo`);
}

/**
 * Mostrar menú de centro (placeholder)
 */
function showCenterMenu(centerId, event) {
    event.stopPropagation();
    console.log('Menú del centro:', centerId);
    alert(`Menú del centro ${centerId} - En desarrollo`);
}

/**
 * Mostrar error general
 */
function showError(message) {
    console.error(message);
    // TODO: Implementar notificaciones toast
    alert('Error: ' + message);
}

/**
 * Función auxiliar para escapar HTML
 */
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text ? text.replace(/[&<>"']/g, function(m) { return map[m]; }) : '';
}

/**
 * Toggle dropdown menu
 */
function toggleDropdown(centerId, btnEl) {
    const dropdown = document.getElementById(`dropdown-${centerId}`);
    const wasVisible = dropdown.classList.contains('show');

    // Cerrar todos los dropdowns
    document.querySelectorAll('.dropdown-menu').forEach(menu => {
        menu.classList.remove('show');
        menu.classList.remove('dropup');
        menu.style.top = '';
        menu.style.left = '';
        menu.style.right = '';
        menu.style.bottom = '';
    });

    if (wasVisible) return; // si estaba visible, ya lo cerramos arriba

    // Obtener posición del contenedor dropdown
    const dropdownContainer = btnEl.closest('.dropdown');
    if (dropdownContainer) {
        const containerRect = dropdownContainer.getBoundingClientRect();
        const viewportH = window.innerHeight || document.documentElement.clientHeight;
        const viewportW = window.innerWidth || document.documentElement.clientWidth;
        
        // Calcular posición
        const isInBottomHalf = containerRect.bottom > (viewportH / 2);
        const isNearRightEdge = containerRect.right > (viewportW - 180); // 180px = ancho aprox del menu
        
        if (isInBottomHalf) {
            dropdown.classList.add('dropup');
            dropdown.style.bottom = (viewportH - containerRect.top) + 'px';
            dropdown.style.top = 'auto';
        } else {
            dropdown.style.top = containerRect.bottom + 'px';
            dropdown.style.bottom = 'auto';
        }
        
        if (isNearRightEdge) {
            dropdown.style.right = (viewportW - containerRect.right) + 'px';
            dropdown.style.left = 'auto';
        } else {
            dropdown.style.left = containerRect.left + 'px';
            dropdown.style.right = 'auto';
        }
    }

    // Mostrar dropdown
    dropdown.classList.add('show');
}

/**
 * Cerrar dropdowns al hacer click fuera
 */
document.addEventListener('click', function(event) {
    if (!event.target.closest('.dropdown')) {
        document.querySelectorAll('.dropdown-menu').forEach(menu => {
            menu.classList.remove('show');
            menu.classList.remove('dropup');
            menu.style.top = '';
            menu.style.left = '';
            menu.style.right = '';
            menu.style.bottom = '';
        });
    }
});

/**
 * Acciones del dropdown de centros
 */
function editCenter(centerId) {
    // Cerrar dropdowns antes de abrir modal
    document.querySelectorAll('.dropdown-menu').forEach(menu => {
        menu.classList.remove('show');
        menu.classList.remove('dropup');
        menu.style.top = '';
        menu.style.left = '';
        menu.style.right = '';
        menu.style.bottom = '';
    });
    // Buscar datos del centro en memoria
    const center = (Dashboard.centers || []).find(c => String(c.id) === String(centerId));
    if (!center) {
        showNotification('No se encontraron datos del centro', 'error');
        return;
    }
    // Prefijar valores
    const idInput = document.getElementById('editCenterId');
    const nameInput = document.getElementById('editCenterName');
    const addrInput = document.getElementById('editCenterAddress');
    if (idInput) idInput.value = center.id;
    if (nameInput) nameInput.value = center.nombre || '';
    if (addrInput) addrInput.value = center.direccion || '';
    // Abrir modal
    showEditCenterModal();
}

async function toggleActiveCenter(centerId, currentActivo) {
    try {
        // Cerrar cualquier dropdown abierto
        document.querySelectorAll('.dropdown-menu').forEach(menu => {
            menu.classList.remove('show');
            menu.classList.remove('dropup');
            menu.style.top = '';
            menu.style.left = '';
            menu.style.right = '';
            menu.style.bottom = '';
        });

        const nuevoEstado = currentActivo ? 0 : 1;
        const resp = await fetch('api/centros/set_active.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: centerId, activo: nuevoEstado })
        });
        const result = await resp.json();
        if (resp.ok && result.success) {
            showNotification(`Centro ${nuevoEstado ? 'activado' : 'desactivado'} correctamente`, 'success');
            await loadCenters();
        } else {
            showNotification('Error: ' + (result.message || 'No se pudo actualizar el centro'), 'error');
        }
    } catch (err) {
        console.error(err);
        showNotification('Error al actualizar el estado del centro', 'error');
    }
}

/**
 * Eliminar centro en cascada (solo superadmin)
 */
async function deleteCenter(id, nombre) {
    if (!window.isSuperAdmin) {
        showNotification('Solo superadmin puede eliminar centros', 'error');
        return;
    }
    
    // Cerrar dropdown
    document.querySelectorAll('.dropdown-menu').forEach(menu => {
        menu.classList.remove('show');
    });
    
    const confirmMsg = `¿Estás seguro de eliminar el centro "${nombre}"?\n\nEsto eliminará PERMANENTEMENTE:\n- Todas las instalaciones del centro\n- Todas las actividades\n- Todos los participantes inscritos\n- Todo el historial de asistencias\n- Todas las asignaciones de administradores\n\nEsta acción NO se puede deshacer.`;
    
    if (!confirm(confirmMsg)) return;
    
    try {
        const response = await fetch('api/centros/delete.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification(result.message, 'success');
            await loadCenters();
            await loadStats();
        } else {
            showNotification(result.message || 'Error al eliminar', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

/**
 * Mostrar modal de crear centro
 */
function showCreateCenterModal() {
    const modal = document.getElementById('createCenterModal');
    if (modal) {
        modal.classList.add('show');
        // Focus en el primer campo
        setTimeout(() => {
            const firstInput = modal.querySelector('input[type="text"]');
            if (firstInput) firstInput.focus();
        }, 100);
    }
}

/**
 * Mostrar modal de opciones de añadir
 */
function showAddOptionsModal() {
    const modal = document.getElementById('addOptionsModal');
    if (modal) {
        modal.classList.add('show');
    }
}

/**
 * Cerrar modal de opciones de añadir
 */
function closeAddOptionsModal() {
    const modal = document.getElementById('addOptionsModal');
    if (modal) {
        modal.classList.remove('show');
    }
}

/**
 * Seleccionar opción de creación
 */
function selectCreateOption(type) {
    // Cerrar modal de opciones
    closeAddOptionsModal();
    
    // Abrir modal correspondiente
    switch(type) {
        case 'centro':
            showCreateCenterModal();
            break;
        case 'instalacion':
            showCreateInstallationModal();
            break;
        case 'actividad':
            showCreateActivityModal();
            break;
        case 'participante':
            showCreateParticipantModal();
            break;
        default:
            showNotification('Funcionalidad en desarrollo', 'info');
    }
}

/**
 * Mostrar modal de crear instalación
 */
async function showCreateInstallationModal() {
    const modal = document.getElementById('createInstallationModal');
    if (modal) {
        // Cargar centros en el selector
        await loadCentersForSelector();
        
        modal.classList.add('show');
        // Focus en el selector de centro
        setTimeout(() => {
            const centerSelect = document.getElementById('installationCenter');
            if (centerSelect) centerSelect.focus();
        }, 100);
    }
}

/**
 * Cerrar modal de crear instalación
 */
function closeCreateInstallationModal() {
    const modal = document.getElementById('createInstallationModal');
    if (modal) {
        modal.classList.remove('show');
    }
    
    // Limpiar formulario
    document.getElementById('createInstallationForm').reset();
    clearFormErrors();
    
    // Limpiar selector personalizado
    const wrapper = document.querySelector('.custom-select-wrapper');
    const input = document.getElementById('installationCenterSearch');
    const hiddenInput = document.getElementById('installationCenter');
    
    if (wrapper && input && hiddenInput) {
        wrapper.classList.remove('open');
        input.value = '';
        input.classList.remove('has-value');
        hiddenInput.value = '';
        
        // Limpiar selecciones
        document.querySelectorAll('.custom-select-option').forEach(opt => {
            opt.classList.remove('selected');
        });
    }
}

/**
 * Cargar centros para el selector
 */
async function loadCentersForSelector() {
    try {
        const response = await fetch('api/centros/list_for_selector.php');
        const data = await response.json();
        
        if (data.success) {
            // Guardar centros globalmente para filtrado
            window.availableCenters = data.centros;
            
            // Inicializar selector personalizado
            initCustomCenterSelector();
            
            // Mostrar todos los centros inicialmente
            renderCenterOptions(data.centros);
            
        } else {
            showNotification('Error al cargar centros: ' + data.message, 'error');
            showCenterSelectorError('Error al cargar centros');
        }
    } catch (error) {
        console.error('Error loading centers:', error);
        showNotification('Error al cargar centros', 'error');
        showCenterSelectorError('Error de conexión');
    }
}

/**
 * Inicializar selector personalizado de centros
 */
function initCustomCenterSelector() {
    const wrapper = document.querySelector('.custom-select-wrapper');
    const input = document.getElementById('installationCenterSearch');
    const dropdown = document.getElementById('installationCenterDropdown');
    const hiddenInput = document.getElementById('installationCenter');
    
    if (!wrapper || !input || !dropdown || !hiddenInput) return;
    
    // Evento click en input para abrir/cerrar
    input.addEventListener('click', function() {
        wrapper.classList.toggle('open');
    });
    
    // Evento input para filtrar
    input.addEventListener('input', function() {
        const query = this.value.toLowerCase();
        const filtered = window.availableCenters.filter(centro => 
            centro.nombre.toLowerCase().includes(query)
        );
        renderCenterOptions(filtered);
        
        if (!wrapper.classList.contains('open')) {
            wrapper.classList.add('open');
        }
    });
    
    // Cerrar al hacer click fuera
    document.addEventListener('click', function(e) {
        if (!wrapper.contains(e.target)) {
            wrapper.classList.remove('open');
        }
    });
    
    // Manejar teclas
    input.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            wrapper.classList.remove('open');
        }
    });
}

/**
 * Renderizar opciones de centros
 */
function renderCenterOptions(centros) {
    const dropdown = document.getElementById('installationCenterDropdown');
    
    if (centros.length === 0) {
        dropdown.innerHTML = '<div class="custom-select-no-results">No se encontraron centros</div>';
        return;
    }
    
    dropdown.innerHTML = centros.map(centro => 
        `<div class="custom-select-option" data-value="${centro.id}" data-name="${escapeHtml(centro.nombre)}">
            ${escapeHtml(centro.nombre)}
        </div>`
    ).join('');
    
    // Añadir eventos click a las opciones
    dropdown.querySelectorAll('.custom-select-option').forEach(option => {
        option.addEventListener('click', function() {
            selectCenterOption(this.dataset.value, this.dataset.name);
        });
    });
}

/**
 * Seleccionar opción de centro
 */
function selectCenterOption(value, name) {
    const wrapper = document.querySelector('.custom-select-wrapper');
    const input = document.getElementById('installationCenterSearch');
    const hiddenInput = document.getElementById('installationCenter');
    
    // Actualizar valores
    input.value = name;
    input.classList.add('has-value');
    hiddenInput.value = value;
    
    // Cerrar dropdown
    wrapper.classList.remove('open');
    
    // Limpiar error si existe
    clearFieldError('installationCenter');
    
    // Marcar opción como seleccionada
    document.querySelectorAll('.custom-select-option').forEach(opt => {
        opt.classList.remove('selected');
    });
    document.querySelector(`[data-value="${value}"]`)?.classList.add('selected');
}

/**
 * Mostrar error en selector de centros
 */
function showCenterSelectorError(message) {
    const dropdown = document.getElementById('installationCenterDropdown');
    dropdown.innerHTML = `<div class="custom-select-loading" style="color: var(--color-red-500);">${message}</div>`;
}

/**
 * Cerrar modal de crear centro
 */
function closeCreateCenterModal() {
    const modal = document.getElementById('createCenterModal');
    if (modal) {
        modal.classList.remove('show');
    }
    
    // Limpiar formulario
    document.getElementById('createCenterForm').reset();
    clearFormErrors();
}

/**
 * Limpiar errores del formulario
 */
function clearFormErrors() {
    document.querySelectorAll('.form-error').forEach(error => {
        error.textContent = '';
    });
    document.querySelectorAll('.form-input').forEach(input => {
        input.classList.remove('error');
    });
}

/**
 * Mostrar error en campo específico
 */
function showFieldError(fieldId, message) {
    const field = document.getElementById(fieldId);
    const errorDiv = document.getElementById(fieldId + '-error');
    
    if (field) field.classList.add('error');
    if (errorDiv) errorDiv.textContent = message;
}

/**
 * Limpiar error en campo específico
 */
function clearFieldError(fieldId) {
    const field = document.getElementById(fieldId);
    const errorDiv = document.getElementById(fieldId + '-error');
    
    if (field) field.classList.remove('error');
    if (errorDiv) errorDiv.textContent = '';
}

/**
 * Crear centro
 */
async function createCenter(data) {
    try {
        const response = await fetch('api/centros/create.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Centro creado exitosamente', 'success');
            closeCreateCenterModal();
            
            // Recargar la lista de centros
            await loadCenters();
            
            // Actualizar estadísticas
            await loadStats();
        } else {
            showNotification('Error: ' + result.message, 'error');
        }
    } catch (error) {
        console.error('Error creating center:', error);
        showNotification('Error al crear el centro', 'error');
    }
}

/**
 * Actualizar centro (desde el dashboard)
 */
async function updateCenter(data) {
    try {
        const payload = {
            id: Number(data.id),
            nombre: String(data.nombre || '').trim(),
            direccion: String(data.direccion || '').trim()
        };
        const resp = await fetch('api/centros/update.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const result = await resp.json();
        if (resp.ok && result.success) {
            // Actualizar el centro en memoria
            if (Array.isArray(Dashboard.centers)) {
                const idx = Dashboard.centers.findIndex(c => String(c.id) === String(payload.id));
                if (idx !== -1) {
                    Dashboard.centers[idx] = {
                        ...Dashboard.centers[idx],
                        nombre: payload.nombre,
                        direccion: payload.direccion
                    };
                }
            }
            // Re-render listado y cerrar modal
            renderCenters();
            closeEditCenterModal();
            showNotification('Centro actualizado correctamente', 'success');
        } else {
            const msg = result.error || result.message || 'No se pudo actualizar el centro';
            showNotification(msg, 'error');
            // Marcar error en campo nombre si aplica
            if (String(msg).toLowerCase().includes('nombre')) {
                showFieldError('editCenterName', msg);
            }
        }
    } catch (err) {
        console.error(err);
        showNotification('Error al actualizar el centro', 'error');
    }
}

/**
 * Crear instalación
 */
async function createInstallation(data) {
    try {
        const response = await fetch('api/instalaciones/create.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Instalación creada exitosamente', 'success');
            closeCreateInstallationModal();
            
            // Recargar la lista de centros para actualizar contadores
            await loadCenters();
            
            // Actualizar estadísticas
            await loadStats();
        } else {
            showNotification('Error: ' + result.message, 'error');
        }
    } catch (error) {
        console.error('Error creating installation:', error);
        showNotification('Error al crear la instalación', 'error');
    }
}

/**
 * Configurar modal de actividades
 */
function setupActivityModal() {
    // Configurar selectores cascada
    setupActivityCenterSelector();
    
    // Configurar formulario de actividades
    const activityForm = document.getElementById('createActivityForm');
    if (activityForm) {
        activityForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            await createActivity();
        });
    }
}

/**
 * Configurar selector de centros para actividades
 */
function setupActivityCenterSelector() {
    // Usar el mismo patrón que el modal de instalaciones que funciona
    initActivityCenterSelector();
}

/**
 * Inicializar selector de centros para actividades
 */
function initActivityCenterSelector() {
    const wrapper = document.querySelector('#createActivityModal .custom-select-wrapper');
    const input = document.getElementById('activityCenterSearch');
    const dropdown = document.getElementById('activityCenterDropdown');
    const hiddenInput = document.getElementById('activityCenter');
    
    if (!wrapper || !input || !dropdown || !hiddenInput) return;
    
    // Evento click en input para abrir/cerrar
    input.addEventListener('click', function() {
        wrapper.classList.toggle('open');
        if (wrapper.classList.contains('open') && !window.activityCenters) {
            loadActivityCenters();
        }
    });
    
    // Evento input para filtrar
    input.addEventListener('input', function() {
        const query = this.value.toLowerCase();
        if (window.activityCenters) {
            const filtered = window.activityCenters.filter(centro => 
                centro.nombre && centro.nombre.toLowerCase().includes(query)
            );
            renderActivityCenterOptions(filtered);
        }
        
        if (!wrapper.classList.contains('open')) {
            wrapper.classList.add('open');
        }
    });
    
    // Cerrar al hacer click fuera
    document.addEventListener('click', function(e) {
        if (!wrapper.contains(e.target)) {
            wrapper.classList.remove('open');
        }
    });
    
    // Manejar teclas
    input.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            wrapper.classList.remove('open');
        }
    });
}

/**
 * Cargar centros para actividades
 */
async function loadActivityCenters() {
    try {
        const response = await fetch('api/centros/list_for_selector.php');
        const data = await response.json();
        
        if (data.success) {
            // Guardar centros globalmente para filtrado
            window.activityCenters = data.centros;
            
            // Mostrar todos los centros inicialmente
            renderActivityCenterOptions(data.centros);
            
        } else {
            showNotification('Error al cargar centros: ' + data.message, 'error');
            showActivityCenterSelectorError('Error al cargar centros');
        }
    } catch (error) {
        console.error('Error loading centers:', error);
        showNotification('Error al cargar centros', 'error');
        showActivityCenterSelectorError('Error de conexión');
    }
}

/**
 * Renderizar opciones de centros para actividades
 */
function renderActivityCenterOptions(centros) {
    const dropdown = document.getElementById('activityCenterDropdown');
    
    if (centros.length === 0) {
        dropdown.innerHTML = '<div class="custom-select-no-results">No se encontraron centros</div>';
        return;
    }
    
    dropdown.innerHTML = centros.map(centro => 
        `<div class="custom-select-option" data-value="${centro.id}" data-name="${escapeHtml(centro.nombre)}">
            <div class="option-content">
                <div class="option-title">${centro.nombre}</div>
                <div class="option-subtitle">${centro.direccion || ''}</div>
            </div>
        </div>`
    ).join('');
    
    // Agregar eventos click a las opciones
    dropdown.querySelectorAll('.custom-select-option').forEach(option => {
        option.addEventListener('click', function() {
            const value = this.dataset.value;
            const name = this.dataset.name;
            
            // Actualizar input y valor oculto
            document.getElementById('activityCenterSearch').value = name;
            document.getElementById('activityCenter').value = value;
            
            // Cerrar dropdown
            const wrapper = document.querySelector('#createActivityModal .custom-select-wrapper');
            wrapper.classList.remove('open');
            
            // Cargar instalaciones para este centro
            loadActivityInstallations(value);
            
            // Limpiar error si existe
            clearFieldError('activityCenter');
        });
    });
}

/**
 * Mostrar error en selector de centros para actividades
 */
function showActivityCenterSelectorError(message) {
    const dropdown = document.getElementById('activityCenterDropdown');
    dropdown.innerHTML = `<div class="custom-select-no-results">${message}</div>`;
}

/**
 * Cargar instalaciones por centro
 */
async function loadActivityInstallations(centroId) {
    const installationInput = document.getElementById('activityInstallationSearch');
    const installationDropdown = document.getElementById('activityInstallationDropdown');
    const installationHidden = document.getElementById('activityInstallation');
    
    if (!installationInput || !installationDropdown || !installationHidden) return;
    
    try {
        // Habilitar selector de instalaciones
        installationInput.disabled = false;
        installationInput.placeholder = 'Buscar instalación...';
        installationDropdown.innerHTML = '<div class="custom-select-loading">Cargando instalaciones...</div>';
        
        const response = await fetch(`api/instalaciones/list_by_center.php?centro_id=${centroId}`);
        const data = await response.json();
        
        if (data.success) {
            const installations = data.instalaciones;
            setupActivityInstallationSelector(installations);
        } else {
            installationDropdown.innerHTML = '<div class="custom-select-no-results">Error cargando instalaciones</div>';
        }
    } catch (error) {
        console.error('Error loading installations:', error);
        installationDropdown.innerHTML = '<div class="custom-select-no-results">Error cargando instalaciones</div>';
    }
}

/**
 * Configurar selector de instalaciones para actividades
 */
function setupActivityInstallationSelector(installations) {
    const wrapper = document.querySelector('#createActivityModal .form-group:nth-child(2) .custom-select-wrapper');
    const input = document.getElementById('activityInstallationSearch');
    const dropdown = document.getElementById('activityInstallationDropdown');
    const hiddenInput = document.getElementById('activityInstallation');
    
    if (!wrapper || !input || !dropdown || !hiddenInput) return;
    
    // Guardar instalaciones globalmente
    window.activityInstallations = installations;
    
    // Evento click en input para abrir/cerrar
    input.addEventListener('click', function() {
        if (!input.disabled) {
            wrapper.classList.toggle('open');
        }
    });
    
    // Evento input para filtrar
    input.addEventListener('input', function() {
        const query = this.value.toLowerCase();
        const filtered = installations.filter(instalacion => 
            instalacion.nombre && instalacion.nombre.toLowerCase().includes(query)
        );
        renderActivityInstallationOptions(filtered);
        
        if (!wrapper.classList.contains('open') && !input.disabled) {
            wrapper.classList.add('open');
        }
    });
    
    // Cerrar al hacer click fuera
    document.addEventListener('click', function(e) {
        if (!wrapper.contains(e.target)) {
            wrapper.classList.remove('open');
        }
    });
    
    // Manejar teclas
    input.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            wrapper.classList.remove('open');
        }
    });
    
    // Renderizar inicialmente
    renderActivityInstallationOptions(installations);
}

/**
 * Renderizar opciones de instalaciones para actividades
 */
function renderActivityInstallationOptions(instalaciones) {
    const dropdown = document.getElementById('activityInstallationDropdown');
    
    if (instalaciones.length === 0) {
        dropdown.innerHTML = '<div class="custom-select-no-results">No se encontraron instalaciones</div>';
        return;
    }
    
    dropdown.innerHTML = instalaciones.map(instalacion => 
        `<div class="custom-select-option" data-value="${instalacion.id}" data-name="${escapeHtml(instalacion.nombre)}">
            <div class="option-content">
                <div class="option-title">${instalacion.nombre}</div>
            </div>
        </div>`
    ).join('');
    
    // Agregar eventos click a las opciones
    dropdown.querySelectorAll('.custom-select-option').forEach(option => {
        option.addEventListener('click', function() {
            const value = this.dataset.value;
            const name = this.dataset.name;
            
            // Actualizar input y valor oculto
            document.getElementById('activityInstallationSearch').value = name;
            document.getElementById('activityInstallation').value = value;
            
            // Cerrar dropdown
            const wrapper = document.querySelector('#createActivityModal .form-group:nth-child(2) .custom-select-wrapper');
            wrapper.classList.remove('open');
            
            // Limpiar error si existe
            clearFieldError('activityInstallation');
        });
    });
}

/**
 * Mostrar modal crear actividad
 */
function showCreateActivityModal() {
    const modal = document.getElementById('createActivityModal');
    if (modal) {
        modal.classList.add('show');
        
        // Limpiar formulario
        const form = document.getElementById('createActivityForm');
        if (form) {
            form.reset();
            clearFormErrors();
        }
        
        // Resetear selectores
        document.getElementById('activityCenterSearch').value = '';
        document.getElementById('activityCenter').value = '';
        document.getElementById('activityInstallationSearch').value = '';
        document.getElementById('activityInstallationSearch').disabled = true;
        document.getElementById('activityInstallationSearch').placeholder = 'Primero selecciona un centro';
        document.getElementById('activityInstallation').value = '';
        
        // Establecer fecha de inicio por defecto
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('activityStartDate').value = today;
    }
}

/**
 * Cerrar modal crear actividad
 */
function closeCreateActivityModal() {
    const modal = document.getElementById('createActivityModal');
    if (modal) {
        modal.classList.remove('show');
    }
}

/**
 * Crear actividad
 */
async function createActivity() {
    const btn = document.getElementById('createActivityBtn');
    const btnText = btn.querySelector('.btn-text');
    const btnLoading = btn.querySelector('.btn-loading');
    
    try {
        // Mostrar loading
        btn.disabled = true;
        btnText.style.display = 'none';
        btnLoading.style.display = 'inline-flex';
        
        // Limpiar errores previos
        clearFormErrors();
        
        // Obtener datos del formulario
        const form = document.getElementById('createActivityForm');
        const formData = new FormData(form);
        
        // Obtener días seleccionados
        const diasSemana = [];
        form.querySelectorAll('input[name="dias_semana[]"]:checked').forEach(checkbox => {
            diasSemana.push(checkbox.value);
        });
        
        // Preparar datos
        const data = {
            nombre: formData.get('nombre'),
            instalacion_id: formData.get('instalacion_id'),
            dias_semana: diasSemana,
            hora_inicio: formData.get('hora_inicio'),
            hora_fin: formData.get('hora_fin'),
            fecha_inicio: formData.get('fecha_inicio'),
            fecha_fin: formData.get('fecha_fin') || null
        };
        
        // Validaciones básicas
        if (!data.nombre.trim()) {
            showFieldError('activityName', 'El nombre es obligatorio');
            return;
        }
        
        if (!data.instalacion_id) {
            showFieldError('activityInstallation', 'Debe seleccionar una instalación');
            return;
        }
        
        if (diasSemana.length === 0) {
            showFieldError('dias_semana', 'Debe seleccionar al menos un día');
            return;
        }
        
        if (!data.hora_inicio || !data.hora_fin) {
            showFieldError('activityStartTime', 'Las horas son obligatorias');
            return;
        }
        
        if (!data.fecha_inicio) {
            showFieldError('activityStartDate', 'La fecha de inicio es obligatoria');
            return;
        }
        
        // Enviar datos
        const response = await fetch('api/actividades/create.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Actividad creada exitosamente', 'success');
            closeCreateActivityModal();
            
            // Actualizar estadísticas
            await loadStats();
        } else {
            showNotification('Error: ' + result.message, 'error');
        }
    } catch (error) {
        console.error('Error creating activity:', error);
        showNotification('Error al crear la actividad', 'error');
    } finally {
        // Ocultar loading
        btn.disabled = false;
        btnText.style.display = 'inline';
        btnLoading.style.display = 'none';
    }
}

/**
 * Configurar modal de participantes
 */
function setupParticipantModal() {
    // Configurar pestañas
    setupParticipantTabs();
    
    // Configurar selectores cascada para pestaña manual
    setupParticipantCenterSelector();
    
    // Configurar selectores cascada para pestaña CSV
    setupCsvParticipantCenterSelector();
    
    // Configurar formularios
    const manualForm = document.getElementById('createParticipantForm');
    if (manualForm) {
        manualForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            await createParticipant();
        });
    }
    
    const csvForm = document.getElementById('uploadParticipantCsvForm');
    if (csvForm) {
        csvForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            await uploadParticipantCsv();
        });
    }
    
    // Inicializar tabla rápida (si existe)
    if (document.getElementById('dashQuickEntryBody')) {
        initializeDashQuickEntryRows();
    }

    // Configurar selector de archivo CSV
    const csvFileInput = document.getElementById('participantCsvFile');
    if (csvFileInput) {
        csvFileInput.addEventListener('change', function() {
            handleCsvFileSelection(this);
        });
    }
}

/**
 * Configurar pestañas del modal de participantes
 */
function setupParticipantTabs() {
    // No necesita configuración adicional, se maneja con onclick
}

/**
 * Cambiar pestaña del modal de participantes
 */
function switchParticipantTab(tabType) {
    // Cambiar botones de pestaña
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    
    // Cambiar contenido de pestañas
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    
    if (tabType === 'manual') {
        document.getElementById('manualTab').classList.add('active');
        document.getElementById('createParticipantBtn').style.display = 'inline-flex';
        document.getElementById('uploadCsvBtn').style.display = 'none';
    } else if (tabType === 'csv') {
        document.getElementById('csvTab').classList.add('active');
        document.getElementById('createParticipantBtn').style.display = 'none';
        document.getElementById('uploadCsvBtn').style.display = 'inline-flex';
    }
}

/**
 * Configurar selector de centros para participantes (pestaña manual)
 */
function setupParticipantCenterSelector() {
    initParticipantCenterSelector('participantCenter', 'participantInstallation', 'participantActivity');
}

/**
 * Configurar selector de centros para CSV
 */
function setupCsvParticipantCenterSelector() {
    initParticipantCenterSelector('csvParticipantCenter', 'csvParticipantInstallation', 'csvParticipantActivity');
}

/**
 * Inicializar selector de centros para participantes (genérico)
 */
function initParticipantCenterSelector(centerPrefix, installationPrefix, activityPrefix) {
    const wrapper = document.querySelector(`#${centerPrefix}Search`).closest('.custom-select-wrapper');
    const input = document.getElementById(`${centerPrefix}Search`);
    const dropdown = document.getElementById(`${centerPrefix}Dropdown`);
    const hiddenInput = document.getElementById(centerPrefix);
    
    if (!wrapper || !input || !dropdown || !hiddenInput) return;
    
    // Evento click en input para abrir/cerrar
    input.addEventListener('click', function() {
        wrapper.classList.toggle('open');
        if (wrapper.classList.contains('open') && !window.participantCenters) {
            loadParticipantCenters(centerPrefix);
        }
    });
    
    // Evento input para filtrar
    input.addEventListener('input', function() {
        const query = this.value.toLowerCase();
        if (window.participantCenters) {
            const filtered = window.participantCenters.filter(centro => 
                centro.nombre && centro.nombre.toLowerCase().includes(query)
            );
            renderParticipantCenterOptions(filtered, centerPrefix, installationPrefix, activityPrefix);
        }
        
        if (!wrapper.classList.contains('open')) {
            wrapper.classList.add('open');
        }
    });
    
    // Cerrar al hacer click fuera
    document.addEventListener('click', function(e) {
        if (!wrapper.contains(e.target)) {
            wrapper.classList.remove('open');
        }
    });
    
    // Manejar teclas
    input.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            wrapper.classList.remove('open');
        }
    });
}

/**
 * Cargar centros para participantes
 */
async function loadParticipantCenters(centerPrefix) {
    try {
        const response = await fetch('api/centros/list_for_selector.php');
        const data = await response.json();
        
        if (data.success) {
            window.participantCenters = data.centros;
            renderParticipantCenterOptions(data.centros, centerPrefix);
        } else {
            showParticipantCenterSelectorError('Error al cargar centros', centerPrefix);
        }
    } catch (error) {
        console.error('Error loading centers:', error);
        showParticipantCenterSelectorError('Error de conexión', centerPrefix);
    }
}

/**
 * Renderizar opciones de centros para participantes
 */
function renderParticipantCenterOptions(centros, centerPrefix, installationPrefix, activityPrefix) {
    const dropdown = document.getElementById(`${centerPrefix}Dropdown`);
    
    if (centros.length === 0) {
        dropdown.innerHTML = '<div class="custom-select-no-results">No se encontraron centros</div>';
        return;
    }
    
    dropdown.innerHTML = centros.map(centro => 
        `<div class="custom-select-option" data-value="${centro.id}" data-name="${escapeHtml(centro.nombre)}">
            <div class="option-content">
                <div class="option-title">${centro.nombre}</div>
                <div class="option-subtitle">${centro.direccion || ''}</div>
            </div>
        </div>`
    ).join('');
    
    // Agregar eventos click a las opciones
    dropdown.querySelectorAll('.custom-select-option').forEach(option => {
        option.addEventListener('click', function() {
            const value = this.dataset.value;
            const name = this.dataset.name;
            
            // Actualizar input y valor oculto
            document.getElementById(`${centerPrefix}Search`).value = name;
            document.getElementById(centerPrefix).value = value;
            
            // Cerrar dropdown
            const wrapper = document.querySelector(`#${centerPrefix}Search`).closest('.custom-select-wrapper');
            wrapper.classList.remove('open');
            
            // Cargar instalaciones para este centro
            loadParticipantInstallations(value, installationPrefix, activityPrefix);
            
            // Limpiar error si existe
            clearFieldError(centerPrefix);
        });
    });
}

/**
 * Cargar instalaciones para participantes
 */
async function loadParticipantInstallations(centroId, installationPrefix, activityPrefix) {
    const input = document.getElementById(`${installationPrefix}Search`);
    const dropdown = document.getElementById(`${installationPrefix}Dropdown`);
    
    try {
        // Habilitar selector de instalaciones
        input.disabled = false;
        input.placeholder = 'Buscar instalación...';
        dropdown.innerHTML = '<div class="custom-select-loading">Cargando instalaciones...</div>';
        
        const response = await fetch(`api/instalaciones/list_by_center.php?centro_id=${centroId}`);
        const data = await response.json();
        
        if (data.success) {
            setupParticipantInstallationSelector(data.instalaciones, installationPrefix, activityPrefix);
        } else {
            dropdown.innerHTML = '<div class="custom-select-no-results">Error cargando instalaciones</div>';
        }
    } catch (error) {
        console.error('Error loading installations:', error);
        dropdown.innerHTML = '<div class="custom-select-no-results">Error cargando instalaciones</div>';
    }
}

/**
 * Configurar selector de instalaciones para participantes
 */
function setupParticipantInstallationSelector(installations, installationPrefix, activityPrefix) {
    const wrapper = document.querySelector(`#${installationPrefix}Search`).closest('.custom-select-wrapper');
    const input = document.getElementById(`${installationPrefix}Search`);
    const dropdown = document.getElementById(`${installationPrefix}Dropdown`);
    const hiddenInput = document.getElementById(installationPrefix);
    
    // Evento click en input para abrir/cerrar
    input.addEventListener('click', function() {
        if (!input.disabled) {
            wrapper.classList.toggle('open');
        }
    });
    
    // Evento input para filtrar
    input.addEventListener('input', function() {
        const query = this.value.toLowerCase();
        const filtered = installations.filter(instalacion => 
            instalacion.nombre && instalacion.nombre.toLowerCase().includes(query)
        );
        renderParticipantInstallationOptions(filtered, installationPrefix, activityPrefix);
        
        if (!wrapper.classList.contains('open') && !input.disabled) {
            wrapper.classList.add('open');
        }
    });
    
    // Renderizar inicialmente
    renderParticipantInstallationOptions(installations, installationPrefix, activityPrefix);
}

/**
 * Renderizar opciones de instalaciones para participantes
 */
function renderParticipantInstallationOptions(instalaciones, installationPrefix, activityPrefix) {
    const dropdown = document.getElementById(`${installationPrefix}Dropdown`);
    
    if (instalaciones.length === 0) {
        dropdown.innerHTML = '<div class="custom-select-no-results">No se encontraron instalaciones</div>';
        return;
    }
    
    dropdown.innerHTML = instalaciones.map(instalacion => 
        `<div class="custom-select-option" data-value="${instalacion.id}" data-name="${escapeHtml(instalacion.nombre)}">
            <div class="option-content">
                <div class="option-title">${instalacion.nombre}</div>
            </div>
        </div>`
    ).join('');
    
    // Agregar eventos click a las opciones
    dropdown.querySelectorAll('.custom-select-option').forEach(option => {
        option.addEventListener('click', function() {
            const value = this.dataset.value;
            const name = this.dataset.name;
            
            // Actualizar input y valor oculto
            document.getElementById(`${installationPrefix}Search`).value = name;
            document.getElementById(installationPrefix).value = value;
            
            // Cerrar dropdown
            const wrapper = document.querySelector(`#${installationPrefix}Search`).closest('.custom-select-wrapper');
            wrapper.classList.remove('open');
            
            // Cargar actividades para esta instalación
            loadParticipantActivities(value, activityPrefix);
            
            // Limpiar error si existe
            clearFieldError(installationPrefix);
        });
    });
}

/**
 * Cargar actividades para participantes
 */
async function loadParticipantActivities(instalacionId, activityPrefix) {
    const input = document.getElementById(`${activityPrefix}Search`);
    const dropdown = document.getElementById(`${activityPrefix}Dropdown`);
    
    try {
        // Habilitar selector de actividades
        input.disabled = false;
        input.placeholder = 'Buscar actividad...';
        dropdown.innerHTML = '<div class="custom-select-loading">Cargando actividades...</div>';
        
        const response = await fetch(`api/actividades/list_by_installation.php?instalacion_id=${instalacionId}`);
        const data = await response.json();
        
        if (data.success) {
            setupParticipantActivitySelector(data.actividades, activityPrefix);
        } else {
            dropdown.innerHTML = '<div class="custom-select-no-results">Error cargando actividades</div>';
        }
    } catch (error) {
        console.error('Error loading activities:', error);
        dropdown.innerHTML = '<div class="custom-select-no-results">Error cargando actividades</div>';
    }
}

/**
 * Configurar selector de actividades para participantes
 */
function setupParticipantActivitySelector(activities, activityPrefix) {
    const wrapper = document.querySelector(`#${activityPrefix}Search`).closest('.custom-select-wrapper');
    const input = document.getElementById(`${activityPrefix}Search`);
    const dropdown = document.getElementById(`${activityPrefix}Dropdown`);
    const hiddenInput = document.getElementById(activityPrefix);
    
    // Evento click en input para abrir/cerrar
    input.addEventListener('click', function() {
        if (!input.disabled) {
            wrapper.classList.toggle('open');
        }
    });
    
    // Evento input para filtrar
    input.addEventListener('input', function() {
        const query = this.value.toLowerCase();
        const filtered = activities.filter(actividad => 
            actividad.nombre && actividad.nombre.toLowerCase().includes(query)
        );
        renderParticipantActivityOptions(filtered, activityPrefix);
        
        if (!wrapper.classList.contains('open') && !input.disabled) {
            wrapper.classList.add('open');
        }
    });
    
    // Renderizar inicialmente
    renderParticipantActivityOptions(activities, activityPrefix);
}

/**
 * Renderizar opciones de actividades para participantes
 */
function renderParticipantActivityOptions(actividades, activityPrefix) {
    const dropdown = document.getElementById(`${activityPrefix}Dropdown`);
    
    if (actividades.length === 0) {
        dropdown.innerHTML = '<div class="custom-select-no-results">No se encontraron actividades</div>';
        return;
    }
    
    dropdown.innerHTML = actividades.map(actividad => 
        `<div class="custom-select-option" data-value="${actividad.id}" data-name="${escapeHtml(actividad.nombre)}">
            <div class="option-content">
                <div class="option-title">${actividad.nombre}</div>
                <div class="option-subtitle">${actividad.dias_semana} • ${actividad.hora_inicio}-${actividad.hora_fin}</div>
            </div>
        </div>`
    ).join('');
    
    // Agregar eventos click a las opciones
    dropdown.querySelectorAll('.custom-select-option').forEach(option => {
        option.addEventListener('click', function() {
            const value = this.dataset.value;
            const name = this.dataset.name;
            
            // Actualizar input y valor oculto
            document.getElementById(`${activityPrefix}Search`).value = name;
            document.getElementById(activityPrefix).value = value;
            
            // Cerrar dropdown
            const wrapper = document.querySelector(`#${activityPrefix}Search`).closest('.custom-select-wrapper');
            wrapper.classList.remove('open');
            
            // Limpiar error si existe
            clearFieldError(activityPrefix);
        });
    });
}

/**
 * Mostrar error en selector de centros para participantes
 */
function showParticipantCenterSelectorError(message, centerPrefix) {
    const dropdown = document.getElementById(`${centerPrefix}Dropdown`);
    dropdown.innerHTML = `<div class="custom-select-no-results">${message}</div>`;
}

/**
 * Mostrar modal crear participante
 */
function showCreateParticipantModal() {
    const modal = document.getElementById('createParticipantModal');
    if (modal) {
        modal.classList.add('show');
        
        // Limpiar formularios
        const manualForm = document.getElementById('createParticipantForm');
        const csvForm = document.getElementById('uploadParticipantCsvForm');
        
        if (manualForm) {
            manualForm.reset();
        }
        if (csvForm) {
            csvForm.reset();
        }
        
        clearFormErrors();
        
        // Resetear selectores manuales
        resetParticipantSelectors('participantCenter', 'participantInstallation', 'participantActivity');
        
        // Resetear selectores CSV
        resetParticipantSelectors('csvParticipantCenter', 'csvParticipantInstallation', 'csvParticipantActivity');
        
        // Activar pestaña manual por defecto
        switchParticipantTab('manual');
        
        // Ocultar info de archivo CSV
        document.getElementById('csvFileInfo').style.display = 'none';
        
        // Configurar modal (event listeners y selectores)
        setupParticipantModal();
    }
}

/**
 * Resetear selectores de participantes
 */
function resetParticipantSelectors(centerPrefix, installationPrefix, activityPrefix) {
    // Resetear centro
    document.getElementById(`${centerPrefix}Search`).value = '';
    document.getElementById(centerPrefix).value = '';
    
    // Resetear instalación
    const installationInput = document.getElementById(`${installationPrefix}Search`);
    installationInput.value = '';
    installationInput.disabled = true;
    installationInput.placeholder = 'Selecciona un centro';
    document.getElementById(installationPrefix).value = '';
    
    // Resetear actividad
    const activityInput = document.getElementById(`${activityPrefix}Search`);
    activityInput.value = '';
    activityInput.disabled = true;
    activityInput.placeholder = 'Selecciona una instalación';
    document.getElementById(activityPrefix).value = '';
}

/**
 * Cerrar modal crear participante
 */
function closeCreateParticipantModal() {
    const modal = document.getElementById('createParticipantModal');
    if (modal) {
        modal.classList.remove('show');
    }
}

/**
 * Manejar selección de archivo CSV
 */
function handleCsvFileSelection(input) {
    const fileInfo = document.getElementById('csvFileInfo');
    const fileName = document.getElementById('csvFileName');
    
    if (input.files.length > 0) {
        const file = input.files[0];
        
        // Verificar que sea CSV
        if (!file.name.toLowerCase().endsWith('.csv')) {
            showNotification('Por favor, selecciona un archivo CSV', 'error');
            input.value = '';
            return;
        }
        
        // Mostrar información del archivo
        fileName.textContent = file.name;
        fileInfo.style.display = 'flex';
        
    } else {
        fileInfo.style.display = 'none';
    }
}

/**
 * Remover archivo CSV seleccionado
 */
function removeCsvFile() {
    document.getElementById('participantCsvFile').value = '';
    document.getElementById('csvFileInfo').style.display = 'none';
}

/**
 * Crear participante individual
 */
async function createParticipant() {
    const btn = document.getElementById('createParticipantBtn');
    const btnText = btn.querySelector('.btn-text');
    const btnLoading = btn.querySelector('.btn-loading');
    
    // Prevenir doble submit
    if (btn.dataset.submitting === 'true') {
        return;
    }
    
    try {
        // Marcar como enviando
        btn.dataset.submitting = 'true';
        
        // Mostrar loading
        btn.disabled = true;
        btnText.style.display = 'none';
        btnLoading.style.display = 'inline-flex';
        
        // Limpiar errores previos
        clearFormErrors();
        
        // Validar actividad seleccionada
        const actividadId = document.getElementById('participantActivity').value;
        if (!actividadId) {
            showFieldError('participantActivity', 'Debe seleccionar una actividad');
            return;
        }
        
        // Recopilar filas de entrada rápida
        const participantes = collectDashQuickEntries();
        if (!participantes.length) {
            const err = document.getElementById('dashQuickEntryError');
            if (err) err.textContent = 'Añade al menos una fila con Nombre y Apellidos';
            return;
        }
        
        // Enviar datos en bloque
        const response = await fetch('api/participantes/create_multiple.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ actividad_id: Number(actividadId), participantes })
        });
        const result = await response.json();
        
        if (result.success) {
            const inserted = Number(result.inserted || 0);
            const errs = (result.errors || []).length;
            showNotification(`Añadidos ${inserted} participante(s)${errs ? `, ${errs} con error` : ''}`, inserted ? 'success' : 'warning');
            // Cerrar modal tras completar correctamente
            closeCreateParticipantModal();
            // Actualizar estadísticas si aplica
            if (typeof loadStats === 'function') { await loadStats(); }
        } else {
            showNotification('Error: ' + (result.message || 'No se pudo añadir'), 'error');
        }
    } catch (error) {
        console.error('Error creating participant:', error);
        showNotification('Error al inscribir el participante', 'error');
    } finally {
        // Limpiar flag de envío
        btn.dataset.submitting = 'false';
        
        // Ocultar loading
        btn.disabled = false;
        btnText.style.display = 'inline';
        btnLoading.style.display = 'none';
    }
}

// Quick entry helpers (Dashboard)
function initializeDashQuickEntryRows() {
    const tbody = document.getElementById('dashQuickEntryBody');
    if (!tbody) return;
    tbody.innerHTML = '';
    for (let i = 0; i < 3; i++) addDashQuickEntryRow();
    enableDashQuickEntryPaste();
    enableDashQuickEntryAutoAdvance();
}

function addDashQuickEntryRow() {
    const tbody = document.getElementById('dashQuickEntryBody');
    if (!tbody) return;
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td><input type="text" class="form-input" placeholder="Nombre"></td>
      <td><input type="text" class="form-input" placeholder="Apellidos"></td>
      <td style="text-align:right">
        <button type="button" class="btn btn-secondary" title="Eliminar" onclick="this.closest('tr').remove()">&times;</button>
      </td>`;
    tbody.appendChild(tr);
}

function collectDashQuickEntries() {
    const rows = Array.from(document.querySelectorAll('#dashQuickEntryBody tr'));
    const items = [];
    rows.forEach(r => {
        const inputs = r.querySelectorAll('input');
        const nombre = (inputs[0]?.value || '').trim();
        const apellidos = (inputs[1]?.value || '').trim();
        if (nombre || apellidos) {
            if (nombre && apellidos) items.push({ nombre, apellidos });
        }
    });
    return items;
}

function enableDashQuickEntryPaste() {
    const tbody = document.getElementById('dashQuickEntryBody');
    if (!tbody) return;
    tbody.addEventListener('paste', handleDashQuickEntryPaste);
}

function handleDashQuickEntryPaste(e) {
    const target = e.target;
    if (!target || target.tagName !== 'INPUT') return;
    const tbody = document.getElementById('dashQuickEntryBody');
    if (!tbody) return;
    const clip = e.clipboardData || window.clipboardData;
    if (!clip) return;
    const text = (clip.getData('text/plain') || '').trim();
    if (!text || (text.indexOf('\n') === -1 && text.indexOf('\t') === -1)) return;

    e.preventDefault();

    const rowEl = target.closest('tr');
    const startIndex = Array.from(tbody.querySelectorAll('tr')).indexOf(rowEl);
    const lines = text.split(/\r?\n/).filter(l => l.trim().length > 0);

    const needed = startIndex + lines.length;
    while (tbody.querySelectorAll('tr').length < needed) addDashQuickEntryRow();

    lines.forEach((line, i) => {
        const cols = line.split(/\t|;|,/);
        let nombre = '';
        let apellidos = '';
        if (cols.length >= 2) {
            nombre = String(cols[0] || '').trim();
            apellidos = cols.slice(1).join(' ').trim();
        } else if (cols.length === 1) {
            const one = String(cols[0] || '').trim();
            const parts = one.split(/\s+/);
            if (parts.length >= 2) { nombre = parts[0]; apellidos = parts.slice(1).join(' '); }
            else { nombre = one; }
        }
        const row = tbody.querySelectorAll('tr')[startIndex + i];
        if (!row) return;
        const inputs = row.querySelectorAll('input');
        if (inputs[0]) inputs[0].value = nombre;
        if (inputs[1]) inputs[1].value = apellidos;
    });

    const nextIndex = startIndex + lines.length;
    while (tbody.querySelectorAll('tr').length <= nextIndex) addDashQuickEntryRow();
    const nextRow = tbody.querySelectorAll('tr')[nextIndex];
    const nextInputs = nextRow ? nextRow.querySelectorAll('input') : null;
    if (nextInputs && nextInputs[0]) { nextInputs[0].focus(); nextInputs[0].select(); }
}

function enableDashQuickEntryAutoAdvance() {
    const tbody = document.getElementById('dashQuickEntryBody');
    if (!tbody) return;
    tbody.addEventListener('keydown', function(ev) {
        if (ev.key !== 'Enter') return;
        const target = ev.target;
        if (!target || target.tagName !== 'INPUT') return;
        ev.preventDefault();
        const row = target.closest('tr');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        const idx = rows.indexOf(row);
        const inputs = row ? row.querySelectorAll('input') : null;
        const nombre = (inputs && inputs[0] ? inputs[0].value.trim() : '');
        const apellidos = (inputs && inputs[1] ? inputs[1].value.trim() : '');
        if (!nombre && !apellidos) return;
        const nextIdx = idx + 1;
        while (tbody.querySelectorAll('tr').length <= nextIdx) addDashQuickEntryRow();
        const nextRow = tbody.querySelectorAll('tr')[nextIdx];
        const nextInputs = nextRow ? nextRow.querySelectorAll('input') : null;
        if (nextInputs && nextInputs[0]) { nextInputs[0].focus(); nextInputs[0].select(); }
    });
}

// expose add row for onclick
if (typeof window !== 'undefined') {
    window.addDashQuickEntryRow = addDashQuickEntryRow;
}

/**
 * Subir CSV de participantes
 */
async function uploadParticipantCsv() {
    const btn = document.getElementById('uploadCsvBtn');
    const btnText = btn.querySelector('.btn-text');
    const btnLoading = btn.querySelector('.btn-loading');
    
    try {
        // Mostrar loading
        btn.disabled = true;
        btnText.style.display = 'none';
        btnLoading.style.display = 'inline-flex';
        
        // Limpiar errores previos
        clearFormErrors();
        
        // Verificar que se seleccionó archivo
        const fileInput = document.getElementById('participantCsvFile');
        if (!fileInput.files.length) {
            showNotification('Debe seleccionar un archivo CSV', 'error');
            return;
        }
        
        // Verificar que se seleccionó actividad
        const activityId = document.getElementById('csvParticipantActivity').value;
        if (!activityId) {
            showFieldError('csvParticipantActivity', 'Debe seleccionar una actividad');
            return;
        }
        
        // Preparar FormData
        const formData = new FormData();
        formData.append('csv', fileInput.files[0]);
        formData.append('actividad_id', activityId);
        const modeSel = document.getElementById('dashCsvImportMode');
        const mode = modeSel ? String(modeSel.value || 'append') : 'append';
        formData.append('mode', mode);
        
        // Enviar archivo
        const response = await fetch('api/participantes/upload_csv.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification(result.message, 'success');
            closeCreateParticipantModal();
            
            // Actualizar estadísticas
            await loadStats();
        } else {
            showNotification('Error: ' + result.message, 'error');
        }
    } catch (error) {
        console.error('Error uploading CSV:', error);
        showNotification('Error al subir el archivo CSV', 'error');
    } finally {
        // Ocultar loading
        btn.disabled = false;
        btnText.style.display = 'inline';
        btnLoading.style.display = 'none';
    }
}

/**
 * Mostrar notificación
 */
function showNotification(message, type = 'info') {
    // Crear elemento de notificación
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <span class="notification-message">${message}</span>
            <button class="notification-close" onclick="this.parentElement.parentElement.remove()">&times;</button>
        </div>
    `;
    
    // Añadir al DOM
    document.body.appendChild(notification);
    
    // Mostrar con animación
    setTimeout(() => notification.classList.add('show'), 100);
    
    // Auto-remover después de 5 segundos
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 5000);
}

/**
 * Filtrar centros por búsqueda
 */
function filterCenters(searchTerm) {
    const filteredCenters = Dashboard.centers.filter(center => {
        const searchLower = searchTerm.toLowerCase();
        return center.nombre.toLowerCase().includes(searchLower) ||
               (center.direccion && center.direccion.toLowerCase().includes(searchLower));
    });
    
    renderFilteredCenters(filteredCenters);
}

/**
 * Ordenar centros
 */
function sortCenters(sortBy) {
    let sortedCenters = [...Dashboard.centers];
    
    switch(sortBy) {
        case 'nombre':
            sortedCenters.sort((a, b) => a.nombre.localeCompare(b.nombre));
            break;
        case '-nombre':
            sortedCenters.sort((a, b) => b.nombre.localeCompare(a.nombre));
            break;
        default:
            // No sorting
            break;
    }
    
    renderFilteredCenters(sortedCenters);
}

/**
 * Renderizar centros filtrados
 */
function renderFilteredCenters(centers) {
    const container = document.getElementById('centers-list');
    
    if (!centers || centers.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <div class="empty-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="48" height="48">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
                <h3>No se encontraron centros</h3>
                <p>Intenta con otros términos de búsqueda</p>
            </div>
        `;
        return;
    }

    const centersHTML = centers.map(center => `
        <div class="center-item">
            <div class="center-main">
                <div class="center-header">
                    <h3 class="center-name">${escapeHtml(center.nombre)}</h3>
                    <span class="center-status active">Activo</span>
                </div>
                <div class="center-details">
                    <span class="center-address">
                        <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10zm0-7a3 3 0 1 1 0-6 3 3 0 0 1 0 6z"/>
                        </svg>
                        ${escapeHtml(center.direccion || 'Sin dirección')}
                    </span>
                    <span class="center-stat">
                        <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M6.5 14.5v-3.505c0-.245.25-.495.5-.495h2c.25 0 .5.25.5.495v3.5a.5.5 0 0 0 .5.5h4a.5.5 0 0 0 .5-.5v-7a.5.5 0 0 0-.146-.354L13 5.793V2.5a.5.5 0 0 0-.5-.5h-1a.5.5 0 0 0-.5.5v1.293L8.354 1.146a.5.5 0 0 0-.708 0l-6 6A.5.5 0 0 0 1.5 7.5v7a.5.5 0 0 0 .5.5h4a.5.5 0 0 0 .5-.5z"/>
                        </svg>
                        ${center.total_instalaciones || 0} instalaciones
                    </span>
                    <span class="center-stat">
                        <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
                        </svg>
                        ${center.total_actividades || 0} actividades
                    </span>
                </div>
            </div>
            <div class="center-actions">
                <div class="dropdown">
                    <button class="more-btn" onclick="toggleDropdown(${center.id})">
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M3 9.5a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zm5 0a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zm5 0a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3z"/>
                        </svg>
                    </button>
                    <div class="dropdown-menu" id="dropdown-${center.id}">
                        <a href="#" onclick="viewActivities(${center.id})">Ver actividades</a>
                        <a href="#" onclick="editCenter(${center.id})">Editar centro</a>
                        <a href="#" onclick="deactivateCenter(${center.id})">Desactivar</a>
                    </div>
                </div>
            </div>
        </div>
    `).join('');

    container.innerHTML = centersHTML;
}

/**
 * Navegar al detalle de un centro
 */
function goToCenter(centerId) {
    window.location.href = `center.php?id=${centerId}`;
}

// Hacer funciones globales para uso en HTML
window.openModal = openModal;
window.viewCenter = viewCenter;
window.showCenterMenu = showCenterMenu;
window.goToCenter = goToCenter;

// ==============================
// Bulk Import - Subida en Lote
// ==============================

let bulkImportCenters = [];

/**
 * Abrir modal de Bulk Import
 */
function showBulkImportModal() {
    closeAddOptionsModal();
    const modal = document.getElementById('bulkImportModal');
    if (modal) {
        modal.classList.add('show');
        initBulkImportCenterSelector();
        initBulkImportTable();
    }
}

/**
 * Cerrar modal de Bulk Import
 */
function closeBulkImportModal() {
    const modal = document.getElementById('bulkImportModal');
    if (modal) {
        modal.classList.remove('show');
        clearBulkImportTable();
        document.getElementById('bulkImportCenter').value = '';
        document.getElementById('bulkImportCenterSearch').value = '';
        document.getElementById('bulkImportError').textContent = '';
        document.getElementById('bulkImportPreview').style.display = 'none';
    }
}

/**
 * Inicializar selector de centros para bulk import
 */
function initBulkImportCenterSelector() {
    const wrapper = document.querySelector('#bulkImportModal .custom-select-wrapper');
    const input = document.getElementById('bulkImportCenterSearch');
    const dropdown = document.getElementById('bulkImportCenterDropdown');
    const hiddenInput = document.getElementById('bulkImportCenter');
    
    if (!wrapper || !input || !dropdown || !hiddenInput) return;
    
    // Cargar centros
    loadBulkImportCenters();
    
    // Evento click en input para abrir/cerrar
    input.onclick = function() {
        wrapper.classList.toggle('open');
    };
    
    // Evento input para filtrar
    input.oninput = function() {
        const query = this.value.toLowerCase();
        if (bulkImportCenters) {
            const filtered = bulkImportCenters.filter(centro => 
                centro.nombre && centro.nombre.toLowerCase().includes(query)
            );
            renderBulkImportCenterOptions(filtered);
        }
        
        if (!wrapper.classList.contains('open')) {
            wrapper.classList.add('open');
        }
    };
    
    // Cerrar al hacer click fuera
    document.addEventListener('click', function(e) {
        if (!wrapper.contains(e.target)) {
            wrapper.classList.remove('open');
        }
    });
}

/**
 * Cargar centros para bulk import
 */
async function loadBulkImportCenters() {
    const dropdown = document.getElementById('bulkImportCenterDropdown');
    
    try {
        dropdown.innerHTML = '<div class="custom-select-loading">Cargando centros...</div>';
        
        const response = await fetch('api/centros/list_for_selector.php');
        const data = await response.json();
        
        if (data.success) {
            bulkImportCenters = data.centros || [];
            renderBulkImportCenterOptions(bulkImportCenters);
        } else {
            dropdown.innerHTML = '<div class="custom-select-no-results">Error cargando centros</div>';
        }
    } catch (error) {
        console.error('Error loading centers:', error);
        dropdown.innerHTML = '<div class="custom-select-no-results">Error cargando centros</div>';
    }
}

/**
 * Renderizar opciones de centros para bulk import
 */
function renderBulkImportCenterOptions(centros) {
    const dropdown = document.getElementById('bulkImportCenterDropdown');
    
    if (!centros || centros.length === 0) {
        dropdown.innerHTML = '<div class="custom-select-no-results">No se encontraron centros</div>';
        return;
    }
    
    dropdown.innerHTML = centros.map(centro => 
        `<div class="custom-select-option" data-value="${centro.id}" data-name="${escapeHtml(centro.nombre)}">
            <div class="option-content">
                <div class="option-title">${escapeHtml(centro.nombre)}</div>
                ${centro.direccion ? `<div class="option-subtitle">${escapeHtml(centro.direccion)}</div>` : ''}
            </div>
        </div>`
    ).join('');
    
    // Agregar eventos click a las opciones
    dropdown.querySelectorAll('.custom-select-option').forEach(option => {
        option.addEventListener('click', function() {
            const value = this.dataset.value;
            const name = this.dataset.name;
            
            document.getElementById('bulkImportCenterSearch').value = name;
            document.getElementById('bulkImportCenter').value = value;
            
            const wrapper = document.querySelector('#bulkImportModal .custom-select-wrapper');
            wrapper.classList.remove('open');
            
            clearFieldError('bulkImportCenter');
        });
    });
}

/**
 * Inicializar tabla de bulk import con filas vacías y soporte para pegado
 */
function initBulkImportTable() {
    const tbody = document.getElementById('bulkImportBody');
    tbody.innerHTML = '';
    
    // Añadir 5 filas iniciales
    for (let i = 0; i < 5; i++) {
        addBulkImportRow();
    }
    
    // Configurar listener para pegado desde Excel
    tbody.addEventListener('paste', handleBulkImportPaste);
    
    updateBulkImportRowCount();
}

/**
 * Añadir una fila a la tabla de bulk import
 */
function addBulkImportRow() {
    const tbody = document.getElementById('bulkImportBody');
    const row = document.createElement('tr');
    row.innerHTML = `
        <td><input type="text" class="bulk-nombre" placeholder="Nombre"></td>
        <td><input type="text" class="bulk-apellidos" placeholder="Apellidos"></td>
        <td><input type="text" class="bulk-instalacion" placeholder="Instalación"></td>
        <td><input type="text" class="bulk-actividad" placeholder="Actividad"></td>
        <td><input type="text" class="bulk-fecha" placeholder="dd/mm/aa"></td>
        <td><input type="text" class="bulk-dias" placeholder="Lunes, Miércoles..."></td>
        <td><button type="button" class="btn-remove-row" onclick="removeBulkImportRow(this)">&times;</button></td>
    `;
    tbody.appendChild(row);
    updateBulkImportRowCount();
}

/**
 * Eliminar una fila de la tabla de bulk import
 */
function removeBulkImportRow(btn) {
    const row = btn.closest('tr');
    if (row) {
        row.remove();
        updateBulkImportRowCount();
    }
}

/**
 * Limpiar tabla de bulk import
 */
function clearBulkImportTable() {
    const tbody = document.getElementById('bulkImportBody');
    tbody.innerHTML = '';
    for (let i = 0; i < 5; i++) {
        addBulkImportRow();
    }
    updateBulkImportRowCount();
}

/**
 * Actualizar contador de filas
 */
function updateBulkImportRowCount() {
    const tbody = document.getElementById('bulkImportBody');
    const rows = tbody.querySelectorAll('tr');
    let filledRows = 0;
    
    rows.forEach(row => {
        const nombre = row.querySelector('.bulk-nombre')?.value?.trim() || '';
        const apellidos = row.querySelector('.bulk-apellidos')?.value?.trim() || '';
        if (nombre || apellidos) filledRows++;
    });
    
    const countEl = document.getElementById('bulkImportRowCount');
    if (countEl) {
        countEl.textContent = `${filledRows} fila(s) con datos`;
    }
}

/**
 * Manejar pegado desde Excel
 */
function handleBulkImportPaste(event) {
    const clipboardData = event.clipboardData || window.clipboardData;
    const pastedText = clipboardData.getData('text');
    
    if (!pastedText) return;
    
    // Detectar si viene de Excel (tiene tabs o múltiples líneas)
    if (pastedText.includes('\t') || pastedText.split('\n').length > 1) {
        event.preventDefault();
        
        const lines = pastedText.split('\n').filter(line => line.trim());
        const tbody = document.getElementById('bulkImportBody');
        
        // Limpiar tabla existente
        tbody.innerHTML = '';
        
        lines.forEach((line, index) => {
            // Ignorar primera línea si parece ser encabezado
            if (index === 0) {
                const firstCell = line.split('\t')[0]?.toLowerCase().trim();
                if (firstCell === 'nombre' || firstCell === 'name') {
                    return; // Saltar encabezado
                }
            }
            
            const cells = line.split('\t');
            
            // Mínimo 2 columnas (nombre, apellidos)
            if (cells.length < 2) return;
            
            // Formato esperado del Excel del usuario:
            // A: Nombre, B: Apellidos, C: Centro (ignorar), D: Instalación, E: Actividad, F: Fecha, G+: Días
            const nombre = (cells[0] || '').trim();
            const apellidos = (cells[1] || '').trim();
            // Columna 2 es "Centro" - la ignoramos porque se selecciona manualmente
            const instalacion = (cells[3] || '').trim(); // Columna D
            const actividad = (cells[4] || '').trim();   // Columna E
            const fecha = (cells[5] || '').trim();       // Columna F
            
            // Días están desde columna G (índice 6) en adelante, pueden ser varias columnas
            let dias = '';
            if (cells.length > 6) {
                const diasCols = cells.slice(6).filter(d => d.trim());
                if (diasCols.length > 0) {
                    dias = diasCols.join(', ');
                }
            }
            
            // Crear fila
            const row = document.createElement('tr');
            row.innerHTML = `
                <td><input type="text" class="bulk-nombre" value="${escapeHtml(nombre)}"></td>
                <td><input type="text" class="bulk-apellidos" value="${escapeHtml(apellidos)}"></td>
                <td><input type="text" class="bulk-instalacion" value="${escapeHtml(instalacion)}"></td>
                <td><input type="text" class="bulk-actividad" value="${escapeHtml(actividad)}"></td>
                <td><input type="text" class="bulk-fecha" value="${escapeHtml(fecha)}"></td>
                <td><input type="text" class="bulk-dias" value="${escapeHtml(dias)}"></td>
                <td><button type="button" class="btn-remove-row" onclick="removeBulkImportRow(this)">&times;</button></td>
            `;
            tbody.appendChild(row);
        });
        
        // Añadir filas vacías si hay menos de 3
        const currentRows = tbody.querySelectorAll('tr').length;
        for (let i = currentRows; i < 3; i++) {
            addBulkImportRow();
        }
        
        updateBulkImportRowCount();
        showNotification(`${lines.length - (lines[0]?.split('\t')[0]?.toLowerCase() === 'nombre' ? 1 : 0)} filas importadas desde el portapapeles`, 'success');
    }
}

/**
 * Ejecutar la importación en lote
 */
async function executeBulkImport() {
    const centroId = document.getElementById('bulkImportCenter').value;
    const errorEl = document.getElementById('bulkImportError');
    const btn = document.getElementById('bulkImportBtn');
    
    // Validar centro
    if (!centroId) {
        showFieldError('bulkImportCenter', 'Debe seleccionar un centro');
        return;
    }
    
    // Recopilar filas
    const tbody = document.getElementById('bulkImportBody');
    const rows = [];
    
    tbody.querySelectorAll('tr').forEach(tr => {
        const nombre = tr.querySelector('.bulk-nombre')?.value?.trim() || '';
        const apellidos = tr.querySelector('.bulk-apellidos')?.value?.trim() || '';
        const instalacion = tr.querySelector('.bulk-instalacion')?.value?.trim() || '';
        const actividad = tr.querySelector('.bulk-actividad')?.value?.trim() || '';
        const fecha = tr.querySelector('.bulk-fecha')?.value?.trim() || '';
        const dias = tr.querySelector('.bulk-dias')?.value?.trim() || '';
        
        // Solo añadir filas que tengan al menos nombre
        if (nombre || apellidos) {
            rows.push({
                nombre,
                apellidos,
                instalacion,
                actividad,
                fecha_inicio: fecha,
                dias_semana: dias
            });
        }
    });
    
    if (rows.length === 0) {
        errorEl.textContent = 'No hay datos para importar. Pega datos desde Excel o añádelos manualmente.';
        return;
    }
    
    // Mostrar loading
    btn.classList.add('loading');
    errorEl.textContent = '';
    
    try {
        const response = await fetch('api/bulk_import.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                centro_id: parseInt(centroId),
                rows: rows
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification(result.message, 'success');
            
            // Mostrar preview con estadísticas
            showBulkImportResults(result.stats);
            
            // Recargar datos del dashboard
            await loadCenters();
            await loadStats();
            
            // Cerrar modal después de 2 segundos si no hay errores
            if (!result.stats.errores || result.stats.errores.length === 0) {
                setTimeout(() => {
                    closeBulkImportModal();
                }, 2000);
            }
        } else {
            errorEl.textContent = result.message || 'Error al importar datos';
            showNotification('Error: ' + (result.message || 'Error desconocido'), 'error');
        }
    } catch (error) {
        console.error('Error en bulk import:', error);
        errorEl.textContent = 'Error de conexión al servidor';
        showNotification('Error de conexión', 'error');
    } finally {
        btn.classList.remove('loading');
    }
}

/**
 * Mostrar resultados de la importación
 */
function showBulkImportResults(stats) {
    const previewEl = document.getElementById('bulkImportPreview');
    const contentEl = document.getElementById('bulkImportPreviewContent');
    
    let html = '<div class="preview-stats">';
    
    if (stats.instalaciones_creadas > 0) {
        html += `<div class="preview-stat"><strong>${stats.instalaciones_creadas}</strong> instalación(es) creada(s)</div>`;
    }
    if (stats.instalaciones_reutilizadas > 0) {
        html += `<div class="preview-stat"><strong>${stats.instalaciones_reutilizadas}</strong> instalación(es) existente(s)</div>`;
    }
    if (stats.actividades_creadas > 0) {
        html += `<div class="preview-stat"><strong>${stats.actividades_creadas}</strong> actividad(es) creada(s)</div>`;
    }
    if (stats.participantes_creados > 0) {
        html += `<div class="preview-stat"><strong>${stats.participantes_creados}</strong> participante(s) inscrito(s)</div>`;
    }
    
    html += '</div>';
    
    // Mostrar errores si los hay
    if (stats.errores && stats.errores.length > 0) {
        html += '<div class="bulk-import-errors">';
        html += `<h5>⚠️ Errores encontrados (${stats.errores.length})</h5>`;
        html += '<ul>';
        stats.errores.slice(0, 10).forEach(error => {
            html += `<li>${escapeHtml(error)}</li>`;
        });
        if (stats.errores.length > 10) {
            html += `<li>... y ${stats.errores.length - 10} más</li>`;
        }
        html += '</ul></div>';
    }
    
    contentEl.innerHTML = html;
    previewEl.style.display = 'block';
}

// Actualizar selectCreateOption para incluir bulk
const originalSelectCreateOption = window.selectCreateOption;
window.selectCreateOption = function(option) {
    if (option === 'bulk') {
        showBulkImportModal();
    } else if (typeof originalSelectCreateOption === 'function') {
        originalSelectCreateOption(option);
    } else {
        // Fallback si no existe la función original
        closeAddOptionsModal();
        switch(option) {
            case 'centro':
                showCreateCenterModal();
                break;
            case 'instalacion':
                showCreateInstallationModal();
                break;
            case 'actividad':
                showCreateActivityModal();
                break;
            case 'participante':
                showAddParticipantModal();
                break;
        }
    }
};

// Exponer funciones globalmente
window.showBulkImportModal = showBulkImportModal;
window.closeBulkImportModal = closeBulkImportModal;
window.addBulkImportRow = addBulkImportRow;
window.removeBulkImportRow = removeBulkImportRow;
window.clearBulkImportTable = clearBulkImportTable;
window.executeBulkImport = executeBulkImport;
