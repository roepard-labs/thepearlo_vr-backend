#!/bin/bash

##############################################################################
# Script: Aumentar Límites de Subida de Archivos en PHP
# Descripción: Modifica php.ini para permitir uploads de hasta 50 MB
# Proyecto: HomeLab AR - Roepard Labs
# Uso: bash increase-upload-limits.sh
##############################################################################

set -e  # Salir si hay algún error

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║  📤 Aumentar Límites de Subida de Archivos en PHP        ║${NC}"
echo -e "${BLUE}║  HomeLab AR - Roepard Labs                                ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""

# Detectar ubicación de php.ini
echo -e "${YELLOW}🔍 Detectando ubicación de php.ini...${NC}"
PHP_INI_PATH=$(php --ini | grep "Loaded Configuration File" | awk '{print $4}')

if [ -z "$PHP_INI_PATH" ] || [ "$PHP_INI_PATH" == "(none)" ]; then
    echo -e "${RED}❌ No se encontró archivo php.ini activo${NC}"
    echo -e "${YELLOW}ℹ️  Ejecuta: php --ini${NC}"
    exit 1
fi

echo -e "${GREEN}✅ php.ini encontrado: ${PHP_INI_PATH}${NC}"
echo ""

# Verificar permisos de escritura
if [ ! -w "$PHP_INI_PATH" ]; then
    echo -e "${RED}❌ No tienes permisos de escritura en ${PHP_INI_PATH}${NC}"
    echo -e "${YELLOW}ℹ️  Intenta ejecutar con sudo:${NC}"
    echo -e "${YELLOW}    sudo bash $0${NC}"
    exit 1
fi

# Crear backup del php.ini original
BACKUP_PATH="${PHP_INI_PATH}.backup.$(date +%Y%m%d_%H%M%S)"
echo -e "${YELLOW}💾 Creando backup: ${BACKUP_PATH}${NC}"
cp "$PHP_INI_PATH" "$BACKUP_PATH"
echo -e "${GREEN}✅ Backup creado exitosamente${NC}"
echo ""

# Mostrar valores actuales
echo -e "${BLUE}📊 Valores actuales de php.ini:${NC}"
echo -e "${YELLOW}────────────────────────────────────────────────────${NC}"
php -i | grep -E "upload_max_filesize|post_max_size|max_execution_time|max_input_time|memory_limit" | head -5
echo -e "${YELLOW}────────────────────────────────────────────────────${NC}"
echo ""

# Función para actualizar o agregar configuración
update_php_config() {
    local key=$1
    local value=$2
    local file=$3
    
    # Verificar si la directiva existe (comentada o no)
    if grep -q "^[;]*${key}" "$file"; then
        # Existe, actualizarla (descomentar si está comentada)
        sed -i "s|^[;]*${key}.*|${key} = ${value}|" "$file"
        echo -e "${GREEN}✅ ${key} actualizado a ${value}${NC}"
    else
        # No existe, agregarla al final
        echo "${key} = ${value}" >> "$file"
        echo -e "${GREEN}✅ ${key} agregado con valor ${value}${NC}"
    fi
}

# Aplicar configuraciones
echo -e "${BLUE}⚙️  Aplicando nuevas configuraciones...${NC}"
echo ""

update_php_config "upload_max_filesize" "50M" "$PHP_INI_PATH"
update_php_config "post_max_size" "60M" "$PHP_INI_PATH"
update_php_config "memory_limit" "256M" "$PHP_INI_PATH"
update_php_config "max_execution_time" "300" "$PHP_INI_PATH"
update_php_config "max_input_time" "300" "$PHP_INI_PATH"

echo ""
echo -e "${GREEN}✅ Todas las configuraciones aplicadas correctamente${NC}"
echo ""

# Verificar nuevos valores
echo -e "${BLUE}📊 Nuevos valores de php.ini:${NC}"
echo -e "${YELLOW}────────────────────────────────────────────────────${NC}"
php -i | grep -E "upload_max_filesize|post_max_size|max_execution_time|max_input_time|memory_limit" | head -5
echo -e "${YELLOW}────────────────────────────────────────────────────${NC}"
echo ""

# Detectar servidor web y sugerir reinicio
echo -e "${BLUE}🔄 Detectando servidor web...${NC}"

if systemctl is-active --quiet php-fpm || systemctl is-active --quiet php8.4-fpm; then
    echo -e "${YELLOW}⚠️  PHP-FPM detectado. Reiniciando servicio...${NC}"
    
    if systemctl is-active --quiet php8.4-fpm; then
        sudo systemctl restart php8.4-fpm && echo -e "${GREEN}✅ PHP 8.4-FPM reiniciado${NC}" || echo -e "${RED}❌ Error al reiniciar PHP-FPM${NC}"
    elif systemctl is-active --quiet php-fpm; then
        sudo systemctl restart php-fpm && echo -e "${GREEN}✅ PHP-FPM reiniciado${NC}" || echo -e "${RED}❌ Error al reiniciar PHP-FPM${NC}"
    fi
elif systemctl is-active --quiet nginx; then
    echo -e "${YELLOW}⚠️  Nginx detectado${NC}"
    echo -e "${YELLOW}ℹ️  Reinicia Nginx si es necesario: sudo systemctl restart nginx${NC}"
elif systemctl is-active --quiet apache2; then
    echo -e "${YELLOW}⚠️  Apache detectado. Reiniciando servicio...${NC}"
    sudo systemctl restart apache2 && echo -e "${GREEN}✅ Apache reiniciado${NC}" || echo -e "${RED}❌ Error al reiniciar Apache${NC}"
else
    echo -e "${YELLOW}ℹ️  No se detectó servidor web activo (Herd Lite, servidor built-in, etc.)${NC}"
    echo -e "${YELLOW}ℹ️  Los cambios se aplicarán en la próxima ejecución de PHP${NC}"
fi

echo ""
echo -e "${GREEN}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║  ✅ Configuración completada exitosamente                 ║${NC}"
echo -e "${GREEN}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""

echo -e "${BLUE}📝 Resumen de cambios:${NC}"
echo -e "  ${GREEN}•${NC} upload_max_filesize: ${GREEN}50M${NC}"
echo -e "  ${GREEN}•${NC} post_max_size: ${GREEN}60M${NC}"
echo -e "  ${GREEN}•${NC} memory_limit: ${GREEN}256M${NC}"
echo -e "  ${GREEN}•${NC} max_execution_time: ${GREEN}300s${NC}"
echo -e "  ${GREEN}•${NC} max_input_time: ${GREEN}300s${NC}"
echo ""

echo -e "${BLUE}💾 Backup guardado en:${NC}"
echo -e "  ${YELLOW}${BACKUP_PATH}${NC}"
echo ""

echo -e "${BLUE}🧪 Para probar los cambios:${NC}"
echo -e "  ${YELLOW}php -i | grep upload_max_filesize${NC}"
echo ""

echo -e "${BLUE}🔙 Para restaurar el backup:${NC}"
echo -e "  ${YELLOW}cp ${BACKUP_PATH} ${PHP_INI_PATH}${NC}"
echo ""

echo -e "${GREEN}¡Listo! Ahora puedes subir archivos de hasta 50 MB${NC}"
