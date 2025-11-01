#!/bin/bash
# Script de seguridad para proteger archivos sensibles en producción

echo "🔒 Aplicando medidas de seguridad..."

# Proteger .env con permisos restrictivos
if [ -f /var/www/html/.env ]; then
    chmod 600 /var/www/html/.env
    chown www-data:www-data /var/www/html/.env
    echo "✓ Permisos .env: 600 (solo lectura para www-data)"
fi

# Proteger directorios de configuración
for dir in config core middleware models services controllers storage/app/private; do
    if [ -d "/var/www/html/$dir" ]; then
        chmod 750 "/var/www/html/$dir"
        echo "✓ Protegido: $dir"
    fi
done

# Eliminar archivos de desarrollo que no deberían estar en producción
rm -f /var/www/html/.git* 2>/dev/null
rm -rf /var/www/html/.git 2>/dev/null
rm -f /var/www/html/test-*.sh 2>/dev/null
rm -f /var/www/html/test-*.html 2>/dev/null

echo "✓ Seguridad aplicada correctamente"
