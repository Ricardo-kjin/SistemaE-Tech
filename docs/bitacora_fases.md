# Bitácora de fases

## Fase 1: Administración → Tipo Cambio (22/10/2026)

### Punto de partida

- `C:\Personal\Cod_Sistema` solo tenía la plantilla `DASHBOARD startbootstrap-sb-admin-gh-pages` y `script.sql`. No había proyecto Laravel, `docs/` ni Git.
- La tabla `tipo_cambio` ya existía en la base PostgreSQL `cambio`, vacía y con la estructura de `script.sql` (incluida `uk_tipo_cambio (fecha, moneda)`). No había tablas de usuarios.
- Se decidió con el usuario crear un proyecto nuevo aquí en lugar de usar `C:\Personal\Proy Curso` (otro sistema, de control de asistencia, con otra base).

### Versiones

| Componente | Versión |
|---|---|
| PHP | 8.2.12 (XAMPP, `pdo_pgsql` y `bcmath` habilitados) |
| Laravel | 12.70.1 (Laravel 13 exige PHP 8.3) |
| Spatie Permission | 6.25.0 |
| Laravel Breeze | 2.4.2, stack Blade |
| PostgreSQL | 18 (servicio `postgresql-x64-18`, zona horaria del servidor `America/La_Paz`) |

`psql` no está en el PATH: `C:\Program Files\PostgreSQL\18\bin\psql.exe`.

Con Composer en PowerShell, el `^` de las versiones se pierde por el envoltorio `.bat`. Usar `~` (por ejemplo `"spatie/laravel-permission:~6.0"`).

### Base de datos

- `.env`: `pgsql`, `127.0.0.1:5432`, base `cambio`, usuario `postgres`.
- Con `php artisan migrate` (no destructivo) se añadieron las tablas de Laravel (`users`, `sessions`, `cache`, `cache_locks`, `jobs`…) y las de Spatie.
- `2026_10_22_200000_create_tipo_cambio_table.php` solo crea `tipo_cambio` si no existe, con el mismo SQL de `script.sql`. En `cambio` no hizo nada. Su `down()` no borra la tabla.
- `tipo_cambio` no se modificó: nombres, tipos, `NUMERIC(10,5)`, identidad y restricción única siguen como estaban.
- Base aparte `cambio_test` para las pruebas. `tests/TestCase.php` aborta si `RefreshDatabase` apunta a otra base. **Nunca ejecutar `migrate:fresh` sobre `cambio`.**

### Autenticación y permisos

- `RolesPermisosSeeder` (idempotente): permisos `ver dashboard`, `ver tipo cambio`, `actualizar tipo cambio`, y roles `Administrador` y `Gerente`, ambos con los tres.
- `UsuariosInicialesSeeder`: crea `admin@sistema.local` (Administrador) y `gerente@sistema.local` (Gerente) con la contraseña de `SEED_USUARIOS_PASSWORD` en `.env`. No sobrescribe la contraseña de usuarios que ya existen. **Cambiarla en producción.**
- Se quitó el registro público de Breeze (ruta, controlador, vista y prueba): sin un rol asignado no se accede a nada.
- Un usuario autenticado sin rol recibe 403 en el dashboard y en el módulo.

### Archivos creados

- `app/Models/TipoCambio.php`
- `app/Services/TipoCambio/BcbTipoCambioService.php`: descarga, extracción, validación, guardado y lock.
- `app/Services/TipoCambio/CotizacionBcb.php`, `ResultadoActualizacion.php`, `TipoCambioBcbException.php`
- `app/Http/Controllers/TipoCambioController.php`: listado y acción `actualizar`, sin lógica de negocio.
- `app/Console/Commands/ActualizarTipoCambio.php`: comando `tipo-cambio:actualizar`.
- `database/migrations/2026_10_22_200000_create_tipo_cambio_table.php`
- `database/seeders/RolesPermisosSeeder.php`, `UsuariosInicialesSeeder.php`
- `resources/views/layouts/sbadmin.blade.php`, `resources/views/tipo-cambio/index.blade.php`
- `public/css/styles.css`, `public/js/scripts.js`, `public/assets/img/`: copias de la plantilla. La original no se tocó.
- `lang/es/*.php`: mensajes de autenticación y validación en español.
- `tests/Feature/TipoCambioModuloTest.php`, `tests/Unit/BcbTipoCambioExtraccionTest.php`, `tests/Concerns/PortadaBcb.php`, `tests/Fixtures/bcb_portada.html` (portada real del BCB del 22/10/2026).

