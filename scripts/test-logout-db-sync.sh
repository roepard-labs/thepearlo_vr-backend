#!/bin/bash

# Test de Sincronización de Logout con Base de Datos
# HomeLab AR - Roepard Labs

echo "🧪 Test: Sincronización Logout PHP ↔ Base de Datos"
echo "=================================================="
echo ""

# Configuración
API_URL="http://localhost:3000"
TEST_USER="user@example.com"
TEST_PASS="password123"
COOKIE_FILE="/tmp/test_logout_session.txt"

# Colores
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Función para imprimir resultados
print_result() {
    if [ $1 -eq 0 ]; then
        echo -e "${GREEN}✅ PASS${NC}: $2"
    else
        echo -e "${RED}❌ FAIL${NC}: $2"
    fi
}

# Test 1: Login
echo "📝 Test 1: Hacer login y registrar sesión"
echo "----------------------------------------"
LOGIN_RESPONSE=$(curl -s -X POST "${API_URL}/routes/user/auth_user.php" \
    -d "username=${TEST_USER}" \
    -d "password=${TEST_PASS}" \
    -c "${COOKIE_FILE}")

LOGIN_STATUS=$(echo $LOGIN_RESPONSE | jq -r '.status' 2>/dev/null)
if [ "$LOGIN_STATUS" = "success" ]; then
    print_result 0 "Login exitoso"
    USER_ID=$(echo $LOGIN_RESPONSE | jq -r '.data.user_id' 2>/dev/null)
    echo "   User ID: ${USER_ID}"
else
    print_result 1 "Login falló"
    echo "   Respuesta: ${LOGIN_RESPONSE}"
    exit 1
fi
echo ""

# Test 2: Verificar sesión activa en BD
echo "📝 Test 2: Verificar sesión activa en BD"
echo "---------------------------------------"
SESSIONS_RESPONSE=$(curl -s -X GET "${API_URL}/routes/user/list_sessions.php" \
    -b "${COOKIE_FILE}")

SESSIONS_COUNT=$(echo $SESSIONS_RESPONSE | jq -r '.data.stats.total_active' 2>/dev/null)
if [ "$SESSIONS_COUNT" -ge 1 ]; then
    print_result 0 "Sesión registrada en BD (${SESSIONS_COUNT} activa(s))"
    CURRENT_SESSION_ID=$(echo $SESSIONS_RESPONSE | jq -r '.data.stats.current_session_id' 2>/dev/null)
    echo "   Session ID: ${CURRENT_SESSION_ID}"
else
    print_result 1 "No se encontró sesión activa en BD"
    echo "   Respuesta: ${SESSIONS_RESPONSE}"
    exit 1
fi
echo ""

# Test 3: Hacer logout
echo "📝 Test 3: Cerrar sesión desde frontend"
echo "---------------------------------------"
LOGOUT_RESPONSE=$(curl -s -X POST "${API_URL}/routes/user/logout_user.php" \
    -b "${COOKIE_FILE}")

LOGOUT_STATUS=$(echo $LOGOUT_RESPONSE | jq -r '.status' 2>/dev/null)
if [ "$LOGOUT_STATUS" = "success" ]; then
    print_result 0 "Logout ejecutado correctamente"
else
    print_result 1 "Logout falló"
    echo "   Respuesta: ${LOGOUT_RESPONSE}"
    exit 1
fi
echo ""

# Test 4: Verificar que la sesión se cerró en BD
echo "📝 Test 4: CRÍTICO - Verificar sesión cerrada en BD"
echo "--------------------------------------------------"
sleep 2  # Esperar a que la BD se actualice

# Hacer login de nuevo para obtener una nueva sesión (necesaria para consultar)
LOGIN2_RESPONSE=$(curl -s -X POST "${API_URL}/routes/user/auth_user.php" \
    -d "username=${TEST_USER}" \
    -d "password=${TEST_PASS}" \
    -c "${COOKIE_FILE}")

# Consultar historial de sesiones
HISTORY_RESPONSE=$(curl -s -X GET "${API_URL}/routes/user/session_history.php?limit=5" \
    -b "${COOKIE_FILE}")

# Buscar la sesión cerrada en el historial
CLOSED_SESSION=$(echo $HISTORY_RESPONSE | jq -r ".data.history[] | select(.session_id == \"${CURRENT_SESSION_ID}\")" 2>/dev/null)

if [ -n "$CLOSED_SESSION" ]; then
    CLOSE_REASON=$(echo $CLOSED_SESSION | jq -r '.close_reason' 2>/dev/null)
    CLOSED_AT=$(echo $CLOSED_SESSION | jq -r '.closed_at' 2>/dev/null)
    
    if [ "$CLOSE_REASON" = "logout" ] && [ "$CLOSED_AT" != "null" ]; then
        print_result 0 "Sesión cerrada correctamente en BD"
        echo "   Close Reason: ${CLOSE_REASON}"
        echo "   Closed At: ${CLOSED_AT}"
        echo ""
        echo -e "${GREEN}🎉 TODOS LOS TESTS PASARON${NC}"
        echo "   La sincronización entre PHP y BD funciona correctamente"
    else
        print_result 1 "Sesión cerrada pero sin datos correctos"
        echo "   Close Reason: ${CLOSE_REASON} (esperado: logout)"
        echo "   Closed At: ${CLOSED_AT}"
    fi
else
    print_result 1 "No se encontró la sesión cerrada en el historial"
    echo "   ⚠️  Esto indica que el logout NO actualizó la BD"
    echo "   Historial: ${HISTORY_RESPONSE}"
fi
echo ""

# Limpieza
rm -f "${COOKIE_FILE}"

echo "=================================================="
echo "Test completado"
echo "=================================================="
