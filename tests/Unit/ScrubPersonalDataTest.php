<?php

namespace Tests\Unit;

use App\Platform\Logging\ScrubPersonalData;
use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

class ScrubPersonalDataTest extends TestCase
{
    public function test_personal_fields_are_removed_and_ids_are_kept(): void
    {
        $record = new LogRecord(
            datetime: new DateTimeImmutable,
            channel: 'test',
            level: Level::Info,
            message: 'importação concluída',
            context: [
                'import_id' => 7,
                'customer_email' => 'maria@exemplo.test',
                'contact' => ['phone' => '+55 71 90000-0000', 'id' => 3],
                'message_body' => 'oi, quanto custa?',
            ],
            extra: ['request_id' => 'abc', 'api_token' => 'segredo'],
        );

        $scrubbed = (new ScrubPersonalData)($record);

        $this->assertSame(7, $scrubbed->context['import_id']);
        $this->assertSame(ScrubPersonalData::REMOVED, $scrubbed->context['customer_email']);
        $this->assertSame(['phone' => ScrubPersonalData::REMOVED, 'id' => 3], $scrubbed->context['contact']);
        $this->assertSame(ScrubPersonalData::REMOVED, $scrubbed->context['message_body']);
        $this->assertSame('abc', $scrubbed->extra['request_id']);
        $this->assertSame(ScrubPersonalData::REMOVED, $scrubbed->extra['api_token']);
    }
}
