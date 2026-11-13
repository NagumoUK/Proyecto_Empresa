# Proyecto_Empresa

## Actividad de la empresa en tiempo real

El dashboard permite registrar movimientos del equipo. Las actividades se guardan
con el usuario que las creó y se emiten a los demás usuarios conectados mediante
un canal privado de Ably Pub/Sub.

El `.env` ya deja configurado `BROADCAST_CONNECTION=ably` y el espacio
`ABLY_KEY=` para que agregues una clave **Root** nueva de Ably. Mientras la clave
esté vacía, Laravel usa temporalmente el driver `log` para permitir comandos de
configuración y registra una advertencia; al completar la clave y limpiar la
configuración, Ably queda activo. No uses una clave `Subscribe only` ni publiques
la clave Root en React, `VITE_*`, Git o este repositorio. La aplicación emite
eventos desde Laravel y autoriza a cada usuario autenticado con permiso de solo
suscripción al canal privado `activities`. El token de cliente expira en una
hora por defecto.

Las claves compartidas anteriormente deben revocarse en el panel de Ably antes
de continuar. Después de guardar la nueva clave Root en `.env`, limpia la
configuración cacheada:

```bash
php artisan config:clear
```

El cliente React se conecta con autenticación del endpoint de broadcasting de
Laravel; no necesita ninguna clave de Ably en el navegador.

### Notificaciones por correo de actividades

Al registrar una actividad, Laravel encola un correo para cada usuario que ya
haya verificado su dirección. Completa `MAIL_MAILER=smtp`, `MAIL_HOST`,
`MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` y
`MAIL_FROM_NAME` en el `.env` con los datos de tu proveedor SMTP. En desarrollo,
`MAIL_MAILER=log` escribe los mensajes en el log en lugar de enviarlos.

La conexión de cola predeterminada es `database`; aplica las migraciones y deja
un worker ejecutándose para procesar las notificaciones:

```bash
php artisan migrate
php artisan queue:work
```

Si no hay un worker en ejecución, los correos quedan pendientes en la tabla
`jobs` y no se envían hasta que la cola se procese. Las notificaciones se
programan después de confirmar la transacción de base de datos.

Las actividades también se guardan en la tabla `notifications` de Supabase y
se emiten por Ably al canal privado del usuario. La campanita muestra avisos
recientes y pendientes, permite marcarlos como leídos y actualiza la lista al
recibir avisos en tiempo real. Si el usuario está desconectado, los recupera al
volver a entrar. Aplica la migración con `php artisan migrate`; el mismo worker
de `queue:work` procesa la persistencia, la emisión en Ably y el correo.

### Espacio colaborativo y clima

El dashboard usa el canal de presencia `workspace` de Ably para mostrar a los
compañeros conectados. Solo usuarios con correo verificado pueden unirse. El
campo de actividad envía avisos de escritura como eventos de cliente de Ably,
limitados en el navegador; el texto no se transmite y estos eventos no pasan
por Laravel. La campanita conserva el flujo persistente descrito arriba.

La tarjeta del clima consulta Open-Meteo para Lima y guarda una respuesta válida
en caché durante diez minutos. Puedes cambiar la ubicación con
`WEATHER_LOCATION`, `WEATHER_LATITUDE`, `WEATHER_LONGITUDE` y `WEATHER_TIMEZONE`;
`WEATHER_API_ENDPOINT` permite elegir otro endpoint compatible. No se requiere
una clave API. Si el servicio no está disponible, el dashboard muestra el
estado de indisponibilidad y mantiene el resto de la página operativo.

## Base de datos Supabase

La conexión PostgreSQL ya está seleccionada en `.env` (`DB_CONNECTION=pgsql`)
y en `.env.example`. En Supabase, abre **Connect** y copia los datos de
**Session pooler** en `DB_HOST`, `DB_PORT` y `DB_USERNAME`; el host y el usuario
pueden diferir de la conexión directa. Usa `postgres` como `DB_DATABASE`, la
contraseña de la base de datos en `DB_PASSWORD` y conserva `DB_SSLMODE=require`.
No compartas ni subas el archivo `.env`. En Windows, PHP debe tener habilitada
la extensión `pdo_pgsql`. Después de completar las credenciales y habilitar la
extensión, ejecuta las migraciones:

```bash
php artisan migrate
```

