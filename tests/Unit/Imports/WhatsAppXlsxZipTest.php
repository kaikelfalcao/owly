<?php

namespace Tests\Unit\Imports;

use App\Domains\Conversations\Data\IncomingConversation;
use App\Domains\Imports\Adapters\WhatsAppXlsxZip\WhatsAppXlsxZip;
use App\Domains\Imports\Data\ImportFailed;
use PHPUnit\Framework\TestCase;
use Tests\Support\WhatsAppZip;

class WhatsAppXlsxZipTest extends TestCase
{
    private const TZ = 'America/Sao_Paulo';

    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        array_map(fn ($file) => @unlink($file), $this->files);
        parent::tearDown();
    }

    public function test_le_uma_conversa_por_planilha(): void
    {
        $reading = $this->read(WhatsAppZip::sample());

        $this->assertSame(2, $reading->files);
        $this->assertSame([], $reading->problems);
        $this->assertCount(2, $reading->conversations);
    }

    public function test_o_cliente_vem_do_telefone_da_planilha(): void
    {
        $first = $this->conversation(WhatsAppZip::sample(), 'wa:5511988887777');

        $this->assertSame('5511988887777', $first->contactPhone);
        $this->assertSame('Cliente Teste', $first->contactName);
    }

    public function test_o_nome_do_arquivo_vira_o_nome_do_cliente_com_emoji(): void
    {
        $maria = $this->conversation(WhatsAppZip::sample(), 'wa:5511977776666');

        $this->assertSame('⭐ Maria Inventada', $maria->contactName);
    }

    public function test_a_assinatura_diz_quem_e_a_vendedora_e_sai_do_texto(): void
    {
        $messages = $this->conversation(WhatsAppZip::sample(), 'wa:5511988887777')->messages;

        $this->assertSame('contact', $messages[0]->author);
        $this->assertSame('in', $messages[0]->direction);
        $this->assertSame('seller', $messages[1]->author);
        $this->assertSame('out', $messages[1]->direction);
        $this->assertSame('Ana', $messages[1]->sellerName);
        $this->assertSame('Bom dia! Custa R$ 90.', $messages[1]->body);
    }

    public function test_ligacao_perdida_e_resposta_automatica_sao_avisos(): void
    {
        $messages = $this->conversation(WhatsAppZip::sample(), 'wa:5511988887777')->messages;

        $this->assertSame(['system', 'missed_call', null], [$messages[2]->author, $messages[2]->event, $messages[2]->body]);
        $this->assertSame(['bot', 'auto_reply'], [$messages[4]->author, $messages[4]->event]);
    }

    public function test_horario_da_planilha_e_do_fuso_da_empresa_e_fica_em_utc(): void
    {
        $messages = $this->conversation(WhatsAppZip::sample(), 'wa:5511988887777')->messages;

        $this->assertSame('2026-09-01 12:00:00', $messages[0]->sentAt->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $messages[0]->sentAt->getTimezone()->getName());
    }

    public function test_midia_fica_so_com_o_nome_do_arquivo(): void
    {
        $photo = $this->conversation(WhatsAppZip::sample(), 'wa:5511977776666')->messages[2];

        $this->assertSame('image', $photo->mediaType);
        $this->assertSame('foto.jpg', $photo->mediaName);
        $this->assertNull($photo->body);
        $this->assertNull($photo->sellerName);
    }

    public function test_documento_com_o_nome_do_arquivo_como_texto_fica_sem_texto(): void
    {
        $document = $this->conversation(WhatsAppZip::sample(), 'wa:5511977776666')->messages[3];

        $this->assertSame(['document', 'tabela.pdf', null], [$document->mediaType, $document->mediaName, $document->body]);
    }

    public function test_o_mesmo_zip_gera_os_mesmos_ids(): void
    {
        $ids = fn () => array_map(fn ($m) => $m->externalId, $this->conversation(WhatsAppZip::sample(), 'wa:5511988887777')->messages);

        $this->assertSame($ids(), $ids());
        $this->assertCount(5, array_unique($ids()));
    }

    public function test_mensagens_iguais_no_mesmo_segundo_nao_se_confundem(): void
    {
        $zip = WhatsAppZip::make()->sheet('5511955554444.xlsx', [
            ['2026-09-01', '09:00:00', '5511955554444', '', 'oi'],
            ['2026-09-01', '09:00:00', '5511955554444', '', 'oi'],
        ]);

        $messages = $this->read($zip)->conversations[0]->messages;

        $this->assertNotSame($messages[0]->externalId, $messages[1]->externalId);
    }

    public function test_planilha_quebrada_vira_problema_e_o_resto_entra(): void
    {
        $reading = $this->read(WhatsAppZip::sample()->file('quebrada.xlsx', 'isto não é uma planilha'));

        $this->assertCount(2, $reading->conversations);
        $this->assertSame([['file' => 'quebrada.xlsx', 'code' => 'unreadable_file']], $reading->problems);
        $this->assertSame(3, $reading->files);
    }

    public function test_zip_sem_planilha_nenhuma_falha(): void
    {
        $this->expectExceptionObject(new ImportFailed('no_conversations'));

        $this->read(WhatsAppZip::make()->file('leia-me.txt', 'nada'));
    }

    public function test_arquivo_que_nao_e_zip_falha(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'owly-test-');
        file_put_contents($file, 'texto qualquer');
        $this->files[] = $file;

        try {
            (new WhatsAppXlsxZip)->read($file, self::TZ);
            $this->fail('Deveria ter falhado.');
        } catch (ImportFailed $e) {
            $this->assertSame('invalid_zip', $e->reason);
        }
    }

    private function read(WhatsAppZip $zip)
    {
        $path = $zip->path();
        $this->files[] = $path;

        return (new WhatsAppXlsxZip)->read($path, self::TZ);
    }

    private function conversation(WhatsAppZip $zip, string $key): IncomingConversation
    {
        foreach ($this->read($zip)->conversations as $conversation) {
            if ($conversation->contactKey === $key) {
                return $conversation;
            }
        }

        $this->fail("Conversa {$key} não encontrada.");
    }
}
