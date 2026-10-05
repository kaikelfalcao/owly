<?php

namespace Tests\Feature\Platform;

use App\Models\User;
use App\Platform\Notifications\Notice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_bell_shows_unread_notices(): void
    {
        $user = User::factory()->create();
        $user->notify(new Notice('import', 'Importação concluída', '12 conversas novas', '/dashboard'));

        $this->actingAs($user)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('notifications.unread', 1)
            ->where('notifications.items.0.title', 'Importação concluída')
            ->where('notifications.items.0.kind', 'import')
            ->where('notifications.items.0.read', false)
        );
    }

    public function test_opening_a_notice_marks_it_read_and_follows_its_link(): void
    {
        $user = User::factory()->create();
        $user->notify(new Notice('import', 'Importação concluída', url: '/conta/atividade'));
        $id = $user->notifications()->sole()->id;

        $this->actingAs($user)->get("/notificacoes/{$id}")->assertRedirect('/conta/atividade');

        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_a_notice_never_redirects_outside_owly(): void
    {
        $user = User::factory()->create();
        $user->notify(new Notice('import', 'Teste', url: '//exemplo.test/fora'));
        $id = $user->notifications()->sole()->id;

        $this->actingAs($user)->from('/dashboard')->get("/notificacoes/{$id}")->assertRedirect('/dashboard');
    }

    public function test_someone_else_s_notice_is_not_found(): void
    {
        $owner = User::factory()->create();
        $owner->notify(new Notice('import', 'Teste'));
        $id = $owner->notifications()->sole()->id;

        $this->actingAs(User::factory()->create())->get("/notificacoes/{$id}")->assertNotFound();
    }

    public function test_all_notices_can_be_marked_read(): void
    {
        $user = User::factory()->create();
        $user->notify(new Notice('import', 'Um'));
        $user->notify(new Notice('import', 'Dois'));

        $this->actingAs($user)->post('/notificacoes/lidas')->assertRedirect();

        $this->assertSame(0, $user->unreadNotifications()->count());
    }
}
