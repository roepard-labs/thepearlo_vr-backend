# 📤 Scripts de Configuración - Backend

## 📋 Índice de Scripts

### 1. `increase-upload-limits.sh` - Aumentar Límites de Subida

Script automático para modificar `php.ini` y permitir uploads de hasta **50 MB**.

---

## 🚀 Uso del Script `increase-upload-limits.sh`

### Ejecución Básica

```bash
# Desde el directorio del backend
cd /path/to/thepearlo_vr-backend/scripts

# Ejecutar script
bash increase-upload-limits.sh

# O si ya tiene permisos de ejecución
./increase-upload-limits.sh
```

### Si Necesitas Permisos de Administrador

```bash
# Si el script no tiene permisos de escritura en php.ini
sudo bash increase-upload-limits.sh
```

---

## ✅ Qué Hace el Script

1. **Detecta automáticamente** la ubicación de `php.ini` activo
2. **Crea un backup** con timestamp (ej: `php.ini.backup.20250511_143022`)
3. **Modifica las siguientes configuraciones**:
   ```ini
   upload_max_filesize = 50M
   post_max_size = 60M
   memory_limit = 256M
   max_execution_time = 300
   max_input_time = 300
   ```
4. **Muestra valores antes y después** para verificar cambios
5. **Reinicia servicios automáticamente** (PHP-FPM, Apache, Nginx si aplica)
6. **Genera reporte completo** de la operación

---

## 📊 Ejemplo de Salida

```
╔════════════════════════════════════════════════════════════╗
║  📤 Aumentar Límites de Subida de Archivos en PHP        ║
║  HomeLab AR - Roepard Labs                                ║
╚════════════════════════════════════════════════════════════╝

🔍 Detectando ubicación de php.ini...
✅ php.ini encontrado: /home/user/.config/herd-lite/bin/php.ini

💾 Creando backup: /home/user/.config/herd-lite/bin/php.ini.backup.20250511_143022
✅ Backup creado exitosamente

📊 Valores actuales de php.ini:
────────────────────────────────────────────────────
upload_max_filesize => 2M => 2M
post_max_size => 8M => 8M
memory_limit => 128M => 128M
max_execution_time => 0 => 0
────────────────────────────────────────────────────

⚙️  Aplicando nuevas configuraciones...

✅ upload_max_filesize actualizado a 50M
✅ post_max_size actualizado a 60M
✅ memory_limit actualizado a 256M
✅ max_execution_time actualizado a 300
✅ max_input_time actualizado a 300

✅ Todas las configuraciones aplicadas correctamente

📊 Nuevos valores de php.ini:
────────────────────────────────────────────────────
upload_max_filesize => 50M => 50M
post_max_size => 60M => 60M
memory_limit => 256M => 256M
max_execution_time => 300 => 300
────────────────────────────────────────────────────

╔════════════════════════════════════════════════════════════╗
║  ✅ Configuración completada exitosamente                 ║
╚════════════════════════════════════════════════════════════╝

📝 Resumen de cambios:
  • upload_max_filesize: 50M
  • post_max_size: 60M
  • memory_limit: 256M
  • max_execution_time: 300s
  • max_input_time: 300s

💾 Backup guardado en:
  /home/user/.config/herd-lite/bin/php.ini.backup.20250511_143022

🧪 Para probar los cambios:
  php -i | grep upload_max_filesize

🔙 Para restaurar el backup:
  cp /home/user/.config/herd-lite/bin/php.ini.backup.20250511_143022 /home/user/.config/herd-lite/bin/php.ini

¡Listo! Ahora puedes subir archivos de hasta 50 MB
```

---

## 🔧 Características del Script

### ✅ Seguridad

- **Crea backup automático** antes de modificar
- **Verifica permisos** antes de ejecutar
- **Validaciones de error** con `set -e`
- **Backup con timestamp** único para no sobrescribir

### ✅ Inteligencia

- **Detecta automáticamente** la ubicación de `php.ini`
- **Actualiza valores existentes** (comentados o no)
- **Agrega nuevas directivas** si no existen
- **Detecta y reinicia servicios** (PHP-FPM, Apache, Nginx)

### ✅ User-Friendly

- **Colores en output** para mejor legibilidad
- **Mensajes informativos** en cada paso
- **Muestra valores antes/después** para verificación
- **Comandos de ayuda** incluidos en la salida

---

## 🧪 Verificación Post-Instalación

### Test 1: Verificar Configuración

```bash
php -i | grep -E "upload_max_filesize|post_max_size|memory_limit"
```

**Resultado esperado**:

```
upload_max_filesize => 50M => 50M
post_max_size => 60M => 60M
memory_limit => 256M => 256M
```

### Test 2: Subir Archivo de Prueba

1. Navegar al gestor de archivos: http://localhost:9000/dashboard/files
2. Entrar a una carpeta (ej: "Documentos")
3. Click "Subir Archivo"
4. Seleccionar archivo de 13 MB (como el que probaste)
5. Verificar que sube correctamente sin error

