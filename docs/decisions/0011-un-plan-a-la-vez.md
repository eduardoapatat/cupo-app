---
status: accepted
date: 2026-11-01
---

# Un plan a la vez: el siguiente queda en espera

## Contexto y problema

En la Fase 3 decidimos que dos planes vigentes convivieran, y que cada reserva usara el que vence antes. Al probarlo apareció el problema:

- Alumna Test quedó con dos «8 clases al mes» comprados el mismo día y que vencen el mismo día: 16 clases.
- Si después se anula uno, no queda claro qué pasa con sus reservas ni con las clases que ya tomó de ese plan.

Para el mesón, dos planes mezclados son difíciles de explicar.

Al mismo tiempo, los estudios sí venden la **renovación anticipada**: el 27 de noviembre Rosa tiene 2 clases de un plan que vence el 30, y quiere pagar diciembre ya. Prohibir un segundo plan obligaría a decirle «vuelve el 1 de diciembre».

## Opciones consideradas

1. **Un plan a la vez, con el siguiente en espera.**
2. Un solo plan, sin excepción: no se vende otro mientras haya uno vigente.
3. Dejar que convivan, avisando las ventas duplicadas y pasando las reservas al otro plan al anular.

## Decisión

Elegimos la **opción 1**.

**Cuándo parte un plan nuevo.** Lo calcula `SellPlan::nextStartFor()`, y es la misma regla al vender en el mesón, al aprobar una transferencia y al confirmar Webpay, porque todos pasan por `SellPlan::newPurchase()`:

- **hoy**, si no tiene un plan con clases;
- **el día siguiente al último día** de su último plan con clases, sea el vigente o uno que ya está en espera.

Un plan que ya no tiene clases no detiene al nuevo.

**La vigencia se cuenta desde que parte:** 30 días desde el 1 de diciembre llegan hasta el 30 de diciembre.

**Un plan en espera** (`PlanPurchase::isQueued()`, scope `queued()`) no cuenta como vigente (`valid()` exige `starts_on <= hoy`). Reservar ya respetaba `starts_on`: un plan en espera solo paga las clases desde el día en que parte.

**Dos ventas a la vez para la misma alumna** podrían leer «no tiene plan» las dos y partir hoy las dos. Por eso `newPurchase` corre en una transacción y toma `pg_advisory_xact_lock(estudio, alumna)` antes de calcular: la segunda espera y queda en fila.

**Lo ya vendido no se mueve:** la regla vale desde ahora.

### Consecuencias

- Bueno: para cualquier día, hay a lo más un plan con clases que pueda pagar una reserva. Anular un plan en espera normalmente no toca ninguna reserva.
- Bueno: una venta duplicada se nota al tiro, porque la segunda dice «Parte el 1 de diciembre».
- Malo: hay que mostrar el plan en espera en las pantallas (vender, comprar, «Mi plan» y la ficha).
- Neutral: si el plan actual se queda sin clases antes de vencer, el nuevo parte hoy y el usado sigue «vigente sin clases» hasta su fecha, sin pagar nada.

### Confirmación

- `tests/Feature/Actions/Plans/SellPlanTest.php`, «one plan at a time»:
    - sin plan, parte hoy;
    - con clases, en fila, y uno tras otro;
    - después de un ilimitado;
    - sin clases, parte hoy;
    - cada estudio por separado;
    - cada reserva usa el plan del día de su clase.
- `TransferReceiptTest` y `WebpayPaymentTest`: aprobar con un plan en uso deja el nuevo en espera.
- `tests/Concurrency/PlanQueueConcurrencyTest.php`: dos ventas a la misma alumna en procesos distintos. Quedan una hoy y la otra en fila. Sin el bloqueo, el test falla con las dos partiendo hoy.
