# Documentacion Laravel del sistema

## 1. Contexto del proyecto

El proyecto es una aplicacion Laravel con:

- Laravel 13 y PHP 8.3.
- React 19 con TypeScript.
- Inertia para conectar rutas y controladores Laravel con paginas React.
- Fortify para autenticacion, verificacion de correo, contrasenas, 2FA y passkeys.
- Tailwind CSS, Radix UI, Lucide y Vite.
- Pest para pruebas y Larastan para analisis estatico.

El sistema propuesto es una plataforma de gestion empresarial con clientes, proyectos, tareas, usuarios, documentos y actividad operativa.

La interfaz no usa vistas Blade para las pantallas principales. Laravel sigue controlando las rutas, validacion, sesiones, autorizacion y datos; React se encarga de presentar la interfaz a traves de Inertia.

---

## 2. Enrutamiento

### Definicion

El enrutamiento determina que ocurre cuando un usuario visita una URL o envia una peticion HTTP. Una ruta puede apuntar a una pagina Inertia, un controlador, una respuesta JSON, una redireccion o una accion especifica.

### Archivos relacionados

- Actual: `routes/web.php`, `routes/settings.php`, `bootstrap/app.php`.
- Frontend: `resources/js/routes/*`, `resources/js/actions/*`.
- Gestion empresarial: `routes/management.php` por crear o ampliacion de `routes/web.php`.

Ejemplos habituales:

```php
Route::get('/clientes', [ClientController::class, 'index']);
Route::post('/clientes', [ClientController::class, 'store']);
Route::patch('/clientes/{client}', [ClientController::class, 'update']);
Route::delete('/clientes/{client}', [ClientController::class, 'destroy']);
```

### Aplicacion en nuestro sistema

Las rutas se organizaran por modulos y usaran nombres descriptivos:

```php
Route::middleware(['auth', 'verified'])
    ->prefix('gestion')
    ->name('management.')
    ->group(function () {
        Route::resource('clientes', ClientController::class);
        Route::resource('proyectos', ProjectController::class);
        Route::resource('tareas', TaskController::class);
    });
```

El dashboard actual se sirve mediante:

```php
Route::inertia('dashboard', 'dashboard')->name('dashboard');
```

Estado actual: el dashboard ya utiliza la ruta autenticada existente. Los recursos de clientes, proyectos y tareas aun deben conectarse a Laravel.

### Parametros y model binding

Laravel puede resolver automaticamente un modelo desde la URL:

```php
Route::get('/clientes/{client}', [ClientController::class, 'show']);

public function show(Client $client): Response
{
    return Inertia::render('clients/show', ['client' => $client]);
}
```

Esto evita buscar manualmente el registro y devuelve un 404 si no existe. Para el sistema se usara tambien binding con slug cuando sea necesario, por ejemplo `{client:slug}`.

### Rutas nombradas

Las rutas nombradas permiten generar URLs sin escribir paths manualmente:

```php
return to_route('management.clientes.index');
```

En React se deben usar las funciones generadas por Wayfinder desde `@/routes` o `@/actions`.

### Rate limiting

Limita la cantidad de peticiones permitidas por usuario o IP. Se aplicara a login, endpoints sensibles, importaciones y carga de archivos.

```php
Route::middleware('throttle:uploads')->post('/documentos', ...);
```

---

## 3. Middleware

### Definicion

El middleware es una capa que inspecciona una peticion antes o despues de que llegue al controlador. Puede autenticar, autorizar, registrar, limitar o modificar una peticion.

### Archivos relacionados

- Actual: `bootstrap/app.php`, `app/Http/Middleware/HandleInertiaRequests.php` y `routes/settings.php`.
- Autenticacion: `app/Providers/FortifyServiceProvider.php`.
- Gestion empresarial: `app/Http/Middleware/EnsureUserHasRole.php` por crear.

### Middleware que ya utiliza el sistema

