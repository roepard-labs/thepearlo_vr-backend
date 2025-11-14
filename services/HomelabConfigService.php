<?php
require_once __DIR__ . '/../models/UserHomelabConfig.php';

class HomelabConfigService
{
    private $model;

    public function __construct()
    {
        $this->model = new UserHomelabConfig();
    }

    /**
     * Obtener configuración con valores por defecto cuando no exista
     */
    public function getConfig(int $userId): array
    {
        $row = $this->model->getByUserId($userId);
        if (!$row) {
            return $this->defaultConfig($userId);
        }

        // Parsear preferences JSON si existe
        if (!empty($row['preferences'])) {
            $decoded = json_decode($row['preferences'], true);
            $row['preferences'] = $decoded === null ? [] : $decoded;
        } else {
            $row['preferences'] = [];
        }

        return $row;
    }

    /**
     * Validar y actualizar (insert/update) la configuración del usuario
     * @param int $userId
     * @param array $data
     * @return array Resultado con status/message y config actualizada
     */
    public function upsertConfig(int $userId, array $data): array
    {
        // Validaciones básicas y normalización
        $allowedThemes = ['dark', 'light'];
        $allowedColors = ['default', 'high_contrast'];

        $payload = [];
        // theme
        if (isset($data['theme']) && in_array($data['theme'], $allowedThemes, true)) {
            $payload['theme'] = $data['theme'];
        }
        // clock_format
        if (isset($data['clock_format'])) {
            $cf = (int) $data['clock_format'];
            $payload['clock_format'] = ($cf === 12) ? 12 : 24;
        }
        // color_accessibility
        if (isset($data['color_accessibility']) && in_array($data['color_accessibility'], $allowedColors, true)) {
            $payload['color_accessibility'] = $data['color_accessibility'];
        }
        // consent_privacy
        if (isset($data['consent_privacy'])) {
            $payload['consent_privacy'] = (int) ($data['consent_privacy'] ? 1 : 0);
        }
        // seen_homelab_modal
        if (isset($data['seen_homelab_modal'])) {
            $payload['seen_homelab_modal'] = (int) ($data['seen_homelab_modal'] ? 1 : 0);
        }
        // preferences (expect array or object)
        if (isset($data['preferences'])) {
            if (is_array($data['preferences']) || is_object($data['preferences'])) {
                $payload['preferences'] = $data['preferences'];
            } else {
                // Try decode if string
                $decoded = json_decode((string)$data['preferences'], true);
                $payload['preferences'] = $decoded === null ? [] : $decoded;
            }
        }

        // If payload empty -> nothing to update
        if (empty($payload)) {
            return ['status' => 'error', 'message' => 'No se proporcionaron campos válidos para actualizar.'];
        }

        // Persistir
        try {
            $this->model->upsertByUserId($userId, $payload);
            $newConfig = $this->getConfig($userId);
            return ['status' => 'success', 'message' => 'Configuración actualizada', 'data' => $newConfig];
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => 'Error al guardar configuración', 'details' => $e->getMessage()];
        }
    }

    private function defaultConfig(int $userId): array
    {
        return [
            'id' => null,
            'user_id' => $userId,
            'theme' => 'dark',
            'clock_format' => 24,
            'color_accessibility' => 'default',
            'consent_privacy' => 1,
            'seen_homelab_modal' => 0,
            'preferences' => [],
            'created_at' => null,
            'updated_at' => null
        ];
    }
}

?>
