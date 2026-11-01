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

## Fase 1: estudios, equipo y alumnas ✅

Decisiones de la fase: quien entra por el enlace queda como alumna al tiro; a una alumna sin app la activa recepción anotando su correo; una persona es dueña de un solo estudio; las comunas son las 52 de la Región Metropolitana; las invitaciones vencen a los 7 días; el selector de estudio va arriba a la derecha.

- [x] **1.1 "Crear el estudio"**:
    - la comuna, las reglas copiadas de `config/studio.php` y el token del enlace en `studios`, con sus `CHECK`, y una sola dueña por persona;
    - la declaración de mayoría de edad en el registro (`users.adult_declared_at`);
    - `/estudios/crear` y la pantalla «Estudio» (nombre y comuna), con la fila nueva `studio.manage` en la matriz;
    - `lib/form.ts` traído de PichangApp con el primer formulario.
- [x] **1.2 "Equipo"**:
    - invitar a recepción e instructores por correo, con URLs firmadas que vencen a los 7 días y correos en cola;
    - aceptar la invitación con o sin cuenta, reenviarla, cancelarla y quitar a alguien del equipo;
    - la pantalla «Equipo», con 404 para los recursos de otro estudio.
- [x] **1.3 "Alumnas, enlace y selector"**:
    - el enlace de inscripción del estudio (copiarlo y renovarlo en «Estudio»);
    - la pantalla «Alumnas»: inscribir en el mesón con nombre y teléfono, editar el contacto de quien no usa la app y mandarle la activación;
    - el selector de estudio arriba a la derecha;
    - el ADR 0004 y el cierre de la fase.

Para las fases siguientes:

- **Fase 2** _(resuelto)_: alguien del equipo toma clases si recepción lo anota como «equipo», y quitar a un instructor deja sus clases sin instructor, que no se pueden reservar hasta tener reemplazo.
- **Fase 3:** los pagos y las cajas apuntan a la persona, no a la membresía, para que el historial conserve a quien cobró aunque ya no esté en el equipo.

## Fase 2: horario, sesiones y reservas

Decisiones de la fase: se reserva hasta 14 días antes (regla del estudio); un cambio de horario no mueve las sesiones con reservas; en esta fase se reserva sin descontar clases; quitar a un instructor deja sus clases sin instructor, y esas no se pueden reservar; el equipo toma clases si recepción lo anota como «equipo»; en la asistencia, quien da la clase marca a quien vino, y quien no queda marcado faltó.

- [x] **2.1 "Clases y horario"**:
    - tipos de clase, horario semanal y sesiones, con sus garantías: el cupo con `CHECK`, sin solapes en la sala (`EXCLUDE`) y sin duplicados al generar;
    - `sessions:generate` cada noche, con el desfase de cada fecha en Santiago (ADR 0006);
    - la pantalla «Clases» y las reglas editables en «Estudio»;
    - quitar a un instructor deja sus clases sin instructor.
- [x] **2.2 "Reservas"**:
    - reservas con el cupo garantizado bajo concurrencia real (contador con `CHECK` y `UPDATE` condicional, ADR 0005) y un test de varios procesos a la vez;
    - «Reservar» y «Mis reservas» para la alumna, la «Agenda» para recepción y la dueña (reservar por una alumna, anotar al equipo, reemplazo y cancelar la sesión) y «Mis clases» para el instructor;
    - cancelación a tiempo o tarde según la ventana, y que la dueña pueda saltársela.
- [x] **2.3 "Lista de espera, asistencia e Inicio"**:
    - la lista de espera con su corte: el cupo liberado pasa a la primera de la fila en la misma transacción (ADR 0007), con un test de varios procesos; la alumna se anota desde «Reservar» y recepción desde la Agenda;
    - la asistencia: quien da la clase marca a quien vino, hasta el fin del día siguiente (la dueña corrige siempre), y `attendance:close` deja cada noche como «Faltó» a quien no se marcó;
    - el Inicio de cada rol con datos reales y el horario de Estudio Flow en los datos demo.

## Fase 3: planes, créditos, pagos y caja ✅

Decisiones de la fase: primero las clases y después la plata; un plan a la vez: el que se compra antes de que termine el actual queda en espera y parte al día siguiente de su último día (cambiado después del punto 3.3, ADR 0011); la clase tiene que caer dentro de la vigencia del plan; la lista de espera se salta a quien no tiene clases; recepción vende a precio de lista y solo la dueña cambia el precio, con motivo; la alumna sube el comprobante de su transferencia desde la app y su plan parte cuando el estudio lo aprueba; anular un pago anula su plan entero; para vender hay que tener la caja abierta, y una olvidada se cierra antes de abrir otra; al vencer, las clases sin usar se pierden con un movimiento que dice cuántas, y la alumna ve el aviso desde los días de aviso del estudio; los reportes van con gráficos (recharts); anular un pago de Webpay le pide la devolución a Transbank, y sin devolución no se anula.

