# test-manager-library-3145555-api

> **Prueba técnica · Ficha ADSO 3145555**
> Horario: **miércoles 14 de octubre de 2026, de 6:50 a. m. a 11:00 a. m.** (hora Colombia). Entrega a las **11:00 a. m.**

Este repositorio es el **backend** de *Manager Library*, en **PHP con Laravel**. **Empieza vacío a propósito**: se construye en el fork de cada aprendiz.

## Instrucciones

Cada aprendiz debe **crear el fork** de los seis repositorios del proyecto y **resolver el proyecto
con el spec planteado**.

1. Hacer fork, a su cuenta de GitHub, de cada repositorio de la tabla del final.
2. Leer el spec en [`test-manager-library-3145555-docs`](https://github.com/code-sena/test-manager-library-3145555-docs).
   Se entrega en dos versiones: `spec-python/` y `spec-.net/`.
3. Desarrollar en los forks.

## El reto se desarrolla con React y PHP (Laravel)

El spec está escrito para Python y para .NET, pero el reto **no** se hace en esos lenguajes:

| Capa | Tecnología del reto |
|---|---|
| Frontend | React |
| Backend | PHP con Laravel |

Lo que el spec define sobre el negocio —historias, criterios de aceptación, reglas, contrato de la
API, modelo de datos— se respeta. Lo que define sobre la tecnología se traduce a React y Laravel.

## La prueba no consiste en escribir el código

El propósito principal es ver la **capacidad de desempeño con SDD** (*Spec-Driven Development*,
desarrollo guiado por especificación): cómo se lee, se interpreta y se aplica una especificación
para llevarla a un stack distinto. El código es el medio, no el fin.

## Entrega

Cuenta lo que quede **subido a los forks antes de las 11:00 a. m.** Lo que llegue después no se revisa.
No hay prórroga: suban avances parciales con commits pequeños durante la prueba.

## Los seis repositorios

| Repositorio | Qué va ahí |
|---|---|
| [`test-manager-library-3145555-docs`](https://github.com/code-sena/test-manager-library-3145555-docs) | El spec: `spec-python/` y `spec-.net/` |
| [`test-manager-library-3145555-api`](https://github.com/code-sena/test-manager-library-3145555-api) | Backend en PHP (Laravel) |
| [`test-manager-library-3145555-app`](https://github.com/code-sena/test-manager-library-3145555-app) | Frontend en React |
| [`test-manager-library-3145555-page`](https://github.com/code-sena/test-manager-library-3145555-page) | Sitio público estático de presentación |
| [`test-manager-library-3145555-infra`](https://github.com/code-sena/test-manager-library-3145555-infra) | Contenedores, red, volúmenes y motor de base de datos vacío |
| [`test-manager-library-3145555-tool`](https://github.com/code-sena/test-manager-library-3145555-tool) | Utilidades: sembrador de datos de demostración |

## Primer avance: T-01

Esta rama prepara el backend con PHP 8.4 y Laravel 13, siguiendo la
[adaptación aprobada](https://github.com/valerysinai/test-manager-library-3145555-docs/blob/main/adaptacion-react-laravel.md).
La única operación de producto implementada en este avance es `GET /api/v1/health`, pública,
con respuesta HTTP 200 y cuerpo `{"status":"ok"}`. No necesita base de datos ni secretos para arrancar.

### Preparar la imagen y verificar

Requisito: Docker con motor Linux activo. Ejecutar desde la raíz de este repositorio:

```sh
docker build --target test -t manager-library-api:test .
docker build -t manager-library-api:bootstrap .
```

El primer comando instala las dependencias de prueba y ejecuta PHPUnit. El segundo crea
la imagen de ejecución con las dependencias de producción fijadas en `composer.lock`.
PHP y Composer se ejecutan dentro del contenedor; no es necesario instalarlos en Windows.

La imagen de PHP y Composer están fijados por digest/checksum. `composer.lock` se generó
con PHP 8.4 en GitHub Actions y se conserva en el commit `b60bc27`. El paso temporal que lo
publicó fue retirado; el flujo final tiene permisos de lectura y no crea commits.
Las instalaciones reproducen las versiones del lockfile sin resolverlas de nuevo.

### Arrancar con un comando

```sh
docker run --rm -p 127.0.0.1:8000:8000 manager-library-api:bootstrap
```

En otra terminal:

```sh
curl http://localhost:8000/api/v1/health
```

En PowerShell también se puede consultar con:

```powershell
Invoke-RestMethod http://localhost:8000/api/v1/health
```

El puerto queda limitado a la máquina local. Este servidor sirve para verificar T-01;
el despliegue integrado y la terminación HTTPS pertenecen a T-20 en `infra`.
No se envían credenciales en este recorrido de salud. Para detenerlo, usar Ctrl+C.

### Estructura y límites del avance

`app/Library/Domain`, `Application/Ports`, `Application/Services` y `Adapters/Outbound`
reservan las capas del diseño mediante archivos `.gitkeep`. No contienen reglas de negocio
simuladas. El adaptador HTTP está en `Adapters/Inbound/Http`; la composición del núcleo
tiene su único punto en `app/Providers/LibraryServiceProvider.php`.

Las pruebas comprueban salud sin conexión a la base, prefijo correcto, 404, 405 y error 500
con el sobre JSON documentado, sin revelar detalles internos incluso en modo debug.
Esto no acredita autenticación ni el contrato completo de T-16, ni las pruebas de arquitectura de T-19.

Este avance no crea tablas, usuarios, sesiones, tokens ni datos de demostración.
T-02 y las demás tareas siguen pendientes. Las migraciones vivirán en `database/migrations`.
Las variables de despliegue están descritas sin valores secretos en `.env.example`;
el archivo `.env` real y los registros de ejecución no se versionan.

La evidencia de este avance y los límites de su validación están en [evidence/t-01.md](evidence/t-01.md).
