---
status: accepted
date: 2026-10-28
---

# Sesiones concretas generadas desde el horario semanal

## Contexto y problema

Estudio Flow tiene un horario fijo: lunes a viernes a las 7:00, 9:00, 19:00 y 20:00, y sábados a las 10:00. Pero las alumnas no reservan «los martes a las 19:00», reservan **el martes 4 a las 19:00**, y esa clase concreta puede tener un reemplazo, cancelarse o llenarse. ¿Guardamos cada clase concreta o la calculamos desde el horario cuando hace falta?

## Factores de decisión

- El cupo se garantiza por clase concreta (ver el ADR 0005), así que tiene que existir una fila a la que apuntar.
- Una clase concreta cambia sola: un reemplazo, una cancelación o su lista de espera.
- Cambiar el horario no debe mover las clases que ya tienen reservas.
- Las horas son de Santiago, que cambia de horario dos veces al año.

## Opciones consideradas

1. **Generar las sesiones de los próximos días** desde el horario, como filas de `class_sessions`, con un comando programado.
2. Calcular las clases al vuelo desde el horario y crear la fila recién cuando alguien reserva.
3. Generar todo un año de sesiones al crear el horario.

## Decisión

Elegimos la **opción 1**:

- `schedule_slots` guarda el horario semanal en hora local (día ISO y hora). `GenerateSessions` crea las sesiones de los próximos `booking_horizon_days` días (14), convirtiendo cada fecha a UTC con **su propio desfase**, así que el cambio de horario de verano no corre las clases.
- `sessions:generate` corre cada noche, a las 00:10 hora de Santiago, para el día que entra al horizonte.
- Es idempotente: el `insert` usa `ON CONFLICT DO NOTHING`, que se salta una sesión que ya existe (`unique(schedule_slot_id, starts_at)`) o que se pisaría con otra (la restricción de exclusión de la sala).
- Cambiar el horario (`ApplyScheduleChange`) borra y vuelve a generar **solo las sesiones futuras sin reservas** (`booked_count = 0`). Las que ya tienen reservas quedan como estaban, y se cambian una a una desde la agenda _(punto 2.2)_. Quitar una clase del horario deja sus sesiones con reservas, sin el vínculo al horario.

### Consecuencias

- Bueno: cada clase concreta es una fila con su cupo, su instructor y su estado, a la que apuntan las reservas, la lista de espera y la asistencia.
- Bueno: el horario y las clases concretas evolucionan por separado, sin mover reservas.
- Bueno: el horizonte acota cuántas filas hay (unas 300 por estudio a la vez).
- Malo: si el comando no corre, el último día no tiene sesiones. Lo mitigamos porque corre cada noche, es idempotente y también se ejecuta al cambiar el horario o las reglas.
- Neutral: una sesión con reservas puede quedar distinta del horario (otra hora o sin horario). Es intencional.

### Confirmación

- `tests/Feature/Actions/Classes/GenerateSessionsTest.php`: los 14 días, el cambio de horario de abril de 2026, que es idempotente y que se salta las clases inactivas y las horas pasadas.
- `tests/Feature/Http/Controllers/Studio/ClassesControllerTest.php`: el cambio de horario mueve las sesiones sin reservas y mantiene las demás.

## Pros y contras de las opciones

### Generar los próximos días

- Bueno: simple de consultar y de proteger con restricciones.
- Malo: necesita un comando programado.

### Calcular al vuelo

- Bueno: sin comando ni filas de más.
- Malo: la agenda, el cupo y la lista de espera tendrían que mezclar clases calculadas y reales, y una restricción de la base no protege lo que no existe.

### Un año completo

- Bueno: nunca falta un día.
- Malo: miles de filas, y cada cambio de horario tendría que revisarlas todas.
