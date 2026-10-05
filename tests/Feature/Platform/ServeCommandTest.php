<?php

namespace Tests\Feature\Platform;

use App\Platform\Console\ServeCommand;
use Illuminate\Foundation\Console\ServeCommand as LaravelServeCommand;
use Tests\TestCase;

class ServeCommandTest extends TestCase
{
    public function test_o_serve_aceita_o_tamanho_do_zip(): void
    {
        $this->assertInstanceOf(ServeCommand::class, $this->app->make(LaravelServeCommand::class));
    }

    public function test_limites_do_php_acompanham_o_limite_do_zip(): void
    {
        $this->assertSame(
            ['-d', 'upload_max_filesize=50M', '-d', 'post_max_size=51M'],
            ServeCommand::uploadLimits(51200),
        );
        $this->assertSame(
            ['-d', 'upload_max_filesize=2M', '-d', 'post_max_size=3M'],
            ServeCommand::uploadLimits(1500),
        );
    }
}
