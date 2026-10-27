# Cupo

Reservas, planes, pagos y caja para estudios pequeños de clases grupales: pilates, yoga, funcional y baile.

> **Proyecto de portafolio.** Cupo es un producto ficticio. El estudio, las personas y los datos de la demo son inventados. Está en construcción: ver el [estado](#estado).

## El problema

Un estudio pequeño de clases grupales para adultos vende planes («4 u 8 clases al mes», «ilimitado») y maneja todo por WhatsApp, cuaderno y Excel:

- se reservan más personas que cupos, o quedan cupos vacíos por cancelaciones de última hora;
- nadie sabe con certeza cuántas clases le quedan a cada alumna;
- las transferencias se revisan a mano;
- los planes vencen sin aviso.

### Qué dice la evidencia

- El 49,1 % de las microempresas chilenas no sumó tecnología nueva en 6 meses (Barómetro CNC, julio de 2026). Muchas reciben pedidos por WhatsApp, cobran por transferencia y siguen con planillas ([fuente](https://ecosistemastartup.com/?p=120496)).
- El 89 % de los comercios que reciben transferencias las gestiona a mano (economista jefe de Itaú, encuesta de la CNC y ChilePay, septiembre de 2026; [misma fuente](https://ecosistemastartup.com/?p=120496)).
- En una encuesta de Xepelin a 125 pymes (2024), los principales problemas fueron la falta de liquidez (58,4 %), la cobranza tardía (34,4 %) y los procesos manuales (28 %) ([Emol](https://www.emol.com/noticias/Economia/2025/04/30/1164979/sondeo-startup-xepelin.html)).
- **Sobre los estudios en particular, la evidencia es débil y comercial** (por ejemplo, [SchedulingKit](https://schedulingkit.com/es/whatsapp-booking/pilates-studios)). El problema se sostiene en los datos generales de pymes, no en un estudio del rubro.

## Qué es éxito

- La alumna reserva en dos toques sabiendo cuántas clases le quedan.
- **Nunca** se sobrevende un cupo, ni siquiera con reservas simultáneas.
- La dueña sabe al instante quién pagó, quién debe y cómo se llenan las clases.
- Recepción cobra y cuadra la caja sin planillas.

## Quién la usa

Cada estudio usa Cupo por su cuenta: no hay catálogo público entre estudios ni super admin. El rol vive en la membresía (persona × estudio):

| Rol        | Qué hace                                                                                       |
| ---------- | ---------------------------------------------------------------------------------------------- |
| Dueña      | Crea su estudio y decide planes, precios, horario, reglas y equipo. Ve reportes e ingresos     |
| Recepción  | Inscribe alumnas, vende planes, cobra, valida comprobantes, reserva por ellas y cuadra su caja |
| Instructor | Ve sus clases con quiénes vienen y marca asistencia. No maneja plata                           |
| Alumna     | Reserva, cancela, se anota en lista de espera y ve sus clases restantes, su plan y sus pagos   |

El detalle, con el menú y la matriz de permisos, está en [`docs/roles-y-permisos.md`](docs/roles-y-permisos.md).

## Estado

En la **Fase 0**: el proyecto, la calidad, la documentación y el menú de los cuatro roles. El avance está en [`docs/roadmap.md`](docs/roadmap.md).

## Stack

Laravel 13 (PHP 8.5), Fortify, Inertia 3, React 19, TypeScript, Tailwind 4, shadcn/ui, Wayfinder y PostgreSQL 18. Calidad con Pest, Pint, PHPStan nivel 7, oxlint/oxfmt y `tsc`, y CI en GitHub Actions.

## Cómo correrlo en local

Requisitos: PHP 8.5 con Composer, Node 24 con pnpm y Docker.

```sh
composer install
cp .env.example .env
docker compose up -d pgsql mailpit
php artisan key:generate
pnpm install
pnpm run build
php artisan migrate --seed
composer dev
```

- La app queda en http://localhost:8000 y los correos (verificación de cuenta) llegan a Mailpit, en http://localhost:8025.
- El seeder crea Estudio Flow con una cuenta demo por rol, todas con la contraseña `password`: `carla@example.com` (dueña), `camila@example.com` (recepción), `fernanda@example.com` y `diego@example.com` (instructores) y `javiera@example.com` (alumna).
- `composer ci:check` corre lo mismo que el CI: lint y formato, tipos, Pint, PHPStan y los tests contra PostgreSQL.

## Documentación

- [`docs/roadmap.md`](docs/roadmap.md): fases y puntos de control.
- [`docs/architecture.md`](docs/architecture.md): mapa del código, invariantes y datos personales.
- [`docs/roles-y-permisos.md`](docs/roles-y-permisos.md): roles, menú y matriz de permisos.
- [`docs/decisions/`](docs/decisions/): decisiones de arquitectura (ADRs).
- [`PRODUCT.md`](PRODUCT.md) y [`DESIGN.md`](DESIGN.md): la ficha de producto y el sistema visual.
