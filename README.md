# TaskFlow API

API REST de gestión de proyectos y tareas, construida en Laravel como **backend puro**: sin vistas, sin sesiones y sin nada que renderizar. Autenticación por tokens y documentación OpenAPI navegable, pensada para que la consuma cualquier cliente — una web, una app móvil o incluso un juego.

Es un Trello simplificado reducido a su contrato: usuarios, proyectos, tareas y estados. Cada usuario solo ve y edita lo suyo.

---

## Stack

| | |
|---|---|
| PHP | 8.4 |
| Laravel | 13 |
| Base de datos | MySQL / MariaDB |
| Autenticación | Laravel Sanctum (tokens) |
| Documentación | OpenAPI 3.0 vía `darkaonline/l5-swagger` + colección de Postman |
| Pruebas | Pest 4 — 69 pruebas, 216 aserciones |
| Análisis estático | Larastan (PHPStan) en **nivel 8** |
| Estilo | Laravel Pint |

---

## Instalación

Necesitas PHP 8.3 o superior, Composer y un MySQL o MariaDB en marcha.

```bash
git clone <url-del-repositorio> taskflow-api
cd taskflow-api
composer install
cp .env.example .env
php artisan key:generate
```

Crea las dos bases de datos — una para desarrollo y otra para las pruebas:

```sql
CREATE DATABASE taskflow_api CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE taskflow_api_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Ajusta `DB_USERNAME` y `DB_PASSWORD` en el `.env` si tu servidor no usa `root` sin contraseña. Después:

```bash
php artisan migrate --seed
php artisan serve
```

La API queda en `http://localhost:8000/api/v1`. Comprueba que responde:

```bash
curl http://localhost:8000/api/v1/health
```

### Datos de prueba

El seeder deja dos usuarios, cuatro proyectos y diez tareas repartidas entre los tres estados, con vencimientos pasados, futuros y sin fecha.

| Usuario | Contraseña | Para qué |
|---|---|---|
| `demo@taskflow.test` | `contrasena-demo` | El que vas a usar |
| `otra@taskflow.test` | `contrasena-demo` | Tiene un proyecto propio, para comprobar el 403 |

---

## Documentación

### Swagger

Con el servidor levantado, abre **<http://localhost:8000/api/documentation>**. Es una interfaz navegable donde puedes probar cada endpoint desde el navegador: pulsa **Authorize**, pega el token que devuelve el login y ejecuta cualquier petición.

El documento OpenAPI en crudo está en `http://localhost:8000/docs`. Para regenerarlo a mano:

```bash
php artisan l5-swagger:generate
```

Hay una prueba que regenera el documento y falla si alguna ruta registrada no está documentada. La documentación que se queda atrás del código es peor que no tener ninguna: promete un contrato que la API ya no cumple.

### Postman

En la carpeta `postman/` hay dos ficheros: la colección y un entorno.

**1. Importar.** En Postman, `Import` → arrastra los dos ficheros de `postman/`.

**2. Seleccionar el entorno.** Arriba a la derecha, elige *TaskFlow API — local*. Solo trae `base_url`; cámbialo si sirves la API en otro puerto.

**3. Iniciar sesión.** Ejecuta **Autenticación → Iniciar sesión**. Su script guarda el token en la variable `token`, y el resto de la colección lo usa sola: **no tienes que copiar y pegar el token en ninguna petición**.

**4. Trabajar.** **Proyectos → Crear proyecto** guarda el id en `project_id`, y **Tareas → Crear tarea** guarda `task_id`. Por eso las demás peticiones de esas carpetas funcionan sin que toques nada.

**5. Ver el contrato de errores.** La carpeta **Errores esperados** demuestra el 401, el 403, el 404 y el 422. Sus dos primeras peticiones son de preparación: obtienen el id de un proyecto que pertenece a otra persona para poder pedirlo con tu token y recibir el 403.

Las carpetas están **en orden de ejecución**: puedes darle a *Run* en el Collection Runner y recorrerla entera de arriba abajo. Cada petición lleva sus propias aserciones. Con Newman:

```bash
npx newman run postman/TaskFlow-API.postman_collection.json -e postman/TaskFlow-API.postman_environment.json
```

---

## Endpoints

Base: `http://localhost:8000/api/v1`. Todo salvo `/health`, `/auth/register` y `/auth/login` exige la cabecera `Authorization: Bearer <token>`.

