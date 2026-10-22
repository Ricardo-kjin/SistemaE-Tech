# Bitácora de fases

## Fase 1: Administración → Tipo Cambio — conexiones independientes (22/10/2026)

### Alcance

El módulo ya existía contra PostgreSQL `tipo_cambio`. Se adaptó para autenticar en la base de usuarios actual y leer/escribir exclusivamente `CAMBIOS.dbo.CAMBIOS` en SQL Server. No se recreó el proyecto ni se tocó la plantilla original.

### Versiones

| Componente | Versión |
|---|---|
| PHP | 8.2.12 ZTS x64 (XAMPP) |
| Laravel | 12.70.1 |
| Spatie Permission | 6.25.0 |
| Laravel Breeze | 2.4.2, stack Blade |
| PostgreSQL (usuarios) | 18, base `cambio` |
| Driver PHP SQL Server | `pdo_sqlsrv` / `sqlsrv` 5.11.1 (DLLs TS x64) |
| ODBC | Microsoft ODBC Driver 18 for SQL Server 18.7.1.1 |

PHP no traía `pdo_sqlsrv`. Se habilitó en `C:\xampp\php\php.ini` con `php_sqlsrv_82_ts.dll` y `php_pdo_sqlsrv_82_ts.dll`. Sin ODBC 18 el driver `sqlsrv` de Laravel no conecta.

### Conexiones

Definidas en `config/database.php` con variables independientes:

| Nombre | Driver | Uso | Variables |
|---|---|---|---|
| `usuarios` (predeterminada, `DB_CONNECTION`) | `pgsql` | `users`, sesiones, caché, colas, Spatie | `DB_*` |
| `cambios` | `sqlsrv` | Solo `CAMBIOS.dbo.CAMBIOS` | `DB_CAMBIOS_*` |

La conexión de usuarios **no se migró a SQL Server**: la base configurada y operativa era PostgreSQL `cambio`. No se inventó otro nombre ni se creó una base de usuarios nueva. `User`, Spatie y las migraciones de autenticación siguen en `usuarios`. Los seeders abortan si el default es `cambios`.

SQL Server de tipos de cambio:

- Host `34.29.162.77`, instancia `E-TECH-SERVER01` (`DB_CAMBIOS_HOST` + `DB_CAMBIOS_INSTANCE`).
- Base `CAMBIOS`, usuario `laravel_user`.
- Contraseña solo en `DB_CAMBIOS_PASSWORD` (`.env`, no versionado).

### Resultado real de la conexión `cambios`

Comprobada por separado de usuarios el 22/10/2026:

1. `usuarios` (PostgreSQL `cambio`): correcta. 2 usuarios, roles Administrador y Gerente.
2. `cambios` con `34.29.162.77\E-TECH-SERVER01`: **falló**. `SQLSTATE[08001]` — el cliente no resolvió el servidor/instancia (SQL Browser / UDP 1434 no respondió).
3. Reintento `34.29.162.77:1433` (sin instancia): **falló**. Tiempo de espera TCP agotado (`login_timeout` 8 s).

`DB_CAMBIOS_PASSWORD` no estaba definido. Ni siquiera se llegó al login: el puerto no es alcanzable desde este equipo. **No se simuló una actualización exitosa contra SQL Server.**

Para que la conexión funcione hace falta, en `.env`:

- `DB_CAMBIOS_PASSWORD` del usuario `laravel_user` (no usar `sa` ni administrador del servidor).
- El puerto TCP real de la instancia, en `DB_CAMBIOS_PORT`, si no usa 1433 o SQL Browser. VPN o regla de firewall si el servidor no acepta el origen.

Hasta entonces, el listado muestra el error concreto de SQL Server y el botón `Actualizar` no inventa filas.

### Tabla `dbo.CAMBIOS`

Estructura respetada, sin `id`, `moneda`, `fuente` ni `fecha_registro`:

- `FECHA datetime NOT NULL` (PK, sin identidad)
- `CAMBIO real NOT NULL`

Modelo `App\Models\TipoCambio`: conexión `cambios`, tabla `CAMBIOS`, PK `FECHA`, `$incrementing = false`. No se creó una segunda tabla ni se alteró el esquema.

Como no hubo conexión, no se pudieron inspeccionar las horas reales almacenadas. El servicio normaliza así:

- Alta nueva: `Y-m-d 00:00:00`.
- Fecha ya existente (mismo día calendario, `CAST(FECHA AS date)`): solo actualiza `CAMBIO`; no cambia `FECHA` ni otras filas.
- Varias filas el mismo día: no modifica ninguna e informa el error.

### Interfaz y BCB

Vista con columnas Fecha y Tipo de cambio, paginación y botón `Actualizar`. Extrae de `https://www.bcb.gob.bo/` la tarjeta `article.is-tc-oficial` (fecha `time[datetime]`, valor `.bcb-tco-num`). La moneda se valida (dólar estadounidense) pero **no se guarda**. El valor se convierte y se rechaza si no es numérico o no es mayor que cero. El botón y `tipo-cambio:actualizar` llaman al mismo `BcbTipoCambioService`. Lock 120 s + `withoutOverlapping`.

### Scheduler

Sigue en `routes/console.php` a las 20:05 `America/La_Paz`. **No está creado en el Programador de tareas.** Comando de Windows:

```
schtasks /Create /TN "Cod_Sistema Laravel Scheduler" /SC MINUTE /MO 1 /TR "cmd /c cd /d C:\Personal\Cod_Sistema && C:\xampp\php\php.exe artisan schedule:run >> storage\logs\schedule_run.log 2>&1"
```

Linux: `* * * * * cd /ruta/al/proyecto && php artisan schedule:run >> /dev/null 2>&1`

### Pruebas

`php artisan test`: 58 pruebas, 167 aserciones, todas en verde.

- Usuarios: PostgreSQL `cambio_test`. Abortan si el default no es `cambio_test`.
- Tipos de cambio: SQLite `database/testing_cambios.sqlite` (gitignored). Abortan si el driver de `cambios` es `sqlsrv`.
- Permisos, listado, paginación, upsert por fecha, vigencia del día siguiente, HTTP 503, timeout, HTML inválido, lock, comando, horario 20:05, conexiones independientes, no tocar otras fechas.

No se usó el SQL Server real en pruebas destructivas.

### Archivos relevantes

Creados o reescritos: `app/Models/TipoCambio.php`, servicio BCB, controlador, vistas del módulo, `docs/*`.

Modificados: `config/database.php`, `app/Models/User.php` (`$connection = 'usuarios'`), `routes/web.php`, `.env` / `.env.example` (`DB_CAMBIOS_*`), `phpunit.xml`, `tests/TestCase.php`, `tests/Feature/TipoCambioModuloTest.php`, migración `2026_10_22_200000_*` (queda vacía; no toca `CAMBIOS`).

No se ejecutó `migrate:fresh`. No hay commit.
