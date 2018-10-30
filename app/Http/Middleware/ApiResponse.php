<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiResponse
{
    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        /** @var JsonResponse $response */
        $response = $next($request);

        if (!$response instanceof JsonResponse) {
            return $response;
        }

        $responseData = [
            'request'  => $this->getRequestData($request),
            'response' => $this->getResponseData($response),
            'version'  => config('api.version'),
            'hash'     => str_random(32)
        ];

        return new JsonResponse($responseData);
    }

    private function getRequestData(Request $request)
    {
        $requestMethod = strtolower($request->getMethod());
        $route = $request->route();

        return [
            'method'     => $requestMethod,
            'command'    => $route->getActionMethod(),
            'parameters' => $request->all()
        ];
    }

    private function getResponseData(JsonResponse $response)
    {
        $responseData = $response->getData(true);

        if (!isset($responseData['status'])) {
            $responseData = array_prepend($responseData, true, 'status');
        }

        return $responseData;
    }
}