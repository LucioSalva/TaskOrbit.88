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
        // ============================================================
        // PUNTO DE INTEGRACIÓN: Twilio WhatsApp API
        // ============================================================
        // Para activar, configura en .env:
        //   WHATSAPP_MODE=real
        //   WHATSAPP_ACCOUNT_SID=ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
        //   WHATSAPP_AUTH_TOKEN=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
        //   WHATSAPP_FROM=whatsapp:+14155238886
        //
        // Instala el SDK: composer require twilio/sdk
        //
        // Descomenta el siguiente bloque:
        // ============================================================
        /*
        try {
            $accountSid = getenv('WHATSAPP_ACCOUNT_SID');
            $authToken  = getenv('WHATSAPP_AUTH_TOKEN');
            $from       = getenv('WHATSAPP_FROM');

            $client = new \Twilio\Rest\Client($accountSid, $authToken);
            $client->messages->create(
                'whatsapp:+52' . preg_replace('/\D/', '', $telefono),
                [
                    'from' => $from,
                    'body' => $mensaje,
                ]
            );

            $this->log(sprintf(
                "[%s] [REAL] [%s] Para: %s | OK\n",
                date('Y-m-d H:i:s'), $tipo, $telefono
            ));
            return true;
        } catch (\Exception $e) {
            $this->log(sprintf(
                "[%s] [REAL] [%s] Para: %s | ERROR: %s\n",
                date('Y-m-d H:i:s'), $tipo, $telefono, $e->getMessage()
            ));
            return false;
        }
        */

        // ============================================================
        // PUNTO DE INTEGRACIÓN: Meta WhatsApp Cloud API
        // ============================================================
        // Para activar con Meta API, descomenta este bloque en su lugar:
        // ============================================================
        /*
        $accessToken  = getenv('WHATSAPP_ACCESS_TOKEN');
        $phoneNumberId = getenv('WHATSAPP_PHONE_NUMBER_ID');
        $url = "https://graph.facebook.com/v18.0/{$phoneNumberId}/messages";

        $payload = json_encode([
            'messaging_product' => 'whatsapp',
            'to'                => '52' . preg_replace('/\D/', '', $telefono),
            'type'              => 'text',
            'text'              => ['body' => $mensaje],
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ],
        ]);
        $response   = curl_exec($ch);
        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $ok = ($httpStatus >= 200 && $httpStatus < 300);
        $this->log(sprintf(
            "[%s] [META] [%s] Para: %s | HTTP: %d\n",
            date('Y-m-d H:i:s'), $tipo, $telefono, $httpStatus
        ));
        return $ok;
        */

        // Fallback al mock si no se configuró correctamente
        return $this->sendMock($telefono, $mensaje, $tipo);
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
