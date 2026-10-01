# Guía de contribución

Este documento establece las reglas y el flujo de trabajo que deben seguir los colaboradores para realizar cambios en los repositorios del proyecto.

## 1. Flujo de ramas

El proyecto utiliza un flujo de trabajo basado en ramas para mantener organizado el desarrollo.

### Rama `main`

La rama `main` contiene el código estable del proyecto.

* Está protegida.
* No se deben realizar cambios directamente sobre ella.
* Los cambios deben llegar mediante Pull Requests.
* Debe contener código que haya sido revisado y validado.

### Rama `develop`

La rama `develop` se utiliza como rama de integración.

* Aquí se integran los cambios antes de llegar a `main`.
* Las nuevas funcionalidades y correcciones se prueban primero en esta rama.
* Los cambios deben ser revisados antes de integrarse.

### Ramas `feature/`

Se utilizan para desarrollar nuevas funcionalidades.

Formato:

```text
feature/nombre-de-la-tarea
```

Ejemplos:

```text
feature/registro-proveedores
feature/modulo-reportes
feature/agregar-facturacion
```

### Ramas `fix/`

Se utilizan para corregir errores existentes.

Formato:

```text
fix/nombre-del-error
```

Ejemplos:

```text
fix/error-login
fix/error-calculo
fix/error-validacion-formulario
```

### Flujo general

El flujo recomendado es:

```text
main
  │
  └── develop
        │
        ├── feature/nueva-funcionalidad
        │
        ├── feature/otra-funcionalidad
        │
        └── fix/correccion-error
```

Las ramas `feature/` y `fix/` se crean a partir de `develop` y posteriormente se integran nuevamente mediante Pull Request.

---

## 2. Convención de commits

Los mensajes de commit deben seguir el siguiente formato:

```text
tipo(area): descripción en imperativo
```

### Tipos permitidos

* `feat`: nueva funcionalidad.
* `fix`: corrección de un error.
* `docs`: cambios en documentación.
* `refactor`: modificación del código sin cambiar su comportamiento.
* `test`: creación o modificación de pruebas.
* `style`: cambios de formato o estilo del código.
* `chore`: tareas de mantenimiento o configuración.

### Ejemplos

```text
feat(auth): agregar registro de proveedores
fix(login): corregir validación de credenciales
docs(readme): actualizar instrucciones de instalación
refactor(users): simplificar consulta de usuarios
test(auth): agregar pruebas para inicio de sesión
chore(dependencies): actualizar dependencias
```

La descripción debe escribirse en **imperativo**, indicando qué cambio realiza el commit.

Ejemplo correcto:

```text
feat(auth): agregar validación de correo
```

Ejemplo a evitar:

```text
feat(auth): se agregó validación de correo
```

---

## 3. Cómo abrir un Pull Request

Cuando una tarea esté terminada:

### Paso 1. Actualizar `develop`

Antes de comenzar o finalizar el trabajo, obtener los cambios más recientes:

```bash
git checkout develop
git pull origin develop
```

### Paso 2. Crear la rama de trabajo

Para una nueva funcionalidad:

```bash
git checkout -b feature/nombre-de-la-tarea
```

Para una corrección:

```bash
git checkout -b fix/nombre-del-error
```

### Paso 3. Realizar los cambios

Desarrollar y probar la tarea correspondiente.

### Paso 4. Crear los commits

Agregar los archivos necesarios:

```bash
git add .
```

Crear el commit siguiendo la convención:

```bash
git commit -m "feat(area): agregar nueva funcionalidad"
```

### Paso 5. Subir la rama

```bash
git push origin feature/nombre-de-la-tarea
```

O, si es una corrección:

```bash
git push origin fix/nombre-del-error
```

### Paso 6. Abrir el Pull Request

En GitHub:

1. Entrar al repositorio.
2. Seleccionar **Pull requests**.
3. Seleccionar **New pull request**.
4. Seleccionar como rama destino `develop`.
5. Seleccionar la rama que contiene los cambios.
6. Escribir un título descriptivo.
7. Explicar los cambios realizados.
8. Indicar las pruebas realizadas.
9. Solicitar la revisión de los integrantes correspondientes.
10. Crear el Pull Request.

Una vez revisado y aprobado, el cambio podrá integrarse a `develop`.

---

## 4. ¿Qué se revisa en un Pull Request?

Antes de aprobar un Pull Request se debe verificar:

* Que el cambio corresponda a la tarea solicitada.
* Que el código funcione correctamente.
* Que no existan errores conocidos.
* Que se hayan realizado las pruebas necesarias.
* Que no se rompan funcionalidades existentes.
* Que el código mantenga una estructura clara.
* Que los nombres de archivos, variables y funciones sean adecuados.
* Que los commits respeten la convención establecida.
* Que no se incluyan archivos innecesarios.
* Que no se incluyan credenciales o información sensible.
* Que la documentación se actualice cuando sea necesario.

Los comentarios realizados durante la revisión deben ser atendidos antes de realizar la integración.

---

## 5. Archivos que NUNCA se deben subir

Nunca se deben subir al repositorio archivos que contengan información sensible, dependencias instaladas localmente o configuraciones específicas del entorno.

### Archivos y carpetas prohibidos

```text
.env
node_modules/
vendor/
```

También está prohibido subir:

* Contraseñas.
* Tokens.
* API keys.
* Credenciales de bases de datos.
* Claves privadas.
* Certificados privados.
* Información sensible de usuarios.
* Archivos de configuración que contengan secretos.

### Ejemplo

No se debe realizar:

```bash
git add .env
```

Tampoco:

```bash
git add node_modules/
git add vendor/
```

Si una credencial fue subida accidentalmente, no se debe considerar suficiente simplemente eliminar el archivo en un commit posterior. La credencial debe ser revocada o reemplazada.

---

## 6. Regla general

Antes de realizar un `push`, cada colaborador debe comprobar que:

* Está trabajando en la rama correcta.
* Los cambios corresponden a su tarea.
* Los archivos funcionan correctamente.
* Los commits siguen la convención.
* No se incluyen `.env`, `node_modules`, `vendor` ni credenciales.
* El código está listo para ser revisado mediante Pull Request.