| Método | Ruta | Descripción |
|---|---|---|
| `GET` | `/health` | Estado de la aplicación y de la base de datos |
| `POST` | `/auth/register` | Registro; devuelve el primer token |
| `POST` | `/auth/login` | Emite un token nuevo |
| `POST` | `/auth/logout` | Revoca **solo** el token usado |
| `GET` | `/auth/me` | Usuario autenticado |
| `GET` | `/projects` | Listado paginado. `?search=` `?sort=` `?per_page=` |
| `POST` | `/projects` | Crea un proyecto |
| `GET` | `/projects/{project}` | Detalle, con `tasks_count` |
| `PATCH` | `/projects/{project}` | Actualización parcial |
| `DELETE` | `/projects/{project}` | Borrado lógico; arrastra sus tareas |
| `GET` | `/projects/{project}/tasks` | Tareas del proyecto. `?status=` `?assigned_to=` `?due_before=` `?due_after=` `?search=` `?sort=` `?per_page=` |
| `POST` | `/projects/{project}/tasks` | Crea una tarea |
| `GET` | `/tasks/{task}` | Detalle, con proyecto y asignado |
| `PATCH` | `/tasks/{task}` | Actualización parcial |
| `PATCH` | `/tasks/{task}/status` | Cambia solo el estado |
| `DELETE` | `/tasks/{task}` | Borrado lógico |

Estados de una tarea: `pending`, `in_progress`, `completed`.

### Ejemplo

```bash
# Iniciar sesión
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"demo@taskflow.test","password":"contrasena-demo"}'
```

```json
{
  "data": {
    "user": { "id": 1, "name": "Usuario Demo", "email": "demo@taskflow.test" },
    "token": "3|kR7pQ2mZ...",
    "token_type": "Bearer"
  }
}
```

```bash
# Listar las tareas en progreso de un proyecto
curl "http://localhost:8000/api/v1/projects/{uuid}/tasks?status=in_progress" \
  -H "Authorization: Bearer 3|kR7pQ2mZ..."
```

---

## Contrato de errores

Un cliente debe poder programar contra los errores, no solo contra los aciertos. Todos están cubiertos por pruebas.

| Código | Cuándo |
|---|---|
| `400` | El cuerpo dice ser JSON pero no lo es |
| `401` | Token ausente, inválido o revocado |
| `403` | El recurso existe pero es de otra persona |
| `404` | No existe, o fue borrado lógicamente |
| `405` | Método no permitido en esa ruta |
| `422` | Falla de validación, en el cuerpo **o** en los parámetros de consulta |
| `429` | Se superó el límite de peticiones |

El 422 es el único con estructura anidada:

```json
{
  "message": "The name field is required.",
  "errors": { "name": ["The name field is required."] }
}
```

---

## Decisiones técnicas

**Sanctum y no Passport.** Passport es un servidor OAuth2 completo, y eso solo se justifica cuando aplicaciones de **terceros** consumen tu API en nombre de tus usuarios. Aquí los clientes son propios, así que bastan los *personal access tokens*: un hash en una tabla, cero infraestructura extra.

**API Resources y no modelos crudos.** Devolver un modelo Eloquent ata la forma de la respuesta al esquema de la base de datos: renombrar una columna rompería a cualquier app móvil ya publicada. El Resource decide qué sale, con qué nombre y en qué formato.

**UUID v7 como identificador público.** Con enteros correlativos, `/projects/1` invita a recorrer `/projects/2` y además revela cuántos proyectos existen. Laravel genera UUID de versión 7, que llevan la marca de tiempo en los bits altos: importa porque un v4 puro inserta en posiciones aleatorias del índice de InnoDB y lo fragmenta.

**Aislamiento en dos capas.** Los listados se construyen desde `$user->projects()`, nunca desde `Project::query()` filtrado después — un `where` olvidado en un listado no filtra un registro, filtra la tabla entera. Encima, cada acceso individual pasa por una Policy. Las tareas heredan el permiso del proyecto.

**403 y no 404 para recursos ajenos.** Responder 404 ocultaría que el recurso existe, pero con UUID adivinar un id válido ya es inviable: el ocultamiento no aporta nada y el 403 le dice la verdad al cliente.

**La autorización corre antes que la validación.** La comprobación de permisos vive en `authorize()` del FormRequest, que Laravel ejecuta antes de las reglas. Así, editar un recurso ajeno con datos inválidos devuelve 403 y no un 422 detallando qué está mal en un *payload* que el usuario nunca tuvo derecho a enviar. Hay una prueba que fija ese orden.

**Rutas anidadas con `shallow()`.** `POST /projects/{project}/tasks` porque una tarea no existe sin proyecto, pero `GET /tasks/{task}` porque su UUID ya es único: pedir también el proyecto obligaría al cliente a arrastrar un dato que el servidor no necesita.

