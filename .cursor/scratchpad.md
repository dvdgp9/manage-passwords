# Background and Motivation
Este proyecto es un gestor de contraseñas en PHP que guarda credenciales en MySQL y las muestra vía web. No hay `.env`; la conexión y claves están en `config.php`. Se solicita auditoría de seguridad y plan de mejoras.
Confirmado: el sitio se sirve bajo HTTPS. Nueva dirección: evolucionar a multiusuario (varios usuarios con control de permisos).

# Key Challenges and Analysis
Principales hallazgos (con referencias):

1) Secretos hardcodeados y gestión de claves
- `config.php`: credenciales DB en texto plano (`$user`, `$pass`) y clave simétrica `ENCRYPTION_KEY` embebida (l.5-13).
- `ver-passwords.php`: contraseña maestra hardcodeada `contraEBO` (l.25).
Riesgo: exposición por fuga de código/backup, ausencia de rotación y trazabilidad.

2) Cifrado inapropiado/no autenticado
- `encrypt()/decrypt()` usan AES-256-CBC sin autenticación, concatenando `ciphertext::iv` (l.35-46 `config.php`).
- No hay HMAC/AEAD → susceptible a padding-oracle/modificación silenciosa.
- Longitud de `ENCRYPTION_KEY` no verificada (posible derivación implícita por OpenSSL).

3) Falta de autenticación/autorización en endpoints críticos
- `guardar.php`, `edit-password.php`, `delete-password.php`, `import-csv.php` no verifican sesión/autenticación.
Riesgo: usuarios no autenticados pueden crear/editar/borrar/importar registros.

4) Ausencia de protección CSRF
- Formularios y `fetch()` no incluyen token CSRF (`guardar.php`, `edit-password.php`, `delete-password.php`, `logout.php`).
Riesgo: acciones no deseadas desde sitios de terceros.

5) Gestión de sesión débil
- `ver-passwords.php` inicia sesión (l.4) y controla inactividad, pero no hace `session_regenerate_id(true)` tras login, ni fija flags `httponly`, `secure`, `samesite`, ni `use_strict_mode`.
Riesgo: fijación de sesión, robo de cookie, CSRF.

6) Errores visibles en producción
- `ver-passwords.php` y `edit-password.php` activan `display_errors` (l.2-3). Mensajes de error detallados en `config.php` (l.31) y otros con `die()`.
Riesgo: fuga de información sensible.

7) Importación CSV sin cifrado/validación
- `import-csv.php` inserta `password` tal cual (l.28); inconsistente con resto del flujo que cifra.
- Endpoint accesible sin control de acceso.
Riesgo: contraseñas en texto claro en BDD, manipulación masiva.

8) Exposición de contraseñas en claro en UI
- `ver-passwords.php` descifra y muestra contraseñas en una tabla (l.151-159).
Riesgo: shoulder-surfing, exfiltración vía XSS si hubiese una vulnerabilidad.

9) Cabeceras de seguridad ausentes
- No se envían `Content-Security-Policy`, `X-Frame-Options/Frame-Ancestors`, `Referrer-Policy`, `X-Content-Type-Options`, `Strict-Transport-Security`.
Riesgo: XSS, clickjacking, filtrado de tipos, transporte inseguro.

10) Validación/normalización de entrada limitada
- URL sólo se prefiere a https, sin validación estricta (`filter_var`), sin límites de longitud. Campos libres sin límites.

11) Rate limiting/lockout inexistente
- `ver-passwords.php` no limita intentos de contraseña maestra.

12) Logging y auditoría
- No hay registros de acceso/cambios.

13) Tabla con `-` en nombre
- Uso de ``passwords-manager`` obliga a escapado; ya se usa con backticks, pero es frágil para mantenibilidad.

# High-level Task Breakdown
P0 – Críticos (bloqueantes)
1. Añadir gate de autenticación a todos los endpoints de mutación y lectura
   - Éxito: `guardar.php`, `edit-password.php`, `delete-password.php`, `import-csv.php` requieren sesión válida; redirigen si no.
