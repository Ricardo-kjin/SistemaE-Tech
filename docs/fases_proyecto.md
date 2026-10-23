# Fases del proyecto

Sistema exclusivo de consulta y actualización del Tipo de Cambio. Lee y escribe `CAMBIOS.dbo.CAMBIOS` en SQL Server. Sin autenticación ni gestión de usuarios. Solo se trabaja la fase abierta. Antes de cada tarea se releen este archivo y `docs/bitacora_fases.md`.

Estados de una fase: Pendiente, En progreso, Completada.

## Fase 1: Administración → Tipo Cambio

Estado: **Completada** (acceso público, sin login; conexión `cambios` a `CAMBIOS.dbo.CAMBIOS`)

- [x] Acceso directo a `/` con el dashboard de Tipo de Cambio. Sin login, registro, recuperación de contraseña ni cierre de sesión.
- [x] Layout SB Admin. Menú `Administración → Tipo Cambio`.
- [x] Listado paginado de `CAMBIOS.dbo.CAMBIOS` (Fecha, Tipo de cambio), ordenado por fecha descendente, botón `Actualizar`.
- [x] Servicio BCB con guardado idempotente por `FECHA`.
- [x] Comando `tipo-cambio:actualizar` a las 23:05 `America/La_Paz`.
- [x] Pruebas sobre SQLite local (tipos de cambio). Nunca sobre el SQL Server real.
- [x] Conexión `cambios` apuntando al SQL Server local `DESKTOP-ALT6VO5:1433`, base `CAMBIOS`, usuario `sa`. La contraseña se configura en `.env`.
- [x] Spatie Permission y Breeze retirados del flujo. PostgreSQL / conexión `usuarios` ya no se usa.

Las siguientes fases no están definidas.
