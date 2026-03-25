<?php
declare(strict_types=1);

namespace App\Services;

class WhatsAppService
{
    private string $mode;
    private string $logFile;

    public function __construct()
    {
        $this->mode    = getenv('WHATSAPP_MODE') ?: 'mock';
        $this->logFile = BASE_PATH . '/storage/logs/whatsapp.log';
    }

    /**
     * Envía un mensaje genérico. Usado por NotificacionService para todos los eventos.
     */
    public function sendGenericMessage(string $telefono, string $mensaje, string $tipo = 'NOTIFICACION'): bool
    {
        return $this->send($telefono, $mensaje, $tipo);
    }

    public function sendTaskAssigned(string $telefono, string $nombreUsuario, string $tituloTarea, string $proyecto): bool
    {
        $mensaje = "Hola $nombreUsuario, se te asignó la tarea \"$tituloTarea\" en el proyecto \"$proyecto\".";
        return $this->send($telefono, $mensaje, 'TAREA_ASIGNADA');
    }

    public function sendTaskStatusChanged(string $telefono, string $nombreUsuario, string $tituloTarea, string $nuevoEstado): bool
    {
        $labels = [
            'por_hacer' => 'Por Hacer',
            'haciendo'  => 'Haciendo',
            'terminada' => 'Terminada',
            'enterado'  => 'Enterado',
            'ocupado'   => 'Ocupado',
            'aceptada'  => 'Aceptada',
        ];
        $estadoLabel = $labels[$nuevoEstado] ?? $nuevoEstado;
        $mensaje     = "Hola $nombreUsuario, la tarea \"$tituloTarea\" cambió a estado: $estadoLabel.";
        return $this->send($telefono, $mensaje, 'CAMBIO_ESTADO_TAREA');
    }

    public function sendProjectAssigned(string $telefono, string $nombreUsuario, string $nombreProyecto): bool
    {
        $mensaje = "Hola $nombreUsuario, se te asignó el proyecto \"$nombreProyecto\".";
        return $this->send($telefono, $mensaje, 'PROYECTO_ASIGNADO');
    }

    private function send(string $telefono, string $mensaje, string $tipo): bool
    {
        if ($this->mode === 'mock') {
            return $this->sendMock($telefono, $mensaje, $tipo);
        }

        return $this->sendReal($telefono, $mensaje, $tipo);
    }

    private function sendMock(string $telefono, string $mensaje, string $tipo): bool
    {
        $entry = sprintf(
            "[%s] [MOCK] [%s] Para: %s | Mensaje: %s\n",
            date('Y-m-d H:i:s'),
            $tipo,
            $telefono,
            $mensaje
        );
        $this->log($entry);
        return true;
    }

    private function sendReal(string $telefono, string $mensaje, string $tipo): bool
    {
        $sid   = getenv('WHATSAPP_ACCOUNT_SID') ?: '';
        $token = getenv('WHATSAPP_AUTH_TOKEN')  ?: '';
        $from  = getenv('WHATSAPP_FROM')         ?: '';

        // Validate credentials before attempting a real send
        if (empty($sid) || empty($token) || empty($from)) {
            error_log('[WhatsAppService::sendReal] WARN: Credenciales incompletas (SID/TOKEN/FROM). Usando mock como fallback.');
            return $this->sendMock($telefono, $mensaje, $tipo);
        }

        // Sanitise phone number
        $telefonoLimpio = preg_replace('/[^+\d]/', '', $telefono);
        if (empty($telefonoLimpio)) {
            error_log('[WhatsAppService::sendReal] ERROR: Telefono invalido: ' . $telefono);
            return false;
        }

        $url = "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json";

        $ch = curl_init($url);
        if ($ch === false) {
            error_log('[WhatsAppService::sendReal] ERROR: curl_init fallo.');
            return false;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD        => "{$sid}:{$token}",
            CURLOPT_POSTFIELDS     => http_build_query([
                'From' => $from,
                'To'   => 'whatsapp:' . $telefonoLimpio,
                'Body' => $mensaje,
            ]),
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log("[WhatsAppService::sendReal] CURL ERROR: {$curlError}");
            return false;
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            error_log("[WhatsAppService::sendReal] OK: {$telefonoLimpio} ({$tipo}) - HTTP {$httpCode}");
            return true;
        }

        error_log("[WhatsAppService::sendReal] FAIL: HTTP {$httpCode} - Response: {$response}");
        return false;
    }

    private function log(string $message): void
    {
        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($this->logFile, $message, FILE_APPEND | LOCK_EX);
    }
}
