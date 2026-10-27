# Roles y permisos

Este documento define qué puede hacer cada rol en un estudio, qué ve al entrar y qué menú tiene. Es la fuente de verdad de los tests de permisos: cada fila de la matriz es un caso del dataset de `tests/Feature/Authorization/PermissionMatrixTest.php`, y el menú de cada rol se prueba en `tests/Feature/Navigation/StudioMenuTest.php`. Si cambia una fila, cambia el test en el mismo commit.

## Principios

Cada permiso sale de uno de estos principios:

1. Las **decisiones del negocio** (precios, horario, reglas, personal y reportes) son de la **dueña**.
2. La **operación del día a día** (atender, inscribir, vender, cobrar y reservar) es de **recepción**, que sigue las reglas pero no las cambia.
3. **Separación de funciones:** quien recibe la plata no puede borrar un cobro una vez cerrada su caja.
4. **Todo deja rastro:** quién, cuándo y con qué motivo.
5. **Mínimo necesario:** cada rol ve solo lo que necesita.
6. La **alumna** solo se gestiona a sí misma.

## El rol vive en la membresía

- El rol es de la **membresía** (persona × estudio), no del usuario. Una persona puede ser dueña de un estudio y alumna de otro.
- Una persona tiene **un solo rol por estudio**, y cada estudio tiene **una sola dueña**. La base de datos garantiza las dos cosas.
- Si una persona pertenece a más de un estudio, aparece el selector de estudio; si no, no.
- No hay super admin de la plataforma: nadie ve los datos de un estudio sin ser miembro. A quien no es miembro, el estudio le responde **404**.

## Los cuatro roles

| Rol            | Código       | Quién                                                                | Cómo llega                                                                                                                                                       |
| -------------- | ------------ | -------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Dueña**      | `owner`      | Quien crea el estudio (una por estudio). También puede dictar clases | Se registra y crea su estudio _(Fase 1)_                                                                                                                         |
| **Recepción**  | `front_desk` | Quien atiende el mesón: inscribe, cobra y reserva por las alumnas    | Invitación de la dueña por correo _(Fase 1)_                                                                                                                     |
| **Instructor** | `instructor` | Quien da clases y cobra por clase dictada. No maneja plata           | Invitación de la dueña por correo _(Fase 1)_                                                                                                                     |
| **Alumna**     | `student`    | Quien toma clases y paga                                             | Entra por el enlace del estudio y crea su cuenta, o recepción la inscribe solo con nombre y teléfono (sin app; puede activarla después con su correo) _(Fase 1)_ |

### Qué ve al entrar

Cada rol llega al **Inicio** de su estudio. En la Fase 0 los bloques ya están, cada uno con la fase en que se llena.

| Rol        | Al entrar ve                                                                                                                                        |
| ---------- | --------------------------------------------------------------------------------------------------------------------------------------------------- |
| Dueña      | Ocupación de hoy _(Fase 2)_, alertas: comprobantes pendientes, cajas con diferencia y anulaciones del día _(Fase 3)_, e ingresos del mes _(Fase 3)_ |
| Recepción  | Clases de hoy _(Fase 2)_, alumnas que llegan _(Fase 2)_, comprobantes por validar _(Fase 3)_ y el estado de su caja _(Fase 3)_                      |
| Instructor | Sus clases de hoy, con quiénes vienen _(Fase 2)_                                                                                                    |
| Alumna     | Su próxima clase _(Fase 2)_, cuántas clases le quedan _(Fase 3)_ y cuándo vence su plan _(Fase 3)_                                                  |

### Menú

Todos los ítems existen desde la Fase 0. Los que aún no funcionan se ven atenuados con «Pronto». Cada ítem exige un permiso de la matriz, y un test verifica que cada rol tenga el permiso de cada ítem de su menú.

| Ítem         | Permiso que exige | Dueña | Recepción | Instructor | Alumna | Llega en |
| ------------ | ----------------- | :---: | :-------: | :--------: | :----: | -------- |
| Inicio       | (ser miembro)     |   1   |     1     |     1      |   1    | Fase 0   |
| Agenda       | `bookings.manage` |   2   |     2     |            |        | Fase 2   |
| Alumnas      | `students.manage` |   3   |     3     |            |        | Fase 1   |
| Vender plan  | `plans.sell`      |   4   |     4     |            |        | Fase 3   |
| Pagos        | `payments.review` |   5   |     5     |            |        | Fase 3   |
| Caja         | `cash.operate`    |   6   |     6     |            |        | Fase 3   |
| Clases       | `schedule.manage` |   7   |           |            |        | Fase 2   |
| Planes       | `plans.manage`    |   8   |           |            |        | Fase 3   |
| Equipo       | `staff.manage`    |   9   |           |            |        | Fase 1   |
| Reportes     | `reports.view`    |  10   |           |            |        | Fase 3   |
| Estudio      | `studio.manage`   |  11   |           |            |        | Fase 1   |
| Mis clases   | `sessions.view`   |       |           |     2      |        | Fase 2   |
| Reservar     | `self.book`       |       |           |            |   2    | Fase 2   |
| Mis reservas | `self.book`       |       |           |            |   3    | Fase 2   |
| Mi plan      | `self.view`       |       |           |            |   4    | Fase 3   |