### Archivos modificados

`app/Models/User.php` (`HasRoles`), `bootstrap/app.php` (alias `role` y `permission`), `app/Providers/AppServiceProvider.php` (paginación Bootstrap 5), `routes/web.php`, `routes/auth.php`, `routes/console.php`, `config/services.php` (`bcb`), `config/logging.php` (canal `tipo_cambio`), `database/seeders/DatabaseSeeder.php`, `resources/views/auth/login.blade.php`, `resources/views/dashboard.blade.php`, `phpunit.xml`, `tests/TestCase.php`, `tests/Feature/ExampleTest.php`, `.env.example`.

### Rutas

| Método | URI | Nombre | Middleware |
|---|---|---|---|
| GET | `/` | redirige a `/dashboard` | |
| GET | `/dashboard` | `dashboard` | `auth`, `permission:ver dashboard` |
| GET | `/administracion/tipo-cambio` | `tipo-cambio.index` | `auth`, `permission:ver tipo cambio` |
| POST | `/administracion/tipo-cambio/actualizar` | `tipo-cambio.actualizar` | además `permission:actualizar tipo cambio` y `throttle:6,1` |

### Fuente oficial del BCB

Revisión del sitio el 22/10/2026:

- La portada `https://www.bcb.gob.bo/` publica la tarjeta "Tipo de cambio oficial" en el HTML del servidor:
  ```html
  <article class="bcb-kpi2-card is-tc-oficial">
    <p class="bcb-kpi2-name">Tipo de cambio oficial</p>
    <div class="bcb-kpi2-sub">Bolivianos por dólar estadounidense</div>
    <div class="bcb-kpi2-asof"><time datetime="2026-10-22">JUEVES 22 DE OCTUBRE, 2026</time></div>
    ...<span class="bcb-tco-num">11,75</span>
  ```
- La página "Más información" (`?q=content/tipo-de-cambio-oficial-del-dólar-estadounidense`) no trae el dato en texto. `?q=cotizaciones_tc` es un formulario de consulta. No se encontró ninguna API ni JSON público. Por eso la extracción usa HTTP y análisis del HTML de la portada.

Cómo funciona el servicio:

1. GET a la portada con `connectTimeout` de 10 s, `timeout` de 25 s y 2 reintentos solo ante errores de conexión (configurable con `BCB_*` en `.env`).
2. Con DOM/XPath busca exactamente una `article.is-tc-oficial`.
3. **Fecha**: atributo `datetime` del `<time>` dentro de `.bcb-kpi2-asof` (fecha de vigencia publicada). Debe ser una fecha `Y-m-d` válida y estar entre 30 días antes y 7 días después de hoy en La Paz. Si el BCB publica la del día siguiente, se guarda esa fecha.
4. **Moneda**: el subtítulo debe decir "Bolivianos por dólar estadounidense". Se guarda `USD` (ISO 4217). Cualquier otro texto da error.
5. **Valor**: formato boliviano (coma decimal, punto de miles). Debe ser mayor que 0, con 5 dígitos enteros y 5 decimales como máximo (`NUMERIC(10,5)`). Se maneja como cadena para no perder precisión.
6. **Fuente**: `BCB - Tipo de cambio oficial (www.bcb.gob.bo)` (45 caracteres).
7. **Guardado**: `INSERT … ON CONFLICT (fecha, moneda) DO UPDATE … WHERE` el valor o la fuente cambiaron. Es atómico y no duplica filas. Distingue entre creado, actualizado y sin cambios. `fecha_registro` usa el `CURRENT_TIMESTAMP` del servidor PostgreSQL (hora de La Paz) al insertar y al corregir un valor.
8. **Concurrencia**: `Cache::lock('tipo-cambio:bcb:actualizar', 120)`. Si ya hay una ejecución en curso, se informa y no se consulta el BCB. Además, el botón se desactiva al enviarse.
9. **Errores**: HTTP distinto de 2xx, error de conexión, respuesta vacía, tarjeta ausente o valor/fecha/moneda inválidos lanzan `TipoCambioBcbException`. No se escribe nada y los datos existentes se conservan. Nunca se guarda cero ni un valor supuesto.
10. **Log**: `storage/logs/tipo_cambio-AAAA-MM-DD.log` (canal `tipo_cambio`, 60 días), con origen (`manual:<email>` o `artisan`), fecha, moneda, valor y resultado o error.