2. Fortalecer sesión
   - `session_set_cookie_params` (Secure, HttpOnly, SameSite=Lax/Strict), `session_regenerate_id(true)` post-login, `use_strict_mode=1`.
   - Éxito: cookies con flags en respuesta y regeneración tras login.
3. Activar protección CSRF en formularios y AJAX
   - Token en sesión, campo hidden en forms y header en `fetch`, verificación server-side.
   - Éxito: peticiones sin token fallan.
4. Eliminar secretos hardcodeados
   - Introducir `.env` (DB creds, MASTER_PASS_HASH, ENC_SALT/PEPPER), usar `vlucas/phpdotenv`.
   - Éxito: repo sin secretos, `.env.example` presente.

P1 – Altos
5. Replantear autenticación maestra
   - Guardar sólo hash Argon2id de la master; derivar clave de cifrado vía Argon2id+HKDF.
   - Éxito: login compara hash; clave de cifrado no está en código.
6. Migrar cifrado a AEAD
   - Usar `sodium_crypto_secretbox` o AES-256-GCM con nonce aleatorio per-registro; almacenar `{v, nonce, ct}`; añadir versión para migraciones.
   - Éxito: integridad autenticada; función de migración para registros antiguos.
7. Arreglar `import-csv.php`
   - Requiere auth, subir archivo, validar, cifrar contraseñas antes de insertar, permitir separador configurable.
   - Éxito: importaciones cifradas y auditables.
8. Desactivar errores en producción y centralizar logging
   - `display_errors=0`, logging a archivo/STDERR con niveles.
   - Éxito: no se exponen trazas al usuario.
9. Cabeceras de seguridad
   - Añadir CSP mínima (default-src 'self'), frame-ancestors 'none', HSTS (en HTTPS), Referrer-Policy, X-CTO.
   - Éxito: cabeceras visibles en respuesta.

P1 – Multiusuario (nuevo alcance)
15. Modelo de usuarios y autenticación
   - Tabla `users` (id, email único, password_hash Argon2id, role, created_at, last_login, 2FA opcional).
   - Registro (opcional), alta por admin y login con sesiones endurecidas.
16. Propiedad y permisos sobre contraseñas
   - Añadir `owner_user_id` (y opcional `org_id`) a la tabla de contraseñas; scope por usuario/organización.
   - Roles: admin/editor/lector para controlar CRUD y visualización.
17. Migración de datos existentes
   - Script para asignar propietario (p. ej., admin) a todos los registros actuales y validar cifrado.
18. UX multiusuario
   - Vistas: login/logout, filtros por propietario/organización, acciones condicionadas por rol.
19. Auditoría por usuario
   - Log de acciones (quién hizo qué y cuándo) sin exponer secretos.

20. Sesión persistente ("mantener la sesión iniciada")
   - Checkbox "Recuérdame" en login. Cookie adicional `remember_token` (Secure, HttpOnly, SameSite=Lax) con expiración p. ej. 30 días.
   - Backend: tabla `user_sessions` (id, user_id, hashed_token, created_at, last_used_at, expires_at, user_agent, ip_hash_opcional, revoked).
   - Flujo: si no hay sesión y existe `remember_token` válido → crear sesión y ROTAR token (emitir nuevo, invalidar antiguo).
   - Seguridad: hash del token (p. ej. SHA-256) en BDD, longitud >= 256 bits, rotación por uso, revocación en logout y "cerrar sesión en todos los dispositivos".
   - Sesión corta + sliding (inactividad 15 min) y remember para sesiones largas.
   - Decisiones confirmadas: duración 60 días; "Recordarme" marcado por defecto; máximo 5 tokens por usuario; no requerir password adicional para restaurar sesión.

P2 – Medios/Mejoras
10. Validaciones y límites
   - `filter_var($enlace, FILTER_VALIDATE_URL)`, límites de longitud por campo, whitelist de esquemas.
11. Rate limiting y lockout
   - Contador en sesión+IP con backoff; CAPTCHA opcional.
12. UX segura para contraseñas
   - Mostrar contraseña sólo bajo acción explícita (revelar/copiar individual), auto-ocultar, no listar todas en claro.
