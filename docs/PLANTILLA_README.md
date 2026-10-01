# CoworkHub

## Qué es

CoworkHub es una aplicación web para la gestión de un espacio de coworking. Permite administrar membresías, espacios, reservas, salones para eventos, facturación, asistencia, asesorías, anuncios y reportes.

El sistema cuenta con un sitio público, un panel operativo para administración y recepción, y un portal para miembros.

## Stack

- Backend: PHP 8.2+ y Laravel 12
- Frontend: Vue 3
- Integración frontend/backend: Inertia.js 2
- Construcción de recursos: Vite 7
- Estilos: Tailwind CSS
- Base de datos: SQLite por configuración de ejemplo; el proyecto puede configurarse con MySQL/MariaDB mediante las variables `DB_*`
- Autenticación: Laravel Breeze y Laravel Sanctum
- OAuth: Laravel Socialite
- 2FA: Google2FA
- Rutas frontend: Ziggy
- Peticiones HTTP: Axios
- Iconos: Lucide Vue Next
- Notificaciones: Vue Sonner

## Requisitos

- PHP 8.2 o superior
- Composer
- Node.js y npm
- Base de datos compatible con la configuración del proyecto
- Git
- Extensiones de PHP requeridas por Laravel y sus dependencias

## Instalación paso a paso

### 1. Clonar el repositorio

```bash
git clone [URL_DEL_REPOSITORIO]
cd iyem-coworkhub-main
```

### 2. Instalar las dependencias de PHP

```bash
composer install
```

### 3. Instalar las dependencias de JavaScript

```bash
npm install
```

### 4. Crear el archivo `.env`

Windows:

```bash
copy .env.example .env
```

Linux/macOS:

```bash
cp .env.example .env
```

### 5. Generar la clave de la aplicación

```bash
php artisan key:generate
```

### 6. Configurar la base de datos

Configurar en `.env` las variables correspondientes a la base de datos que se utilizará.

Para el entorno local utilizado durante el desarrollo, la base de datos puede configurarse con MySQL/MariaDB, incluyendo el puerto correspondiente del servidor local.

### 7. Ejecutar las migraciones

```bash
php artisan migrate
```

### 8. Crear el enlace de almacenamiento

```bash
php artisan storage:link
```

### 9. Ejecutar el proyecto en desarrollo

El proyecto incluye un script que levanta Laravel, la cola, los logs y Vite:

```bash
composer run dev
```

También pueden ejecutarse los procesos por separado:

```bash
php artisan serve
npm run dev
```

### 10. Generar los recursos para producción

```bash
npm run build
```

## Variables de entorno

No se deben colocar contraseñas, tokens, claves API ni otros valores reales en este documento o en el repositorio.

Variables principales del proyecto:

```env
APP_NAME=[NOMBRE_DE_LA_APLICACION]
APP_ENV=[ENTORNO]
APP_KEY=[GENERADA_CON_ARTISAN]
APP_DEBUG=[true_o_false]
APP_URL=[URL_DE_LA_APLICACION]

APP_LOCALE=es
APP_FALLBACK_LOCALE=en

DB_CONNECTION=[MOTOR_DE_BASE_DE_DATOS]
DB_HOST=[HOST]
DB_PORT=[PUERTO]
DB_DATABASE=[BASE_DE_DATOS]
DB_USERNAME=[USUARIO]
DB_PASSWORD=[CONTRASEÑA]

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database

NODICO_CSP_ESTRICTA=[true_o_false]
NODICO_GOOGLE_LOGIN_ENABLED=[true_o_false]
GOOGLE_CLIENT_ID=[CLIENT_ID]
GOOGLE_CLIENT_SECRET=[CLIENT_SECRET]
GOOGLE_REDIRECT_URI=[URL_DE_RETORNO]

NODICO_ENLACE_MAGICO_ENABLED=[true_o_false]
NODICO_APERTURA=[HORA]
NODICO_CIERRE=[HORA]
NODICO_EMAIL_FACTURACION=[CORREO]
NODICO_SALON_PRECIO_HORA=[PRECIO]
```

## Roles y permisos

El sistema define tres roles principales:

| Rol | Descripción | Permisos principales |
|---|---|---|
| Administrador (`admin`) | Usuario con funciones de administración | Acceso al panel operativo y a las funciones administrativas según los permisos configurados |
| Recepción (`staff`) | Personal operativo del coworking | Acceso al panel operativo y a las funciones de operación permitidas |
| Miembro (`miembro`) | Usuario del espacio de coworking | Acceso al portal de miembro, reservas, membresía, perfil, datos fiscales, asesorías y accesos |

El proyecto utiliza permisos mediante middleware `can:`. Entre los permisos implementados se encuentran gestión de planes, espacios, miembros, reservas, salones, asesorías, check-ins, facturación, anuncios, eventos, reportes y bitácora.

Los roles `admin` y `staff` pertenecen al panel operativo. El rol `miembro` pertenece al portal de miembros.

## Módulos

### Sitio público

- Inicio
- Nosotros
- Membresías
- Eventos
- Actividades/comunidad
- Contacto
- Aviso de privacidad
- Términos
- Sitemap y robots.txt

### Panel operativo

- Dashboard
- Planes
- Espacios
- Miembros
- Agenda
- Reservas
- Salones y cotizaciones
- Asesorías
- Catálogo de asesores
- Check-ins
- Facturación
- Anuncios
- Eventos
- Reportes
- Bitácora
- Seguridad

### Portal de miembros

- Dashboard del miembro
- Reservar espacios
- Mis reservas
- Mi membresía
- Mi perfil
- Datos fiscales
- Asesoría IYEM
- Accesos y pagos
- Check-in y check-out

### Autenticación y seguridad

- Registro e inicio de sesión
- Verificación de correo
- Recuperación de contraseña
- Enlace mágico
- Autenticación de dos factores
- Inicio de sesión con Google configurable
- Suspensión de cuentas
- Consentimiento
- Control de inactividad
- Registro de eventos de autenticación

## Cómo contribuir

1. Crear una rama para el cambio:

```bash
git checkout -b feature/nombre-del-cambio
```

2. Realizar los cambios necesarios.

3. Verificar que la aplicación funcione correctamente.

4. Ejecutar las pruebas:

```bash
composer run test
```

5. Compilar el frontend para comprobar que los recursos se generen correctamente:

```bash
npm run build
```

6. Registrar los cambios:

```bash
git add .
git commit -m "Descripcion del cambio"
```

7. Subir la rama:

```bash
git push origin feature/nombre-del-cambio
```

8. Crear un Pull Request describiendo los cambios realizados.

## Contacto

**Responsable:** [NOMBRE DEL RESPONSABLE]

**Correo:** [CORREO DE CONTACTO]

**Organización:** [NOMBRE DE LA ORGANIZACIÓN]

**Repositorio:** [URL DEL REPOSITORIO]
