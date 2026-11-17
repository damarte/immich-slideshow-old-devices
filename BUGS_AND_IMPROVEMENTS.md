# Bugs y Mejoras — Immich Slideshow for Old Devices

**Fecha:** 2026-07-28  
**Estado:** Analisis completo del proyecto

---

## Tabla de Contenidos

- [Bugs Criticos](#bugs-críticos)
- [Bugs Moderados](#bugs-moderados)
- [Bugs Menores](#bugs-menores)
- [Mejoras de Rendimiento](#mejoras-de-rendimiento)
- [Mejoras de Arquitectura](#mejoras-de-arquitectura)
- [Mejoras de UX](#mejoras-de-ux)
- [Resumen de Prioridades](#resumen-de-prioridades)

---

## Bugs Críticos

---

### BUG-001: ~~Variable de entorno `CSS_BACKGROUND_COLOR` vs `BACKGROUND_COLOR` — Desajuste total~~ ~~SOLUCIONADO~~

| Campo | Detalle |
|---|---|
| **Estado** | **SOLUCIONADO** |
| **Severidad** | CRITICAL |
| **Archivo(s)** | `docker-compose.yml:14`, `docker-compose.prod.yml:14`, `Configuration.php:18` |
| **Impacto** | El color de fondo **nunca** se aplica desde `.env` ni desde docker-compose. Siempre usa el default `#000000`. |

**Solucion aplicada:**

Se renombró `CSS_BACKGROUND_COLOR` → `BACKGROUND_COLOR` en `docker-compose.yml`, `docker-compose.prod.yml`, `.env.example`, y `README.md`. PHP (`Configuration.php`) ya usaba `BACKGROUND_COLOR` correctamente, por lo que no requirió cambios.

**Descripcion:**

El nombre de la variable de entorno es inconsistente entre la definicion de docker-compose y la lectura en PHP.

**Codigo problematico:**

`docker-compose.yml` y `docker-compose.prod.yml`:
```yaml
CSS_BACKGROUND_COLOR=${CSS_BACKGROUND_COLOR:-black}
```

`Configuration.php:18`:
```php
const BACKGROUND_COLOR = 'BACKGROUND_COLOR';
```

`.env.example:5`:
```env
CSS_BACKGROUND_COLOR=#000000
```

**Resultado:**

La cadena `CSS_BACKGROUND_COLOR` se define en docker-compose y `.env`, pero `getenv('BACKGROUND_COLOR')` siempre devuelve `false` porque la variable no existe con ese nombre. El usuario puede configurar `CSS_BACKGROUND_COLOR` en su `.env` y nunca tendra efecto.

**Correccion propuesta:**

Elegir un nombre y mantenerlo en todos lados. Opciones:

**Opción A (recomendada):** Renombrar a `BACKGROUND_COLOR` en todos lados.

`Configuration.php` (sin cambios):
```php
const BACKGROUND_COLOR = 'BACKGROUND_COLOR';
```

`docker-compose.yml`:
```yaml
BACKGROUND_COLOR=${BACKGROUND_COLOR:-black}
```

`docker-compose.prod.yml`:
```yaml
BACKGROUND_COLOR=${BACKGROUND_COLOR:-black}
```

`.env.example`:
```env
BACKGROUND_COLOR=#000000
```

**Opción B:** Renombrar a `CSS_BACKGROUND_COLOR` en PHP.

`Configuration.php:18`:
```php
const BACKGROUND_COLOR = 'CSS_BACKGROUND_COLOR';
```

Esto mantiene compatibilidad con `.env` existente pero requiere actualizar los docker-compose.

**Nota adicional:** El mismo desajuste existe en `index.php:23` donde se usa `Configuration::BACKGROUND_COLOR`, y en `proxy.php:51` que tambien usa `Configuration::BACKGROUND_COLOR`.

---

### BUG-002: ~~`explode()` sobre `null` en `management.php`~~ ~~SOLUCIONADO~~

| Campo | Detalle |
|---|---|
| **Estado** | **SOLUCIONADO** |
| **Severidad** | CRITICAL |
| **Archivo** | `management.php:62` |
| **Impacto** | Warning de PHP 8.1+; si `ALBUM_ID` no esta configurado, `explode()` recibe `null` y puede lanzar `TypeError`. |

**Codigo problematico:**
```php
$album_ids = explode(',', $configuration->get(Configuration::ALBUM_ID)) ?? [];
```

**Analisis:**

El operador null coalescing (`??`) tiene precedencia **mas baja** que la llamada a `explode()`. La evaluacion es:
```php
$album_ids = explode(',', $configuration->get(Configuration::ALBUM_ID)) ?? [];
//                     ^--- si es null, explode() se ejecua con null de todas formas
```

En PHP 8.1+, `explode(',', null)` produce:
```
Warning: explode(): Argument #2 ($string) must be of type string, null given
```

**Solucion aplicada:**
```php
$album_ids = empty($configuration->get(Configuration::ALBUM_ID)) ? [] : explode(',', $configuration->get(Configuration::ALBUM_ID));
```

Se usa `empty()` para cubrir `null`, `""` y otros valores falsy. Si `get()` retorna un valor valido, se ejecuta `explode()` sobre el string.

---

## Bugs Moderados

---

### BUG-003: ~~Sin timeouts en llamadas cURL~~ ~~SOLUCIONADO~~

| Campo | Detalle |
|---|---|
| **Estado** | **SOLUCIONADO** |
| **Severidad** | MODERATE |
| **Archivo** | `ImmichApi.php` (todas las funciones) |
| **Impacto** | Si el servidor Immich no responde, los workers PHP-FPM se quedan bloqueados indefinidamente hasta que nginx timeout (por defecto 60s). En un album con muchas paginas, esto puede agotar todos los workers. |

**Solucion aplicada:**

Se agregaron constantes `CONNECT_TIMEOUT = 10` y `OPERATION_TIMEOUT = 30` a la clase. Se creo un metodo privado `applyCurlOptions()` que centraliza las opciones comunes (timeouts, API key, headers) y se refactorizaron los 3 metodos (`getAlbums()`, `searchAssets()`, `getAsset()`) para usarlo. Esto elimina duplicacion y asegura que toda request a Immich falla en maximo 30s.

---

### BUG-004: `thumbnail.php` usa `getenv()` directamente en lugar de `Configuration`

| Campo | Detalle |
|---|---|
| **Severidad** | MODERATE |
| **Archivo** | `thumbnail.php:5-6` |
| **Impacto** | Si un usuario configura valores a traves de la Management UI (que escribe `config.json`), `thumbnail.php` los ignorara porque lee `getenv()` directamente. |

**Codigo problematico:**
```php
$immich_url = getenv('IMMICH_URL');
$immich_api_key = getenv('IMMICH_API_KEY');
```

**Comportamiento inconsistente:**

| Archivo | Lectura de config |
|---|---|
| `index.php` | `new Configuration()` (lee config.json + env) |
| `proxy.php` | `new Configuration()` (lee config.json + env) |
| `management.php` | `new Configuration()` (lee config.json + env) |
| `thumbnail.php` | `getenv()` solo (NO lee config.json) |

**Adicional:** `getenv()` devuelve `false` para variables no existentes. Si `IMMICH_URL` o `IMMICH_API_KEY` no estan en el entorno, el constructor de `ImmichApi` recibira `false` en lugar de `null` o cadena vacia.

**Correccion propuesta:**
```php
require_once './ImmichApi.php';
require_once './Configuration.php';

$configuration = new Configuration();
$immich_url = $configuration->get(Configuration::IMMICH_URL);
$immich_api_key = $configuration->get(Configuration::IMMICH_API_KEY);
```

---

### BUG-005: Condicion `dead code` en `ImmichApi.php:47`

| Campo | Detalle |
|---|---|
| **Severidad** | MODERATE |
| **Archivo** | `ImmichApi.php:47` |
| **Impacto** | La rama `$response === false` es inalcanzable. No causa mal comportamiento pero confunde la lectura del codigo. |

**Codigo problematico:**
```php
$response = curl_exec($ch);
if ($response === false) {
    $error = "Error: " . curl_error($ch);
    error_log($error);
    throw new Exception($error);
}

$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($http_code !== 200 || $response === false) {
//                      ^^^^^^^^^^^^^^^^^
//                      ALREADY CHECKED ABOVE — UNREACHABLE
```

**Correccion propuesta:**
```php
if ($http_code !== 200) {
    $error = "HTTP error $http_code when connecting to Immich: $response";
    error_log($error);
    throw new Exception($error);
}
```

---

### BUG-006: `entrypoint.sh` sin manejo de errores ni `wait`

| Campo | Detalle |
|---|---|
| **Severidad** | MODERATE |
| **Archivo** | `entrypoint.sh` |
| **Impacto** | Si nginx falla, php-fpm se ejecuta de todas formas y el contenedor queda en un estado inconsistente. Ademas, si nginx termina, el script termina y el contenedor se para. |

**Codigo problematico:**
```bash
#!/bin/bash
nginx
php-fpm
```

**Problemas:**

1. Sin `set -e`: si nginx falla, php-fpm se ejecua igual
2. nginx se ejecuta en primer plano, php-fpm nunca se alcanza si nginx no termina
3. nginx en primer plano podria terminar en algun caso (ej: config invalida tras un reload)
4. No hay healthcheck ni senal de gracia

**Correccion propuesta:**
```bash
#!/bin/bash
set -e

# Start php-fpm in background
php-fpm &

# Start nginx in foreground
nginx -g 'daemon off;'
```

O alternativamente con `exec`:
```bash
#!/bin/bash
set -e

php-fpm &
wait %1
```

---

## Bugs Menores

---

### BUG-007: `<input type="color">` sin valor inicial + funcion JS frágil

| Campo | Detalle |
|---|---|
| **Severidad** | LOW |
| **Archivo** | `management.php:161`, `management.php:188-198` |
| **Impacto** | Si el color no es un nombre CSS valido, la funcion `colorNameToHex` produce un error JS silencioso. |

**Codigo problematico:**
```javascript
function colorNameToHex(color) {
    const temp = document.createElement("div");
    temp.style.color = color;
    document.body.appendChild(temp);

    const style = window.getComputedStyle(temp).color;
    document.body.removeChild(temp);

    const rgb = style.match(/\d+/g).map(Number);
    return "#" + rgb.map(x => x.toString(16).padStart(2, '0')).join('');
}
```

**Problemas:**

1. Si `color` es un valor invalido (ej: `"none"`), `getComputedStyle` devuelve `"rgb(0, 0, 0)"` (default), no el valor esperado.
2. Si el usuario establece `CSS_BACKGROUND_COLOR` como un nombre CSS no valido, el input de color mostrara `#000000` incorrectamente.
3. Si `style.match(/\d+/g)` devuelve `null` (imposible en practice pero posible en edge cases), `.map(Number)` lanzara un error.

**Correccion propuesta:**
```javascript
function colorNameToHex(color) {
    const temp = document.createElement("div");
    temp.style.color = color;
    document.body.appendChild(temp);

    const style = window.getComputedStyle(temp).color;
    document.body.removeChild(temp);

    const match = style.match(/\d+/g);
    if (!match || match.length < 3) return "#000000";

    const rgb = match.map(Number);
    return "#" + rgb.map(x => x.toString(16).padStart(2, '0')).join('');
}
```

---

### BUG-008: IDs de album sin sanitizacion en HTML attributes

| Campo | Detalle |
|---|---|
| **Severidad** | LOW |
| **Archivo** | `management.php:116-117` |
| **Impacto** | Si Immich retorna un ID con caracteres especiales (`"`, `'`, `>`), podria romper el HTML o inyectar atributos. Riesgo bajo ya que los IDs de Immich son UUIDs. |

**Codigo problematico:**
```php
<label for="<?= $album['id'] ?>">
    <input type="checkbox" name="album_ids[]" id="<?= $album['id'] ?>" value="<?= $album['id'] ?>">
```

**Correccion propuesta:**
```php
<label for="<?= htmlspecialchars($album['id']) ?>">
    <input type="checkbox" name="album_ids[]" id="<?= htmlspecialchars($album['id']) ?>" value="<?= htmlspecialchars($album['id']) ?>">
```

---

### BUG-009: `getAlbums()` sin paginacion

| Campo | Detalle |
|---|---|
| **Severidad** | LOW |
| **Archivo** | `ImmichApi.php:28-61` |
| **Impacto** | Si el usuario tiene mas albums de los que Immich retorna en una sola respuesta, no todos apareceran en la Management UI. |

**Analisis:**

La API de Immich puede limitar el numero de albums retornados. Si un usuario tiene 200 albums, es posible que solo vea los primeros 100.

**Correccion propuesta:**

Verificar si la API de Immich soporta paginacion para `/api/albums` y agregar paginacion si es el caso. Si la API retorna todos en una respuesta, agregar un comentario explicativo.

---

## Mejoras de Rendimiento

---

### IMP-001: Sin caché de imagenes procesadas en disco

| Campo | Detalle |
|---|---|
| **Prioridad** | ALTA |
| **Archivo** | `proxy.php` |
| **Impacto** | Cada request a `proxy.php` descarga la imagen de Immich, la decodifica con GD, la redimensiona y la codifica como JPEG. Con multiples dispositivos o recargas frecuentes, esto genera carga innecesaria. |

**Analisis:**

El flujo actual para cada imagen:
```
Browser → proxy.php → Immich API (HTTP) → GD decode → GD resize → GD encode → Browser
```

Con 50 fotos a 5s cada una, el slideshow hace ~10 requests/minuto. Con 3 dispositivos, son 30 requests/minuto, todos haciendo el mismo trabajo de procesamiento.

**Propuesta de solucion:**

Implementar caché en disco con un hash del URL completo:

```php
// Al inicio de proxy.php, despues de validar params
$cacheKey = md5($asset_id . $screen_width . $screen_height . $image_quality);
$cacheDir = '/tmp/slideshow_cache';
$cacheFile = $cacheDir . '/' . $cacheKey . '.jpg';

// Ensure cache directory exists
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}

// Check cache
if (file_exists($cacheFile)) {
    $age = time() - filemtime($cacheFile);
    if ($age < 86400) { // 24h TTL
        header('Content-Type: image/jpeg');
        header('Cache-Control: public, max-age=86400');
        readfile($cacheFile);
        exit;
    }
}

// ... process image ...

// Save to cache
file_put_contents($cacheFile, $imageData);
```

**Consideraciones:**

- Limpiar caché periodicamente (cron o al iniciar el contenedor)
- Limitar tamaño maximo del caché (ej: 500MB) con LRU eviction
- El volumen `/tmp` en Docker se pierde al reiniciar el contenedor; considerar un volumen dedicado

---

### IMP-002: `IMAGE_QUALITY` no configurable desde Management UI

| Campo | Detalle |
|---|---|
| **Prioridad** | MEDIA |
| **Archivo** | `management.php` |
| **Impacto** | Los usuarios que usan la Management UI no pueden cambiar entre `preview` y `fullsize`, aunque el slideshow soporta ambos modos. |

**Propuesta de solucion:**

Agregar campo al formulario:
```php
// En la seccion de footer
<div class="flex" style="flex:1; flex-direction: column;">
    <label for="image_quality">Image quality</label>
    <select name="image_quality" id="image_quality">
        <option value="preview" <?= ($image_quality ?? 'preview') === 'preview' ? 'selected' : '' ?>>Preview (recommended)</option>
        <option value="fullsize" <?= ($image_quality ?? 'preview') === 'fullsize' ? 'selected' : '' ?>>Full size</option>
    </select>
</div>
```

Y guardarlo en el POST:
```php
Configuration::IMAGE_QUALITY => $_POST['image_quality'],
```

---

### IMP-003: Sin limpieza de imagenes en memoria en `proxy.php`

| Campo | Detalle |
|---|---|
| **Prioridad** | MEDIA |
| **Archivo** | `proxy.php:34-40` |
| **Impacto** | La imagen fuente se libera con `unset($source)` (linea 115) pero `$data[1]` se vacia manualmente en linea 40. El patrón es inconsistente y podria no liberar memoria en algunos casos. |

**Codigo actual:**
```php
$source = imagecreatefromstring($data[1]);
// ...
$data[1] = '';          // Manual clear (linea 40)
// ... procesado ...
unset($source);         // Proper unset (linea 115)
```

**Propuesta:**
```php
$source = imagecreatefromstring($data[1]);
$data[1] = '';           // Liberar string binario
unset($data);            // Liberar array completo
// ... procesado ...
imagedestroy($source);   // Mejor que unset para recursos GD
imagedestroy($resized);  // Liberar imagen procesada despues de output
```

---

## Mejoras de Arquitectura

---

### IMP-004: Falta de autenticacion en la Management UI

| Campo | Detalle |
|---|---|
| **Prioridad** | ALTA |
| **Archivo** | `management.php` |
| **Impacto** | Cualquier persona con acceso a `http://device:8080/management.php` puede modificar la configuracion del slideshow. Si el dispositivo esta en red local sin proteccion, esto es un riesgo. |

**Propuesta de solucion:**

Opciones por orden de complejidad:

1. **Basic Auth en nginx (simple, recomendado):**
```nginx
location /management.php {
    auth_basic "Admin Area";
    auth_basic_user_file /etc/nginx/.htpasswd;
}
```

2. **Variable de entorno `MANAGEMENT_PASSWORD`:**
```php
$management_password = $configuration->get('MANAGEMENT_PASSWORD');
if ($management_password && (!isset($_COOKIE['mgmt_auth']) || $_COOKIE['mgmt_auth'] !== hash('sha256', $management_password . $_SERVER['REMOTE_ADDR']))) {
    // redirect to login
}
```

3. **Separar puerto de management:** Diferente puerto o path protegido.

---

### IMP-005: Docker — volumen de desarrollo sobrescribe `chown`

| Campo | Detalle |
|---|---|
| **Prioridad** | MEDIA |
| **Archivo** | `docker-compose.yml:21`, `Dockerfile:36` |
| **Impacto** | El volumen `./public:/var/www/html` monta los archivos del host, sobrescribiendo los permisos configurados en el Dockerfile. Si los archivos del host son owned por root, PHP-FPM (www-data) no podra escribir `config.json`. |

**Codigo problematico:**

`Dockerfile:36`:
```dockerfile
RUN chown -R www-data:www-data /var/www/html
```

`docker-compose.yml:21`:
```yaml
volumes:
    - ./public:/var/www/html
```

El volumen monta `./public` sobre `/var/www/html`, haciendo que el `chown` del Dockerfile sea irrelevante.

**Propuesta de solucion:**

Opcion A — Configurar permissions en el host antes de ejecutar:
```bash
chmod -R 755 ./public
```

Opcion B — Usar `user` en docker-compose:
```yaml
user: "${UID:-1000}:${GID:-1000}"
```

Opcion C — Mapear solo archivos especificos:
```yaml
volumes:
    - ./public/ImmichApi.php:/var/www/html/ImmichApi.php
    - ./public/management.php:/var/www/html/management.php
```

---

### IMP-006: No hay healthcheck en Docker

| Campo | Detalle |
|---|---|
| **Prioridad** | MEDIA |
| **Archivo** | `docker-compose.yml`, `Dockerfile` |
| **Impacto** | Docker no puede verificar si el servicio esta funcionando correctamente. Si PHP-FPM se crasha pero nginx sigue corriendo, Docker no detectara el fallo. |

**Propuesta:**

Agregar healthcheck al docker-compose:
```yaml
healthcheck:
    test: ["CMD", "curl", "-f", "http://localhost/"]
    interval: 30s
    timeout: 10s
    retries: 3
    start_period: 10s
```

O en el Dockerfile:
```dockerfile
HEALTHCHECK --interval=30s --timeout=10s --retries=3 \
    CMD curl -f http://localhost/ || exit 1
```

---

### IMP-007: Falta soporte para assets archivados o eliminados

| Campo | Detalle |
|---|---|
| **Prioridad** | BAJA |
| **Archivo** | `ImmichApi.php:157` |
| **Impacto** | El slideshow filtra `isArchived` y `isTrashed` al construir la lista. Pero si una foto se archiva o elimina **despues** de cargar el slideshow, la proxima request a `proxy.php` fallara con un error 404 silencioso. |

**Propuesta:**

En `ImmichApi.php::getAsset()`, agregar manejo explicito para HTTP 404:
```php
if ($http_code === 404) {
    throw new Exception("Asset not found (may have been archived or deleted)");
}
```

Y en `main.js`, el handler de error del img podria saltar a la siguiente foto automaticamente:
```javascript
currentImg.addEventListener("error", function() {
    nextImage();  // Saltar automaticamente
});
```

---

## Mejoras de UX

---

### IMP-008: `window.location.reload()` al completar el slideshow

| Campo | Detalle |
|---|---|
| **Prioridad** | MEDIA |
| **Archivo** | `main.js:129` |
| **Impacto** | Recargar la pagina completa al dar la vuelta sobre todas las fotos causa un parpadeo y pierde el estado actual (ej: si el usuario pauso manualmente). |

**Codigo problematico:**
```javascript
if (currentIndex === 0) {
    window.location.reload();
    return;
}
```

**Propuesta de solucion:**

Reiniciar el slideshow sin recargar:
```javascript
if (currentIndex === 0) {
    // Refetch photos to get updates
    location.href = location.pathname + location.search;
    return;
}
```

O mejor, simplemente continuar sin recargar:
```javascript
// Eliminar el reload y dejar que el slideshow loop infinitamente
// Los cambios en album se detectan al siguiente refresh manual
```

---

### IMP-009: Sin indicador visual de transicion entre fotos

| Campo | Detalle |
|---|---|
| **Prioridad** | BAJA |
| **Archivo** | `main.js`, `main.css` |
| **Impacto** | El usuario no tiene feedback visual de que una foto esta por cambiar. En dispositivos antiguos, el fade puede ser abrupto. |

**Propuesta:**

Agregar una barra de progreso sutil en la parte inferior:
```css
.progress-bar {
    position: fixed;
    bottom: 0;
    left: 0;
    height: 3px;
    background: rgba(255, 255, 255, 0.5);
    width: 0%;
    transition: width linear;
    z-index: 1001;
}
```

Y en JS, resetear la barra en cada transicion:
```javascript
function scheduleNextTransition() {
    if (!isPaused) {
        const bar = document.getElementById('progress-bar');
        bar.style.transition = 'none';
        bar.style.width = '0%';
        bar.offsetHeight; // Force reflow
        bar.style.transition = `width ${duration}ms linear`;
        bar.style.width = '100%';

        timeoutId = setTimeout(nextImage, duration);
    }
}
```

---

### IMP-010: No hay manejo de videos en albums

| Campo | Detalle |
|---|---|
| **Prioridad** | BAJA |
| **Archivo** | `ImmichApi.php` |
| **Impacto** | Si un album contiene videos, estos seran incluidos en el slideshow pero mostraran como imagenes rotas. |

**Propuesta de solucion:**

Filtrar por tipo de asset en `searchAssets()`:
```php
if (!isset($asset['id']) || $asset['isArchived'] || $asset['isTrashed']) {
    continue;
}

// Skip non-image assets
if (($asset['type'] ?? 'IMAGE') !== 'IMAGE') {
    continue;
}
```

---

## Resumen de Prioridades

| ID | Tipo | Severidad | Estado | Impacto |
|---|---|---|---|---|
| **BUG-001** | Bug | CRITICAL | ✅ Solucionado | Color de fondo no funciona nunca |
| **BUG-002** | Bug | CRITICAL | ✅ Solucionado | Warning PHP 8.1+ en management UI |
| **BUG-003** | Bug | MODERATE | ✅ Solucionado | Timeouts agregados a todas las llamadas cURL |
| **BUG-004** | Bug | MODERATE | ⏳ Pendiente | Thumbnails ignoran config.json |
| **BUG-005** | Bug | MODERATE | ⏳ Pendiente | Dead code confuso |
| **BUG-006** | Bug | MODERATE | ⏳ Pendiente | Entrypoint sin error handling |
| **BUG-007** | Bug | LOW | ⏳ Pendiente | Color picker JS frágil |
| **BUG-008** | Bug | LOW | ⏳ Pendiente | IDs sin sanitizacion HTML |
| **BUG-009** | Bug | LOW | ⏳ Pendiente | Albums sin paginacion |
| **IMP-001** | Mejora | ALTA | ⏳ Pendiente | Caché de imagenes en disco |
| **IMP-002** | Mejora | MEDIA | ⏳ Pendiente | IMAGE_QUALITY en Management UI |
| **IMP-003** | Mejora | MEDIA | ⏳ Pendiente | Limpieza de memoria GD |
| **IMP-004** | Mejora | ALTA | ⏳ Pendiente | Autenticacion en Management UI |
| **IMP-005** | Mejora | MEDIA | ⏳ Pendiente | Permisos de volumen Docker |
| **IMP-006** | Mejora | MEDIA | ⏳ Pendiente | Healthcheck Docker |
| **IMP-007** | Mejora | BAJA | ⏳ Pendiente | Manejo de assets eliminados |
| **IMP-008** | Mejora | MEDIA | ⏳ Pendiente | Reload suave del slideshow |
| **IMP-009** | Mejora | BAJA | ⏳ Pendiente | Barra de progreso visual |
| **IMP-010** | Mejora | BAJA | ⏳ Pendiente | Filtrar videos del slideshow |

---

## Fixes de mayor impacto (recomendados como primer paso)

1. **BUG-001** ✅ ~~(1 archivo, cambio trivial)~~ ~~Renombrar `CSS_BACKGROUND_COLOR` → `BACKGROUND_COLOR` consistentemente~~
2. **BUG-002** ✅ ~~(1 linea)~~ ~~Arreglar precedencia del operador `??`~~
3. **BUG-003** ✅ ~~(3 lugares)~~ ~~Agregar timeouts a cURL~~
4. **BUG-004** (2 lineas) — Unificar lectura de config en `thumbnail.php`
5. **BUG-006** (entrypoint) — Arreglar script de inicio del contenedor

Estos 5 fixes resuelven los problemas mas criticos con minimo esfuerzo y riesgo.