13. Auditoría
   - Registrar altas/bajas/cambios con timestamp/IP/usuario.
14. Renombrar tabla para evitar `-`.

# Project Status Board
- [ ] P0: Gate de autenticación en todos los endpoints (guardar, editar, borrar, importar)
- [ ] P0: Sesiones endurecidas (flags cookie, regenerate, strict mode)
- [ ] P0: CSRF tokens en formularios y AJAX
- [ ] P0: Externalizar secretos con .env y dotenv
- [ ] P1: Autenticación maestra (Argon2id) y derivación de clave
- [ ] P1: Migración a cifrado AEAD con versión de esquema
- [ ] P1: Arreglar importación CSV (auth, validación, cifrado)
- [ ] P1: Desactivar errores en prod y logging centralizado
- [ ] P1: Cabeceras de seguridad (CSP, HSTS, etc.)
- [ ] P1: Multiusuario – usuarios y login (tabla `users`, Argon2id, roles)
- [ ] P1: Multiusuario – propiedad y permisos (owner_user_id/org, scoping)
- [ ] P1: Multiusuario – migración de datos existentes al nuevo esquema
- [ ] P1: Multiusuario – UX (pantallas y controles por rol)
- [ ] P1: Multiusuario – auditoría de acciones por usuario
- [ ] P1: Multiusuario – sesión persistente (remember me con tokens rotativos y revocación)
  - Parámetros: 60 días, default checked, 5 tokens/usuario, sin revalidación de password.
- [ ] P2: Validación de entradas y límites
- [ ] P2: Rate limiting de login
- [ ] P2: UX segura (revelar/copiar bajo demanda)
- [ ] P2: Auditoría de cambios
- [ ] P2: Renombrar tabla a `passwords_manager`

# Current Status / Progress Tracking
- Planner: análisis inicial completado y plan redactado.
- Executor (P0):
  - Implementado archivo `security.php` con cabeceras, sesión endurecida, timeout deslizante, helpers CSRF y logout seguro.
  - Integrado en `ver-passwords.php`, `introducir.php`, `guardar.php`, `edit-password.php`, `delete-password.php`, `logout.php`.
  - Añadido token CSRF en formularios y meta `<meta name="csrf-token">` + `scripts.js` envía CSRF en borrado AJAX.
  - Pendiente P0: externalizar secretos a `.env` (Composer/phpdotenv) [bloqueado por Composer no instalado].
  - Añadido `index.php` como gateway de autenticación: si hay sesión → redirige a `ver-passwords.php`, si no → `login.php`.
  - Actualizados enlaces de navegación de `index.html` → `index.php` en `ver-passwords.php`, `introducir.php` y `edit-password.php`.
  - [Nuevo][UX] Añadido botón de copiar en la columna de contraseña en `ver-passwords.php` (icono SVG inline estilo Untitled UI) con manejador en `scripts.js` usando `navigator.clipboard.writeText` y fallback `execCommand('copy')`. Estilos en `style.css` (`.copy-btn`, feedback `.copied`/`.copy-error`).

- Executor (Bulk Import Feature - 18/12/2024):
  - **Implementado** `bulk-import.php`: API endpoint que recibe filas JSON, valida campos obligatorios, cifra contraseñas, inserta en `passwords_manager` y crea accesos en `passwords_access` y `password_department_access`.
  - **Modificado** `introducir.php`: Añadido botón "Importación en lote" y modal completo con:
    - Tabla editable para pegar datos desde Excel (7 columnas: Línea Negocio, Nombre, Usuario, Contraseña, Enlace, Descripción, Info Adicional)
    - Sección de acceso compartido (usuarios y departamentos) idéntica al formulario normal
    - Instrucciones de uso y orden de columnas
  - **Añadido** en `scripts.js`: Funcionalidad completa de bulk import:
    - `handleBulkImportPaste()`: detecta pegado tabular, parsea columnas separadas por tabs, auto-detecta y omite fila de cabeceras
    - Gestión de filas (añadir, eliminar, limpiar tabla)
    - `executeBulkImport()`: envía JSON al backend con CSRF, muestra resultados y errores
    - Filtros de búsqueda en listas de usuarios/departamentos
    - Notificaciones toast de éxito/error
  - **Añadido** en `style.css`: Estilos completos para modal grande, tabla editable, botones, notificaciones, responsive

