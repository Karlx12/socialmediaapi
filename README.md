# Social Media API

This microservice provides endpoints to publish posts to Meta platforms (Facebook and Instagram) using the Meta Graph API. It integrates with the core-domain package for data persistence.

Base route prefix: `/api/socialmedia`

All endpoints require authentication via Bearer token (`Authorization: Bearer <token>`).

How to get a Sanctum token (local/dev)

There are two quick ways to obtain a token for local testing and store it in a shell variable called $TOKEN:

1) If there's a SANCTUM_TOKEN in the project's `.env`, export it directly:

```bash
# run this from the project root
export TOKEN=$(grep -E '^SANCTUM_TOKEN=' .env | cut -d'=' -f2-)

# Social Media API

Este microservicio expone endpoints para publicar contenido en plataformas Meta (Facebook e Instagram) usando la Graph API.

Base route prefix: `/api/socialmedia`

IMPORTANTE: Todas las llamadas a los endpoints protegidos requieren autenticación con un token Sanctum en el header `Authorization: Bearer <token>`.

--------------------------------------------------------------------------------
Índice rápido
- Autenticación (obtener token)
- Variables de entorno necesarias
- Endpoints (Facebook, Instagram, Chats)
- Flujo recomendado (funcionó)
- Ejemplos incorrectos (por qué fallan)
- Códigos de error comunes y limpieza
--------------------------------------------------------------------------------

1) Autenticación — cómo obtener un token Sanctum (local/dev)

Hay varias formas de obtener un token para pruebas locales. Usar una cuenta que ya exista en la base de datos (por ejemplo `admin@incadev.com`) es la opción recomendada en desarrollo.

- Opción A — usar el `SANCTUM_TOKEN` preexistente en `.env` (rápido para dev):

```bash
# desde la raíz del proyecto
export TOKEN=$(grep -E '^SANCTUM_TOKEN=' .env | cut -d'=' -f2-)
echo "$TOKEN"
```

- Opción B — crear un token para un usuario existente usando Tinker (ejemplo con `admin@incadev.com`):

```bash
# crea y muestra el token (sustituir email según tu BD)
php artisan tinker --execute "if (\$u=\App\\Models\\User::where('email','admin@incadev.com')->first()) echo \$u->createToken('cli')->plainTextToken;"

# exportarlo directamente a la variable TOKEN
export TOKEN=$(php artisan tinker --execute "if (\$u=\App\\Models\\User::where('email','admin@incadev.com')->first()) echo \$u->createToken('cli')->plainTextToken;")
echo "$TOKEN"
```

Nota: si el usuario no existe, la llamada Tinker devolverá `null` y deberás crear el usuario o usar otro email existente.

2) Variables de entorno importantes

- `META_PAGE_ACCESS_TOKEN`: Page access token (usado para publicar en páginas y para WhatsApp/Messenger)
- `META_PAGE_ID`: Facebook Page ID (opcional si se pasa en la request)
- `META_IG_USER_ID`: Instagram Business/Creator user id
- `META_IG_ACCESS_TOKEN`: Instagram-specific token (opcional)

3) Endpoints principales

Publicar en Facebook
- Método: `POST`
- URL: `/api/socialmedia/posts/facebook`
- Parámetros (form-data o x-www-form-urlencoded):
  - `message` (string, opcional)
  - `link` (url, opcional)
  - `image_url` (url, opcional)
  - `image` (file, opcional; se envía como multipart `-F image=@path`)
  - `video` (file, opcional)
  - `page_id`, `access_token`, `campaign_id` (opcionales)

Ejemplo — publicar texto (x-www-form-urlencoded):
```bash
curl -X POST "http://localhost:8000/api/socialmedia/posts/facebook" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "message=Hola Mundo desde la API&campaign_id=1"
```

Ejemplo — subir archivo local (multipart) (recomendado si la URL pública falla):
```bash
curl -X POST "http://localhost:8000/api/socialmedia/posts/facebook" \
  -H "Authorization: Bearer $TOKEN" \
  -F "image=@/ruta/a/tu/image.png" \
  -F "message=Subida de imagen para prueba"
