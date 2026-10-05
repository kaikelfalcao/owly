<?php

namespace App\Platform\Http;

use App\Platform\Telemetry\Telemetry;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Dá um id a cada requisição, abre o rastro dela e mede a duração.
 *
 * O id vai para o contexto do Laravel, então aparece em toda linha de log e
 * segue para as tarefas da fila disparadas pela requisição.
 */
class TraceRequest
{
    public const HEADER = 'X-Request-Id';

    public function __construct(private readonly Telemetry $telemetry) {}

    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->requestId($request);
        $started = hrtime(true);

        $span = $this->telemetry->tracer()
            ->spanBuilder($request->method())
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->setAttribute('http.request.method', $request->method())
            ->setAttribute('url.path', '/'.ltrim($request->path(), '/'))
            ->setAttribute('owly.request_id', $requestId)
            ->startSpan();
        $scope = $span->activate();

        Context::add('request_id', $requestId);

        if ($span->getContext()->isValid()) {
            Context::add('trace_id', $span->getContext()->getTraceId());
        }

        try {
            $response = $next($request);
        } catch (Throwable $e) {
            $span->recordException($e);
            $span->setStatus(StatusCode::STATUS_ERROR);
            $scope->detach();
            $span->end();

            throw $e;
        }

        $route = $this->route($request);
        $status = $response->getStatusCode();

        $span->updateName("{$request->method()} {$route}");
        $span->setAttribute('http.route', $route);
        $span->setAttribute('http.response.status_code', $status);

        if ($status >= 500) {
            $span->setStatus(StatusCode::STATUS_ERROR);
        }

        $scope->detach();
        $span->end();

        $this->telemetry
            ->histogram('http.server.request.duration', 's', 'Tempo de resposta das requisições')
            ->record((hrtime(true) - $started) / 1e9, [
                'http.request.method' => $request->method(),
                'http.route' => $route,
                'http.response.status_code' => $status,
            ]);

        $response->headers->set(self::HEADER, $requestId);

        return $response;
    }

    /**
     * Mandar o fluxo inteiro para o coletor só depois que a resposta saiu.
     */
    public function terminate(): void
    {
        $this->telemetry->flush();
    }

    /**
     * Aceita o id que vier do proxy, se tiver formato seguro; senão cria um.
     */
    private function requestId(Request $request): string
    {
        $incoming = (string) $request->headers->get(self::HEADER);

        return preg_match('/^[A-Za-z0-9-]{8,64}$/', $incoming) === 1
            ? $incoming
            : (string) Str::uuid();
    }

    /**
     * O molde da rota (`/conversas/{conversation}`), nunca o endereço com
     * ids, para as métricas não explodirem em séries.
     */
    private function route(Request $request): string
    {
        $uri = $request->route()?->uri();

        return $uri === null ? 'desconhecida' : '/'.ltrim($uri, '/');
    }
}
