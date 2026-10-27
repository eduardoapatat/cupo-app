---
status: accepted
date: 2026-10-27
---

# Alumnas sin app: correo y contraseña opcionales, y activación con URL firmada

## Contexto y problema

La señora Rosa paga en efectivo y reserva en el mesón: no usa la app. Recepción tiene que poder inscribirla, venderle un plan y reservar por ella _(Fases 2 y 3)_ con su nombre y su celular, aunque no tenga correo. Si un día quiere reservar desde el celular, tiene que poder activar su cuenta sin perder lo que ya tiene en el estudio. ¿Cómo la representamos?

## Factores de decisión

- Una alumna sin app tiene su historial (plan, créditos, reservas, pagos) igual que una con app. Activar la cuenta no debe obligar a mover datos.
- Mínimo necesario: no pedir un correo a quien no lo usa.
- Nadie del estudio debe poder quedarse con la cuenta de alguien.
- Seguir usando Fortify para el inicio de sesión, sin un segundo tipo de usuario.

## Opciones consideradas

1. **Un `User` con `email` y `password` opcionales, y una URL firmada para crear la contraseña.**
2. Una tabla aparte de «alumnas sin cuenta», que se convierte en `User` al activarse.
3. Pedir siempre el correo y crear la cuenta con una contraseña al azar que se cambia después.

## Decisión

Elegimos la **opción 1**:

- `users.email` y `users.password` pasan a ser nullable, y se agrega `users.phone`. PostgreSQL permite varios `NULL` bajo el índice único del correo. `User::usesApp()` es `password !== null`.
- Recepción inscribe con nombre y celular (obligatorio, normalizado a `+569XXXXXXXX`), correo opcional y la declaración de que es mayor de 18, que marca ella. La alumna queda con una membresía `student` como cualquier otra.
- Si tiene correo, le llega `ActivateAccount` (en cola) con una URL `temporarySignedRoute` que vence a los `config('studio.activation_days')` días (7). El enlace lleva el hash del correo: si recepción lo corrige, los enlaces enviados al correo anterior dejan de servir.
- Al crear su contraseña, su correo queda verificado (el enlace llegó ahí) e inicia sesión. Desde entonces el enlace responde «Tu cuenta ya está activa».
- **Mientras no use la app, recepción mantiene sus datos. Cuando ya la usa, sus datos son suyos:** nadie del estudio los edita, porque esa cuenta puede estar en otros estudios y cambiarle el correo sería quedarse con ella.
- No se inscribe en el mesón un correo que ya tiene cuenta en Cupo: se le comparte el enlace del estudio para que se una con la suya.

### Consecuencias

- Bueno: una sola tabla de personas, así que las reservas y los pagos _(Fases 2 y 3)_ apuntan siempre a `users`, tenga o no la app.
- Bueno: activar no mueve datos; solo agrega una contraseña.
- Bueno: Fortify no cambia. Quien no tiene contraseña simplemente no puede iniciar sesión.
- Malo: `email` y `password` ya no están garantizados en el tipo, y el código que los usa tiene que contemplar `null`.
- Malo: si dos estudios inscriben a la misma persona sin correo, quedan dos `User`. Lo aceptamos: sin correo no hay forma segura de saber que son la misma persona.

### Confirmación

- `tests/Feature/Models/UserTest.php`: se guardan varias personas sin correo ni contraseña.
- `tests/Feature/Http/Controllers/Studio/StudentControllerTest.php`: inscribir, el celular normalizado, la activación al anotar un correo y que no se edita a quien ya usa la app.
- `tests/Feature/Http/Controllers/AccountActivationControllerTest.php`: crear la contraseña, el enlace que deja de servir si se corrige el correo, el vencido y el alterado.

## Pros y contras de las opciones

### `User` con correo y contraseña opcionales

- Bueno: un solo modelo de persona, y activar es agregar una contraseña.
- Malo: dos columnas que antes eran obligatorias ahora pueden ser `null`.

### Tabla aparte de alumnas sin cuenta

- Bueno: `users` queda como venía del starter.
- Malo: cada reserva, crédito y pago tendría que apuntar a dos tablas, o moverse al activar la cuenta.

### Correo obligatorio y contraseña al azar

- Bueno: el modelo no cambia.
- Malo: exige un correo a quien no lo usa, contra la minimización de datos, y deja afuera a la señora Rosa si no tiene.
