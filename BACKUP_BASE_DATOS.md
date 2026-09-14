# Backup de base de datos desde phpMyAdmin

Base actual:

```text
ezyro_40914543_votantes
```

## Pasos

1. Entra al panel del hosting.
2. Abre `phpMyAdmin`.
3. Selecciona la base `ezyro_40914543_votantes`.
4. Entra en la pestana `Exportar`.
5. Elige `Personalizado`.
6. Selecciona todas las tablas.
7. Formato: `SQL`.
8. En `Salida`, elige descargar archivo.
9. Si aparece compresion, usa `gzip` si la base es grande.
10. Activa estas opciones si aparecen:
    - `Agregar DROP TABLE`
    - `Agregar CREATE TABLE`
    - `Exportar estructura y datos`
    - `Usar INSERT extendido`
11. Pulsa `Exportar` o `Continuar`.

Al terminar deberias tener un archivo parecido a:

```text
ezyro_40914543_votantes.sql
```

o:

```text
ezyro_40914543_votantes.sql.gz
```

Guarda ese archivo fuera del hosting, por ejemplo en tu PC o Google Drive.