```

Publicar en Instagram
- Método: `POST`
- URL: `/api/socialmedia/posts/instagram`
- Parámetros (form-data o x-www-form-urlencoded):
  - `image_url` (string, requerido si no se sube `image`)
  - `image` (file, opcional; upload alternativo)
  - `caption`, `ig_user_id`, `access_token`, `campaign_id` (opcionales)

Ejemplo — publicar usando `image_url` directo (puede fallar si la URL no es aceptada por Meta):
```bash
curl -X POST "http://localhost:8000/api/socialmedia/posts/instagram" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "image_url=https://example.com/image.jpg&caption=Mi foto&campaign_id=1"
```

4) Flujo recomendado (funcionó en pruebas)

En nuestra sesión de pruebas el flujo que funcionó fue:

1. Subir la imagen local al endpoint de Facebook usando multipart (`/posts/facebook`). El microservicio sube la foto a la página y devuelve un `photo id`.
   - Ejemplo de respuesta tras la subida (simplificado):
   ```json
   {"id":"122107415721088743", "post_id":"819795971219628_122107415739088743"}
   ```

2. Construir una URL pública de la foto subida usando el `photo id` y el `META_PAGE_ACCESS_TOKEN`:

```
https://graph.facebook.com/122107415721088743/picture?access_token=<META_PAGE_ACCESS_TOKEN>
```

3. Llamar al endpoint de Instagram (`/posts/instagram`) usando esa `image_url` (la Graph URL es aceptada por Meta) y obtener la publicación en Instagram.

Ejemplo de resultado exitoso recibido:
```json
{"meta_post_id":"18369241954085742","post_id":10,"data":{"id":"18369241954085742"}}
```

HTTP: 200 OK

5) Ejemplos incorrectos (marcados como tales)

- INCORRECTO #1 — URL pública con redirecciones o ubicación que Meta no puede descargar:
```bash
# INCORRECTO — puede devolver media_creation_failed
curl -X POST "http://localhost:8000/api/socialmedia/posts/instagram" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "image_url=https://via.placeholder.com/1200x630.jpg&caption=Test&campaign_id=1"
```

- INCORRECTO #2 — host que devuelve HTML/redirección al intentar recuperar la imagen (transfer.sh, algunos CDN con redirect):
```bash
# INCORRECTO — puede devolver media_creation_failed o error de descarga
curl -X POST "http://localhost:8000/api/socialmedia/posts/instagram" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "image_url=https://0x0.st/KWlQ.png&caption=Test&campaign_id=1"
```

Si recibes un error del tipo `media_creation_failed` con detalles que indiquen que Meta no pudo recuperar el URI, usa el flujo recomendado (subir a Facebook y usar la URL graph.facebook.com/<photo_id>/picture).

6) Códigos de error comunes

- 200 OK — llamada exitosa; en publicaciones se devuelve `meta_post_id` y `post_id`.
- 400 Bad Request — validación o payload inválido.
- 401 Unauthorized — token faltante o inválido.
- 404 Not Found — recurso inexistente (p.ej. campaign_id inválido). En ese caso la API devuelve:

```json
{"error":"Campaign not found","code":"CAMPAIGN_NOT_FOUND"}
```
- 502 Bad Gateway — error o respuesta inesperada del API de Meta (upstream). La respuesta incluirá detalles del error recibido desde Meta.

7) Limpieza — revocar tokens (opcional)

```bash
# eliminar todos los tokens del usuario admin@incadev.com
php artisan tinker --execute "\$u=\App\\Models\\User::where('email','admin@incadev.com')->first(); if(\$u) \$u->tokens()->delete(); echo 'done';"
```

--------------------------------------------------------------------------------
Si quieres, puedo:
- Añadir un pequeño comando `artisan` de ayuda para crear un usuario + token en entornos de desarrollo.
- Añadir tests que simulen las respuestas de Meta y verifiquen el controlador.

Si quieres que haga alguno de los dos, dímelo y lo dejo listo.


Este flujo (subir a Facebook -> usar la imagen subida para Instagram) es la forma confiable que se usó cuando `image_url` directo fue rechazado por el API de Meta.

## Ejemplos incorrectos (marcados como tales)

- Ejemplo incorrecto #1 — usar una URL pública que realiza redirecciones o no permite que Meta la recupere (rechazado por el crawler):

```bash
# INCORRECTO — puede fallar con "media_creation_failed" por no poder recuperar la URL
curl -X POST "http://localhost:8000/api/socialmedia/posts/instagram" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "image_url=https://via.placeholder.com/1200x630.jpg&caption=Test IG post&campaign_id=1"
```

- Ejemplo incorrecto #2 — usar un host que devuelve HTML o redirecciones al intentar servir el archivo (transfer.sh returned a redirect HTML in one attempt):

```bash
# INCORRECTO — el host/URL no es adecuado para descargar la imagen desde Meta
curl -X POST "http://localhost:8000/api/socialmedia/posts/instagram" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "image_url=https://0x0.st/KWlQ.png&caption=Test IG post&campaign_id=1"
```

En ambos casos la API de Meta puede devolver:

```json
{"error":"media_creation_failed","details":{...}}
```

Si ves ese error, sube el archivo al endpoint de Facebook (`/posts/facebook`) usando multipart (`-F "image=@path/to/file"`) y luego publica en Instagram con la URL construida a partir del `photo id`.
