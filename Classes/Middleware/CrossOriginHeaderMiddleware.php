<?php

namespace Hn\Video\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * To enable multi threaded wasm executing, access to SharedArrayBuffer is required.
 * Because of Spectre and Meltdown, SharedArrayBuffer were disabled in 2018.
 * They are now available again but with additional security requirements.
 * This middleware adds the required headers to enable SharedArrayBuffer.
 */
class CrossOriginHeaderMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        $response = $response->withHeader('Cross-Origin-Opener-Policy', 'same-origin');
        $response = $response->withHeader('Cross-Origin-Embedder-Policy', 'require-corp');
        return $response;
    }
}