- [x] **3.1 "Planes y clases disponibles"**:
    - «Planes» (lista con panel), «Vender plan» (una página con resumen) y la pestaña «Plan y pagos» en la ficha de la alumna;
    - reservar descuenta 1 clase del plan que vence antes, con la fila del plan bloqueada y un `UPDATE` condicional (ADR 0008) y un test de varios procesos; cancelar a tiempo, devolverla o cancelar la clase la devuelven, y queda cada movimiento;
    - «Mi plan», el estado «sin plan» en «Reservar» y en la Agenda, y los bloques de la alumna en el Inicio;
    - los planes y las compras de Estudio Flow en la demo.
- [x] **3.2 "Caja y pagos"**:
    - caja por persona y día: apertura con el efectivo inicial, cierre con los totales por medio y la diferencia del efectivo (con nota si no cuadra); vender cobra en la caja abierta de hoy, y una olvidada se cierra antes de abrir otra. Un trigger con `FOR SHARE` garantiza que un pago entra en los totales o se rechaza, nunca queda en una caja cerrada (ADR 0009), con un test de varios procesos;
    - la alumna compra desde la app por transferencia y sube el comprobante (disco privado); recepción o la dueña lo aprueban, lo que crea su plan desde ese día, o lo rechazan con motivo; revisarlo a la vez no crea dos planes;
    - estados del pago (por validar → aprobado o rechazado; un aprobado se anula), anulaciones con motivo que anulan el plan y cancelan sus reservas que vienen; recepción anula solo en una caja abierta y la dueña, cualquiera (`cash.review` para ver las cajas del equipo);
    - «Caja», «Pagos» con el detalle de cada pago, «Comprar plan», los datos para transferir en «Estudio», el descuento en la ficha y los bloques del Inicio de recepción y de la dueña.
- [x] **3.3 "Vencimientos, reportes y Webpay"**:
    - `plans:expire` cada noche: las clases que quedaban en un plan vencido salen en un solo movimiento («Vencieron sin usar»), con la fila bloqueada e idempotente; el aviso a la alumna en su Inicio y en «Mi plan», con «Renovar»;
    - `paid_at` en cada pago, para contar los ingresos el día en que entró la plata;
    - «Reportes» de la dueña, por mes: ingresos por día, medio y plan, descuentos y anulaciones; ocupación por horario y asistencia; alumnas con plan; planes que vencen en 7 días. «Ingresos del mes» en su Inicio;
    - Webpay Plus en integración, con el cliente HTTP de Laravel: confirmación con la fila bloqueada (dos a la vez crean un solo plan, con un test de varios procesos), la vuelta por `POST` sin sesión, `webpay:reconcile` cada 5 minutos y la devolución a la tarjeta al anular (ADR 0010).

## Cierre ✅

El proyecto se cerró el 1 de noviembre de 2026, con las fases 0 a 3 terminadas:

- [x] El README final, con capturas, cómo correrlo, las cuentas demo y lo destacado.
- [x] La licencia MIT.
- [x] La insignia del CI en el README.
- [x] Repositorio público: se adelantó al 28 de octubre de 2026, con autorización explícita, junto con su descripción y sus temas en GitHub.
- [x] Después del punto 3.3, a partir de las pruebas de recorrido:
    - «Vender plan» en tres pasos;
    - la caja con la misma forma abierta o cerrada, y el conteo por billete y moneda;
    - «Mis clases», donde se elige una clase y se pasa lista con «Guardar»;
    - la asistencia desde 15 minutos antes;
    - un plan a la vez, con el siguiente en espera (ADR 0011);
    - el turquesa como color de marca.

## Ideas futuras (fuera del alcance)

Quedaron planeadas, pero no se hicieron:

- **Automatizaciones:**
    - recordatorios de clase por correo;
    - avisos de la lista de espera;
    - el aviso de vencimiento por correo (en pantalla ya existe);
    - un resumen diario para la dueña;
    - las invitaciones pendientes dentro de la app.
- **Terminar un plan y devolver lo no usado:** hoy anular deshace la venta completa y sirve para errores de cobro.
- **API REST** con Sanctum, para la alumna, documentada con OpenAPI.
- **Calidad:** tests de arquitectura de Pest y cobertura en el CI.
- **Demo en línea:**
    - despliegue, con correos reales;
    - cuentas con acceso de un clic;
    - un modo demo que se reinicia cada noche.
