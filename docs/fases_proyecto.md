# Fases del proyecto

Sistema de administración con Laravel. Usuarios en PostgreSQL (`cambio`) y tipos de cambio en SQL Server (`CAMBIOS`). Solo se trabaja la fase abierta. Antes de cada tarea se releen este archivo y `docs/bitacora_fases.md`.

Estados de una fase: Pendiente, En progreso, Completada.

## Fase 1: Administración → Tipo Cambio

Estado: **Completada** (ajustada a dos conexiones: usuarios + `CAMBIOS.dbo.CAMBIOS`)

- [x] Login con Breeze (Blade) y roles Spatie Permission 6 sobre la conexión `usuarios`.
- [x] Roles `Administrador` y `Gerente` con `ver dashboard`, `ver tipo cambio` y `actualizar tipo cambio`.
- [x] Layout SB Admin. Menú `Administración → Tipo Cambio`.
- [x] Listado paginado de `CAMBIOS.dbo.CAMBIOS` (Fecha, Tipo de cambio), ordenado por fecha descendente, botón `Actualizar`.
- [x] Servicio BCB con guardado idempotente por `FECHA`.
- [x] Comando `tipo-cambio:actualizar` a las 23:05 `America/La_Paz`.
- [x] Pruebas sobre `cambio_test` (usuarios) y SQLite local (tipos de cambio). Nunca sobre el SQL Server real.
- [x] Conexión `cambios` apuntando al SQL Server local `DESKTOP-ALT6VO5:1433`, base `CAMBIOS`, usuario `sa`. La contraseña se configura en `.env`.

Las siguientes fases no están definidas.