- `auth`: exige que el usuario haya iniciado sesion.
- `verified`: exige que el correo este verificado.
- `web`: sesiones, cookies y proteccion CSRF.
- `RequirePassword`: vuelve a solicitar la contrasena para operaciones delicadas.
- `throttle`: limita peticiones de cambio de contrasena y autenticacion.

### Aplicacion prevista

Se agregara middleware de roles o permisos para diferenciar:

- Administrador: configura usuarios y todo el sistema.
- Gerente: administra clientes, proyectos e informes.
- Colaborador: trabaja en tareas y consulta proyectos asignados.

El middleware no reemplaza las Policies. El middleware protege grupos generales y las Policies deciden si el usuario puede operar sobre un registro concreto.

---

## 4. Proteccion CSRF

### Definicion

CSRF evita que un sitio externo ejecute peticiones de escritura usando la sesion de un usuario autenticado. Laravel valida el token y el origen de las peticiones que cambian datos.

### Archivos relacionados

- Configuracion: `bootstrap/app.php` y middleware del grupo `web`.
- Formularios React: `resources/js/pages/*` y `resources/js/components/*`.
- Configuracion del documento raiz: `resources/views/app.blade.php`.

### Aplicacion con React e Inertia

Las peticiones hechas mediante Inertia conservan la proteccion CSRF del grupo `web`. No se deshabilitara globalmente.

Para webhooks externos, si fueran necesarios, se creara una ruta separada y se excluira solo esa URI de CSRF con una justificacion concreta.

---

## 5. Controladores

### Definicion

Un controlador agrupa la logica relacionada con un recurso y evita poner reglas de negocio directamente en las rutas.

### Archivos relacionados

- Actuales: `app/Http/Controllers/Settings/ProfileController.php` y `SecurityController.php`.
- Gestion empresarial: `app/Http/Controllers/Management/ClientController.php`, `ProjectController.php` y `TaskController.php` por crear.

### Aplicacion

Cada modulo tendra un controlador resource:

```text
app/Http/Controllers/Management/ClientController.php
app/Http/Controllers/Management/ProjectController.php
app/Http/Controllers/Management/TaskController.php
```

Sus metodos principales seran:

- `index`: lista registros y filtros.
- `create`: prepara el formulario.
- `store`: valida y crea.
- `show`: muestra el detalle.
- `edit`: prepara la edicion.
- `update`: valida y actualiza.
- `destroy`: elimina o archiva.

Cada metodo que muestra una pagina usara `Inertia::render()` y entregara props tipadas a React.

---

## 6. Resource Controllers

### Definicion

`Route::resource()` genera las rutas CRUD estandar de un recurso.

### Archivos relacionados

- Rutas: `routes/web.php` o `routes/management.php`.
- Controladores: `app/Http/Controllers/Management/*Controller.php` por crear.
- Paginas React: `resources/js/pages/clients/*`, `projects/*` y `tasks/*` por crear.

```php
Route::resource('clientes', ClientController::class);
```

Genera index, create, store, show, edit, update y destroy.

### Aplicacion

Se usara para clientes, proyectos y tareas. Cuando un recurso no necesite todas las acciones se limitaran sus metodos:

```php
Route::resource('clientes', ClientController::class)
    ->only(['index', 'show', 'create', 'store']);
```

Las acciones adicionales, como archivar o cambiar estado, se definiran antes de `resource` y tendran nombres propios.

---

## 7. Peticiones HTTP

### Definicion

`Illuminate\Http\Request` representa la peticion actual y permite acceder a parametros, query strings, cabeceras, archivos, usuario y ruta.

### Archivos relacionados

- Actual: `app/Http/Controllers/Settings/*`, `app/Http/Middleware/*` y `routes/*.php`.
- Gestion empresarial: controladores y Form Requests de `app/Http/Controllers/Management/` y `app/Http/Requests/Management/`.

### Aplicacion

Se usara para:

- Filtros de busqueda y paginacion.
- Lectura de archivos subidos.
- Identificacion del usuario actual.
- Deteccion de respuestas JSON.
- Obtencion de parametros de ruta.

