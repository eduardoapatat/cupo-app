---
status: accepted
date: 2026-10-28
---

# El cupo se garantiza con un contador y un `UPDATE` condicional

## Contexto y problema

La clase de las 19:00 tiene 10 cupos y quedan 2. A las 18:00 se liberan y tres alumnas tocan «Reservar» desde el celular en el mismo segundo. Si el código lee «quedan 2» y después escribe, las tres pasan y la clase queda con 11. El problema del handoff es justamente ese: más personas que cupos. ¿Cómo garantizamos que nunca se sobrevenda, incluso con reservas simultáneas?

## Factores de decisión

- La garantía tiene que vivir en PostgreSQL, no en la buena conducta del código: cualquier camino que reserve (la alumna, recepción o un futuro job) queda cubierto.
- Una clase tiene **varios** cupos, no uno.
- Reservar tiene que ser rápido y no bloquear otras clases.
- Debe poder probarse con procesos reales, no solo con un test que simula el orden.

## Opciones consideradas

1. **Un contador en la clase** (`class_sessions.booked_count`) con `CHECK (booked_count <= capacity)` y un `UPDATE … SET booked_count = booked_count + 1 WHERE booked_count < capacity` en la misma transacción que crea la reserva.
2. Una restricción de exclusión, como en PichangApp.
3. `SELECT … FOR UPDATE` de la clase, contar sus reservas y después insertar.
4. Contar las reservas antes de insertar, sin bloqueo.

## Decisión

Elegimos la **opción 1** (`App\Actions\Bookings\BookSession`):

- El `UPDATE` condicional es atómico: PostgreSQL toma el bloqueo de la fila de esa clase, así que las reservas simultáneas a la **misma** clase pasan una detrás de otra, y cada una vuelve a evaluar `booked_count < capacity` con el valor ya confirmado. Si no actualiza ninguna fila, la clase está llena y no se crea la reserva.
- El `CHECK (booked_count >= 0 AND booked_count <= capacity)` respalda la garantía aunque algún código futuro se salte la acción.
- La reserva se crea en la misma transacción. Si falla (por ejemplo, el índice único parcial de «una reserva vigente por persona» con un doble toque), la transacción se deshace y el contador vuelve atrás.
- Cancelar (`CancelBooking`) cambia el estado con otro `UPDATE` condicional (`WHERE status = 'confirmed'`) y recién entonces descuenta, así que dos cancelaciones de la misma reserva liberan un solo cupo. Cancelar la clase (`CancelSession`) actualiza primero la fila de la clase, que la bloquea, y después sus reservas.

### Consecuencias

- Bueno: la garantía está en la base y no depende de que todo el código tome un bloqueo a mano.
- Bueno: el bloqueo dura una sentencia y solo toca la fila de esa clase; las demás clases se reservan en paralelo.
- Bueno: saber los cupos libres es leer una columna, sin contar reservas.
- Malo: el contador es un dato derivado que hay que mantener al reservar, cancelar y cancelar la clase. Por eso todo pasa por las acciones, y la factory de reservas lo advierte.
- Neutral: el cupo de una clase se copia del tipo de clase al generarla (ADR 0006), así que cambiar el cupo del tipo no afecta a las clases ya generadas.

### Confirmación

- `tests/Concurrency/BookSessionConcurrencyTest.php`: 15 procesos PHP, cada uno con su conexión, reservan a la vez una clase de 10 cupos. Quedan exactamente 10 reservas y 5 respuestas «Esta clase está llena». Con una versión ingenua (leer y después escribir) el mismo test deja pasar a las 15.
- `tests/Feature/Models/ClassSessionTest.php`: el `CHECK` rechaza un contador sobre el cupo.
- `tests/Feature/Actions/Bookings/`: cada regla de reservar y cancelar, y que cancelar dos veces libera un solo cupo.

## Pros y contras de las opciones

### Contador con `UPDATE` condicional

- Bueno: atómico, rápido y respaldado por un `CHECK`.
- Malo: un dato derivado que mantener.

### Restricción de exclusión (PichangApp)

- Bueno: en PichangApp garantiza que una cancha no se reserve dos veces en el mismo horario.
- Malo: sirve para «uno por horario», no para «hasta 10 por clase». Aquí ya la usamos para otra cosa: que dos clases no se pisen en la sala.

### `SELECT … FOR UPDATE` y contar

- Bueno: correcto si todo el código lo hace.
- Malo: la garantía depende de que cada camino tome el bloqueo; un camino que lo olvide sobrevende y la base no lo detiene.

### Contar sin bloqueo

- Bueno: lo más simple.
- Malo: con reservas simultáneas sobrevende, que es justo el problema que queremos resolver.