### Usar Docker en Windows

#### Perfil de desarrollo

El despliegue existente de `compose.yaml` permanece sin cambios. Para desarrollar
sin compartir sus contenedores ni volúmenes, inicia el perfil separado:

```powershell
docker compose -p proyecto-empresa-dev -f compose.yaml -f compose.dev.yaml --profile development up --build
```

La aplicación de desarrollo queda disponible en `http://localhost:8001` y Vite
en el puerto `5173`. Los contenedores `app` y `queue` incluyen PHP, Composer,
Node.js, npm, pnpm y las dependencias de desarrollo. `vite` ejecuta el servidor
de assets. `web` solo contiene Nginx, como corresponde a su función; que no
tenga PHP o Node instalados es esperado.

El worker `queue` se deja fuera del arranque predeterminado para evitar que
procese trabajos de la base configurada en `.env` accidentalmente. Si ya
configuraste una base de desarrollo, puedes iniciarlo agregando
`--profile development-queue` al comando de inicio. Revisa las credenciales de
la base antes de ejecutar migraciones o iniciar ese worker.

Para comprobar herramientas, ejecuta los comandos en el servicio que las
incluye:

```powershell
docker compose -p proyecto-empresa-dev -f compose.yaml -f compose.dev.yaml --profile development exec app php -m
docker compose -p proyecto-empresa-dev -f compose.yaml -f compose.dev.yaml --profile development exec app composer --version
docker compose -p proyecto-empresa-dev -f compose.yaml -f compose.dev.yaml --profile development exec app node --version
docker compose -p proyecto-empresa-dev -f compose.yaml -f compose.dev.yaml --profile development exec app npm --version
docker compose -p proyecto-empresa-dev -f compose.yaml -f compose.dev.yaml --profile development exec app pnpm --version
```

Detén el entorno sin borrar sus datos:

```powershell
docker compose -p proyecto-empresa-dev -f compose.yaml -f compose.dev.yaml --profile development --profile development-queue down
```

Como alternativa a instalar `pdo_pgsql` en el PHP de Herd, instala e inicia
Docker Desktop. Desde la carpeta del proyecto, construye el contenedor PHP que
incluye `pdo_pgsql` y Composer:

```powershell
docker compose build
docker compose run --rm app composer install --no-interaction --prefer-dist
docker compose run --rm app php artisan db:show --database=pgsql --no-interaction
```

Si la comprobación de conexión funciona, crea las tablas con Laravel y arranca
la aplicación:

```powershell
docker compose run --rm app php artisan migrate --force
docker compose up
```

La aplicación estará disponible en `http://localhost:8000`. Compose lee las
credenciales del `.env` local; Docker no las incorpora a la imagen. Para
detenerla, pulsa `Ctrl+C`. La primera instalación de Composer puede tardar unos
minutos.

`database/database.sql` incluye el esquema equivalente para la configuración
inicial manual en un proyecto Supabase vacío, incluidas las tablas Laravel de
sesiones, caché y colas. Se recomienda usar `php artisan migrate`; ejecuta el
script SQL solo como alternativa en una base vacía. El script registra las
migraciones para que Artisan reconozca las tablas si luego consultas su estado.

### Archivos adjuntos de proyectos

Los proyectos aceptan adjuntos privados PDF, documentos Word/Excel, CSV,
imágenes PNG/JPG/WebP y texto plano, con un máximo de 20 MB por archivo. Se
guardan en el disco configurado con `PROJECT_FILES_DISK` (`local` por defecto,
bajo `storage/app/private`) y se sirven únicamente mediante una ruta autenticada
de descarga. Para cambiar el disco, configúralo en el `.env`; el driver S3
requiere sus credenciales y el adaptador Flysystem correspondiente.

Aplica el cambio de esquema con:

```bash
php artisan migrate
```

La pantalla de detalle del proyecto permite cargar, descargar y eliminar
adjuntos. El tamaño se presenta según el idioma del navegador, y Laravel usa
helpers como `to_route()` para redirigir después de las operaciones y `report()`
para registrar fallos al limpiar un archivo si falla su registro en la base.

Para depurar registros antiguos, primero revisa cuántos se eliminarían y luego
ejecuta la limpieza:

```bash
php artisan activities:prune --days=90 --pretend
php artisan activities:prune --days=90
```

### Eliminación recuperable, auditoría y funciones opcionales