La entrada se leera con metodos especificos (`string`, `integer`, `boolean`, `date`) y no se confiara directamente en datos sin validar.

---

## 8. Validacion

### Definicion

La validacion garantiza que los datos recibidos cumplen las reglas del dominio antes de escribirlos en la base de datos.

### Archivos relacionados

- Actuales: `app/Http/Requests/Settings/*`, `app/Concerns/ProfileValidationRules.php` y `PasswordValidationRules.php`.
- Gestion empresarial: `app/Http/Requests/Management/StoreClientRequest.php`, `UpdateClientRequest.php`, `StoreProjectRequest.php` y `StoreTaskRequest.php` por crear.
- Frontend: `resources/js/pages/*` y `resources/js/components/*` para mostrar errores.

### Form Requests

Cada formulario importante tendra su propia clase:

```text
app/Http/Requests/Management/StoreClientRequest.php
app/Http/Requests/Management/UpdateClientRequest.php
app/Http/Requests/Management/StoreProjectRequest.php
app/Http/Requests/Management/StoreTaskRequest.php
```

Ejemplo:

```php
public function rules(): array
{
    return [
        'name' => ['required', 'string', 'max:150'],
        'email' => ['nullable', 'email', 'max:255'],
        'status' => ['required', Rule::in(['active', 'inactive'])],
    ];
}
```

Si falla la validacion, Laravel redirige de vuelta con errores y React los recibe a traves de Inertia. Si la peticion espera JSON, la respuesta sera 422.

---

## 9. Respuestas HTTP e Inertia

### Definicion

Laravel puede responder con texto, JSON, redirecciones, archivos, streams o vistas. En este proyecto las pantallas de usuario se entregan como respuestas Inertia.

### Archivos relacionados

- Servidor: `app/Http/Middleware/HandleInertiaRequests.php`, `app/Http/Controllers/Settings/*` y `app/Providers/FortifyServiceProvider.php`.
- Cliente: `resources/js/app.tsx`, `resources/js/pages/*` y `resources/js/components/*`.

### Aplicacion

- `Inertia::render()`: cargar una pagina React.
- `to_route()`: redirigir despues de crear o actualizar.
- `back()`: volver al formulario anterior.
- `response()->json()`: endpoints JSON o respuestas de integracion.
- `response()->download()`: descarga de documentos.
- `with()` o `Inertia::flash()`: mensajes de confirmacion.

Ejemplo:

```php
Inertia::flash('toast', [
    'type' => 'success',
    'message' => __('Client created.'),
]);

return to_route('management.clientes.index');
```

---

## 10. Vistas, Blade y React

### Definicion

Blade es el sistema de plantillas de Laravel. React es una alternativa para construir interfaces interactivas. Inertia permite usar React sin convertir Laravel en una API separada obligatoriamente.

### Archivos relacionados

- React: `resources/js/pages/*`, `resources/js/components/*` y `resources/js/layouts/*`.
- Inertia: `resources/js/app.tsx` y `app/Http/Middleware/HandleInertiaRequests.php`.
- Blade: `resources/views/app.blade.php`.

### Aplicacion

- Blade se mantiene para el documento raiz y elementos propios de Laravel.
- React se usa para dashboard, formularios, tablas, filtros y detalles.
- Los componentes viven en `resources/js/components`.
- Las paginas viven en `resources/js/pages`.
- Los layouts del starter kit se reutilizan para sidebar, breadcrumbs y menu de usuario.

El dashboard actual esta implementado en React/TypeScript y utiliza componentes del kit, no HTML estatico de SB Admin ni scripts CDN.

---

## 11. Plantillas Blade

### Definicion

Blade permite crear vistas, layouts, componentes, directivas, formularios con CSRF y mensajes de validacion.

### Archivos relacionados

- Raiz actual: `resources/views/app.blade.php`.
- Componentes Blade: `resources/views/components/*`.
- Errores y correos: `resources/views/errors/*` y `resources/views/emails/*` por crear si se necesitan.

### Aplicacion

No se duplicara la pantalla React en Blade. Blade se utilizara para:

