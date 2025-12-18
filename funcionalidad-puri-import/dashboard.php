<?php
/**
 * Dashboard Principal del Admin
 * Arquitectura simple: PHP + AJAX básico
 */

require_once '../config/config.php';
require_once 'auth_middleware.php';

// Verificar autenticación
$admin_info = getAdminInfo();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Admin Puri</title>
    <link rel="stylesheet" href="assets/css/admin.css">
    <link href="https://fonts.googleapis.com/css2?family=GeistSans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Header -->
    <header class="admin-header">
        <div class="logo-section">
            <div class="logo">P</div>
            <div class="title">Puri: Gestión de centros deportivos</div>
        </div>
        <div class="actions">
            <a href="../informes.php" class="btn btn-secondary">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="16" height="16">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17h6l3 3v-3h2V9h-2M4 4h11v8H9l-3 3v-3H4V4z"/>
                </svg>
                Informes
            </a>
            <a href="search.php" class="btn btn-secondary">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="16" height="16">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                Buscar
            </a>
            <?php if (isSuperAdmin()) { ?>
            <button class="btn btn-secondary" onclick="showAdminsPanel()">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="16" height="16">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5V4H2v16h5m10 0V10m0 10H7m10-10H7"/>
                </svg>
                Administradores
            </button>
            <?php } ?>
            <button class="btn btn-primary" onclick="showAddOptionsModal()">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="16" height="16">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                </svg>
                Añadir
            </button>
            <div class="dropdown">
                <button class="btn btn-secondary" id="profile-dropdown-btn">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="16" height="16">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    <?php echo htmlspecialchars($admin_info['username']); ?>
                </button>
                <div class="dropdown-content" id="profile-dropdown">
                    <a href="account.php" class="dropdown-item">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="16" height="16">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756.426-1.756 2.924 0 3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        Mi Cuenta
                    </a>
                    <a href="logout.php" class="dropdown-item">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="16" height="16">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                        Cerrar Sesión
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="admin-content">
        <!-- Stats Grid -->
        <div class="stats-grid" id="stats-grid">
            <div class="loading-card">Cargando estadísticas...</div>
        </div>

        <!-- Panel de Administradores (solo visible cuando se activa) -->
        <div class="centers-panel" id="admins-panel" style="display:none;">
            <div class="centers-header">
                <h2 class="centers-title">Administradores</h2>
                <div class="centers-actions">
                    <input type="text" id="search-admins" class="search-input" placeholder="Buscar por usuario...">
                    <select id="sort-admins" class="sort-select">
                        <option value="created_at_desc">Más recientes</option>
                        <option value="created_at_asc">Más antiguos</option>
                        <option value="username_asc">Usuario A-Z</option>
                        <option value="username_desc">Usuario Z-A</option>
                    </select>
                    <button class="btn btn-primary" id="addAdminBtn" onclick="showCreateAdminModal()">
                        + Añadir Admin
                    </button>
                </div>
            </div>
            <div class="centers-content">
                <div id="admins-list" class="centers-list">
                    <!-- Los administradores se cargarán aquí dinámicamente -->
                </div>
            </div>
        </div>

        <!-- Panel de Centros -->
        <div class="centers-panel">
            <div class="centers-header">
                <h2 class="centers-title">Centros Deportivos</h2>
                <div class="centers-actions">
                    <input type="text" id="search-centers" class="search-input" placeholder="Buscar centros...">
                    <select id="sort-centers" class="sort-select">
                        <option value="nombre">Ordenar A-Z</option>
                        <option value="-nombre">Ordenar Z-A</option>
                    </select>
                    <button class="btn btn-primary" id="addButton" onclick="showCreateCenterModal()">
                        + Añadir Centro
                    </button>
                </div>
            </div>
            <div class="centers-content">
                <div id="centers-list" class="centers-list">
                    <!-- Los centros se cargarán aquí dinámicamente -->
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <h3>Acciones Rápidas</h3>
            <div class="actions-grid">
                <a href="#" class="action-btn" onclick="showCreateCenterModal()">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                    Crear nuevo centro
                </a>
                <a href="#" class="action-btn" onclick="showCreateInstallationModal()">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M10.5 3L12 2l1.5 1H21v4H3V3h7.5z"/>
                    </svg>
                    Crear nueva instalación
                </a>
                <a href="#" class="action-btn" onclick="showCreateActivityModal()">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Crear nueva actividad
                </a>
                <a href="#" class="action-btn" onclick="showAddParticipantModal()">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/>
                    </svg>
                    Añadir participantes
                </a>
            </div>
        </div>
    </main>

    <!-- Modal Container -->
    <div id="modal-container"></div>

    <!-- Modal Crear Centro -->
    <div class="modal-overlay" id="createCenterModal">
        <div class="modal">
            <div class="modal-header">
                <h2 class="modal-title">Crear Nuevo Centro</h2>
                <button class="modal-close" onclick="closeCreateCenterModal()">&times;</button>
            </div>
            <form id="createCenterForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label" for="centerName">Nombre del Centro</label>
                        <input type="text" id="centerName" name="nombre" class="form-input" placeholder="Ej: Centro Deportivo Municipal" required>
                        <div class="form-error" id="centerNameError"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="centerAddress">Dirección</label>
                        <input type="text" id="centerAddress" name="direccion" class="form-input" placeholder="Ej: Calle Principal 123">
                        <div class="form-error" id="centerAddressError"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="centerPassword">Contraseña del Centro *</label>
                        <input type="text" id="centerPassword" name="password" class="form-input" placeholder="Contraseña para acceso de trabajadores" required>
                        <small class="form-text">Esta contraseña la usarán los trabajadores para acceder al centro.</small>
                        <div class="form-error" id="centerPasswordError"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeCreateCenterModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="createCenterBtn">Crear Centro</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Editar Centro -->
    <div class="modal-overlay" id="editCenterModal">
        <div class="modal">
            <div class="modal-header">
                <h2 class="modal-title">Editar Centro</h2>
                <button class="modal-close" onclick="closeEditCenterModal()">&times;</button>
            </div>
            <form id="editCenterForm">
                <input type="hidden" id="editCenterId" name="id">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label" for="editCenterName">Nombre del Centro</label>
                        <input type="text" id="editCenterName" name="nombre" class="form-input" required>
                        <div class="form-error" id="editCenterName-error"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="editCenterAddress">Dirección</label>
                        <input type="text" id="editCenterAddress" name="direccion" class="form-input">
                        <div class="form-error" id="editCenterAddress-error"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeEditCenterModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="saveEditCenterBtn">
                        <span class="btn-text">Guardar cambios</span>
                        <span class="btn-loading">
                            <svg class="loading-spinner" width="16" height="16" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none" stroke-dasharray="60" stroke-dashoffset="60"/>
                            </svg>
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Opciones de Añadir -->
    <div class="modal-overlay" id="addOptionsModal">
        <div class="modal">
            <div class="modal-header">
                <h2 class="modal-title">¿Qué quieres crear?</h2>
                <button class="modal-close" onclick="closeAddOptionsModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="options-grid">
                    <button class="option-btn" onclick="selectCreateOption('centro')">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="24" height="24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                        <span class="option-title">Centro Deportivo</span>
                        <span class="option-desc">Crear un nuevo centro deportivo</span>
                    </button>
                    <button class="option-btn" onclick="selectCreateOption('instalacion')">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="24" height="24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M10.5 3L12 2l1.5 1H21v4H3V3h7.5z"/>
                        </svg>
                        <span class="option-title">Instalación</span>
                        <span class="option-desc">Añadir instalación a un centro</span>
                    </button>
                    <button class="option-btn" onclick="selectCreateOption('actividad')">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="24" height="24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="option-title">Actividad</span>
                        <span class="option-desc">Crear actividad en una instalación</span>
                    </button>
                    <button class="option-btn" onclick="selectCreateOption('participante')">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="24" height="24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/>
                        </svg>
                        <span class="option-title">Participante</span>
                        <span class="option-desc">Añadir participante a una actividad</span>
                    </button>
                    <button class="option-btn option-btn-highlight" onclick="selectCreateOption('bulk')">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="24" height="24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <span class="option-title">Subida en Lote</span>
                        <span class="option-desc">Importar desde Excel (instalaciones, actividades y participantes)</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Crear Instalación -->
    <div class="modal-overlay" id="createInstallationModal">
        <div class="modal">
            <div class="modal-header">
                <h2 class="modal-title">Crear Nueva Instalación</h2>
                <button class="modal-close" onclick="closeCreateInstallationModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="createInstallationForm">
                    <div class="form-group">
                        <label for="installationCenter">Centro Deportivo *</label>
                        <div class="custom-select-wrapper">
                            <input type="text" id="installationCenterSearch" class="custom-select-input" 
                                   placeholder="Buscar centro..." autocomplete="off">
                            <input type="hidden" id="installationCenter" name="centro_id" required>
                            <div class="custom-select-dropdown" id="installationCenterDropdown">
                                <div class="custom-select-loading">Cargando centros...</div>
                            </div>
                            <svg class="custom-select-arrow" width="12" height="12" viewBox="0 0 12 12">
                                <path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="1.5" fill="none"/>
                            </svg>
                        </div>
                        <span class="field-error" id="installationCenter-error"></span>
                    </div>
                    
                    <div class="form-group">
                        <label for="installationName">Nombre de la Instalación *</label>
                        <input type="text" id="installationName" name="nombre" required 
                               placeholder="Ej: Piscina Olímpica, Cancha de Tenis 1">
                        <span class="field-error" id="installationName-error"></span>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeCreateInstallationModal()">
                    Cancelar
                </button>
                <button type="submit" form="createInstallationForm" class="btn btn-primary" id="createInstallationBtn">
                    <span class="btn-text">Crear Instalación</span>
                    <span class="btn-loading">
                        <svg class="loading-spinner" width="16" height="16" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none" stroke-dasharray="60" stroke-dashoffset="60"/>
                        </svg>
                    </span>
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Crear Actividad -->
    <div class="modal-overlay" id="createActivityModal">
        <div class="modal modal-large">
            <div class="modal-header">
                <h2 class="modal-title">Crear Nueva Actividad</h2>
                <button class="modal-close" onclick="closeCreateActivityModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="createActivityForm">
                    <!-- Selectores cascada: Centro → Instalación -->
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="activityCenter">Centro Deportivo *</label>
                            <div class="custom-select-wrapper">
                                <input type="text" id="activityCenterSearch" class="custom-select-input" 
                                       placeholder="Buscar centro..." autocomplete="off">
                                <input type="hidden" id="activityCenter" name="centro_id" required>
                                <div class="custom-select-dropdown" id="activityCenterDropdown">
                                    <div class="custom-select-loading">Cargando centros...</div>
                                </div>
                                <svg class="custom-select-arrow" width="12" height="12" viewBox="0 0 12 12">
                                    <path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                </svg>
                            </div>
                            <span class="field-error" id="activityCenter-error"></span>
                        </div>
                        
                        <div class="form-group col-md-6">
                            <label for="activityInstallation">Instalación *</label>
                            <div class="custom-select-wrapper">
                                <input type="text" id="activityInstallationSearch" class="custom-select-input" 
                                       placeholder="Primero selecciona un centro" autocomplete="off" disabled>
                                <input type="hidden" id="activityInstallation" name="instalacion_id" required>
                                <div class="custom-select-dropdown" id="activityInstallationDropdown">
                                    <div class="custom-select-loading">Selecciona un centro primero</div>
                                </div>
                                <svg class="custom-select-arrow" width="12" height="12" viewBox="0 0 12 12">
                                    <path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                </svg>
                            </div>
                            <span class="field-error" id="activityInstallation-error"></span>
                        </div>
                    </div>
                    
                    <!-- Nombre de la actividad -->
                    <div class="form-group">
                        <label for="activityName">Nombre de la Actividad *</label>
                        <input type="text" id="activityName" name="nombre" required 
                               placeholder="Ejemplo: Taller de Pintura, Natación Avanzada">
                        <span class="field-error" id="activityName-error"></span>
                    </div>

                    <!-- Días de la semana -->
                    <div class="form-group">
                        <label>Días de la semana *</label>
                        <div class="checkbox-group">
                            <label class="checkbox-inline">
                                <input type="checkbox" name="dias_semana[]" value="Lunes"> Lunes
                            </label>
                            <label class="checkbox-inline">
                                <input type="checkbox" name="dias_semana[]" value="Martes"> Martes
                            </label>
                            <label class="checkbox-inline">
                                <input type="checkbox" name="dias_semana[]" value="Miércoles"> Miércoles
                            </label>
                            <label class="checkbox-inline">
                                <input type="checkbox" name="dias_semana[]" value="Jueves"> Jueves
                            </label>
                            <label class="checkbox-inline">
                                <input type="checkbox" name="dias_semana[]" value="Viernes"> Viernes
                            </label>
                            <label class="checkbox-inline">
                                <input type="checkbox" name="dias_semana[]" value="Sábado"> Sábado
                            </label>
                            <label class="checkbox-inline">
                                <input type="checkbox" name="dias_semana[]" value="Domingo"> Domingo
                            </label>
                        </div>
                        <span class="field-error" id="dias_semana-error"></span>
                    </div>

                    <!-- Horarios -->
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="activityStartTime">Hora de inicio *</label>
                            <input type="time" id="activityStartTime" name="hora_inicio" required>
                            <span class="field-error" id="activityStartTime-error"></span>
                        </div>
                        
                        <div class="form-group col-md-6">
                            <label for="activityEndTime">Hora de finalización *</label>
                            <input type="time" id="activityEndTime" name="hora_fin" required>
                            <span class="field-error" id="activityEndTime-error"></span>
                        </div>
                    </div>

                    <!-- Fechas -->
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="activityStartDate">Fecha de inicio *</label>
                            <input type="date" id="activityStartDate" name="fecha_inicio" required>
                            <span class="field-error" id="activityStartDate-error"></span>
                        </div>
                        
                        <div class="form-group col-md-6">
                            <label for="activityEndDate">Fecha de finalización</label>
                            <input type="date" id="activityEndDate" name="fecha_fin">
                            <small class="form-text">Opcional. Dejar en blanco si no tiene fecha de finalización definida.</small>
                            <span class="field-error" id="activityEndDate-error"></span>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeCreateActivityModal()">
                    Cancelar
                </button>
                <button type="submit" form="createActivityForm" class="btn btn-primary" id="createActivityBtn">
                    <span class="btn-text">Crear Actividad</span>
                    <span class="btn-loading">
                        <svg class="loading-spinner" width="16" height="16" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none" stroke-dasharray="60" stroke-dashoffset="60"/>
                        </svg>
                    </span>
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Crear Participante -->
    <div class="modal-overlay" id="createParticipantModal">
        <div class="modal modal-large">
            <div class="modal-header">
                <h2 class="modal-title">Añadir Participantes</h2>
                <button class="modal-close" onclick="closeCreateParticipantModal()">&times;</button>
            </div>
            <div class="modal-body">
                <!-- Pestañas -->
                <div class="tab-navigation">
                    <button class="tab-btn active" onclick="switchParticipantTab('manual')">
                        <i class="fas fa-user-plus"></i> Añadir Manual
                    </button>
                    <button class="tab-btn" onclick="switchParticipantTab('csv')">
                        <i class="fas fa-upload"></i> Subir CSV
                    </button>
                </div>

                <!-- Pestaña Manual -->
                <div class="tab-content active" id="manualTab">
                    <form id="createParticipantForm">
                        <!-- Selectores cascada: Centro → Instalación → Actividad -->
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label for="participantCenter">Centro Deportivo *</label>
                                <div class="custom-select-wrapper">
                                    <input type="text" id="participantCenterSearch" class="custom-select-input" 
                                           placeholder="Buscar centro..." autocomplete="off">
                                    <input type="hidden" id="participantCenter" name="centro_id" required>
                                    <div class="custom-select-dropdown" id="participantCenterDropdown">
                                        <div class="custom-select-loading">Cargando centros...</div>
                                    </div>
                                    <svg class="custom-select-arrow" width="12" height="12" viewBox="0 0 12 12">
                                        <path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                    </svg>
                                </div>
                                <span class="field-error" id="participantCenter-error"></span>
                            </div>
                            
                            <div class="form-group col-md-4">
                                <label for="participantInstallation">Instalación *</label>
                                <div class="custom-select-wrapper">
                                    <input type="text" id="participantInstallationSearch" class="custom-select-input" 
                                           placeholder="Selecciona un centro" autocomplete="off" disabled>
                                    <input type="hidden" id="participantInstallation" name="instalacion_id" required>
                                    <div class="custom-select-dropdown" id="participantInstallationDropdown">
                                        <div class="custom-select-loading">Selecciona un centro primero</div>
                                    </div>
                                    <svg class="custom-select-arrow" width="12" height="12" viewBox="0 0 12 12">
                                        <path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                    </svg>
                                </div>
                                <span class="field-error" id="participantInstallation-error"></span>
                            </div>

                            <div class="form-group col-md-4">
                                <label for="participantActivity">Actividad *</label>
                                <div class="custom-select-wrapper">
                                    <input type="text" id="participantActivitySearch" class="custom-select-input" 
                                           placeholder="Selecciona una instalación" autocomplete="off" disabled>
                                    <input type="hidden" id="participantActivity" name="actividad_id" required>
                                    <div class="custom-select-dropdown" id="participantActivityDropdown">
                                        <div class="custom-select-loading">Selecciona una instalación primero</div>
                                    </div>
                                    <svg class="custom-select-arrow" width="12" height="12" viewBox="0 0 12 12">
                                        <path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                    </svg>
                                </div>
                                <span class="field-error" id="participantActivity-error"></span>
                            </div>
                        </div>
                        
                        <!-- Entrada rápida en tabla editable -->
                        <div class="form-group">
                            <label>Entrada rápida</label>
                            <div class="table-wrapper">
                                <table class="editable-table" id="dashQuickEntryTable">
                                    <thead>
                                        <tr>
                                            <th style="width:48%">Nombre</th>
                                            <th style="width:48%">Apellidos</th>
                                            <th style="width:4%"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="dashQuickEntryBody">
                                        <!-- filas dinámicas -->
                                    </tbody>
                                </table>
                            </div>
                            <div style="margin-top:8px">
                                <button type="button" class="btn btn-outline" onclick="addDashQuickEntryRow()">+ Añadir fila</button>
                            </div>
                            <div style="margin-top:6px; font-size:12px; color:#6b7280;">
                                Consejo: puedes pegar directamente desde Excel (Nombre y Apellidos en columnas)
                            </div>
                            <div class="form-error" id="dashQuickEntryError"></div>
                        </div>
                    </form>
                </div>

                <!-- Pestaña CSV -->
                <div class="tab-content" id="csvTab">
                    <form id="uploadParticipantCsvForm">
                        <!-- Selectores cascada para CSV -->
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label for="csvParticipantCenter">Centro Deportivo *</label>
                                <div class="custom-select-wrapper">
                                    <input type="text" id="csvParticipantCenterSearch" class="custom-select-input" 
                                           placeholder="Buscar centro..." autocomplete="off">
                                    <input type="hidden" id="csvParticipantCenter" name="centro_id" required>
                                    <div class="custom-select-dropdown" id="csvParticipantCenterDropdown">
                                        <div class="custom-select-loading">Cargando centros...</div>
                                    </div>
                                    <svg class="custom-select-arrow" width="12" height="12" viewBox="0 0 12 12">
                                        <path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                    </svg>
                                </div>
                                <span class="field-error" id="csvParticipantCenter-error"></span>
                            </div>
                            
                            <div class="form-group col-md-4">
                                <label for="csvParticipantInstallation">Instalación *</label>
                                <div class="custom-select-wrapper">
                                    <input type="text" id="csvParticipantInstallationSearch" class="custom-select-input" 
                                           placeholder="Selecciona un centro" autocomplete="off" disabled>
                                    <input type="hidden" id="csvParticipantInstallation" name="instalacion_id" required>
                                    <div class="custom-select-dropdown" id="csvParticipantInstallationDropdown">
                                        <div class="custom-select-loading">Selecciona un centro primero</div>
                                    </div>
                                    <svg class="custom-select-arrow" width="12" height="12" viewBox="0 0 12 12">
                                        <path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                    </svg>
                                </div>
                                <span class="field-error" id="csvParticipantInstallation-error"></span>
                            </div>

                            <div class="form-group col-md-4">
                                <label for="csvParticipantActivity">Actividad *</label>
                                <div class="custom-select-wrapper">
                                    <input type="text" id="csvParticipantActivitySearch" class="custom-select-input" 
                                           placeholder="Selecciona una instalación" autocomplete="off" disabled>
                                    <input type="hidden" id="csvParticipantActivity" name="actividad_id" required>
                                    <div class="custom-select-dropdown" id="csvParticipantActivityDropdown">
                                        <div class="custom-select-loading">Selecciona una instalación primero</div>
                                    </div>
                                    <svg class="custom-select-arrow" width="12" height="12" viewBox="0 0 12 12">
                                        <path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                    </svg>
                                </div>
                                <span class="field-error" id="csvParticipantActivity-error"></span>
                            </div>
                        </div>

                        <!-- Modo de importación -->
                        <div class="form-group" style="margin-top:12px">
                            <label for="dashCsvImportMode">Modo de importación</label>
                            <select id="dashCsvImportMode" name="mode" class="form-control">
                                <option value="append">Añadir al listado actual</option>
                                <option value="replace">Reemplazar listado (borrar todos los participantes actuales)</option>
                            </select>
                        </div>

                        <!-- Descarga de plantilla y subida de archivo -->
                        <div class="csv-section">
                            <div class="csv-info">
                                <h4><i class="fas fa-info-circle"></i> Instrucciones</h4>
                                <p>1. Descarga la plantilla CSV</p>
                                <p>2. Completa con los datos de los participantes (Nombre, Apellidos)</p>
                                <p>3. En Excel (Mac): usa “Guardar como…” → “CSV UTF-8 (delimitado por comas)”</p>
                                <p>4. Sube el archivo completado</p>
                            </div>
                            
                            <div class="csv-actions">
                                <a href="../public/assets/plantilla-asistentes.csv" class="btn btn-outline" download>
                                    <i class="fas fa-download"></i> Descargar Plantilla CSV
                                </a>
                                
                                <label class="btn btn-primary file-upload-btn">
                                    <i class="fas fa-upload"></i> Seleccionar Archivo CSV
                                    <input type="file" id="participantCsvFile" accept=".csv" style="display: none;">
                                </label>
                            </div>
                            
                            <div class="file-info" id="csvFileInfo" style="display: none;">
                                <i class="fas fa-file-csv"></i>
                                <span id="csvFileName"></span>
                                <button type="button" class="btn-remove" onclick="removeCsvFile()">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeCreateParticipantModal()">
                    Cancelar
                </button>
                <button type="submit" form="createParticipantForm" class="btn btn-primary" id="createParticipantBtn">
                    <span class="btn-text">Inscribir Participante</span>
                    <span class="btn-loading">
                        <svg class="loading-spinner" width="16" height="16" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none" stroke-dasharray="60" stroke-dashoffset="60"/>
                        </svg>
                    </span>
                </button>
                <button type="submit" form="uploadParticipantCsvForm" class="btn btn-primary" id="uploadCsvBtn" style="display: none;">
                    <span class="btn-text">Subir CSV</span>
                    <span class="btn-loading">
                        <svg class="loading-spinner" width="16" height="16" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none" stroke-dasharray="60" stroke-dashoffset="60"/>
                        </svg>
                    </span>
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Crear Admin -->
    <div class="modal-overlay" id="createAdminModal">
        <div class="modal">
            <div class="modal-header">
                <h2 class="modal-title">Crear Administrador</h2>
                <button class="modal-close" onclick="closeCreateAdminModal()">&times;</button>
            </div>
            <form id="createAdminForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label" for="adminUsername">Usuario</label>
                        <input type="text" id="adminUsername" name="username" class="form-input" placeholder="usuario" required>
                        <div class="form-error" id="adminUsername-error"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="adminPassword">Contraseña</label>
                        <input type="password" id="adminPassword" name="password" class="form-input" placeholder="mín. 8 caracteres" required>
                        <div class="form-error" id="adminPassword-error"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="adminRole">Rol</label>
                        <select id="adminRole" name="role" class="form-input" required>
                            <option value="admin">Admin</option>
                            <option value="superadmin">Superadmin</option>
                        </select>
                        <div class="form-error" id="adminRole-error"></div>
                    </div>
                    <?php if (isSuperAdmin()) { ?>
                    <div class="form-group">
                        <label class="form-label" for="createAdminCentersSearch">Centros asignados</label>
                        <div id="createAdminCentersWrapper">
                            <input type="text" id="createAdminCentersSearch" class="form-input" placeholder="Buscar centros..." oninput="filterCentersList('createAdminCentersList', 'createAdminCentersNoResults', this.value)">
                            <div id="createAdminCentersList" class="checkbox-group" style="max-height: 240px; overflow: auto; padding-right: 4px;">
                                <!-- Centros para asignar (solo superadmin) -->
                            </div>
                            <div id="createAdminCentersNoResults" class="empty-state" style="display:none;">No hay centros que coincidan</div>
                        </div>
                        <div id="createAdminCentersInfo" class="form-hint" hidden>Los usuarios con permisos de Superadmin tienen acceso a todos los centros, instalaciones y actividades.</div>
                    </div>
                    <?php } ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeCreateAdminModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="createAdminBtn">
                        <span class="btn-text">Crear</span>
                        <span class="btn-loading">
                            <svg class="loading-spinner" width="16" height="16" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none" stroke-dasharray="60" stroke-dashoffset="60"/>
                            </svg>
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Editar Admin -->
    <div class="modal-overlay" id="editAdminModal">
        <div class="modal">
            <div class="modal-header">
                <h2 class="modal-title">Editar Administrador</h2>
                <button class="modal-close" onclick="closeEditAdminModal()">&times;</button>
            </div>
            <form id="editAdminForm">
                <input type="hidden" id="editAdminId" name="id">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Usuario</label>
                        <input type="text" id="editAdminUsername" class="form-input" disabled>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="editAdminRole">Rol</label>
                        <select id="editAdminRole" name="role" class="form-input">
                            <option value="admin">Admin</option>
                            <option value="superadmin">Superadmin</option>
                        </select>
                        <div class="form-error" id="editAdminRole-error"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="editAdminNewPassword">Nueva contraseña (opcional)</label>
                        <input type="password" id="editAdminNewPassword" name="new_password" class="form-input" placeholder="dejar vacío si no cambia">
                        <div class="form-error" id="editAdminNewPassword-error"></div>
                    </div>
                    <?php if (isSuperAdmin()) { ?>
                    <div class="form-group">
                        <label class="form-label" for="editAdminCentersSearch">Centros asignados</label>
                        <div id="editAdminCentersWrapper">
                            <input type="text" id="editAdminCentersSearch" class="form-input" placeholder="Buscar centros..." oninput="filterCentersList('editAdminCentersList', 'editAdminCentersNoResults', this.value)">
                            <div id="editAdminCentersList" class="checkbox-group" style="max-height: 240px; overflow: auto; padding-right: 4px;">
                                <!-- Centros asignados se cargarán aquí -->
                            </div>
                            <div id="editAdminCentersNoResults" class="empty-state" style="display:none;">No hay centros que coincidan</div>
                        </div>
                        <div id="editAdminCentersInfo" class="form-hint" hidden>Los usuarios con permisos de Superadmin tienen acceso a todos los centros, instalaciones y actividades.</div>
                    </div>
                    <?php } ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeEditAdminModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="saveEditAdminBtn">
                        <span class="btn-text">Guardar cambios</span>
                        <span class="btn-loading">
                            <svg class="loading-spinner" width="16" height="16" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none" stroke-dasharray="60" stroke-dashoffset="60"/>
                            </svg>
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal Gestionar Centros por Admin (Superadmin) -->
    <div class="modal-overlay" id="manageAdminCentersModal">
        <div class="modal">
            <div class="modal-header">
                <h2 class="modal-title" id="manageCentersTitle">Centros del administrador</h2>
                <button class="modal-close" onclick="closeManageCentersModal()">&times;</button>
            </div>
            <form id="manageCentersForm" onsubmit="saveManageCenters(event)">
                <input type="hidden" id="manageCentersAdminId" name="admin_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label" for="manageCentersSearch">Buscar centros</label>
                        <input type="text" id="manageCentersSearch" class="form-input" placeholder="Escribe para filtrar..." oninput="filterManageCenters(this.value)">
                    </div>
                    <div id="manageCentersList" class="checkbox-group" style="max-height: 320px; overflow: auto; padding-right: 4px;">
                        <!-- Lista de centros con checkboxes -->
                    </div>
                    <div id="manageCentersNoResults" class="empty-state" style="display: none;">No hay centros que coincidan</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeManageCentersModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="saveManageCentersBtn">
                        <span class="btn-text">Guardar</span>
                        <span class="btn-loading">
                            <svg class="loading-spinner" width="16" height="16" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none" stroke-dasharray="60" stroke-dashoffset="60"/>
                            </svg>
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Subida en Lote -->
    <div class="modal-overlay" id="bulkImportModal">
        <div class="modal modal-large">
            <div class="modal-header">
                <h2 class="modal-title">Subida en Lote</h2>
                <button class="modal-close" onclick="closeBulkImportModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="bulk-import-info">
                    <div class="info-box info-box-primary">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div>
                            <strong>Importa desde Excel</strong>
                            <p>Copia las columnas desde tu hoja de cálculo y pégalas aquí. El sistema creará automáticamente las instalaciones, actividades y participantes.</p>
                        </div>
                    </div>
                </div>

                <!-- Selector de Centro -->
                <div class="form-group">
                    <label for="bulkImportCenter">Centro Deportivo *</label>
                    <div class="custom-select-wrapper">
                        <input type="text" id="bulkImportCenterSearch" class="custom-select-input" 
                               placeholder="Buscar centro..." autocomplete="off">
                        <input type="hidden" id="bulkImportCenter" name="centro_id" required>
                        <div class="custom-select-dropdown" id="bulkImportCenterDropdown">
                            <div class="custom-select-loading">Cargando centros...</div>
                        </div>
                        <svg class="custom-select-arrow" width="12" height="12" viewBox="0 0 12 12">
                            <path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="1.5" fill="none"/>
                        </svg>
                    </div>
                    <span class="field-error" id="bulkImportCenter-error"></span>
                </div>

                <!-- Área de pegado -->
                <div class="form-group">
                    <label>Pegar datos desde Excel</label>
                    <div class="bulk-paste-instructions">
                        <strong>Columnas esperadas (en este orden):</strong>
                        <ol>
                            <li><strong>Nombre</strong> - Nombre del participante</li>
                            <li><strong>Apellidos</strong> - Apellidos del participante</li>
                            <li><strong>Instalación</strong> - Nombre de la instalación</li>
                            <li><strong>Actividad</strong> - Nombre de la actividad</li>
                            <li><strong>Fecha inicio</strong> - Fecha de inicio (d/m/aa o dd/mm/aaaa)</li>
                            <li><strong>Días</strong> - Días de la semana (ej: "Lunes, Miércoles" o en columnas separadas)</li>
                        </ol>
                    </div>
                    <div class="table-wrapper bulk-table-wrapper">
                        <table class="editable-table" id="bulkImportTable">
                            <thead>
                                <tr>
                                    <th style="width:14%">Nombre</th>
                                    <th style="width:16%">Apellidos</th>
                                    <th style="width:16%">Instalación</th>
                                    <th style="width:16%">Actividad</th>
                                    <th style="width:12%">Fecha inicio</th>
                                    <th style="width:22%">Días semana</th>
                                    <th style="width:4%"></th>
                                </tr>
                            </thead>
                            <tbody id="bulkImportBody">
                                <!-- filas dinámicas -->
                            </tbody>
                        </table>
                    </div>
                    <div style="margin-top:8px; display: flex; gap: 8px; align-items: center;">
                        <button type="button" class="btn btn-outline btn-sm" onclick="addBulkImportRow()">+ Añadir fila</button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="clearBulkImportTable()">Limpiar tabla</button>
                        <span id="bulkImportRowCount" style="margin-left: auto; font-size: 12px; color: #6b7280;">0 filas</span>
                    </div>
                    <div class="form-error" id="bulkImportError"></div>
                </div>

                <!-- Preview de resultados -->
                <div id="bulkImportPreview" class="bulk-import-preview" style="display: none;">
                    <h4>Vista previa</h4>
                    <div id="bulkImportPreviewContent"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeBulkImportModal()">
                    Cancelar
                </button>
                <button type="button" class="btn btn-primary" id="bulkImportBtn" onclick="executeBulkImport()">
                    <span class="btn-text">Importar Datos</span>
                    <span class="btn-loading">
                        <svg class="loading-spinner" width="16" height="16" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none" stroke-dasharray="60" stroke-dashoffset="60"/>
                        </svg>
                    </span>
                </button>
            </div>
        </div>
    </div>

    <script>
        window.isSuperAdmin = <?= isSuperAdmin() ? 'true' : 'false' ?>;
    </script>
    <script src="assets/js/dashboard.js"></script>
</body>
</html>
