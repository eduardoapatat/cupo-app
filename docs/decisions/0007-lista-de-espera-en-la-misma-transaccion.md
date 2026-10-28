---
status: accepted
date: 2026-10-28
---

# La lista de espera reserva en la misma transacción que libera el cupo

## Contexto y problema

La clase del martes a las 19:00 está llena y Javiera se anota en la lista de espera. El lunes en la noche alguien cancela. Según la regla del estudio, ese cupo pasa solo a la primera de la lista mientras falten al menos 6 horas (el corte de la lista de espera); después queda libre para cualquiera. ¿Cuándo y cómo se le da el cupo, sin que otra persona lo tome en el medio y sin pasarse del cupo si dos personas cancelan a la vez?

## Factores de decisión

- El cupo liberado no debe quedar libre ni un instante antes del corte: si queda libre, otra alumna puede reservarlo desde «Reservar» y saltarse la fila.
- Nunca se pasa del cupo (ADR 0005), tampoco con dos cancelaciones simultáneas.
- Todavía no hay colas ni avisos (llegan en la Fase 4), y las alumnas sin app se enteran por recepción.

## Opciones consideradas

1. **Promover dentro de la transacción de `CancelBooking`**, después de descontar el cupo, con la fila de la clase bloqueada.
2. Un job en cola que se despacha al cancelar.
3. Un comando programado que cada pocos minutos revisa las clases con cupo y lista de espera.

## Decisión

Elegimos la **opción 1** (`App\Actions\Bookings\PromoteFromWaitlist`, llamada por `CancelBooking`):

- El `UPDATE` que descuenta `booked_count` bloquea la fila de la clase hasta el final de la transacción. Dentro de ella se toma la primera entrada `waiting` (por orden de llegada), se suma el cupo con el mismo `increment` condicional de reservar, se crea la reserva y la entrada queda `promoted` con su `booking_id`. Al confirmar, el cupo ya tiene dueña.
- Dos cancelaciones simultáneas pasan una detrás de otra por ese bloqueo: la segunda ve la entrada de la primera ya `promoted` y le da su cupo a la siguiente.
- Si la primera de la fila ya no puede tomarlo (por ejemplo, ahora da la clase), su entrada queda `left` y se prueba con la siguiente. Se revisa antes de insertar porque, en PostgreSQL, un `INSERT` fallido aborta toda la transacción.
- Anotarse (`JoinWaitlist`) bloquea la misma fila y exige que la clase siga llena, así que nadie queda esperando junto a un cupo libre.
- Un índice único parcial permite una sola entrada `waiting` por persona y clase, y un `CHECK` exige `resolved_at` exactamente cuando la entrada ya no espera.

### Consecuencias

- Bueno: el cupo nunca queda libre antes del corte, y la garantía del ADR 0005 se mantiene porque la promoción usa el mismo `UPDATE` condicional.
- Bueno: sin colas ni jobs; quien cancela desde la Agenda ve al tiro quién tomó el cupo.
- Malo: cancelar hace un poco más de trabajo dentro de la transacción, aunque solo sobre la fila de esa clase.
- Neutral: en la Fase 4 el aviso a la alumna promovida se despachará después de confirmar la transacción.

### Confirmación

- `tests/Concurrency/WaitlistConcurrencyTest.php`: dos procesos cancelan a la vez en una clase llena con dos personas esperando. Cada una recibe un cupo, y el contador queda en el cupo.
- `tests/Feature/Actions/Bookings/WaitlistTest.php`: el orden de la fila, el corte, saltar a quien ya no puede y cerrar la entrada al reservar o al cancelar la clase.

## Pros y contras de las opciones

### En la misma transacción

- Bueno: atómico; el cupo pasa de una persona a otra sin quedar libre.
- Malo: la cancelación hace más trabajo.

### Job en cola

- Bueno: la cancelación responde antes.
- Malo: entre la cancelación y el job el cupo queda libre y cualquiera lo puede reservar; además, el proyecto todavía no tiene colas en uso.

### Comando programado

- Bueno: simple de escribir.
- Malo: el cupo queda libre hasta la próxima pasada, que es justo lo que la regla quiere evitar.
