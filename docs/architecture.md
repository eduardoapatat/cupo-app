# Arquitectura

Este documento es un mapa de Cupo para quien llega por primera vez al código, sea una persona o una IA. Describe lo que **existe hoy**. Lo que aún no está construido aparece marcado con su fase (ver `docs/roadmap.md`). Se actualiza en el mismo commit que cambia el código.

## Vista general

Cupo es una app ficticia para estudios pequeños de clases grupales para adultos (pilates, yoga, funcional, baile). Cada estudio la usa por su cuenta: la dueña lo administra, recepción atiende el mesón, los instructores ven sus clases y las alumnas reservan y pagan. Es **una sola app con login**: no hay sitio público, catálogo entre estudios ni super admin de la plataforma.

```mermaid
flowchart LR
    duena([Dueña])
    recepcion([Recepción])
    instructor([Instructor])
    alumna([Alumna])
    app[Cupo<br/>Laravel + Inertia/React]
    db[(PostgreSQL 18)]
    mail[Correo<br/>Mailpit en local]
    webpay[Webpay Plus<br/>Fase 3]

    duena -- decide planes, horario y reglas --> app
    recepcion -- inscribe, cobra y reserva --> app
    instructor -- ve sus clases y marca asistencia --> app
    alumna -- reserva y paga --> app
    app --> db
    app -- verificación, invitaciones y avisos --> mail
    app -.-> webpay
```

Los roles y lo que puede hacer cada uno están en `docs/roles-y-permisos.md`.

## Stack

