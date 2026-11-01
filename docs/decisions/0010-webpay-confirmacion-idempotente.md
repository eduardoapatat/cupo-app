---
status: accepted
date: 2026-11-01
---

# Webpay: confirmación idempotente y vuelta sin sesión

## Contexto y problema

Desde el punto 3.3, la alumna paga su plan con Webpay Plus. El pago tiene tres momentos:

1. Cupo crea la transacción en Transbank.
2. El navegador de la alumna va a pagar a Webpay.
3. Webpay la devuelve a Cupo (`return_url`), y Cupo confirma la transacción (_commit_).

Pueden pasar varias cosas en ese camino:

- Javiera vuelve, recarga la página o la abre en dos pestañas. Confirmar dos veces no puede crear dos planes.
- Paga y cierra la pestaña antes de volver. Transbank cobró, y el plan tiene que aparecer igual.
- Anula en Webpay. En el ambiente de integración, esa vuelta llega por `POST` desde otro sitio, y el navegador no manda la cookie de sesión (`SameSite=Lax`). Si Laravel abriera una sesión nueva, pisaría la suya y la sacaría de la app.

¿Cómo confirmamos cada pago una sola vez, sin perder los que no vuelven y sin cerrar la sesión de nadie?

## Factores de decisión

- Un pago de Webpay crea un plan. Dos confirmaciones no pueden crear dos.
- El monto sale del plan en la base, nunca del navegador.
- Cupo es un proyecto de portafolio: sin dependencias nuevas si el cliente HTTP de Laravel alcanza.

## Opciones consideradas

1. **Bloquear el pago con `FOR UPDATE` durante el _commit_,** con una revisión cada 5 minutos para los pagos que quedaron pendientes.
2. Confirmar sin bloquear y ver después si Transbank dice que ya se confirmó.
3. Usar el SDK oficial de Transbank para PHP.

## Decisión

Elegimos la **opción 1**, con el cliente HTTP de Laravel contra la API REST v1.2 (`app/Webpay/WebpayClient.php`):

- **`StartWebpayPayment`:**
    - crea el pago `pending` con el precio del plan y una orden de compra única;
    - después pide la transacción y guarda el token y la URL de pago.

    Una página manda sola un formulario `POST` con `token_ws` a esa URL, como lo exige Transbank.

- **`ConfirmWebpayPayment`:**
    - bloquea la fila del pago. Si ya no está `pending`, lo devuelve tal cual;
    - si sigue pendiente, hace el _commit_. Si Transbank lo rechaza (porque ya se confirmó o porque no se pagó), consulta el estado;
    - aprueba solo si se cumplen tres cosas: `AUTHORIZED`, `response_code = 0`, y el monto y la orden coinciden con los del pago. Entonces crea la compra en la misma transacción;
    - si Transbank no responde, el pago sigue `pending`;
    - si la alumna anuló, no hace el _commit_, como pide Transbank: solo lee el estado, por si se pagó igual.
- **La vuelta:**
    - **por `GET`** (`/webpay/retorno`): exige sesión y busca el pago por token u orden de compra, solo entre los de quien vuelve. Lo ajeno responde 404;
    - **por `POST`:** corre sin el grupo de middleware `web`, así que no abre sesión, no toca cookies ni revisa CSRF. Solo redirige (303) al mismo `GET`, que sí lleva la cookie.
- **`webpay:reconcile`** corre cada 5 minutos. Les pasa `ConfirmWebpayPayment` a los pagos que llevan más de 15 minutos pendientes. Para entonces, el formulario de Webpay ya venció: 10 minutos en integración y 4 en producción.
- **Anular** un pago de Webpay (solo la dueña) le pide la devolución a Transbank con la fila del pago bloqueada. Antes, consulta el estado: si ya está `NULLIFIED` o `REVERSED`, no la pide otra vez. Si Transbank no la acepta, no se anula nada.
- **Las credenciales** por defecto en `config/services.php` son las públicas de integración que publica Transbank. En producción, cada estudio tendría su propio código de comercio; eso queda fuera de este proyecto.

### Consecuencias

- Bueno: dos confirmaciones a la vez hacen un solo _commit_ y crean un solo plan. Recargar la vuelta no hace nada dos veces.
- Bueno: un pago cobrado aparece aunque la alumna no vuelva, como mucho unos 20 minutos después.
- Malo: la fila queda bloqueada durante una llamada HTTP. Lo acota un timeout de 10 segundos, y solo espera quien confirma ese mismo pago.
- Neutral: sin el SDK, el cliente cubre solo lo que Cupo usa: crear, confirmar, consultar y devolver.

### Confirmación

- `tests/Concurrency/WebpayConfirmConcurrencyTest.php`: dos procesos confirman el mismo pago a la vez, cada uno con Transbank simulado dentro de su proceso. Hay un solo _commit_ y un solo plan. Sin el `FOR UPDATE`, el test falla con dos _commits_.
- `tests/Feature/Actions/Payments/WebpayPaymentTest.php` y `tests/Feature/Http/Controllers/WebpayControllerTest.php`, con `Http::fake()`. Cubren:
    - confirmar dos veces;
    - rechazado, anulado, tiempo agotado y un monto distinto;
    - Transbank caído;
    - la revisión de cada 5 minutos;
    - que la vuelta por `POST` no deje cookies.

## Pros y contras de las opciones

### Bloqueo durante el _commit_

- Bueno: la garantía es la misma que en el resto de Cupo, una fila bloqueada y un estado que se revisa después del bloqueo.
- Malo: una llamada HTTP dentro de una transacción.

### Confirmar sin bloquear

- Bueno: sin esperas.
- Malo: dos procesos pueden recibir `AUTHORIZED` (uno del _commit_ y otro de la consulta del estado) y crear dos planes.

### SDK de Transbank

- Bueno: lo mantiene Transbank.
- Malo: es una dependencia más, y no resuelve la idempotencia ni la vuelta sin sesión, que son el problema real.
