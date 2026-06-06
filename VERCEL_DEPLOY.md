# Despliegue en Vercel

Este proyecto es PHP clasico con MySQL. Vercel no es un cPanel, asi que se usa el runtime comunitario `vercel-php`.

## Archivos agregados

- `vercel.json`
- `api/index.php`

## Variables de entorno en Vercel

Configura estas variables en `Project Settings > Environment Variables`:

```text
DB_HOST
DB_USER
DB_PASS
DB_NAME
DB_PORT
```

Ejemplo:

```text
DB_PORT=3306
```

## Desplegar

Desde la carpeta del proyecto:

```bash
npm i -g vercel
vercel login
vercel
vercel --prod
```

## Advertencia importante

La base MySQL actual del hosting puede no aceptar conexiones externas desde Vercel. Si Vercel despliega pero el sistema muestra error de conexion, hay que usar una base accesible remotamente, por ejemplo:

- PlanetScale
- Aiven MySQL
- Railway MySQL
- Neon no sirve directamente porque es PostgreSQL, no MySQL

Para este sistema, una base MySQL remota con acceso externo es obligatoria si se usa Vercel.
