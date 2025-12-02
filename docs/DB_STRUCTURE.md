# Database Structure – Password Manager

Este documento resume el estado actual de la base de datos y propuestas de evolución.

Última actualización: 2025-12-02

## 1) Estado actual (Diciembre 2025)

- ✅ Multiusuario con roles (admin/editor/lector)
- ✅ Sistema de compartición por usuario individual (`passwords_access`)
- ✅ Propiedad por registro (`owner_user_id`) con scoping
- ✅ Sesión persistente (remember-me) segura: 60 días, máximo 5 tokens/usuario
- ✅ Rate limiting por IP
- ✅ Sistema de contraseñas temporales compartibles (tabla `passwords`)
- 🔄 **Próximo**: Sistema de departamentos/grupos para gestión masiva de permisos

## 2) Tablas actuales en producción

### 2.1) `passwords` (contraseñas temporales compartibles)

```sql
CREATE TABLE `passwords` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `password` TEXT NOT NULL,           -- contraseña cifrada
  `iv` VARCHAR(255) NOT NULL,         -- vector de inicialización
  `link_hash` VARCHAR(255) NOT NULL,  -- hash único del link de acceso
  `email` VARCHAR(255) DEFAULT NULL,  -- email del creador (opcional)
  `is_retrieved` TINYINT(1) DEFAULT 0,-- si ya fue vista
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_link_hash` (`link_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 2.2) `passwords_manager` (gestor principal de credenciales)

```sql
CREATE TABLE `passwords_manager` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `owner_user_id` BIGINT UNSIGNED NULL,
  `linea_de_negocio` VARCHAR(255) NOT NULL,
  `nombre` VARCHAR(255) NOT NULL,
  `descripcion` TEXT,
  `usuario` VARCHAR(255) NOT NULL,
  `password` TEXT NOT NULL,           -- contraseña cifrada
  `enlace` VARCHAR(2048) NOT NULL,
  `info_adicional` TEXT,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pm_owner` (`owner_user_id`),
  KEY `idx_pm_busqueda` (`linea_de_negocio`, `nombre`, `usuario`),
  CONSTRAINT `fk_passwords_manager_owner` 
    FOREIGN KEY (`owner_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 2.3) `passwords_access` (compartición usuario-a-contraseña)

```sql
CREATE TABLE `passwords_access` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `password_id` INT NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `perm` ENUM('owner','editor','viewer') NOT NULL DEFAULT 'editor',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_password_user` (`password_id`, `user_id`),
  KEY `idx_access_user` (`user_id`),
  CONSTRAINT `fk_access_password` 
    FOREIGN KEY (`password_id`) REFERENCES `passwords_manager`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_access_user` 
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 2.4) `users`

```sql
CREATE TABLE `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(190) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('admin','editor','lector') NOT NULL DEFAULT 'editor',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 2.5) `user_sessions` (remember-me tokens)

```sql
CREATE TABLE `user_sessions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `token_hash` CHAR(64) NOT NULL,     -- SHA-256 hex
  `user_agent` TEXT,
  `ip_hash` CHAR(64) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_used_at` TIMESTAMP NULL DEFAULT NULL,
  `expires_at` TIMESTAMP NOT NULL,
  `revoked` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_token_hash` (`token_hash`),
  KEY `idx_user_expires` (`user_id`, `expires_at`),
  CONSTRAINT `fk_user_sessions_user` 
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 2.6) `rate_limits` (control de tasa por IP)

```sql
CREATE TABLE `rate_limits` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `ip` VARCHAR(45) NOT NULL,
  `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rate_ip_time` (`ip`, `timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## 3) Propuesta: Sistema de Departamentos/Grupos

### Objetivo

Permitir asignar permisos de contraseñas no solo a usuarios individuales, sino también a **departamentos/grupos completos**. Esto facilita la gestión cuando hay muchos usuarios (ej: dar acceso a todo el departamento de Marketing sin tener que seleccionar uno por uno).

### Caso de uso

- Admin crea departamento "Marketing"
- Admin asigna 10 usuarios al departamento "Marketing"
- Admin comparte contraseña de "Facebook Ads" con el departamento "Marketing"
- → Los 10 usuarios automáticamente tienen acceso
- Si se añade un nuevo usuario a Marketing, automáticamente ve todas las contraseñas compartidas con ese departamento

### Diseño propuesto

#### 3.1) Nueva tabla: `departments`

```sql
CREATE TABLE `departments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_dept_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### 3.2) Nueva tabla: `user_departments` (relación N:M usuarios-departamentos)

Un usuario puede pertenecer a múltiples departamentos.

```sql
CREATE TABLE `user_departments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `department_id` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_dept` (`user_id`, `department_id`),
  KEY `idx_dept_users` (`department_id`),
  CONSTRAINT `fk_userdept_user` 
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_userdept_dept` 
    FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### 3.3) Nueva tabla: `password_department_access` (compartir con departamentos)

Similar a `passwords_access`, pero para departamentos completos.

```sql
CREATE TABLE `password_department_access` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `password_id` INT NOT NULL,
  `department_id` BIGINT UNSIGNED NOT NULL,
  `perm` ENUM('owner','editor','viewer') NOT NULL DEFAULT 'viewer',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_password_dept` (`password_id`, `department_id`),
  KEY `idx_dept_access` (`department_id`),
  CONSTRAINT `fk_deptaccess_password` 
    FOREIGN KEY (`password_id`) REFERENCES `passwords_manager`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_deptaccess_dept` 
    FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### 3.4) SQL completo para ejecutar en phpMyAdmin

**⚠️ IMPORTANTE: Ejecuta esto en phpMyAdmin en un solo bloque**

```sql
-- 1) Crear tabla de departamentos
CREATE TABLE `departments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_dept_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2) Crear relación usuarios-departamentos
CREATE TABLE `user_departments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `department_id` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_dept` (`user_id`, `department_id`),
  KEY `idx_dept_users` (`department_id`),
  CONSTRAINT `fk_userdept_user` 
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_userdept_dept` 
    FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3) Crear tabla de accesos por departamento
CREATE TABLE `password_department_access` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `password_id` INT NOT NULL,
  `department_id` BIGINT UNSIGNED NOT NULL,
  `perm` ENUM('owner','editor','viewer') NOT NULL DEFAULT 'viewer',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_password_dept` (`password_id`, `department_id`),
  KEY `idx_dept_access` (`department_id`),
  CONSTRAINT `fk_deptaccess_password` 
    FOREIGN KEY (`password_id`) REFERENCES `passwords_manager`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_deptaccess_dept` 
    FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4) (Opcional) Crear departamentos de ejemplo
INSERT INTO `departments` (`name`, `description`) VALUES
('Marketing', 'Equipo de marketing y comunicación'),
('Ventas', 'Equipo comercial y ventas'),
('IT', 'Tecnología e infraestructura'),
('RRHH', 'Recursos humanos');
```

## 6) Notas de compatibilidad y rendimiento

- Collation: usar `utf8mb4_unicode_ci` en MySQL 5.7/MariaDB. Si más adelante se actualiza a MySQL 8.0+, se puede migrar a `utf8mb4_0900_ai_ci`.
- Índices con utf8mb4: si necesitas índices compuestos, preferir VARCHAR(190) para evitar límite histórico de 767 bytes en versiones antiguas.