Clientes, proyectos y documentos financieros usan eliminación lógica mediante
`deleted_at`. Las solicitudes de eliminación aprobadas mantienen sus
referencias: no borran archivos ni partidas y no restauran registros
relacionados. La restauración es individual y solo está disponible para
administradores.

La tabla `business_model_audits` conserva actor, acción, entidad, fecha y
valores permitidos del modelo. No registra contraseñas, credenciales, datos de
contacto, notas, descripciones, rutas/nombres de archivos ni su contenido.

Pennant habilita globalmente `document-management` y `project-attachments` por
defecto. Se pueden activar o desactivar con `Laravel\Pennant\Feature` desde un
entorno local configurado con una base de datos aislada. No se creó una bandera
de informes porque el sistema no tiene una ruta o módulo real de informes.

Las migraciones nuevas no se ejecutaron durante estos cambios. Para probarlas,
confirma primero que `.env` apunta a una base local desechable y luego ejecuta
`php artisan migrate`; no uses una conexión Supabase o de producción para esa
verificación.

PHP-FPM sigue siendo el runtime predeterminado. Redis y RoadRunner/Octane son
opcionales:

```bash
docker compose --profile redis up -d redis
docker compose --profile octane up -d --build octane
```

Para Redis configura `REDIS_CLIENT=predis` y `REDIS_HOST=redis` en el `.env`;
elige también explícitamente los drivers de caché, cola y sesión si quieres
usarlos con Redis. Octane expone `127.0.0.1:8002`; el servicio Nginx/PHP-FPM
predeterminado en el puerto `8000` no se reemplaza ni se redirige
automáticamente.

### Desplegar en DigitalOcean con Docker y Nginx

El despliegue usa Nginx para servir los archivos estáticos y enviar las
peticiones PHP a PHP-FPM. Vite compila React durante la construcción de la
imagen. PostgreSQL es externo: puedes conservar Supabase o usar Managed
PostgreSQL de DigitalOcean; no se incluye una base de datos local en Compose.

1. Crea un Droplet Ubuntu en DigitalOcean, apunta el registro DNS `A` de tu
   dominio a su IP pública y configura un Cloud Firewall para permitir SSH
   desde tu IP y tráfico TCP en los puertos `80` y `443`.
2. Instala Docker Engine y el plugin Docker Compose en el Droplet, clona el
   repositorio y entra en su directorio.
3. Crea el `.env` en el servidor y configura sus valores de producción:

    ```bash
    cp .env.example .env
    nano .env
    ```

    Usa `APP_ENV=production`, `APP_DEBUG=false` y la URL pública en `APP_URL`.
    Completa las credenciales de PostgreSQL, `ABLY_KEY` con una clave Root,
    SMTP y las demás variables necesarias. Si usas Managed PostgreSQL, agrega
    el Droplet a sus trusted sources y configura `DB_SSLMODE=require`. No
    subas `.env` al repositorio.

4. Genera una clave de aplicación sin guardarla en la imagen y cópiala al
   `APP_KEY` del `.env` del servidor:

    ```bash
    docker compose run --rm app php artisan key:generate --show
    ```

5. Construye y arranca la aplicación. Las migraciones y la caché de Laravel
   se preparan después de que los contenedores estén arriba; el worker de cola
   se ejecuta como servicio separado.

    ```bash
    docker compose up -d --build
    docker compose exec app php artisan migrate --force
    docker compose exec app php artisan optimize
    docker compose restart app queue
    docker compose ps
    ```

    Nginx publica la aplicación en el puerto `80`. Los adjuntos privados
    persisten en un volumen Docker. Laravel escribe los logs en `stderr`, que
    puedes consultar con `docker compose logs app queue`; así no depende de los
    permisos de escritura de un volumen de logs.

6. Antes de usar credenciales en el sitio, configura HTTPS para el dominio con
   Let's Encrypt y actualiza Nginx para servir TLS y redirigir HTTP a HTTPS. La
   configuración incluida escucha en HTTP (`80`) y no crea certificados TLS.

Para desplegar cambios posteriormente, actualiza el repositorio y repite la
construcción, migración, optimización y reinicio:

```bash
git pull
docker compose up -d --build
docker compose exec app php artisan migrate --force
docker compose exec app php artisan optimize
docker compose restart app queue
```
