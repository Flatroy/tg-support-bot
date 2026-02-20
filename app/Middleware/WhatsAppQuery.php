<?php

namespace App\Middleware;

use App\Logging\LokiLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WhatsAppQuery
{
    /**
     * Handle an incoming request.
     *
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $signature = $request->header('X-Hub-Signature-256');
            if (empty($signature)) {
                throw new Exception('Signature is missing!');
            }

            $appSecret = config('traffic_source.settings.whatsapp.app_secret');
            $expectedSignature = 'sha256=' . hash_hmac('sha256', $request->getContent(), $appSecret);

            if (!hash_equals($expectedSignature, $signature)) {
                throw new Exception('Signature is invalid!');
            }

            $this->sendRequestInLoki($request);

            return $next($request);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Access is forbidden',
                'error' => $e->getMessage(),
            ], Response::HTTP_FORBIDDEN);
        }
    }

    /**
     * @param Request $request
     *
     * @return void
     */
    private function sendRequestInLoki(Request $request): void
    {
        $dataRequest = json_encode($request->all());

        $logger = new LokiLogger();
        $logger->log('whatsapp_request', $dataRequest);
    }
}
