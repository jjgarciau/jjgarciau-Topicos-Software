# Tutorial 05 – Laravel, MySQL y phpMyAdmin con Docker en AWS

Despliegue en la instancia EC2 del grupo (Amazon Linux 2023), siguiendo la guía compartida por el profesor. Cada estudiante tiene sus propios tres contenedores: **Laravel**, **MySQL** y **phpMyAdmin**, con red y volúmenes propios.

## Archivos

| Archivo | Para qué sirve |
|---|---|
| `Dockerfile` | Imagen `php:8.4-apache` con `pdo_mysql` y Composer. Apache sirve `public/`, así que la app queda en `http://IP:PUERTO/` (sin `/public`). No usa Node ni Vite: el layout carga Bootstrap desde CDN. |
| `.dockerignore` | Deja fuera de la imagen `.env`, `vendor`, `node_modules`, claves `.pem` y cachés. |
| `compose.infra.yaml` | MySQL 8.4 (ajustado para poca memoria) y phpMyAdmin 5.2, en la red `${STUDENT_ID}-net`. MySQL solo se publica en `127.0.0.1`. |
| `compose.app.yaml` | La aplicación Laravel, conectada a la red creada por `compose.infra.yaml`. |

La configuración real (contraseñas, `APP_KEY`, puertos) va en el `.env` de la instancia, que **no** se sube a Git ni se copia en la imagen.

## Datos de este despliegue

| Variable | Valor |
|---|---|
| `STUDENT_ID` | `juan-garcia` |
| `APP_IMAGE` | `laravel-juan-garcia:1.0` |
| Base de datos / usuario | `juangarcia` |
| Laravel (`APP_PORT`) | 8081 |
| phpMyAdmin (`PMA_PORT`) | 9081 |
| MySQL en el host (`MYSQL_PORT`) | 33061 (solo `127.0.0.1`) |

## Despliegue (resumen)

```bash
mkdir -p ~/students/juan-garcia && cd ~/students/juan-garcia
git clone https://github.com/jjgarciau/jjgarciau-Topicos-Software.git app
cd app/laravelcourse
test -f .env || cp .env.example .env && chmod 600 .env && nano .env   # ver variables arriba

docker build -t laravel-juan-garcia:1.0 .
docker run --rm laravel-juan-garcia:1.0 php artisan key:generate --show   # copiar a APP_KEY

docker compose -f compose.infra.yaml up -d --wait --wait-timeout 240
# phpMyAdmin (root): crear base juangarcia y usuario juangarcia
docker compose -f compose.app.yaml up -d
docker compose -f compose.app.yaml exec --user www-data app php artisan migrate --force
```

Verificación: `/products/create`, `/products`, `/api/products`, `/api/v2/products`, `/api/v3/products`, `/cart/add/121` y `/cart`.

## Evidencias

> Agregar capturas en `docs/` y enlazarlas aquí.

1. Laravel funcionando en `http://IP:8081` (o por túnel en `http://localhost:18081`).
2. phpMyAdmin con la base `juangarcia`, sus tablas y el producto guardado.
3. El producto sigue apareciendo después de `down` y `up` sin `-v`.

## Respuestas a las preguntas

1. **Imagen, contenedor y volumen.** La *imagen* es una plantilla de solo lectura con el sistema, PHP, Apache y el código (`laravel-juan-garcia:1.0`). El *contenedor* es una instancia en ejecución de esa imagen, con su propia capa escribible que se pierde al borrarlo. El *volumen* es almacenamiento administrado por Docker que vive aparte del contenedor (`mysql_data`, `app_storage`), por eso los datos sobreviven a `down`/`up`.
2. **Puerto interno 3306 repetido, puertos publicados distintos.** Cada contenedor tiene su propia pila de red, así que los tres MySQL pueden escuchar en 3306 sin chocar. Los puertos *publicados* pertenecen al host (la EC2), que es uno solo: dos procesos no pueden ocupar el mismo puerto del host, por eso cada estudiante usa 33061/33062/33063, 8081/8082/8083, etc.
3. **`DB_HOST=mysql` y no `localhost`.** Dentro del contenedor de Laravel, `localhost` es el propio contenedor de Laravel, donde no hay MySQL. Docker tiene un DNS interno en cada red: `mysql` es el nombre del servicio y se resuelve a la IP del contenedor de MySQL dentro de `juan-garcia-net`.
4. **Cómo se conectan los dos Compose.** `compose.infra.yaml` crea la red con nombre fijo `juan-garcia-net`; `compose.app.yaml` la declara como `external: true` con ese mismo nombre, así que el contenedor de Laravel se une a esa red y ve a `mysql` por nombre.
5. **GitHub vs Docker Hub.** GitHub guarda el *código fuente* y su historial (Git). Docker Hub es un *registro de imágenes*: guarda los artefactos ya construidos, listos para hacer `docker pull` y ejecutarlos sin compilar.
6. **Por qué importa la arquitectura.** Una imagen contiene binarios compilados para una CPU concreta (`linux/amd64` o `linux/arm64`). Si se construye en un Mac con chip ARM y la EC2 es x86_64, el contenedor no arranca (`exec format error`) salvo que se construya con `--platform linux/amd64`.
7. **Si se elimina el volumen MySQL.** Se pierden la base, el usuario y todos los datos. Al volver a levantar `compose.infra.yaml` MySQL se inicializa vacío con el `MYSQL_ROOT_PASSWORD` actual, y hay que recrear base, usuario y migraciones.