**Enum de PHP sobre `varchar`, no `ENUM` de MySQL.** `App\Enums\TaskStatus` es la única fuente de verdad: alimenta el *cast* del modelo, la validación y el esquema OpenAPI. Añadir un cuarto estado a un `ENUM` de MySQL sería un `ALTER TABLE` que reconstruye la tabla; así es una línea.

**El borrado lógico se propaga a mano.** El `ON DELETE CASCADE` es una restricción de MySQL que reacciona a un `DELETE`; un borrado lógico es un `UPDATE`, y la base no se entera. Sin un evento `deleting` en `Project`, las tareas de un proyecto borrado seguirían respondiendo 200, huérfanas de un padre que ya no existe.

**Las pruebas corren sobre MySQL, no SQLite.** Es más lento, pero SQLite y MySQL difieren en tipos de fecha, modo estricto y comportamiento de las claves foráneas. Un test verde en un motor que no es el de producción prueba menos de lo que aparenta.

**Rate limiting con clave compuesta.** El limitador de login combina email e IP: solo por IP castigaría a todos los usuarios detrás de una misma red, y solo por email permitiría bloquear la cuenta ajena a voluntad.

---

## Calidad

Las tres comprobaciones de una vez:

```bash
composer check    # estilo + análisis estático + pruebas
```

O por separado:

```bash
composer test      # 69 pruebas, 216 aserciones
composer analyse   # Larastan en nivel 8
composer lint      # Pint, solo verifica
composer format    # Pint, corrige
```

### Pruebas

Corren sobre la base `taskflow_api_test`, el mismo motor que desarrollo. No hay objetivo de porcentaje de cobertura; el criterio es otro: **todo camino de autorización tiene una prueba que verifica que se deniega**. Una suite que solo prueba el camino feliz demuestra que la API funciona, no que es segura.

Hay tres suites: `Feature` (el contrato HTTP), `Arch` (invariantes de arquitectura) y `Unit`.

Las pruebas de arquitectura fijan cosas que ninguna prueba funcional detectaría, porque no fallan al ejecutar sino al crecer: que no queden restos de depuración, que toda petición parta de `ApiFormRequest`, que toda respuesta pase por un API Resource, y que los modelos y las policies no conozcan la capa HTTP.

También hay dos pruebas de regresión sobre problemas concretos: una cuenta las consultas del listado de tareas y falla si vuelve un N+1; otra comprueba que un JSON mal formado devuelve 400 y no un confuso 422.

### Análisis estático

Larastan en **nivel 8**, el máximo antes del modo estricto. Además de exigir tipos en todas partes, prohíbe llamar métodos sobre valores que puedan ser `null` — y eso obligó a un cambio real: dejar de asumir que `$request->user()` nunca es `null` solo porque el middleware lo garantice. Ahora `ApiFormRequest::authenticatedUser()` lo comprueba y lanza un 401 si falla, en vez de reventar con un 500 opaco el día que alguien quite el middleware de una ruta.

Las pruebas quedan fuera del análisis a propósito: PHPStan no sabe modelar el enlace de `$this` dentro de las closures de Pest, y serían decenas de falsos positivos enterrando los hallazgos reales.

### Integración continua

`.github/workflows/tests.yml` levanta un servicio MariaDB y ejecuta `composer audit`, `pint --test`, `phpstan` y la suite completa.

---

## Estructura

```
app/
├── Enums/TaskStatus.php              Fuente única del estado de una tarea
├── Http/
│   ├── Controllers/Api/              Controladores + anotaciones OpenAPI
│   ├── Middleware/                   Fuerzan el contrato JSON en los bordes
│   ├── Requests/                     Validación y autorización
│   └── Resources/                    Contrato público + esquemas OpenAPI
├── Models/                           Project, Task, User
├── OpenApi/ApiSpec.php               Bloque raíz del documento OpenAPI
└── Policies/                         Reglas de propiedad
bootstrap/app.php                     Rutas, middleware y contrato de errores
postman/                              Colección y entorno
```

---

## Fuera del alcance

Decisiones tomadas, no olvidos:

- **Colaboración multiusuario.** Un proyecto tiene un solo dueño. La tabla pivote `project_user` es la extensión natural, pero duplicaría la lógica de autorización sin demostrar ninguna técnica nueva.
- **Restaurar lo borrado.** Restaurar un proyecto no devuelve sus tareas: hacerlo bien exigiría distinguir las que se borraron *con* él de las que ya lo estaban. La API no expone restauración en la v1.
- **Refresh tokens.** Los tokens caducan a los siete días y se renuevan iniciando sesión. La rotación pertenece al mundo OAuth2.
- **Interfaz de usuario.** Es el punto del proyecto.

---

## Autor

**Junni Díaz** — [junnidiazp@gmail.com](mailto:junnidiazp@gmail.com)

Licencia MIT.
