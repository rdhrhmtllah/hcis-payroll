<?php

namespace App\Services\SYSTEM\TaskProcessing;

use App\Helpers\WhatsappLogHelper;
use App\Models\SYSTEM\Application\Outbox\Models\SystemTaskOutbox;

class WhatsappOutboxHandler implements TaskHandler
{
    public function __construct(private readonly WhatsappProviderClient $client) {}

    public function handle(SystemTaskOutbox $outbox, array $payload): ?array
    {
        $to = WhatsappLogHelper::normalizeNumber((string) ($payload['to'] ?? ''));
        if (! preg_match('/^62[1-9][0-9]{7,13}$/', $to)) {
            throw new PermanentTaskException('Nomor WhatsApp tujuan tidak valid.');
        }

        $messagePayload = $payload;
        $messagePayload['to'] = $to;
        $result = $this->client->send($messagePayload);
        $result['metadata'] = [
            'sender_employee_code' => $outbox->Sender_Employee_Code,
            'source_module' => $outbox->Source_Module,
            'source_feature' => $outbox->Source_Feature,
            'message_template' => $outbox->Message_Template,
        ];

        return $result;
    }
}