# Executor's Feedback or Assistance Requests

- Validación manual solicitada (entorno):
  1) Abrir `index.php` sin sesión → debe redirigir a `login.php`.
  2) Iniciar sesión → debe redirigir automáticamente a `ver-passwords.php`.
  3) Navegar con el botón Inicio → debe ir a `index.php` y respetar la lógica anterior.
  4) Crear, editar y borrar registros según permisos (propietario vs admin).
  5) Cerrar sesión (POST + CSRF) → volver a `login.php` desde `index.php`.
  6) Copiar contraseña: en `ver-passwords.php`, pulsa el botón con icono de copiar en cualquier fila → debe copiar la contraseña en texto plano al portapapeles y mostrar feedback visual verde (título "Copiado"). En caso de error, alertará.
- Nota: si hay errores de CSP con fuentes, avisar para ajustar la política.
- Confirmar versión de PHP y si se permite Composer (para `vlucas/phpdotenv` y `paragonie/sodium_compat` si hiciera falta). HTTPS confirmado: activaremos HSTS y cookie Secure.
- Aceptación del orden propuesto (P0→P1→P2) o ajustes.
- Multiusuario: confirmar alcance deseado
  - ¿Alta por invitación o self-signup?
  - ¿Modelo organización/grupos o solo por usuario?
  - ¿Roles necesarios (admin/editor/lector)? ¿2FA?

# Lessons
- Evitar cifrado sin autenticación; preferir AEAD siempre.
- No exponer secretos en repositorio; usar `.env` y rotación.
- Verificar acceso y CSRF en todos los endpoints de cambio de estado.
- **Share passwords enhancement**: El texto del email debe ser claro sobre los dos tipos de expiración (por usos y por tiempo) para evitar confusión.
- **Cron job cleanup.php**: Funciona correctamente eliminando enlaces con más de 7 días, independientemente del contador de usos.

---

# Proposed DB Structure (Draft – pending confirmation)

Basado en la tabla relevante `passwords-manager` (otras dos tablas son de otra app). Validar con `SHOW CREATE TABLE` antes de ejecutar.

## Estado actual (confirmado por DDL)

```sql
CREATE TABLE `passwords-manager` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `linea_de_negocio` varchar(255) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `usuario` varchar(255) NOT NULL,
  `password` text NOT NULL,
  `enlace` varchar(255) NOT NULL,
  `info_adicional` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=196 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
```

- Índices: sólo PK sobre `id`.
- Observaciones: sin timestamps ni `owner_user_id`; columnas `varchar(255)` y `text`; colación `utf8_general_ci` (no `utf8mb4`).

## Propuesta de esquema objetivo (multiusuario + seguridad)

1) Renombrar tabla y añadir columnas/índices
```sql
-- Renombrar para evitar guiones
RENAME TABLE `passwords-manager` TO `passwords_manager`;

-- Añadir columnas estándar y propietario
ALTER TABLE `passwords_manager`
  ADD COLUMN `owner_user_id` BIGINT UNSIGNED NULL AFTER `id`,
  ADD COLUMN `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `info_adicional`,
  ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;

-- Índices útiles para búsquedas frecuentes
CREATE INDEX idx_pm_owner ON `passwords_manager` (`owner_user_id`);
CREATE INDEX idx_pm_busqueda ON `passwords_manager` (`linea_de_negocio`, `nombre`, `usuario`);
```

2) Usuarios
```sql
CREATE TABLE IF NOT EXISTS `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(190) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('admin','editor','lector') NOT NULL DEFAULT 'editor',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
```

3) Sesiones persistentes (remember-me)
```sql
CREATE TABLE IF NOT EXISTS `user_sessions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `token_hash` CHAR(64) NOT NULL, -- SHA-256 hex
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `ip_hash` CHAR(64) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_used_at` TIMESTAMP NULL DEFAULT NULL,
  `expires_at` TIMESTAMP NOT NULL,
  `revoked` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_token_hash` (`token_hash`),
  KEY `idx_user_expires` (`user_id`, `expires_at`),
  CONSTRAINT `fk_user_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
```