El botón `Actualizar` y el Scheduler llaman al mismo `BcbTipoCambioService::actualizar()`.

### Automatización diaria (Scheduler)

`routes/console.php`:

```php
Schedule::command('tipo-cambio:actualizar')
    ->dailyAt('20:05')
    ->timezone('America/La_Paz')
    ->withoutOverlapping(10)
    ->appendOutputTo(storage_path('logs/tipo_cambio_scheduler.log'));
```

- La zona horaria se fija solo en esta tarea. `APP_TIMEZONE` sigue en UTC, así que `php artisan schedule:list` muestra `5 0 * * *`. Con `php artisan schedule:list --timezone=America/La_Paz` se ve `5 20 * * *`.
- Si a las 20:05 el BCB todavía no publicó el nuevo dato, la portada sigue mostrando el vigente. Se registra "sin cambios" o el error en el log y no se inserta nada. Se puede repetir con el botón o con `php artisan tipo-cambio:actualizar`.

**El Scheduler no corre solo.** Hay que ejecutar `php artisan schedule:run` cada minuto. **En este equipo no se configuró ninguna tarea programada.**

Producción en Linux (crontab del usuario del servidor web):

```
* * * * * cd /ruta/al/proyecto && php artisan schedule:run >> /dev/null 2>&1
```

Windows, con el Programador de tareas (desde una consola, como el usuario que ejecutará la tarea):

```
schtasks /Create /TN "Cod_Sistema Laravel Scheduler" /SC MINUTE /MO 1 /TR "cmd /c cd /d C:\Personal\Cod_Sistema && C:\xampp\php\php.exe artisan schedule:run >> storage\logs\schedule_run.log 2>&1"
```

O desde la interfaz de `taskschd.msc`: Crear tarea → Desencadenador "Diariamente", repetir cada 1 minuto durante 1 día → Acción "Iniciar un programa": programa `C:\xampp\php\php.exe`, argumentos `artisan schedule:run`, iniciar en `C:\Personal\Cod_Sistema`. Marcar "Ejecutar tanto si el usuario inició sesión como si no" para que funcione sin sesión abierta. El equipo debe estar encendido a las 20:05.

Para comprobarla: `schtasks /Query /TN "Cod_Sistema Laravel Scheduler" /V` y revisar `storage/logs/tipo_cambio_scheduler.log` después de las 20:05.

En desarrollo se puede usar `php artisan schedule:work` (en primer plano, se detiene al cerrar la consola).

### Verificaciones

- `php artisan test`: 57 pruebas y 163 aserciones, todas en verde, sobre `cambio_test`. Cubren permisos (invitado, usuario sin rol, Administrador y Gerente), orden descendente, paginación, actualización, idempotencia, corrección de valor, vigencia del día siguiente, HTTP 503, error de conexión, estructura cambiada, valor no numérico, lock concurrente, comando y programación del Scheduler. El parser se probó contra la portada real guardada.
- BCB real, 22/10/2026 ~15:41: `php artisan tipo-cambio:actualizar` registró `USD 11.75000` con vigencia 2026-10-22. La segunda ejecución respondió "sin cambios" (sigue habiendo una sola fila).
- Navegador: login como `gerente@sistema.local` → dashboard con el último tipo de cambio → `Administración → Tipo Cambio` → botón `Actualizar` (se desactiva mientras consulta) → mensaje de éxito sin duplicar.
- A 390 px: el sidebar se oculta, la tabla se desplaza dentro de su contenedor y la página no desborda.

### Limitaciones reales

- No hay API oficial: si el BCB cambia el HTML de la tarjeta (clases `is-tc-oficial`, `bcb-kpi2-asof`, `bcb-kpi2-sub`, `bcb-tco-num`), la actualización falla con un error explícito y no guarda nada. Habría que ajustar los selectores de `BcbTipoCambioService`.
- La tarjeta solo publica el tipo de cambio oficial del dólar estadounidense. No hay otras monedas.
- La hora de publicación (20:00) viene del requerimiento. No se pudo confirmar en el sitio ni observar el cambio al día siguiente durante esta sesión (la revisión fue a las 15:16). El servicio guarda la fecha que el BCB declare, sea cual sea.
- El rango de fechas aceptado (−30/+7 días) es una salvaguarda propia, no una regla del BCB.
- Las páginas de perfil y recuperación de contraseña de Breeze conservan su estilo Tailwind (compilado con Vite en `public/build`). Solo el login, el dashboard y el módulo usan SB Admin.