- `resources/views/app.blade.php`, raiz de Inertia.
- Metadatos y carga de assets.
- Paginas de error si se necesita una respuesta fuera de Inertia.
- Emails, documentos o vistas simples del servidor.

Los formularios de negocio seran componentes React, pero continuaran enviando peticiones protegidas por el middleware web.

---

## 12. Vite y assets

### Definicion

Vite compila TypeScript, React, Tailwind y los assets para desarrollo y produccion.

### Archivos relacionados

- Configuracion: `vite.config.ts`, `package.json` y `tsconfig.json`.
- Estilos: `resources/css/app.css`.
- Entrada React: `resources/js/app.tsx`.
- Assets: `resources/images/*` y `resources/fonts/*` por crear cuando sean necesarios.

Comandos principales:

```bash
npm run dev
npm run build
npm run types:check
```

### Aplicacion

- Los estilos del dashboard se escriben con Tailwind.
- Los iconos se importan desde `lucide-react`.
- No se cargan Bootstrap, Font Awesome, Chart.js ni tablas externas desde CDN.
- Las imagenes y fuentes nuevas deben pasar por Vite.
- El build de produccion ya fue validado con `npm run build`.

---

## 13. URLs y navegacion

### Definicion

Laravel ofrece `url()`, `route()`, `action()` y objetos URI para construir URLs consistentes y seguras.

### Archivos relacionados

- Backend: `routes/*.php` y `app/Http/Controllers/*`.
- Wayfinder: `resources/js/routes/*` y `resources/js/actions/*`.
- Navegacion visual: componentes `Link` en `resources/js/pages/*` y `resources/js/components/*`.

### Aplicacion

En PHP se preferiran rutas nombradas:

```php
return redirect()->route('management.clientes.show', $client);
```

En React se usaran rutas generadas por Wayfinder y `Link` de Inertia. Esto evita concatenar URLs manualmente y mantiene sincronizados frontend y backend.

---

## 14. Sesiones

### Definicion

Las sesiones mantienen informacion entre peticiones HTTP. Laravel puede almacenarlas en base de datos, archivos, Redis u otros drivers.

### Archivos relacionados

- Configuracion: `config/session.php`.
- Middleware y cookies: `bootstrap/app.php` y grupo `web`.
- Uso actual: `app/Http/Controllers/Settings/*` y `resources/js/components/*`.

### Aplicacion

Las sesiones se usaran para:

- Autenticacion.
- Mensajes flash despues de operaciones.
- Errores y datos antiguos de formularios.
- Preferencias temporales del usuario.
- Estado del sidebar y apariencia, ya soportados por el starter kit.

No se almacenaran contrasenas, tokens sensibles ni grandes cantidades de datos en la sesion.

---

## 15. Route Model Binding y relaciones

### Definicion

El binding transforma automaticamente un parametro de ruta en un modelo Eloquent. Las relaciones permiten conectar clientes, proyectos, tareas y usuarios.

### Archivos relacionados

- Modelo actual: `app/Models/User.php`.
- Base de datos actual: `database/migrations/*` y `database/factories/UserFactory.php`.
- Modelos empresariales: `app/Models/Client.php`, `Project.php` y `Task.php` por crear.
- Rutas con binding: `routes/management.php` por crear.

### Modelo del sistema

```text
User
  └── hasMany(Project)
Client
  └── hasMany(Project)
Project
  └── belongsTo(Client)
  └── hasMany(Task)
Task
  └── belongsTo(Project)
  └── belongsTo(User, assigned_to)
```

En proyectos y tareas se usara binding con scoping para evitar que un registro perteneciente a otro contexto sea accesible por URL.

---

## 16. Autorizacion y Policies

### Definicion

Una Policy decide si un usuario puede realizar una accion sobre un modelo. Es mas precisa que un middleware general.

### Archivos relacionados

