# Fases del proyecto

Sistema de administración con Laravel + PostgreSQL (base `cambio`). Solo se trabaja la fase abierta. Antes de cada tarea se releen este archivo y `docs/bitacora_fases.md`.

Estados de una fase: Pendiente, En progreso, Completada.

## Fase 1: Administración → Tipo Cambio

Estado: **Completada**

- [x] Proyecto Laravel 12 creado en `C:\Personal\Cod_Sistema` (no existía ninguno). Sin tocar la plantilla original ni `script.sql`.
- [x] Login con Breeze (Blade) y roles y permisos con Spatie Permission 6.
- [x] Roles `Administrador` y `Gerente` con los mismos permisos: `ver dashboard`, `ver tipo cambio` y `actualizar tipo cambio`.
- [x] Layout SB Admin integrado. Menú `Administración → Tipo Cambio`.
- [x] Consulta paginada de `tipo_cambio`, ordenada por fecha descendente, con el botón `Actualizar`. Sin altas, ediciones ni bajas.
- [x] Servicio de extracción del tipo de cambio oficial desde la portada del BCB, con guardado idempotente por `(fecha, moneda)`.
- [x] Comando `tipo-cambio:actualizar`, programado a las 20:05 en `America/La_Paz`.
- [x] Pruebas automatizadas sobre la base `cambio_test`.

Las siguientes fases no están definidas.
