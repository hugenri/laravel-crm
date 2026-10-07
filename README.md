# Laravel CRM
Proyecto personal desarrollado con Laravel para practicar y demostrar habilidades en el desarrollo de aplicaciones web.

Es un CRM sencillo enfocado en la gestión de clientes, contactos, prospectos, oportunidades, actividades y cotizaciones, incluyendo roles y permisos para controlar el acceso a las funcionalidades.

Proyecto desarrollado con fines de aprendizaje y portafolio.

## Módulos

- **Dashboard**: resumen del estado comercial (prospectos nuevos, cuentas,
  oportunidades abiertas y ganadas, cotizaciones pendientes y próximas
  actividades).
- **Usuarios**: administración de cuentas, activación/desactivación, asignación
  de roles y control de acceso. Incluye registro, inicio y cierre de sesión,
  recuperación de contraseña, verificación de correo y perfil.
- **Roles y permisos**: catálogo de roles y permisos, con una matriz para
  decidir qué puede ver y hacer cada rol. La autorización se aplica en el
  backend.
- **Cuentas (Clientes)**: empresas o negocios con los que existe una relación
  comercial, con sus datos fiscales y de contacto, activación/desactivación y
  su cartera de contactos, oportunidades y cotizaciones.
- **Contactos**: personas asociadas a una cuenta, con su puesto y datos de
  contacto. Cada cuenta puede tener varios contactos y uno marcado como
  principal.
- **Prospectos**: posibles clientes que todavía no forman parte de la cartera,
  con su fuente, estado y responsable. Un prospecto puede convertirse en cuenta
  y contacto.
- **Oportunidades**: posibles ventas asociadas a una cuenta y un contacto, con
  monto, fecha estimada de cierre y una etapa dentro del embudo. Pueden
  cerrarse como ganadas o perdidas.
- **Actividades**: seguimiento comercial en un único módulo con varios tipos
  (llamada, correo, reunión y tarea), vinculable a clientes, prospectos y
  oportunidades.
- **Calendario**: representación visual de las actividades, con vistas de mes,
  semana y día. No administra datos propios: muestra las mismas actividades del
  módulo de Actividades.
- **Cotizaciones**: propuestas comerciales con conceptos, subtotal, IVA y
  total, fecha de vigencia, estados y generación de PDF.
- **Reportes**: resúmenes sencillos del proceso comercial (prospectos,
  oportunidades, cotizaciones y actividades) con filtros.

## Flujo comercial

```text
Prospecto
    ↓
Cuenta / Contacto
    ↓
Oportunidad
    ↓
Cotización
    ↓
Ganada / Perdida
```

Las **Actividades** sirven para dar seguimiento a cualquiera de estas etapas y
el **Calendario** las visualiza en el tiempo sobre la misma información.

## Tecnologías

Versiones tomadas de `composer.json` y `package.json`:

| Componente | Uso |
|------------|-----|
| PHP `^8.3` | Lenguaje del backend |
| Laravel `^13.0` | Framework |
| Laravel Fortify `^1.40` | Autenticación (login, registro, recuperación, verificación) |
| Inertia.js (`inertiajs/inertia-laravel ^3.4`, `@inertiajs/vue3`) | Puente entre Laravel y Vue |
| Vue `^3.5` | Interfaz |
| Vite `^7.0` | Empaquetado de assets |
| Tailwind CSS `^4.0` | Estilos |
| Spatie Laravel Permission `^8.3` | Roles y permisos |
| barryvdh/laravel-dompdf `^3.1` | Generación del PDF de cotizaciones |
| MySQL | Base de datos |
| pnpm | Gestor de paquetes del frontend |

Las pruebas usan SQLite en memoria (ver `phpunit.xml`), por lo que no dependen
de una base de datos MySQL.

## Requisitos

- PHP 8.3 o superior con las extensiones que requiere Laravel.
- Composer.
- Node.js y `pnpm`.
- MySQL.

## Instalación

1. Instalar las dependencias del backend:

   ```powershell
   composer install
   ```

2. Crear el archivo de entorno a partir del ejemplo:

   ```powershell
   Copy-Item .env.example .env
   ```

   En sistemas Unix:

   ```bash
   cp .env.example .env
   ```

3. Generar la clave de la aplicación:

   ```powershell
   php artisan key:generate
   ```

4. Crear una base de datos MySQL y ajustar las variables `DB_*` en `.env`.