- Autenticacion actual: `app/Models/User.php`, `app/Providers/FortifyServiceProvider.php` y middleware `auth`.
- Policies empresariales: `app/Policies/ClientPolicy.php`, `ProjectPolicy.php` y `TaskPolicy.php` por crear.
- Registro adicional, si se necesita: `app/Providers/AppServiceProvider.php`.

### Aplicacion

Se crearan Policies para:

- Ver clientes.
- Editar o archivar clientes.
- Ver proyectos asignados.
- Modificar tareas propias o asignadas.
- Descargar documentos.

Las rutas pueden usar `can` y los controladores pueden usar `$this->authorize()`.

---

## 17. Archivos y documentos

### Definicion

Laravel permite recibir archivos, validarlos y almacenarlos en discos locales o servicios como S3.

### Archivos relacionados

- Configuracion actual: `config/filesystems.php`, `storage/app/` y `public/`.
- Modelo futuro: `app/Models/Document.php` por crear.
- Validacion futura: `app/Http/Requests/Management/StoreDocumentRequest.php` por crear.
- Controlador futuro: `app/Http/Controllers/Management/DocumentController.php` por crear.

### Aplicacion

El modulo de documentos validara:

- Tipo MIME y extension.
- Tamano maximo.
- Nombre seguro.
- Usuario autorizado.

Se usaran `store()` y discos configurables. Las descargas pasaran por una ruta autorizada y no expondran directamente rutas internas del servidor.

---

## 18. Errores y excepciones

### Definicion

Laravel captura excepciones, registra errores y decide si debe responder con HTML, JSON o una pagina Inertia.

### Archivos relacionados

- Configuracion: `bootstrap/app.php` y `config/app.php`.
- Errores Blade: `resources/views/errors/*` por crear si se personalizan.
- Errores Inertia: `resources/js/pages/*` y `app/Http/Middleware/HandleInertiaRequests.php`.

### Aplicacion

- Errores de validacion: se muestran junto al formulario.
- 403: usuario autenticado sin permiso.
- 404: registro o ruta inexistente.
- 419: sesion o token CSRF expirado.
- 500: error inesperado, registrado sin mostrar informacion sensible en produccion.

Las respuestas JSON se usaran cuando el cliente o endpoint las solicite. `APP_DEBUG` debe estar desactivado en produccion.

---

## 19. Logging

### Definicion

Laravel usa canales basados en Monolog para registrar eventos en archivos, syslog, Slack u otros destinos.

### Archivos relacionados

- Configuracion: `config/logging.php`.
- Logs locales: `storage/logs/`.
- Registro de contexto: `app/Providers/*` y middleware de auditoria por crear.

### Aplicacion

Se registraran eventos relevantes como:

- Inicio y cierre de sesion.
- Creacion, edicion y archivado de clientes.
- Cambios de estado de proyectos y tareas.
- Descargas de documentos.
- Fallos de integraciones.

Los logs no deben incluir contrasenas, tokens, archivos completos ni datos personales innecesarios. Para trazabilidad se puede compartir un identificador de peticion.

---

## 20. Cache y optimizacion

### Definicion

Laravel puede cachear configuracion, rutas y vistas para mejorar el rendimiento.

### Archivos relacionados

- Configuracion: `config/cache.php`, `config/database.php` y `config/view.php`.
- Dashboard: `routes/web.php`, `resources/js/pages/dashboard.tsx` y consultas futuras del controlador.
- Despliegue: comandos Artisan de cache y configuracion del entorno.

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Aplicacion

Se aplicara en despliegues, no durante el desarrollo diario. Los listados grandes usaran paginacion y consultas con columnas seleccionadas. Los indicadores del dashboard podran cachearse cuando existan suficientes datos reales.

---

## 21. Seguridad adicional

### Definicion

La seguridad del sistema se construye combinando autenticacion, validacion, autorizacion, proteccion CSRF, sesiones seguras, rate limiting y manejo cuidadoso de errores.

### Archivos relacionados

- Autenticacion: `app/Providers/FortifyServiceProvider.php`, `app/Models/User.php` y `routes/settings.php`.
- Validacion: `app/Http/Requests/*`.
- Autorizacion: `app/Policies/*` por crear.
- Configuracion sensible: `.env`, `config/auth.php`, `config/session.php` y `config/filesystems.php`.

