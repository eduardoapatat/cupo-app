---
status: accepted
date: 2026-10-27
---

# Un solo tipo de negocio, self-service y sin super admin

## Contexto y problema

El proyecto anterior, PichangApp, era un marketplace de dos lados: la plataforma creaba los recintos, los dueños los administraban y los jugadores reservaban. Eso trajo tres problemas: no se entendía la diferencia entre «super admin», «admin» y «jugador»; los recintos dependían de que la plataforma los creara; y el rol vivía en el usuario, así que había que ajustarlo a mano cuando alguien pasaba de jugador a dueño. Además, el sitio público quedó bloqueado esperando un diseño.

Para Cupo, ¿qué forma le damos al producto para que los roles se entiendan solos y nadie dependa de la plataforma?

## Factores de decisión

- Cada rol debe entenderse con una frase y con la historia del estudio (Carla, Camila, Fernanda, Diego, Javiera y la señora Rosa).
- Un estudio nuevo debe poder empezar a operar sin que nadie de la plataforma intervenga.
- Una persona puede tener papeles distintos en negocios distintos.
- Ninguna pantalla debe quedar esperando un diseño externo.
- Mínimo necesario: nadie ve los datos de un estudio sin pertenecer a él.

## Opciones consideradas

1. **Un solo tipo de negocio (estudios), self-service, sin super admin y con el rol en la membresía.**
2. Repetir el modelo de PichangApp: marketplace con catálogo público, super admin que crea los negocios y rol global en el usuario.
3. Un solo tipo de negocio, pero con un super admin de soporte que pueda entrar a cualquier estudio.

## Decisión

Elegimos la **opción 1**:

- **Un solo tipo de negocio:** estudios pequeños de clases grupales para adultos. Quedan fuera los marketplaces, el catálogo público entre estudios, las sucursales, RR. HH. y contabilidad.
- **Self-service:** quien se registra crea su propio estudio y queda como su dueña _(Fase 1)_. La dueña invita a recepción e instructores, y las alumnas entran por el enlace del estudio o las inscribe recepción.
- **Sin super admin:** la app no tiene un rol de plataforma ni un `Gate::before` que lo deje pasar todo. El soporte, si hace falta, se hace fuera de la app (por ejemplo, con `php artisan tinker`).
- **El rol vive en la membresía** (persona × estudio), como los equipos de Jetstream. El detalle de los roles, el menú y la matriz está en `docs/roles-y-permisos.md`, y cómo se programa, en el ADR 0003 _(punto 0.3)_.
- **Una sola app con login**, con el menú por rol y los componentes shadcn del starter. No hay sitio público que diseñar.

### Consecuencias

- Bueno: cuatro roles que se explican con la historia del estudio, y cada permiso sale de un principio (negocio para la dueña, operación para recepción, separación de funciones, rastro y mínimo necesario).
- Bueno: un estudio empieza a operar sin intervención de la plataforma.
- Bueno: ningún usuario de la app puede ver todos los estudios, lo que reduce el riesgo con los datos personales.
- Malo: no hay una pantalla de soporte para resolver problemas de un estudio. Lo aceptamos porque es un proyecto de portafolio; si un día hiciera falta, sería otra app.
- Malo: sin catálogo público, una alumna llega solo por el enlace que le comparte su estudio.
- Neutral: una persona con roles en dos estudios ve un selector de estudio. Si pertenece a uno solo, no lo ve.

### Confirmación

- `docs/roles-y-permisos.md` define los cuatro roles, su menú y la matriz, y los tests del punto 0.3 verifican cada fila.
- Un test verifica que quien no es miembro de un estudio recibe 404 _(punto 0.3)_.
- El roadmap no tiene pantallas de plataforma ni de catálogo público.
