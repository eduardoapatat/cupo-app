---
status: accepted
date: 2026-10-29
---

# La caja cerrada no recibe pagos: un trigger que lee la caja con `FOR SHARE`

## Contexto y problema

Desde el punto 3.2, cada venta en el mesón entra en la caja abierta de quien cobra. Al cerrarla, Camila cuenta el efectivo y la caja congela sus totales por medio de pago. Supongamos que Camila cierra su caja desde el computador a las 21:00:00 y en ese mismo segundo termina una venta en efectivo desde la tablet del mesón. Si el cierre suma los pagos y en paralelo se inserta uno nuevo, la venta queda dentro de una caja cerrada pero fuera de sus totales: hay $45.000 en el cajón que el cierre no cuenta. Y nadie puede corregirlo, porque una caja cerrada no cambia. ¿Cómo garantizamos que un pago entra en los totales de la caja o no entra en la caja?

## Factores de decisión

- PRODUCT.md promete que quien recibe la plata no la puede borrar después: una caja cerrada no cambia nunca.
- La garantía no puede depender de que cada camino que cobra revise la caja en el orden correcto. Hoy cobra `SellPlan`; después vendrán otros.
- Las ventas de cajas distintas no deben esperarse entre sí.

## Opciones consideradas

1. **Un trigger en `payments`** que lee la caja con `SELECT … FOR SHARE` y rechaza el pago si está cerrada. El cierre toma la misma fila con `FOR UPDATE` y recién después suma.
2. Revisar en `SellPlan` con `lockForUpdate` sobre la caja, sin trigger.
3. Calcular los totales al vuelo, sin congelarlos al cierre.

## Decisión

Elegimos la **opción 1**:

- El trigger `payments_need_an_open_register` corre antes de insertar un pago con caja o de cambiarle la caja. Lee la caja con `FOR SHARE` y responde `23514` si está cerrada. También impide sacar un pago de una caja cerrada.
- `CloseCashRegister` toma la caja con `FOR UPDATE`, que choca con el `FOR SHARE`:
    - **si la venta llegó primero**, el cierre espera a que confirme y la suma la incluye;
    - **si el cierre llegó primero**, la venta espera, ve la caja cerrada y se rechaza. `SellPlan` lo traduce a «Tu caja se cerró, así que no se cobró nada», y la compra vuelve atrás con el pago.
- Otro trigger, `cash_registers_keep_closed`, impide cambiar una caja cerrada. La única excepción es `user_id`, que pasa a `NULL` si se borra la cuenta de quien la abrió.
- Un índice único parcial deja una sola caja abierta por persona, y un `UNIQUE` deja una por persona y día.

### Consecuencias

- Bueno: ningún pago queda en una caja cerrada sin contarse, venga de donde venga la escritura.
- Bueno: el bloqueo es por caja. Solo esperan una venta y el cierre de la misma caja.
- Malo: es lógica en PL/pgSQL, menos visible que en PHP. Por eso el trigger se describe en la migración y aquí.
- Neutral: anular un pago después del cierre (punto 3.2) cambia su estado, no su caja, así que el trigger lo permite y los totales congelados quedan como estaban.

### Confirmación

- `tests/Concurrency/CashRegisterConcurrencyTest.php`: en 8 cajas, una venta en efectivo y el cierre corren a la vez en procesos distintos. En cada caja, el efectivo congelado es igual a la suma de sus pagos. Con el trigger sin `FOR SHARE`, el test falla.
- `tests/Feature/Models/CashRegisterTest.php`: el trigger rechaza un pago en una caja cerrada y uno que sale de ella, y una caja cerrada no cambia.

## Pros y contras de las opciones

### Trigger con `FOR SHARE`

- Bueno: la garantía vive en la base y cubre cualquier camino que cobre.
- Malo: hay que acordarse de que existe al escribir datos de prueba; por eso la demo cierra cada caja después de sus ventas.

### `lockForUpdate` en `SellPlan`

- Bueno: es PHP, fácil de leer.
- Malo: un camino nuevo que cobre y olvide el bloqueo rompe la garantía sin que nada avise.

### Totales al vuelo

- Bueno: no hay nada que congelar.
- Malo: un pago tardío cambia una caja ya cuadrada, justo lo que la caja cerrada debe impedir.
