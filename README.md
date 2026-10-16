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