### Test 3: Verificar Logs

```bash
# Ver últimas 20 líneas del log de PHP
tail -20 /var/log/php-fpm/error.log

# O para Laravel Herd
tail -20 ~/.config/herd-lite/logs/php.log
```

---

## 🔙 Restaurar Backup

Si algo sale mal, puedes restaurar el backup:

```bash
# Listar backups disponibles
ls -lh /home/user/.config/herd-lite/bin/php.ini.backup.*

# Restaurar último backup
BACKUP_FILE=$(ls -t /home/user/.config/herd-lite/bin/php.ini.backup.* | head -1)
cp "$BACKUP_FILE" /home/user/.config/herd-lite/bin/php.ini

# Verificar restauración
php -i | grep upload_max_filesize
```

---

## 🎯 Límites Configurados

| Directiva             | Valor Anterior | Valor Nuevo | Propósito                                  |
| --------------------- | -------------- | ----------- | ------------------------------------------ |
| `upload_max_filesize` | 2M             | **50M**     | Tamaño máximo de archivo individual        |
| `post_max_size`       | 8M             | **60M**     | Tamaño máximo del POST (debe ser > upload) |
| `memory_limit`        | 128M           | **256M**    | Memoria para procesar archivo              |
| `max_execution_time`  | 0 o 30         | **300s**    | Tiempo máximo de ejecución (5 min)         |
| `max_input_time`      | 60             | **300s**    | Tiempo máximo para recibir datos (5 min)   |

### ¿Por qué estos valores?

- **50 MB** es suficiente para:

  - Imágenes de alta resolución (10-20 MB)
  - Documentos PDF con imágenes (5-15 MB)
  - Modelos 3D pequeños (.glb, .gltf) (10-30 MB)
  - Videos cortos (20-50 MB)

- **60 MB** en `post_max_size` da margen adicional para:

  - Metadata del formulario
  - Múltiples campos adicionales
  - Headers HTTP

- **256 MB** en `memory_limit`:
  - Al menos 2x el tamaño de `post_max_size`
  - Suficiente para procesar imágenes
  - Margen para operaciones adicionales

---

## ⚠️ Troubleshooting

### Problema 1: "No se encontró php.ini"

**Causa**: PHP no tiene un php.ini configurado

**Solución**:

```bash
# Encontrar ubicación de php.ini
php --ini

# Si dice "Configuration File: (none)"
# Crear uno desde el template
cp /etc/php/8.4/cli/php.ini-development ~/.config/herd-lite/bin/php.ini
```

### Problema 2: "No tienes permisos de escritura"

**Causa**: El usuario actual no puede modificar php.ini

**Solución**:

```bash
# Opción 1: Ejecutar con sudo
sudo bash increase-upload-limits.sh

# Opción 2: Cambiar propietario del archivo
sudo chown $USER:$USER /path/to/php.ini
bash increase-upload-limits.sh
```

### Problema 3: "Cambios no se aplican"

**Causa**: PHP está usando un php.ini diferente

**Solución**:

```bash
# Verificar cuál php.ini está usando PHP
php --ini

# Ver valores actuales en runtime
php -r "echo ini_get('upload_max_filesize');"

# Si no coinciden, edita el php.ini correcto
nano $(php --ini | grep "Loaded Configuration File" | awk '{print $4}')
```

### Problema 4: "Error 413 Request Entity Too Large"

**Causa**: Nginx tiene límite adicional

**Solución**:

```bash
# Editar configuración de Nginx
sudo nano /etc/nginx/sites-available/default

# Agregar dentro de server { }
client_max_body_size 50M;

# Reiniciar Nginx
sudo systemctl restart nginx
```

---

## 📚 Referencias

- [PHP File Upload Configuration](https://www.php.net/manual/en/ini.core.php#ini.upload-max-filesize)
- [PHP Memory Limit](https://www.php.net/manual/en/ini.core.php#ini.memory-limit)
- [Nginx client_max_body_size](http://nginx.org/en/docs/http/ngx_http_core_module.html#client_max_body_size)

---

## 🔄 Actualización del Script

Si necesitas cambiar los límites, edita las líneas de configuración:

```bash
# Editar script
nano increase-upload-limits.sh

# Buscar estas líneas y modificar valores:
update_php_config "upload_max_filesize" "100M" "$PHP_INI_PATH"  # Cambiar de 50M a 100M
update_php_config "post_max_size" "120M" "$PHP_INI_PATH"        # Cambiar de 60M a 120M

# Guardar y ejecutar
bash increase-upload-limits.sh
```

---

## 📞 Soporte

Si tienes problemas con el script:

1. Verifica que tienes permisos de escritura en `php.ini`
2. Revisa la salida del script para errores específicos
3. Consulta la sección de Troubleshooting
4. Verifica los logs de PHP: `tail -f /var/log/php-fpm/error.log`

---

**Última actualización**: Noviembre 2025  
**Versión del script**: 1.0  
**Mantenido por**: Roepard Labs Development Team