El número es la posición del ítem en el menú de ese rol. La dueña ve sus propias sesiones en la Agenda, por eso no tiene «Mis clases».

## Matriz de permisos

✅ permitido · ❌ denegado · — no aplica: la acción es de autoservicio, así que se trata como denegada.

| Área         | Acción                                                   | Código                  | Dueña | Recepción |    Instructor     |      Alumna       |
| ------------ | -------------------------------------------------------- | ----------------------- | :---: | :-------: | :---------------: | :---------------: |
| Negocio      | Planes y precios                                         | `plans.manage`          |  ✅   |    ❌     |        ❌         |        ❌         |
|              | Horario semanal y tipos de clase                         | `schedule.manage`       |  ✅   |    ❌     |        ❌         |        ❌         |
|              | Reglas (ventana de cancelación, anticipación)            | `rules.manage`          |  ✅   |    ❌     |        ❌         |        ❌         |
|              | Reportes e ingresos                                      | `reports.view`          |  ✅   |    ❌     |        ❌         |        ❌         |
|              | Datos del estudio y enlace de inscripción                | `studio.manage`         |  ✅   |    ❌     |        ❌         |        ❌         |
| Personal     | Invitar o quitar recepción e instructores                | `staff.manage`          |  ✅   |    ❌     |        ❌         |        ❌         |
| Alumnas      | Inscribir en el local y editar contacto                  | `students.manage`       |  ✅   |    ✅     |        ❌         |        ❌         |
|              | Ver teléfono y correo de una alumna                      | `students.view_contact` |  ✅   |    ✅     |        ❌         |        ❌         |
| Cobros       | Vender un plan a precio de lista                         | `plans.sell`            |  ✅   |    ✅     |        ❌         |        ❌         |
|              | Descuentos o regalar clases                              | `plans.discount`        |  ✅   |    ❌     |        ❌         |        ❌         |
|              | Validar o rechazar un comprobante                        | `payments.review`       |  ✅   |    ✅     |        ❌         |        ❌         |
|              | Anular un pago de la caja abierta de hoy (con motivo)    | `payments.void_open`    |  ✅   |    ✅     |        ❌         |        ❌         |
|              | Anular un pago ya cerrado o devolver plata               | `payments.void_closed`  |  ✅   |    ❌     |        ❌         |        ❌         |
|              | Abrir y cerrar la caja del día                           | `cash.operate`          |  ✅   |    ✅     |        ❌         |        ❌         |
| Clases       | Reservar o cancelar por una alumna, dentro de las reglas | `bookings.manage`       |  ✅   |    ✅     |        ❌         |        ❌         |
|              | Saltarse la regla de cancelación y devolver la clase     | `bookings.override`     |  ✅   |    ❌     |        ❌         |        ❌         |
|              | Poner un reemplazo si un instructor se enferma           | `sessions.substitute`   |  ✅   |    ✅     |        ❌         |        ❌         |
|              | Cancelar una sesión (avisa y devuelve clases)            | `sessions.cancel`       |  ✅   |    ✅     |        ❌         |        ❌         |
|              | Ver clases y asistentes (solo nombres)                   | `sessions.view`         |  ✅   |    ✅     | ✅ solo las suyas |        ❌         |
|              | Marcar asistencia                                        | `attendance.mark`       |  ✅   |    ✅     | ✅ solo las suyas |        ❌         |
| Autoservicio | Comprar un plan en línea (Webpay) o subir comprobante    | `self.buy_plan`         |   —   |     —     |         —         |        ✅         |
|              | Reservar, cancelar y anotarse en lista de espera         | `self.book`             |   —   |     —     |         —         |   ✅ las suyas    |
|              | Ver sus clases restantes, reservas y pagos               | `self.view`             |   —   |     —     |         —         | ✅ solo los suyos |

## Cómo se aplica en el código

- **Por acción, no por rol:** cada código de la matriz es un caso del enum `StudioPermission`, y `StudioRole::permissions()` arma cada columna. Se registra un Gate por permiso, con la firma `(User, Studio)`. Así, sumar un rol es sumar un caso al enum con sus permisos y su menú, sin `if rol === ...` en ningún lado. El menú de cada rol sale de `StudioRole::menu()`.
- **«Solo las suyas»:** el Gate dice si el rol puede hacer la acción en el estudio. Que el instructor vea solo **sus** sesiones, o la alumna solo **sus** reservas, lo filtran las Policies de cada recurso cuando ese recurso exista _(Fases 2 y 3)_.
- **404 para otros estudios:** un recurso de un estudio ajeno responde 404 (`Response::denyAsNotFound()`), para no revelar que existe. Si es del estudio pero el rol no tiene el permiso, responde 403.
