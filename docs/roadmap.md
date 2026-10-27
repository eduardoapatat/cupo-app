# Hoja de ruta

Este archivo es la fuente de verdad del avance de Cupo. Se actualiza en el mismo commit que cierra cada punto de control.

## Cómo trabajamos

- **Una fase a la vez.** Antes de cada fase se detallan sus archivos y pasos, y se aprueba el plan.
- Cada fase se divide en **2 o 3 puntos de control**. Al cerrar uno se informa:
    - qué se hizo (los commits);
    - cómo probarlo (URLs, cuentas, comandos);
    - el resultado de los tests y las revisiones.
- No se avanza al siguiente punto de control sin un "sigue" explícito.
- **Definición de terminado** de una fase:
    - `composer ci:check` en verde: oxlint/oxfmt, `tsc`, Pint, PHPStan nivel 7 y Pest contra PostgreSQL;
    - la documentación de `docs/` actualizada;
    - commits pequeños en español (Conventional Commits).

**Leyenda:** `[x]` hecho · `[ ]` pendiente · _(en curso)_ marca el punto de control actual.

## Fase 0: preparar el proyecto ✅

- [x] **0.1 "Hola, Cupo"**:
    - el proyecto creado con el starter kit de React, PostgreSQL 18 y Mailpit en Docker, Node 24 fijado y el registro con correo de verificación funcionando;
    - tests contra PostgreSQL;
    - validación, correos y pantallas en español, y páginas de error con avisos de sesión y de conexión;
    - `config/studio.php` con las reglas por defecto;
    - lint y tipos en verde.
- [x] **0.2 "Producto, docs y CI"**:
    - `PRODUCT.md`, `DESIGN.md` y `docs/roles-y-permisos.md`;
    - este roadmap, `docs/architecture.md`, los ADRs 0001 y 0002 y el README;
    - guías de IA versionadas;
    - el workflow de GitHub Actions con pnpm y PostgreSQL.
- [x] **0.3 "Roles, menú e Inicio"**:
    - `studios` y `memberships` con sus garantías en la base: un rol válido, una membresía por persona y estudio, y una sola dueña por estudio;
    - los enums `StudioRole` y `StudioPermission`, un Gate por permiso y 404 para quien no es miembro;
    - el menú de los 4 roles con «Pronto» y el Inicio de cada rol;
    - usuarios demo por rol en Estudio Flow;
    - tests de la matriz completa (una fila por permiso y rol) y del menú de cada rol;
    - ADR 0003.

## Fase 1: estudios, equipo y alumnas

- [ ] Crear el estudio (self-service): quien se registra crea su estudio (nombre, comuna y enlace) y queda como su dueña. Las reglas se copian de `config/studio.php`.
- [ ] Invitar a recepción e instructores por correo, con URLs firmadas y correos en cola. Quitar a alguien del equipo.
- [ ] El enlace de inscripción del estudio: la alumna crea su cuenta y declara ser mayor de 18.
- [ ] Alumnas sin app: recepción las inscribe con nombre y teléfono (correo opcional) y ellas pueden activar su cuenta después con su correo.
- [ ] El selector de estudio, solo para quien pertenece a más de uno, con el último usado recordado en la sesión.
- [ ] Policies con 404 para recursos de otro estudio.
- [ ] Traer `resources/js/lib/form.ts` de PichangApp con el primer formulario (errores accesibles y foco en el primer error).
- [ ] Decidir qué pasa si alguien del equipo también toma clases en su estudio (hoy, un solo rol por estudio).

## Fase 2: horario, sesiones y reservas

- [ ] Tipos de clase y horario semanal (día, hora, cupo e instructor).
- [ ] Generación de sesiones con el scheduler, y la agenda.
- [ ] Reservas con cupos garantizados bajo concurrencia real (transacción con bloqueo o restricción en la base), con un test de varios procesos a la vez.
- [ ] Lista de espera: «Inscribirme si se libera un lugar». El cupo liberado se reserva solo a la primera de la lista mientras falten al menos `waitlist_cutoff_hours` (por defecto, las 6 h de la ventana de cancelación). Más cerca del inicio, queda libre para cualquiera.
- [ ] Reemplazo de instructor, cancelación de una sesión (avisa y devuelve clases) y asistencia.
- [ ] Las reglas del estudio editables por la dueña: ventana de cancelación (6 h), cierre de reservas (1 h antes) y corte de la lista de espera.
- [ ] Policies de «solo las suyas» para el instructor y la alumna.

## Fase 3: planes, créditos, pagos y caja

- [ ] Planes (precio, créditos, vigencia y máximo diario; el ilimitado parte en 1 clase al día), compra de plan y movimientos de créditos.
- [ ] Pagos en el mesón (efectivo, tarjeta con POS externo y transferencia), registrados en la caja de quien cobra.
- [ ] Transferencias con comprobante (subida de archivos) que valida recepción o la dueña.
- [ ] Caja por persona y día: apertura, cierre con totales por medio y la diferencia del efectivo. Anulaciones con motivo; una caja cerrada solo la toca la dueña.
- [ ] Máquina de estados del pago (pendiente → por validar → aprobado, rechazado o anulado) e historial.
- [ ] Vencimiento de planes con el scheduler.
- [ ] Al cierre de la fase, Webpay Plus en sandbox, con confirmación idempotente.

## Fase 4: automatizaciones

- [ ] Recordatorios de clase, avisos de la lista de espera y aviso de vencimiento 3 días antes.
- [ ] Resumen diario para la dueña.
- [ ] Eventos, listeners, notificaciones en cola y jobs programados con `withoutOverlapping()`.

## Fase 5: API REST

- [ ] Sanctum, los endpoints de la alumna, API Resources, paginación y rate limiting.
- [ ] Documentación OpenAPI.

## Fase 6: calidad

- [ ] Arch tests de Pest y cobertura en CI.
- [ ] Licencia MIT.

## Fase 7: demo en línea

- [ ] Seeders de la demo y cuentas con acceso de un clic.
- [ ] Modo demo y reinicio cada noche.
- [ ] Correos reales, despliegue y el README final.
- [ ] Repositorio público (con autorización explícita) e insignia del CI en el README. Hasta aquí, el repositorio es privado.
