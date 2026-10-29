---
status: accepted
date: 2026-10-29
---

# Las clases del plan se descuentan con la fila del plan bloqueada y un `UPDATE` condicional

## Contexto y problema

Desde la Fase 3, reservar descuenta 1 clase del plan de la alumna. A Javiera le queda 1 clase y reserva el martes y el miércoles desde el celular y el computador en el mismo segundo. Si el código lee «le queda 1» y después escribe, las dos reservas pasan y el plan queda en −1. Con un plan ilimitado pasa lo mismo con el máximo diario: si permite 1 clase al día, dos reservas simultáneas del mismo día pasan las dos. ¿Cómo garantizamos que nunca se usen más clases de las compradas ni se pase del máximo diario?

## Factores de decisión

- Igual que el cupo (ADR 0005), la garantía no puede depender de que el código lea y escriba en el orden correcto.
- Las dos reservas compiten por **la misma compra**, aunque sean de clases distintas, así que el bloqueo de la fila de la clase no las ordena.
- El máximo diario no es un contador: se cuenta en las reservas de ese día.
- Una alumna puede tener dos planes. La reserva usa el que vence antes; si ese se agota en la carrera, prueba con el siguiente.

## Opciones consideradas

1. **Bloquear la fila de la compra** (`SELECT … FOR UPDATE`) dentro de la transacción de la reserva, revisar ahí el máximo diario y descontar con `UPDATE plan_purchases SET credits_left = credits_left - 1 WHERE … AND credits_left > 0`, respaldado por un `CHECK (credits_left BETWEEN 0 AND credits)`.
2. Solo el `UPDATE` condicional, como el cupo.
3. Un candado con nombre por alumna (`pg_advisory_xact_lock`).

## Decisión

Elegimos la **opción 1** (`App\Actions\Plans\TakeClassFromPlan`, llamada por `BookSession` y por `PromoteFromWaitlist`):

- Se buscan los planes que sirven para la clase: los vigentes el día de la clase y con clases disponibles, ordenados por vencimiento. A cada uno se le bloquea la fila antes de revisarlo. Una segunda reserva de la misma alumna espera hasta que la primera confirme, y después ve su resultado.
- Con la fila bloqueada se cuenta el máximo diario y se descuenta con el `UPDATE` condicional. El `CHECK` respalda la garantía aunque algún código futuro se salte la acción.
- Todo pasa en la misma transacción que toma el cupo. Si ningún plan puede pagar, no se crea la reserva y el cupo vuelve atrás.
- El orden de los bloqueos es siempre el mismo: primero la clase, después el plan. También al cancelar, donde `ReturnClassToPlan` devuelve la clase dentro de la transacción de la cancelación. Por eso no hay esperas cruzadas.
- Cada clase tomada o devuelta queda en `credit_movements`, con el motivo y quién lo hizo.

### Consecuencias

- Bueno: nunca se usan más clases de las compradas ni se pasa del máximo diario, aunque las reservas sean simultáneas.
- Bueno: el bloqueo es por compra, así que solo espera la misma alumna; las demás reservan en paralelo.
- Malo: es un dato derivado más (`credits_left`) que se mantiene al reservar, al cancelar y al cancelar la clase. Por eso todo pasa por las acciones.
- Neutral: las reservas de la Fase 2 no tienen plan, y cancelarlas no devuelve nada.

### Confirmación

- `tests/Concurrency/PlanConcurrencyTest.php`: dos procesos reservan a la vez dos clases con la última clase del plan, y otros dos, dos clases del mismo día con un plan ilimitado de 1 al día. En los dos casos pasa una sola reserva. Sin el bloqueo de la fila, el segundo test deja pasar las dos.
- `tests/Feature/Actions/Plans/BookingWithPlanTest.php`: el plan que vence antes, la vigencia el día de la clase, el máximo diario, las devoluciones y la lista de espera.
- `tests/Feature/Models/PlanPurchaseTest.php`: el `CHECK` rechaza un saldo negativo o mayor a lo comprado.

## Pros y contras de las opciones

### Bloquear la compra y `UPDATE` condicional

- Bueno: cubre el saldo y el máximo diario con un solo mecanismo.
- Malo: un bloqueo explícito que todo camino que reserve debe tomar; por eso vive en una sola acción.

### Solo el `UPDATE` condicional

- Bueno: es lo mismo que el cupo.
- Malo: protege el saldo, pero no el máximo diario de un plan ilimitado, que no es un contador.

### Candado con nombre por alumna

- Bueno: ordena todo lo de una alumna.
- Malo: es más difícil de seguir y de probar, y se olvida fácil en un camino nuevo.