Se mantendran estas reglas:

- No desactivar CSRF globalmente.
- Validar todo input proveniente del navegador.
- Usar Policies para autorizacion por registro.
- Proteger endpoints costosos con rate limiting.
- No confiar en la IP del cliente como identidad.
- No mostrar excepciones detalladas en produccion.
- Validar y autorizar cada descarga.
- Escapar contenido mostrado por defecto.
- Usar rutas nombradas y HTTPS en produccion.

---

## 22. Estado de implementacion

### Definicion

Este apartado distingue las funcionalidades que ya existen en el repositorio de las que forman parte del siguiente desarrollo del sistema.

### Archivos relacionados

- Dashboard implementado: `resources/js/pages/dashboard.tsx`.
- Layout implementado: `resources/js/layouts/app/*` y `resources/js/components/app-sidebar.tsx`.
- Autenticacion implementada: `app/Providers/FortifyServiceProvider.php`, `app/Models/User.php` y `routes/settings.php`.

### Implementado

- Autenticacion con Fortify.
- Verificacion de email.
- 2FA y passkeys disponibles.
- Layout con sidebar del starter kit.
- Dashboard React con TypeScript.
- Tarjetas de metricas, grafico visual, actividad y tabla de proyectos de ejemplo.
- Tailwind, Vite y modo oscuro.
- Validacion TypeScript y build de produccion.

### Pendiente

- Modelos `Client`, `Project` y `Task`.
- Migraciones y factories.
- Resource Controllers.
- Form Requests.
- Policies y roles.
- Datos reales para el dashboard.
- Modulo de documentos.
- Auditoria y actividad persistente.
- Pruebas Feature de rutas, validacion y autorizacion.

---

## 23. Orden recomendado de desarrollo

### Definicion

El orden de desarrollo organiza el trabajo desde el primer modulo funcional hasta las capacidades de seguridad, auditoria y optimizacion.

### Archivos que se iran incorporando

- Primera fase: `app/Models/Client.php`, migracion, factory, controller, requests, policy y paginas React.
- Segunda fase: equivalentes de `Project` y `Task`.
- Tercera fase: documentos, auditoria, roles, pruebas y metricas reales.

1. Crear clientes: modelo, migracion, CRUD, validacion y Policy.
2. Conectar clientes con el dashboard.
3. Crear proyectos relacionados con clientes.
4. Crear tareas y asignacion a usuarios.
5. Agregar roles y permisos.
6. Incorporar documentos y descargas autorizadas.
7. Sustituir metricas de ejemplo por consultas reales.
8. Agregar auditoria, logs y pruebas.
9. Optimizar consultas y preparar cache para produccion.

---

## 24. Autenticacion, autorizacion y Request

### Definicion

La autenticacion responde a la pregunta: **quien es el usuario?**

La autorizacion responde a la pregunta: **que puede hacer ese usuario?**

`Request` representa la peticion HTTP actual. Permite leer los datos enviados por el navegador, obtener el usuario autenticado, acceder a la sesion y consultar los parametros de la ruta.

En nuestro sistema estos conceptos trabajan juntos:

```text
Usuario intenta acceder
    |
    v
Middleware auth y verified
    |
    v
FormRequest: valida datos y autorizacion
    |
    v
Policy: confirma permiso sobre el modelo
    |
    v
Controller: ejecuta la operacion
    |
    v
Modelos, base de datos e Inertia
```

### Archivos relacionados

#### Autenticacion actual

- `app/Models/User.php`: modelo autenticable del sistema.
- `app/Providers/FortifyServiceProvider.php`: configuracion de login, registro, recuperacion, verificacion, 2FA y passkeys.
- `app/Actions/Fortify/*`: acciones de creacion de usuario y cambio de contrasena.
- `routes/web.php`: rutas publicas y dashboard protegido.
- `routes/settings.php`: perfil, contrasena y seguridad.
- `config/auth.php`: guard y proveedor de usuarios.
- `config/session.php`: configuracion de la sesion autenticada.

