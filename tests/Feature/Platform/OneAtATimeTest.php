<?php

namespace Tests\Feature\Platform;

use App\Platform\Queue\OneAtATime;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class OneAtATimeTest extends TestCase
{
    public function test_com_outra_tarefa_rodando_volta_para_a_fila(): void
    {
        $lock = Cache::lock('one-at-a-time:organization:7:data', 60);
        $lock->get();
        $job = new class
        {
            public ?int $released = null;

            public function release(int $delay): void
            {
                $this->released = $delay;
            }
        };

        $ran = false;
        OneAtATime::organization(7, 600)->handle($job, function () use (&$ran) {
            $ran = true;
        });

        $this->assertFalse($ran);
        $this->assertSame(30, $job->released);
        $lock->release();
    }

    public function test_tarefa_disparada_de_dentro_de_outra_entra_direto_e_a_trava_solta_no_fim(): void
    {
        $middleware = OneAtATime::organization(7, 600);
        $inner = false;

        $middleware->handle(new \stdClass, function () use ($middleware, &$inner) {
            $middleware->handle(new \stdClass, function () use (&$inner) {
                $inner = true;
            });
        });

        $this->assertTrue($inner);
        $this->assertTrue(Cache::lock('one-at-a-time:organization:7:data', 60)->get(), 'a trava foi solta');
    }
}