5. Crear el esquema y cargar los datos iniciales (roles, permisos y cuentas de
   desarrollo):

   ```powershell
   php artisan migrate --seed
   ```

6. Instalar y compilar el frontend:

   ```powershell
   pnpm install
   pnpm run build
   ```

El proyecto incluye además el script de Composer `setup`, que automatiza los
pasos anteriores salvo la creación de la base de datos y los seeders:

```powershell
composer run setup
php artisan migrate --seed
```

## Variables de entorno

Revisar `.env.example` para la lista completa. Las más relevantes:

| Variable | Descripción |
|----------|-------------|
| `APP_NAME` | Nombre visible de la aplicación. |
| `APP_ENV` | Entorno (`local`, `production`, `testing`). |
| `APP_KEY` | Clave de cifrado; se genera con `php artisan key:generate`. |
| `APP_DEBUG` | Muestra el detalle de los errores; debe ser `false` en producción. |
| `APP_URL` | URL base de la aplicación. |
| `APP_LOCALE` | Idioma de la interfaz (por defecto `es`). |
| `APP_FALLBACK_LOCALE` | Idioma de reserva (por defecto `en`). |
| `DB_CONNECTION` | Motor de base de datos (`mysql`). |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Datos de conexión a MySQL. |
| `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION` | Controladores de sesión, caché y colas (por defecto `database`). |
| `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | Configuración de correo. |
| `CRM_ADMIN_EMAILS` | Correos que tienen acceso de administrador de arranque. |
| `CRM_REGISTRATION_ENABLED` | Habilita o deshabilita el registro público de cuentas. |
| `CRM_PER_PAGE` | Filas por página en los listados. |

La zona horaria se define en `config/app.php` (`UTC` en la configuración
actual).

No se deben versionar contraseñas, tokens ni credenciales reales.

# Usuarios de desarrollo

El seeder `Database\Seeders\UserSeeder` crea las siguientes cuentas al ejecutar
`php artisan migrate --seed`:

| Correo | Contraseña | Rol | Estado |
|--------|------------|-----|--------|
| `admin@crm.test` | `password` | Administrador | Activo |
| `demo@crm.test` | `password` | Vendedor | Activo |
| `inactivo@crm.test` | `password` | Vendedor | Inactivo |

El correo del administrador se toma de `CRM_ADMIN_EMAILS`.

> Estas credenciales son **solo para desarrollo local**.

## Roles y permisos

El sistema incluye tres roles:

- **Administrador**: acceso completo a todos los módulos y acciones.
- **Gerente**: supervisa la operación comercial, administra usuarios y aprueba
  las decisiones sensibles, pero no elimina registros.
- **Vendedor**: opera la cartera diaria (clientes, contactos, prospectos,
  oportunidades, actividades y cotizaciones) sin eliminar ni tomar decisiones
  reservadas a la gerencia.

El acceso a cada módulo y acción se controla con roles y permisos, tanto en el
backend como en la interfaz. El catálogo de permisos y la matriz por rol viven
en `config/roles.php`; los detalles de instalación y mantenimiento de cada
módulo están en el documento de alcance.

## Desarrollo local

`package.json` expone un script que levanta el servidor de Laravel y el de Vite
a la vez:

```powershell
pnpm dev
```

Para trabajar además con la cola de trabajos y el visor de logs como parte del
mismo proceso:

```powershell
composer run dev
```

## Pruebas y calidad

```powershell
php artisan test
```

`php artisan test` ejecuta la suite de pruebas del backend.

```powershell
vendor\bin\pint --test
```

Pint comprueba el formato del código PHP. Para aplicarlo:

```powershell
vendor\bin\pint
```

En sistemas Unix, la ruta es `vendor/bin/pint`.

## Compilar el frontend

```powershell
pnpm run build
```

Genera los assets de producción en `public/build`. Para desarrollo, Vite se
ejecuta dentro de `pnpm dev`.


## Alcance

El proyecto se mantiene deliberadamente como un CRM enfocado en el proceso comercial.

Quedan fuera del alcance actual:

Facturación electrónica y CFDI
Contabilidad
Inventario
Compras
Nómina
Marketing automation
WhatsApp y chat
Soporte y tickets
Firma electrónica
Automatizaciones complejas
IA y lead scoring avanzado
BI avanzado
Aplicación móvil
Integraciones externas complejas
Multiempresa
Multimoneda avanzada
Suscripciones
Portal de clientes