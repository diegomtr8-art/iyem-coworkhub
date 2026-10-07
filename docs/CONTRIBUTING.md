# Guía de contribución

Este documento explica cómo trabajar en los repositorios del proyecto utilizando Git y GitHub.

La finalidad es mantener un flujo de trabajo ordenado, evitar conflictos y asegurarnos de que los cambios que llegan a producción hayan sido revisados.

---

## 1. Antes de comenzar

Antes de trabajar en una tarea:

1. Asegúrate de tener el repositorio actualizado.
2. Utiliza la rama `develop` como base para nuevos cambios.
3. No trabajes directamente sobre `main`.
4. Revisa si ya existe una rama relacionada con la tarea que vas a realizar.
5. Lee la documentación del proyecto (`README.md`) antes de modificar código.

Para actualizar tu repositorio:

```bash
git checkout develop
git pull origin develop
```

---

# 2. Flujo de ramas

El proyecto utiliza tres tipos principales de ramas.

```text
main
  ↑
  │ Pull Request
  │
develop
  ↑
  │ Pull Request
  │
feature/...
fix/...
```

## `main`

La rama `main` representa la versión estable del proyecto.

* Está **protegida**.
* No se deben realizar cambios directamente sobre ella.
* Los cambios llegan mediante Pull Requests.
* No se debe hacer `push` directamente a `main`.

## `develop`

La rama `develop` es la rama de **integración**.

Aquí se juntan los cambios de las diferentes tareas antes de pasar a `main`.

Para comenzar una tarea normalmente se parte de `develop`.

```bash
git checkout develop
git pull origin develop
```

## `feature/`

Se utiliza para desarrollar una nueva funcionalidad.

Formato:

```text
feature/nombre-de-la-tarea
```

Ejemplo:

```text
feature/registro-proveedores
```

Crear una rama:

```bash
git checkout develop
git pull origin develop
git checkout -b feature/registro-proveedores
```

## `fix/`

Se utiliza para corregir un error existente.

Formato:

```text
fix/nombre-del-error
```

Ejemplo:

```text
fix/error-login
```

Crear una rama:

```bash
git checkout develop
git pull origin develop
git checkout -b fix/error-login
```

### Regla importante

Cada tarea debe tener su propia rama.

No mezcles diferentes funcionalidades o correcciones en una misma rama si no están relacionadas.

---

# 3. Convención de commits

Los commits deben seguir esta estructura:

```text
tipo(area): descripción en imperativo
```

### Tipos

| Tipo       | Uso                                                       |
| ---------- | --------------------------------------------------------- |
| `feat`     | Nueva funcionalidad                                       |
| `fix`      | Corrección de un error                                    |
| `docs`     | Cambios en documentación                                  |
| `refactor` | Reestructuración del código sin cambiar su funcionamiento |
| `test`     | Creación o modificación de pruebas                        |
| `style`    | Cambios de formato o estilo                               |
| `chore`    | Tareas de mantenimiento o configuración                   |

### Ejemplos

```text
feat(auth): agrega recuperación de contraseña
```

```text
fix(reservas): corrige validación de fechas
```

```text
docs(readme): actualiza instrucciones de instalación
```

```text
refactor(usuarios): reorganiza controlador de usuarios
```

```text
test(reservas): agrega pruebas para creación de reservas
```

### Reglas para los commits

* Escribir la descripción en **imperativo**.
* Mantener el mensaje claro y breve.
* Indicar el área afectada.
* No utilizar mensajes genéricos como:

```text
cambios
arreglos
actualizacion
cosas nuevas
```

En su lugar, indicar exactamente qué se modificó.

---

# 4. Trabajar en una tarea

Una vez creada la rama correspondiente:

```bash
git checkout feature/nombre-de-la-tarea
```

Realiza los cambios necesarios y revisa que todo funcione correctamente.

Después, verifica los archivos modificados:

```bash
git status
```

Puedes revisar los cambios realizados con:

```bash
git diff
```

Cuando estés seguro de los cambios:

```bash
git add .
```

Realiza el commit siguiendo la convención:

```bash
git commit -m "tipo(area): descripcion en imperativo"
```

Finalmente, sube la rama:

```bash
git push origin feature/nombre-de-la-tarea
```

Para una corrección se utiliza el mismo procedimiento cambiando `feature/` por `fix/`.

---

# 5. Cómo abrir un Pull Request

Después de subir la rama a GitHub:

1. Entra al repositorio.
2. GitHub mostrará la opción para crear un Pull Request.
3. Selecciona como rama de destino `develop`.
4. Selecciona como rama de origen tu rama `feature/` o `fix/`.
5. Escribe un título claro.
6. Describe qué cambios realizaste.
7. Indica si existe algún punto que el revisor deba conocer.
8. Revisa los cambios antes de crear el Pull Request.
9. Solicita la revisión de los integrantes correspondientes.

El flujo normal es:

```text
feature/* o fix/*
        ↓
     develop
        ↓
      main
```

