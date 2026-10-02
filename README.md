# Proyecto_Empresa

## Actividad de la empresa en tiempo real

El dashboard permite registrar movimientos del equipo. Las actividades se guardan
con el usuario que las creó y se emiten a los demás usuarios conectados mediante
un canal privado de Laravel Reverb.

Ejecuta las migraciones y levanta Reverb en una terminal aparte:

```bash
php artisan migrate
php artisan reverb:start
```

En producción, configura `REVERB_ALLOWED_ORIGINS` con los dominios que sirven
la aplicación.

Para depurar registros antiguos, primero revisa cuántos se eliminarían y luego
ejecuta la limpieza:

```bash
php artisan activities:prune --days=90 --pretend
php artisan activities:prune --days=90
```