4) Integridad entre contraseñas y usuarios
```sql
ALTER TABLE `passwords_manager`
  ADD CONSTRAINT `fk_pm_owner` FOREIGN KEY (`owner_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL;
```

5) Consideraciones de tipos y longitudes
- Recomendado migrar a `utf8mb4` (MySQL 8+: `utf8mb4_0900_ai_ci`; si no, `utf8mb4_unicode_ci`).
- `usuario`, `nombre`, `linea_de_negocio`: `VARCHAR(190)` si van en índices compuestos en utf8mb4.
- `enlace`: `VARCHAR(2048)` para URLs.
- `password`: `TEXT` (almacenará AEAD base64 con metadatos `{v,alg,nonce,tag,ct}`).
- `descripcion`, `info_adicional`: `TEXT`.

## Plan de migración (orden sugerido)
1. Crear `users` y usuario admin inicial.
2. Renombrar ``passwords-manager`` → `passwords_manager`.
3. Opcional: convertir a `utf8mb4` con colación objetivo.
   ```sql
   ALTER TABLE `passwords_manager` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
   ```
4. Añadir columnas `owner_user_id`, `created_at`, `updated_at` y fijar `owner_user_id` = admin para filas existentes.
5. Crear `user_sessions`.
6. Añadir índices y FK.
7. Ajustar tipos/longitudes si aplican (p. ej., `enlace` a `VARCHAR(2048)`).
8. Actualizar app: scoping por `owner_user_id` y gates de auth.

Notas: Validar `SHOW CREATE TABLE` actual para ajustar tipos exactos y colaciones.

---

# Plan: Mejora de Funcionalidad Share Passwords

## Background and Motivation

El sistema actual de compartir contraseñas mediante enlaces temporales tiene limitaciones importantes:

1. **Campo de contraseña limitado**: Usa un `input type="text"` simple que no permite introducir información adicional (como contexto, usuario asociado, notas, etc.)
2. **Un solo uso obligatorio**: Cada enlace solo se puede usar una vez, lo cual es restrictivo cuando se necesita compartir la misma contraseña con varias personas
3. **Email genérico**: Cuando se envía por correo, el mensaje es estándar y no permite personalización (título ni mensaje), resultando poco informativo para el destinatario

Estas limitaciones reducen la utilidad real del sistema en escenarios de trabajo colaborativo.

## Key Challenges and Analysis

### Desafíos técnicos identificados:

1. **Estructura de base de datos**: La tabla `passwords` (del share) actual no tiene campos para:
   - Número máximo de recuperaciones permitidas
   - Contador de recuperaciones realizadas
   - Título del recurso compartido
   - Mensaje personalizado

2. **Lógica de retrieve.php**: Actualmente elimina el registro inmediatamente después de la recuperación. Hay que cambiar esto a un sistema de conteo.

3. **Envío de email**: El email actual es estático en `store.php`. Necesita campos dinámicos para título y mensaje.

4. **UI/UX**: Añadir nuevos campos sin sobrecargar el formulario actual.

## High-level Task Breakdown

### Fase 1: Base de Datos
- [ ] **Tarea 1.1**: Añadir columnas a tabla `passwords` (share)
  - `max_retrievals` (INT, DEFAULT 1) - Número máximo de veces que se puede recuperar
  - `retrieval_count` (INT, DEFAULT 0) - Contador de recuperaciones realizadas
  - `title` (VARCHAR(255), NULL) - Título del recurso compartido
  - `message` (TEXT, NULL) - Mensaje personalizado para el email
  - **Criterio de éxito**: La tabla tiene las nuevas columnas y los enlaces existentes funcionan (default=1 uso)

### Fase 2: Frontend (Formulario)
- [ ] **Tarea 2.1**: Actualizar `share/index.html`
  - Cambiar `input type="text"` por `textarea` para el campo de contraseña
  - Añadir input numérico para "Número de usos permitidos" (mínimo 1, default 1)
  - Añadir campo de texto para "Título" (visible solo si se introduce email)
  - Añadir textarea para "Mensaje personalizado" (visible solo si se introduce email)
  - JavaScript para mostrar/ocultar campos condicionales
  - **Criterio de éxito**: Formulario muestra nuevos campos y se ocultan/muestran correctamente según el email

- [ ] **Tarea 2.2**: Actualizar `share/styles.css`
  - Estilos para textarea de contraseña
  - Estilos para campos condicionales del email
  - Asegurar responsive design
  - **Criterio de éxito**: Visualmente atractivo y funcional en móvil y desktop

### Fase 3: Backend (Almacenamiento)
- [ ] **Tarea 3.1**: Actualizar `share/store.php`
  - Recibir y validar nuevos campos del formulario
  - Insertar nuevos campos en la base de datos
  - Actualizar template de email para usar título y mensaje personalizado
  - Email subject dinámico basado en el título proporcionado
  - **Criterio de éxito**: Los datos se guardan correctamente y el email se envía con contenido personalizado

### Fase 4: Backend (Recuperación)
- [ ] **Tarea 4.1**: Actualizar `share/retrieve.php`
  - Modificar lógica: en lugar de eliminar, incrementar `retrieval_count`
  - Validar que `retrieval_count < max_retrievals`
  - Solo eliminar cuando se alcance el límite
  - Mostrar información al usuario: "Quedan X usos de este enlace"
  - **Criterio de éxito**: Un enlace con 3 usos funciona exactamente 3 veces y falla en el cuarto

### Fase 5: Testing
- [ ] **Tarea 5.1**: Tests manuales
  - Crear enlace con 3 usos permitidos, verificar que funcione 3 veces
  - Verificar que el cuarto intento falle con mensaje apropiado
  - Verificar envío de email con título y mensaje personalizado
  - Verificar que textarea permite multilinea (saltos de línea se preservan)
  - **Criterio de éxito**: Todos los tests pasan manualmente

## Project Status Board (Share Passwords Enhancement)

| Tarea | Estado | Notas |
|-------|--------|-------|
| 1.1 - Migración BD | ✅ Completado | SQL entregado al usuario |
| 2.1 - Formulario HTML | ✅ Completado | textarea, campos condicionales, JS |
| 2.2 - Estilos CSS | ✅ Completado | Estilos para nuevos elementos |
| 3.1 - Store.php | ✅ Completado | Nuevos campos + email dinámico |
| 4.1 - Retrieve.php | ✅ Completado | Lógica de conteo implementada |
| 5.1 - Testing | Pendiente | Usuario debe probar manualmente |
| 5.2 - Mejora email | ✅ Completado | Texto más claro sobre expiración |
| 5.3 - Revisar cron cleanup | ✅ Completado | Funciona correctamente |

## SQL de Migración (Ejecutar en phpMyAdmin)

```sql
-- Añadir columnas necesarias a la tabla passwords (del share)
ALTER TABLE `passwords` 
ADD COLUMN `max_retrievals` INT NOT NULL DEFAULT 1 AFTER `email`,
ADD COLUMN `retrieval_count` INT NOT NULL DEFAULT 0 AFTER `max_retrievals`,
ADD COLUMN `title` VARCHAR(255) DEFAULT NULL AFTER `retrieval_count`,
ADD COLUMN `message` TEXT DEFAULT NULL AFTER `title`;
```

## Notas de Implementación

1. **Backward compatibility**: Los enlaces existentes tienen `max_retrievals=1` y `retrieval_count=0`, por lo que seguirán funcionando como antes (un solo uso).

2. **UX para campos condicionales**: Los campos de título y mensaje solo deben mostrarse cuando el usuario escriba algo en el campo de email, para no saturar la UI.

3. **Validaciones**:
   - `max_retrievals` debe ser >= 1
   - `email` debe ser válido si se proporciona
   - `title` máximo 255 caracteres
   - `message` texto libre, límites razonables (ej. 2000 chars)

4. **Email dinámico**: Si hay título personalizado, usarlo como Subject. Si hay mensaje, insertarlo antes del enlace en el cuerpo del email.