#### Middleware de autenticacion

- `bootstrap/app.php`: registro general de middleware.
- `routes/web.php`: uso de `auth` y `verified`.
- `routes/settings.php`: uso de `auth`, `verified` y `RequirePassword`.
- `app/Http/Middleware/HandleInertiaRequests.php`: comparte el usuario autenticado con React.

#### Request y Form Requests actuales

- `app/Http/Requests/Settings/ProfileUpdateRequest.php`: valida la actualizacion del perfil.
- `app/Http/Requests/Settings/ProfileDeleteRequest.php`: valida la eliminacion de la cuenta.
- `app/Http/Requests/Settings/PasswordUpdateRequest.php`: valida el cambio de contrasena.
- `app/Http/Requests/Settings/TwoFactorAuthenticationRequest.php`: protege operaciones de 2FA.
- `app/Concerns/ProfileValidationRules.php`: reglas reutilizables del perfil.
- `app/Concerns/PasswordValidationRules.php`: reglas reutilizables de contrasena.

#### Autorizacion futura del sistema empresarial

- `app/Policies/ClientPolicy.php`: permisos sobre clientes.
- `app/Policies/ProjectPolicy.php`: permisos sobre proyectos.
- `app/Policies/TaskPolicy.php`: permisos sobre tareas.
- `app/Http/Requests/Management/*Request.php`: autorizacion y validacion de formularios empresariales.
- `app/Http/Middleware/EnsureUserHasRole.php`: middleware de roles, si se necesita proteger grupos completos.

### Como funciona la autenticacion en este proyecto

Laravel Fortify proporciona la infraestructura de autenticacion. El usuario envia correo y contrasena desde la pagina React de login. Fortify valida las credenciales, inicia la sesion y regenera el identificador de sesion.

El modelo utilizado es `App\Models\User`. El campo `password` se transforma automaticamente con el cast `hashed` definido en `User.php`, por lo que no se debe guardar una contrasena en texto plano desde un controlador.

Cuando una ruta tiene `auth`, Laravel ejecuta el middleware antes del controlador:

```php
Route::middleware(['auth', 'verified'])->group(function () {
  Route::inertia('dashboard', 'dashboard')->name('dashboard');
});
```

Si el usuario no esta autenticado, se redirige al login. Si esta autenticado pero no verifico su correo, se redirige al proceso de verificacion.

### Obtener el usuario desde Request

En un controlador se recomienda inyectar `Request` y usar `$request->user()`:

```php
use Illuminate\Http\Request;

public function index(Request $request): Response
{
  $user = $request->user();

  return Inertia::render('clients/index', [
    'userName' => $user->name,
  ]);
}
```

Tambien existe `auth()->user()`, pero `$request->user()` deja mas claro que el usuario proviene de la peticion actual y facilita las pruebas.

El objeto `Request` tambien permite:

```php
$request->input('name');
$request->query('search');
$request->string('status')->trim();
$request->integer('per_page');
$request->boolean('archived');
$request->file('document');
$request->route('client');
$request->session();
$request->user();
```

### Diferencia entre Request y FormRequest

`Request` se usa principalmente para leer la peticion y acceder al usuario, sesion, filtros, parametros y archivos.

`FormRequest` se usa cuando una operacion necesita reglas de validacion y autorizacion propias. Esto mantiene los controladores pequenos.

Ejemplo del patron que ya utiliza el proyecto:

```php
class ProfileUpdateRequest extends FormRequest
{
  public function rules(): array
  {
    return $this->profileRules($this->user()->id);
  }
}
```

El Form Request tiene dos responsabilidades relacionadas:

```php
public function authorize(): bool
{
  return $this->user() !== null;
}

public function rules(): array
{
  return [
    'name' => ['required', 'string', 'max:150'],
    'email' => ['required', 'email', 'max:255'],
  ];
}
```

