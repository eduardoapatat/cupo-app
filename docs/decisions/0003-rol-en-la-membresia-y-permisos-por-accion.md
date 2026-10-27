---
status: accepted
date: 2026-10-27
---

# Rol en la membresía y permisos por acción, con enums y Gates, sin spatie/laravel-permission

## Contexto y problema

Cupo tiene cuatro roles (dueña, recepción, instructor y alumna), y una persona puede tener roles distintos en estudios distintos. La matriz de `docs/roles-y-permisos.md` define 22 acciones y qué rol puede hacer cada una. En PichangApp el rol vivía en el usuario y había que ajustarlo a mano (ADR 0003 de ese proyecto). ¿Dónde guardamos el rol y cómo programamos la matriz para que sumar un rol no obligue a revisar condiciones por todo el código?

## Factores de decisión

- El permiso depende de **en qué estudio** actúa la persona, no solo de quién es.
- Los roles y las acciones son fijos: no se editan desde la aplicación.
- Sumar un rol debe ser «agregar una columna», sin buscar `if rol === ...` repartidos por el código.
- La matriz tiene que poder probarse fila por fila.
- Preferir lo que trae Laravel (Gates, Policies) antes que sumar dependencias.
- La base de datos debe rechazar un rol inválido, una membresía repetida y una segunda dueña.

## Opciones consideradas

1. **El rol en la tabla `memberships` (persona × estudio), el enum `StudioPermission` con un caso por acción y un Gate por permiso.**
2. El paquete `spatie/laravel-permission` con equipos (`teams`), con roles y permisos en tablas.
3. El rol en `users` y el estudio en `users.studio_id`.

## Decisión

Elegimos la **opción 1**:

- **`memberships`** guarda `studio_id`, `user_id` y `role`. `$table->enum()` genera un `CHECK` en PostgreSQL, `unique(studio_id, user_id)` deja un solo rol por persona y estudio, y un índice único parcial (`WHERE role = 'owner'`) deja una sola dueña por estudio.
- **`App\Enums\StudioRole`** tiene un caso por rol. `permissions()` arma la columna de la matriz de cada rol y `menu()` su menú.
- **`App\Enums\StudioPermission`** tiene un caso por fila de la matriz. Su valor (`plans.manage`, `payments.void_closed`, ...) es el nombre de un Gate que se define en un ciclo en `AppServiceProvider`, con la firma `(User, Studio)`: `Gate::allows('plans.manage', $studio)`.
- **`StudioPolicy::view`** decide quién entra a un estudio, y responde `Response::denyAsNotFound()` a quien no es miembro. Las rutas del estudio la aplican con `->can('view', 'studio')`.
- **`User::roleIn(Studio)`** lee las membresías ya cargadas, así varias verificaciones en una petición hacen una sola consulta.
- **No hay `Gate::before`**: no existe un rol que lo pueda todo.
- Lo de «solo las suyas» (el instructor con sus sesiones, la alumna con sus reservas) no va en el Gate, sino en la Policy de cada recurso, cuando ese recurso exista _(Fases 2 y 3)_.

### Consecuencias

- Bueno: sumar un rol es sumar un caso a `StudioRole` con sus permisos y su menú, más una migración que cambie el `CHECK`. Ninguna otra parte del código pregunta por el rol.
- Bueno: la matriz se prueba completa con un dataset de Pest (22 permisos × 4 roles), más un test que falla si se agrega un permiso sin su fila.
- Bueno: cero dependencias nuevas, y código que cualquier dev Laravel reconoce (enum cast, Gates, Policies, `can` en las rutas).
- Bueno: el frontend no decide nada por rol. Recibe el menú ya armado y solo lo dibuja.
- Malo: los permisos no se configuran desde la interfaz. Lo aceptamos porque el producto no lo necesita y la matriz es parte del diseño.
- Malo: una persona del equipo que además toma clases en su estudio necesitaría dos roles. Se decide en la Fase 1.

### Confirmación

- `tests/Feature/Authorization/PermissionMatrixTest.php` recorre la matriz entera, verifica que cada permiso tenga su fila y que nadie tenga permisos en un estudio ajeno.
- `tests/Feature/Policies/StudioPolicyTest.php` y `tests/Feature/Http/Controllers/Studio/HomeControllerTest.php` verifican el 404 para quien no es miembro.
- `tests/Feature/Models/MembershipTest.php` verifica que la base rechaza un rol inválido (SQLSTATE 23514), una membresía repetida y una segunda dueña (23505).
- `tests/Feature/Navigation/StudioMenuTest.php` verifica el menú de cada rol y que cada ítem esté permitido a su rol.

## Pros y contras de las opciones

### Membresía con enums y Gates

- Bueno: nativo, simple, validado en la base y probado fila por fila.
- Malo: los roles y los permisos quedan fijos en el código.

### spatie/laravel-permission con equipos

- Bueno: roles y permisos editables, y muy conocido.
- Malo: suma 5 tablas y una dependencia para 4 roles fijos, y la matriz quedaría en datos en vez de código revisable.

### Rol y estudio en `users`

- Bueno: es lo más simple.
- Malo: una persona no podría ser dueña de un estudio y alumna de otro, que es justo la lección de PichangApp.