| Capa                | Tecnología                                                                              |
| ------------------- | --------------------------------------------------------------------------------------- |
| Backend             | Laravel 13 (PHP 8.5) y Fortify para la autenticación (incluye 2FA y passkeys)           |
| Frontend            | Inertia 3, React 19, TypeScript, Tailwind 4, shadcn/ui y Wayfinder (rutas tipadas)      |
| Base de datos       | PostgreSQL 18. En local corre en Docker (`compose.yaml`, a partir de los stubs de Sail) |
| Correo local        | Mailpit (http://localhost:8025)                                                         |
| Base de datos local | Adminer (http://localhost:8080), opcional: `docker compose up -d adminer`               |
| Calidad             | Pest, Pint, PHPStan/Larastan nivel 7, oxlint/oxfmt (`vp check`) y `tsc`                 |
| Herramientas        | pnpm, Node 24 (`.node-version`), Laravel Boost (MCP y guías para IAs)                   |

## Mapa del código

| Ruta                                           | Qué contiene                                                                                                                                                                                               |
| ---------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `app/Actions/Fortify/`                         | Creación de usuarios y restablecimiento de contraseña, invocados por Fortify                                                                                                                               |
| `app/Concerns/`                                | Reglas de validación compartidas para el perfil y la contraseña                                                                                                                                            |
| `app/Enums/`                                   | `StudioRole` (los 4 roles, con sus permisos y su menú) y `StudioPermission` (una acción por fila de la matriz)                                                                                             |
| `app/Navigation/`                              | `MenuItem`: los ítems del menú, con su etiqueta, el permiso que exigen y su ruta (o «Pronto»)                                                                                                              |
| `app/Http/Controllers/`                        | `DashboardController` (`/inicio`), `Studio/` (las pantallas de cada estudio) y `Settings/` (perfil y seguridad)                                                                                            |
| `app/Http/Requests/Settings/`                  | Validación de los formularios de Configuración                                                                                                                                                             |
| `app/Http/Middleware/`                         | `HandleInertiaRequests` (props compartidas, incluido el estudio con su menú), `RememberCurrentStudio` (el estudio actual) y `HandleAppearance` (tema)                                                      |
| `app/Models/`                                  | `User`, `Studio` y `Membership`, con casts a enums y relaciones                                                                                                                                            |
| `app/Policies/`                                | `StudioPolicy`, que Laravel descubre sola: solo los miembros entran a un estudio                                                                                                                           |
| `app/Providers/`                               | `AppServiceProvider` (fechas, contraseñas, un Gate por permiso, verbos en español y páginas de error) y `FortifyServiceProvider` (vistas)                                                                  |
| `config/studio.php`                            | La zona horaria y las reglas por defecto de cada estudio nuevo                                                                                                                                             |
| `database/`                                    | Migraciones (con las garantías de PostgreSQL), factories con estados y el seeder demo (`DemoStudioSeeder`)                                                                                                 |
| `lang/es/`, `lang/es.json`                     | Traducciones del backend: validación, autenticación y correos                                                                                                                                              |
| `resources/js/pages/`                          | Una página Inertia por pantalla: `studios/` (Inicio de cada rol y el estado sin estudio), `auth/`, `settings/`, `error.tsx` y `welcome.tsx`                                                                |
| `resources/js/layouts/`, `components/`         | Layouts y componentes. `studio-nav.tsx` dibuja el menú del estudio; `components/ui/` son los de shadcn, sin modificar                                                                                      |
| `resources/js/actions/`, `routes/`             | Funciones de Wayfinder **generadas** (están en `.gitignore`); no se editan a mano                                                                                                                          |
| `routes/web.php`, `studio.php`, `settings.php` | Rutas web, del estudio y de Configuración. Las de autenticación las registra Fortify                                                                                                                       |
| `tests/Feature/`                               | Tests contra PostgreSQL: la matriz de permisos (`Authorization/`), el menú (`Navigation/`), la base (`Models/`), las policies, las pantallas (`Http/`), el seeder, la autenticación y las páginas de error |
| `docs/`                                        | Esta documentación, la hoja de ruta, los roles y permisos, y las decisiones (`docs/decisions/`)                                                                                                            |

Lo que viene, según el plan (cada fase lo agrega a esta tabla):

- **Fase 1:** las acciones para crear el estudio, invitar al equipo e inscribir alumnas (`app/Actions/`).
- **Fases 2 y 3:** las acciones de reservas, créditos, pagos y caja, y las Policies de cada recurso.

## Invariantes

Reglas que el código siempre debe cumplir:

- **Tiempo:** todo se guarda en UTC (`timestamptz`). Se muestra e interpreta en `config('studio.timezone')` (`America/Santiago`), usando el desfase de cada fecha.
- **Dinero:** siempre en CLP, como entero. Nunca `float`.
- **Reglas del negocio:** los valores por defecto viven en `config/studio.php`. Una vez creado el estudio, mandan sus propias reglas. Nunca se repiten los números en el código.
- **Roles:** el rol vive en la membresía (persona × estudio), nunca en el usuario. Los permisos se arman por acción, sin `if rol === ...`. Ver `docs/roles-y-permisos.md`.
- **Aislamiento entre estudios:** un recurso de otro estudio responde 404, para no revelar que existe.
- **Idioma:** el código, los comentarios, los nombres de tablas y la API van en inglés. Todo lo que ve el usuario (textos, URLs del producto, correos y validación) va en español. Ver `docs/decisions/0001-interfaz-solo-en-espanol.md`.
- **Secretos:** `.env` no se versiona. `.env.example` solo trae valores de desarrollo.

## Temas transversales

### Idioma

La interfaz es solo en español:

- Los textos de React se escriben en español directamente en los componentes.
- El backend usa `lang/es` y `lang/es.json`, y `APP_LOCALE=es`.
- Las URLs de Fortify (`/login`, `/register`, `/settings/...`) quedan en inglés.
- Las URLs propias del producto van en español (`/inicio`, `/estudios/{slug}/...`). Las rutas resource usan `/crear` y `/editar` gracias a `Route::resourceVerbs()` en `AppServiceProvider`.

### Tests

- Pest corre contra PostgreSQL, en la base `testing` del mismo contenedor (`phpunit.xml`), nunca contra SQLite. Así se prueban de verdad las garantías de la base: `CHECK`, índices únicos parciales y, en la Fase 2, el cupo bajo concurrencia.
- `composer test` corre Pint, PHPStan y Pest.
- `composer ci:check` agrega oxlint/oxfmt y `tsc`. Es lo que corre GitHub Actions.

### Reglas por defecto

`config/studio.php` reúne la zona horaria y las reglas con que parte cada estudio nuevo: cancelación gratis hasta 6 h antes, reservas hasta 60 minutos antes, lista de espera con el mismo corte que la cancelación, 1 clase al día en los planes ilimitados y aviso 3 días antes del vencimiento. Cada valor se puede cambiar con una variable `STUDIO_*` en `.env`. Al crear un estudio, las reglas se copian a él _(Fase 1)_ y la dueña las puede cambiar _(Fase 2)_.

### Páginas de error

Los 403, 404, 500 y 503 de una visita renderizan la página `error`, en español. Una sesión expirada (419) vuelve atrás con un aviso, y un corte de conexión muestra un toast. Las peticiones JSON conservan sus errores JSON. Ver `AppServiceProvider::configureErrorPages()` y `resources/js/hooks/use-network-error-toast.ts`.

## Datos personales

Cupo guarda lo mínimo para operar (minimización de datos desde el día 1):

| Entidad                                        | Datos                                                                                                         | Nota                                                                                                 |
| ---------------------------------------------- | ------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------- |
| Persona                                        | Nombre, teléfono y correo (opcional si la inscribe recepción)                                                 | Sin RUT, fecha de nacimiento ni datos de salud. Al unirse declara ser mayor de 18                    |
| Estudio                                        | Nombre, comuna, enlace de inscripción y reglas                                                                | Hora de Santiago y CLP como enteros                                                                  |
| Membresía                                      | Persona, estudio y rol                                                                                        | El rol vive aquí                                                                                     |
| Tipo de clase y horario semanal                | Nombre, duración, cupo, día, hora e instructor                                                                | Del horario se generan las sesiones (scheduler)                                                      |
| Sesión                                         | Fecha y hora (UTC), cupo, instructor y estado                                                                 | La clase concreta, por ejemplo la del martes a las 19:00                                             |
| Reserva                                        | Sesión, alumna, estado (confirmada, cancelada a tiempo, cancelada tarde, asistió, no asistió) y quién la hizo | Una reserva activa por alumna y sesión                                                               |
| Plan, compra de plan y movimientos de créditos | Precio, créditos, vigencia, máximo diario, saldo y vencimiento                                                | Cada reserva descuenta 1 crédito y cancelar a tiempo lo devuelve. Los movimientos quedan registrados |
| Pago                                           | Monto, medio, comprobante, estado, quién lo registró y la caja                                                | Pendiente → por validar → aprobado, rechazado o anulado                                              |
| Caja                                           | Quién, apertura y cierre, totales por medio y efectivo declarado frente al esperado                           | Cerrada no se modifica; después solo la dueña anula                                                  |

**Por qué no se guardan datos de salud ni de menores.** La Ley 21.719 de protección de datos personales tiene su vigencia fijada para el 1 de diciembre de 2026, aunque hay un proyecto para postergarla a 2027. Esa ley exige consentimiento expreso para los datos sensibles, como los de salud, y el de su representante para los de menores de 16 años. Cupo no los necesita para operar, así que no los pide ni los guarda.

## Modelo de datos

```mermaid
erDiagram
    users ||--o{ memberships : "pertenece a"
    studios ||--o{ memberships : tiene

    users {
        bigint id PK
        varchar name
        varchar email UK
    }
    studios {
        bigint id PK
        varchar name
        varchar slug UK "su URL: /estudios/{slug}"
    }
    memberships {
        bigint id PK
        bigint studio_id FK
        bigint user_id FK
        varchar role "enum: owner, front_desk, instructor, student"
    }
```

Las garantías viven en PostgreSQL, no solo en PHP:

| Garantía                                                                                                   | Dónde         | Qué evita                                                     |
| ---------------------------------------------------------------------------------------------------------- | ------------- | ------------------------------------------------------------- |
| `CHECK` del rol (generado por `$table->enum()`)                                                            | `memberships` | Un rol desconocido (SQLSTATE `23514`)                         |
| `UNIQUE (studio_id, user_id)`                                                                              | `memberships` | Dos roles para la misma persona en el mismo estudio (`23505`) |
| `UNIQUE (studio_id) WHERE role = 'owner'`, con `DB::statement` porque Blueprint no tiene índices parciales | `memberships` | Una segunda dueña en el estudio (`23505`)                     |
| Claves foráneas con `ON DELETE CASCADE`                                                                    | `memberships` | Membresías de un estudio o de una persona que ya no existen   |

## Permisos y navegación

### Cómo se autoriza

Todo usa mecanismos nativos de Laravel, sin middleware propio de roles ni super admin. Ver `docs/decisions/0003-rol-en-la-membresia-y-permisos-por-accion.md`.

- **Entrar a un estudio:** `StudioPolicy::view`, aplicada al grupo de rutas con `->can('view', 'studio')`. A quien no es miembro le responde **404** (`Response::denyAsNotFound()`), igual que a un estudio que no existe.
- **Cada acción:** un Gate por caso de `StudioPermission`, definido en `AppServiceProvider::configureAuthorization()`, que mira el rol de la persona en ese estudio: `Gate::allows('payments.review', $studio)`. Las pantallas de las próximas fases los aplican en sus rutas o con `#[Authorize]`.
- **Solo las suyas:** el instructor con sus sesiones y la alumna con sus reservas los filtra la Policy de cada recurso _(Fases 2 y 3)_.

### Rutas

| URL                | Qué es                                                                                                                                   |
| ------------------ | ---------------------------------------------------------------------------------------------------------------------------------------- |
| `/inicio`          | El `home` de Fortify. Lleva al Inicio del estudio actual o, a quien no pertenece a ninguno, muestra «Todavía no perteneces a un estudio» |
| `/estudios/{slug}` | El Inicio del estudio, con los bloques del rol de la persona                                                                             |

### El estudio actual y el menú

- **Siempre hay un estudio actual:** el de la página o, fuera de un estudio (por ejemplo en Configuración), el último que la persona abrió. Lo guarda en la sesión el middleware `RememberCurrentStudio`. Si ya no pertenece a ese estudio, se usa el primero por nombre. El selector para quien pertenece a más de uno llega en la Fase 1.
- **Props:** `HandleInertiaRequests` comparte `studio` (nombre, slug, rol y menú). Nunca comparte un estudio al que la persona no pertenece, ni siquiera en el 404 de uno ajeno, y las membresías no viajan dentro de `auth.user`.
- **El menú lo arma el servidor:** `StudioRole::menu()` da los ítems y su orden, y `MenuItem` su etiqueta y su ruta. El frontend (`studio-nav.tsx`) solo elige el ícono de cada ítem. Los ítems sin ruta se ven atenuados con «Pronto».

### Usuarios demo

`php artisan migrate --seed` crea Estudio Flow (`/estudios/estudio-flow`) con una cuenta por rol, todas con la contraseña `password`:

| Persona         | Correo                 | Rol        |
| --------------- | ---------------------- | ---------- |
| Carla Rojas     | `carla@example.com`    | Dueña      |
| Camila Soto     | `camila@example.com`   | Recepción  |
| Fernanda Díaz   | `fernanda@example.com` | Instructor |
| Diego Herrera   | `diego@example.com`    | Instructor |
| Javiera Morales | `javiera@example.com`  | Alumna     |

## Reservas _(Fase 2)_

Pendiente: cómo se generan las sesiones, cómo se garantiza el cupo bajo concurrencia y cómo funciona la lista de espera.

## Créditos, pagos y caja _(Fase 3)_

Pendiente: los movimientos de créditos, los estados del pago, la caja y las anulaciones, y Webpay Plus.

## Automatizaciones y API _(Fases 4 y 5)_

Pendiente: los eventos, las colas, el scheduler y la API.
