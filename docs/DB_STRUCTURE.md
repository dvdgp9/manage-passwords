# Database Structure – Password Manager

Este documento resume el estado actual de la base de datos relevante y la propuesta objetivo para habilitar multiusuario seguro y sesión persistente (remember-me).

Fecha: 2025-08-26

## 1) Estado actual

- Multiusuario con roles (admin/editor/lector).
- Propiedad por registro (`owner_user_id`) y scoping por usuario.
- Sesión persistente (remember-me) segura: 60 días, recordarme por defecto, máximo 5 tokens/usuario, sin password extra al restaurar.

## 3) Esquema objetivo propuesto

Renombrar tabla para evitar guiones y añadir metadatos e integridad referencial.

### 3.1) passwords_manager (antes `passwords-manager`)

```sql
RENAME TABLE `passwords-manager` TO `passwords_manager`;

-- Opcional (MySQL < 8.0: usar utf8mb4_unicode_ci)
ALTER TABLE `passwords_manager`
  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `passwords_manager`
  ADD COLUMN `owner_user_id` BIGINT UNSIGNED NULL AFTER `id`,
  ADD COLUMN `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `info_adicional`,
  ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;

CREATE INDEX idx_pm_owner ON `passwords_manager` (`owner_user_id`);
CREATE INDEX idx_pm_busqueda ON `passwords_manager` (`linea_de_negocio`, `nombre`, `usuario`);
```

Tipos/longitudes recomendadas si se ajusta más adelante:
- `usuario`, `nombre`, `linea_de_negocio`: VARCHAR(190) si participan en índices compuestos en utf8mb4.
- `enlace`: VARCHAR(2048) para URLs largas.
- `password`: TEXT para almacenar cifrado AEAD base64 con metadatos `{v,alg,nonce,tag,ct}`.

### 3.2) users

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 3.3) user_sessions (remember-me)

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 3.4) Integridad referencial

```sql
ALTER TABLE `passwords_manager`
  ADD CONSTRAINT `fk_passwords_manager_owner` FOREIGN KEY (`owner_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL;
```

## 6) Notas de compatibilidad y rendimiento

- Collation: usar `utf8mb4_unicode_ci` en MySQL 5.7/MariaDB. Si más adelante se actualiza a MySQL 8.0+, se puede migrar a `utf8mb4_0900_ai_ci`.
- Índices con utf8mb4: si necesitas índices compuestos, preferir VARCHAR(190) para evitar límite histórico de 767 bytes en versiones antiguas.