- `authorize()` decide si el usuario puede intentar la operacion.
- `rules()` decide si los datos enviados tienen un formato valido.

Si `authorize()` devuelve `false`, Laravel responde con 403. Si las reglas fallan, Laravel redirige con errores o responde con 422 cuando la peticion espera JSON.

### Autorizacion con Policies

Las Policies centralizan permisos sobre modelos. Por ejemplo, un colaborador puede consultar un proyecto asignado, pero no eliminarlo.

```php
class ClientPolicy
{
  public function update(User $user, Client $client): bool
  {
    return $user->isManager() || $client->created_by === $user->id;
  }
}
```

El controlador puede exigir el permiso antes de modificar el registro:

```php
public function update(UpdateClientRequest $request, Client $client): RedirectResponse
{
  $this->authorize('update', $client);

  $client->update($request->validated());

  return to_route('management.clientes.show', $client);
}
```

La ruta tambien puede usar el alias `can`:

```php
Route::patch('/clientes/{client}', [ClientController::class, 'update'])
  ->middleware('can:update,client')
  ->name('management.clientes.update');
```

### Ejemplo completo para clientes

#### Ruta

```php
Route::middleware(['auth', 'verified'])
  ->prefix('gestion')
  ->name('management.')
  ->group(function () {
    Route::resource('clientes', ClientController::class);
  });
```

#### Form Request

```php
class StoreClientRequest extends FormRequest
{
  public function authorize(): bool
  {
    return $this->user()->can('create', Client::class);
  }

  public function rules(): array
  {
    return [
      'name' => ['required', 'string', 'max:150'],
      'email' => ['nullable', 'email', 'max:255'],
      'phone' => ['nullable', 'string', 'max:30'],
    ];
  }
}
```

#### Controlador

```php
public function store(StoreClientRequest $request): RedirectResponse
{
  $client = Client::create([
    ...$request->validated(),
    'created_by' => $request->user()->id,
  ]);

  Inertia::flash('toast', [
    'type' => 'success',
    'message' => __('Client created.'),
  ]);

  return to_route('management.clientes.show', $client);
}
```

#### Pagina React

La pagina recibira los datos mediante Inertia y enviara el formulario usando las rutas generadas por Wayfinder:

```text
resources/js/pages/clients/index.tsx
resources/js/pages/clients/create.tsx
resources/js/pages/clients/show.tsx
resources/js/pages/clients/edit.tsx
```

### Responsabilidad de cada parte

| Parte | Responsabilidad en nuestro sistema |
|---|---|
| Fortify | Login, registro, logout, contrasenas, verificacion, 2FA y passkeys |
| `User` | Representar al usuario autenticado y sus relaciones |
| `auth` | Impedir acceso a usuarios no autenticados |
| `verified` | Exigir correo verificado para el area empresarial |
| `Request` | Leer usuario, filtros, parametros, archivos y sesion |
| `FormRequest` | Validar datos y autorizar la operacion inicial |
| Policy | Decidir permisos sobre un cliente, proyecto o tarea concreta |
| Controller | Coordinar la operacion sin concentrar todas las reglas |
| Model | Representar datos y relaciones con la base de datos |
| Inertia | Enviar props de Laravel a las paginas React |
| React | Mostrar formularios, tablas, errores y estados de la interfaz |

### Flujo recomendado para escribir codigo ordenado

1. Proteger la ruta con `auth` y, cuando corresponda, `verified`.
2. Recibir un Form Request en lugar de validar manualmente dentro del controlador.
3. Usar `authorize()` para el permiso inicial y una Policy para el permiso sobre el modelo.
4. Leer el usuario con `$request->user()`.
5. Usar solamente `$request->validated()` para guardar datos.
6. Dejar la consulta y persistencia en el modelo o servicio correspondiente.
7. Responder con `Inertia::render()`, `to_route()` o una respuesta JSON adecuada.
8. Cubrir acceso permitido y denegado con pruebas Feature.

Este patron sera la base de clientes, proyectos, tareas, documentos y cualquier modulo nuevo del sistema.