Los Pull Requests hacia `main` deben realizarse únicamente cuando los cambios correspondientes ya hayan sido integrados y validados en `develop`.

---

# 6. Qué debe contener un Pull Request

El Pull Request debe permitir que otra persona entienda rápidamente qué se modificó y por qué.

La descripción debe incluir, cuando corresponda:

### ¿Qué se hizo?

Explica brevemente el cambio.

### ¿Por qué se hizo?

Indica qué problema resuelve o qué necesidad atiende.

### ¿Cómo se probó?

Indica las pruebas realizadas para verificar que el cambio funciona.

### Consideraciones adicionales

Indica si existe alguna configuración, migración, variable de entorno o procedimiento especial que deba realizarse.

---

# 7. Revisión de Pull Requests

Antes de aprobar un Pull Request se debe revisar:

### Código

* El cambio corresponde a la tarea.
* El código es comprensible.
* No existen cambios innecesarios.
* Se respeta la estructura del proyecto.
* No se introducen errores evidentes.

### Funcionalidad

* La funcionalidad solicitada funciona correctamente.
* Las correcciones solucionan el problema reportado.
* No se rompen funcionalidades existentes.

### Pruebas

* Las pruebas existentes continúan funcionando.
* Se agregan pruebas cuando sean necesarias.
* Se realizaron pruebas manuales cuando corresponda.

### Base de datos

Si existen cambios de base de datos:

* Las migraciones están incluidas.
* Los cambios son necesarios.
* No se incluyen bases de datos personales o archivos innecesarios.

### Seguridad

* No se incluyen contraseñas.
* No se incluyen tokens.
* No se incluyen claves API.
* No se incluyen credenciales.
* No se incluye información sensible.

### Documentación

Si el cambio modifica la forma de utilizar o configurar el sistema:

* Se actualiza el `README.md`.
* Se actualiza la documentación correspondiente.

---

# 8. Qué NUNCA se sube al repositorio

Estos archivos o datos **nunca deben subirse a GitHub**.

## `.env`

No subir archivos `.env` porque pueden contener:

* Contraseñas.
* Tokens.
* Claves API.
* Credenciales de bases de datos.
* Configuraciones privadas.

En su lugar, utilizar:

```text
.env.example
```

El `.env.example` debe contener únicamente los nombres de las variables necesarias y valores de ejemplo que no sean secretos.

---

## `node_modules`

No subir:

```text
node_modules/
```

Las dependencias de Node.js se instalan mediante:

```bash
npm install
```

---

## `vendor`

No subir:

```text
vendor/
```

Las dependencias de PHP se instalan mediante:

```bash
composer install
```

---

## Credenciales

Nunca subir:

* Contraseñas.
* Tokens.
* API keys.
* Claves privadas.
* Credenciales de bases de datos.
* Certificados privados.
* Archivos con información sensible.
* Credenciales de servicios externos.

Si una credencial se subió accidentalmente, **no basta con eliminarla del commit siguiente**. Se debe avisar al responsable para que la credencial sea revocada o reemplazada.

---

# 9. Archivos que normalmente tampoco deben subirse

Antes de hacer `git add .`, revisa que no estés incluyendo archivos generados automáticamente o específicos de tu equipo.

Por ejemplo:

```text
node_modules/
vendor/
.env
*.log
```

También evita subir archivos temporales, archivos personales del editor o archivos generados por herramientas que no formen parte del proyecto.

---

# 10. Antes de crear el Pull Request

Utiliza esta lista como revisión final:

* [ ] Estoy trabajando en una rama `feature/` o `fix/`.
* [ ] Mi rama parte de `develop`.
* [ ] No modifiqué directamente `main`.
* [ ] Mis commits siguen `tipo(area): descripción en imperativo`.
* [ ] Probé los cambios.
* [ ] No hay errores conocidos sin documentar.
* [ ] No subí `.env`.
* [ ] No subí `node_modules/`.
* [ ] No subí `vendor/`.
* [ ] No subí contraseñas, tokens ni credenciales.
* [ ] Actualicé la documentación si era necesario.
* [ ] Mi Pull Request tiene una descripción clara.
* [ ] El Pull Request tiene como destino `develop`.

---

# 11. Resumen del flujo

Para una nueva funcionalidad:

```bash
git checkout develop
git pull origin develop
git checkout -b feature/nueva-funcionalidad
```

Realizar cambios:

```bash
git add .
git commit -m "feat(area): agrega nueva funcionalidad"
git push origin feature/nueva-funcionalidad
```

Después:

```text
feature/nueva-funcionalidad
            ↓
          develop
            ↓
           main
```

Para una corrección:

```bash
git checkout develop
git pull origin develop
git checkout -b fix/nombre-del-error
```

Y seguir el mismo proceso.

---

# 12. Regla principal

Antes de subir cualquier cambio, recuerda:

> **Trabaja en tu propia rama, mantén `main` protegida, integra mediante `develop`, documenta tus cambios y nunca subas información sensible.**
