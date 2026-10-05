<?php

namespace Tests\Feature\Platform;

use App\Models\User;
use App\Platform\Telemetry\Telemetry;
use App\Platform\Telemetry\TelemetryFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenTelemetry\SDK\Metrics\MetricExporter\InMemoryExporter as InMemoryMetrics;
use OpenTelemetry\SDK\Trace\ImmutableSpan;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter as InMemorySpans;
use Tests\TestCase;

class TraceRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_response_carries_a_request_id(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $response->headers->get('X-Request-Id'));
    }

    public function test_a_safe_incoming_request_id_is_kept(): void
    {
        $this->get('/login', ['X-Request-Id' => 'abc12345-from-proxy'])
            ->assertHeader('X-Request-Id', 'abc12345-from-proxy');
    }

    public function test_an_unsafe_incoming_request_id_is_replaced(): void
    {
        $response = $this->get('/login', ['X-Request-Id' => "x\nset-cookie: a=b"]);

        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $response->headers->get('X-Request-Id'));
    }

    public function test_a_request_becomes_a_span_named_by_the_route_template(): void
    {
        $spans = new InMemorySpans;
        $metrics = new InMemoryMetrics;
        $telemetry = TelemetryFactory::make($spans, $metrics);
        $this->app->instance(Telemetry::class, $telemetry);

        $this->actingAs(User::factory()->create())->get('/conta/perfil')->assertOk();
        $telemetry->flush();

        $names = array_map(fn (ImmutableSpan $span) => $span->getName(), $spans->getSpans());
        $this->assertContains('GET /conta/perfil', $names);

        $request = collect($spans->getSpans())->first(fn (ImmutableSpan $span) => $span->getName() === 'GET /conta/perfil');
        $this->assertSame(200, $request->getAttributes()->get('http.response.status_code'));

        $recorded = array_map(fn ($metric) => $metric->name, $metrics->collect());
        $this->assertContains('http.server.request.duration', $recorded);
    }

    public function test_telemetry_is_off_without_an_endpoint(): void
    {
        $this->assertFalse(TelemetryFactory::fromConfig([
            'endpoint' => null,
            'service' => 'owly',
            'environment' => 'testing',
        ])->enabled());
    }
}
