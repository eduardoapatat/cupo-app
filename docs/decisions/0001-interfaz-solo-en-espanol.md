---
status: accepted
date: 2026-10-27
---

# Interfaz solo en español, sin librería de internacionalización

## Contexto y problema

Cupo apunta a estudios y alumnas de Chile. El starter kit de React trae todos los textos en inglés, escritos directamente en los componentes, y Laravel trae la validación y los correos en inglés. ¿Cómo dejamos la aplicación en español sin sumar complejidad innecesaria?

## Factores de decisión

- La aplicación tiene un solo público y un solo idioma. No hay planes de una versión en inglés.
- Pocas dependencias nuevas, siguiendo la regla del proyecto.
- El código de los componentes debe seguir siendo fácil de leer.
- El español debe ser neutro y cercano (tuteo), entendible en toda Latinoamérica.

## Opciones consideradas

1. Textos en español directamente en los componentes React, y `lang/es` en el backend.
2. Una librería de i18n en el frontend (por ejemplo `laravel-react-i18n`), con claves de traducción.
3. El paquete `laravel-lang/lang` para las traducciones del backend.

## Decisión

Elegimos la **opción 1**:

- **Frontend:** los textos se escriben en español directamente en el JSX, sin claves de traducción.
- **Backend:** `lang/es/*.php` traducido a mano a partir de `php artisan lang:publish`, más `lang/es.json` para los textos de Laravel, Fortify y passkeys. El locale por defecto es `es`.
- **URLs:** las rutas de Fortify quedan en inglés (`/login`, `/register`, `/settings/...`), porque Fortify no permite cambiarlas sin reemplazar sus rutas. Las rutas propias del producto van en español (`/inicio`, `/estudios/{slug}/...`), y las rutas resource usan `/crear` y `/editar`.
- **Tono:** tuteo neutro, sin chilenismos.

### Consecuencias

- Bueno: cero dependencias nuevas, y los componentes se leen tal como se ven en pantalla.
- Bueno: controlamos cada texto, con un español latinoamericano en vez del de España.
- Malo: agregar otro idioma más adelante obligaría a extraer todos los textos a claves. Lo aceptamos porque no está en el alcance.
- Malo: al actualizar el starter kit o publicar nuevos textos de Laravel, hay que traducirlos a mano.
- Neutral: el código, los nombres y los comentarios siguen en inglés. Solo lo que ve el usuario está en español.

### Confirmación

- `tests/Feature/Auth/RegistrationTest.php` verifica que los errores de validación salen en español.
- `tests/Feature/Providers/AppServiceProviderTest.php` verifica el aviso de sesión expirada en español y los verbos `/crear` y `/editar`.
- `phpunit.xml` fija `APP_LOCALE=es`, para que los tests usen el mismo idioma que la aplicación.
- La revisión manual de las pantallas forma parte de cada punto de control.
