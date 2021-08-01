<?php

namespace Test;

use Kovagoz\Http\Middleware\FastRouter\FastRouteMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class FastRouteMiddlewareTest extends TestCase
{
    private Psr17Factory          $factory;
    private FastRouteMiddleware   $middleware;

    public function setUp(): void
    {
        $this->factory = new Psr17Factory();

        // Create our router instance
        $dispatcher = \FastRoute\simpleDispatcher(
            function (\FastRoute\RouteCollector $collector) {
                $collector->addRoute('GET', '/', 'frontpage');
                $collector->addRoute('GET', '/post/{id}', 'article');
            }
        );

        $this->middleware = new FastRouteMiddleware($dispatcher, $this->factory);
    }

    public function testNotAllowedRequestMethod(): void
    {
        $request = $this->createServerRequest('POST', '/');

        // Request handler should not be hit
        $handler = $this->getMockForAbstractClass(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        $response = $this->middleware->process($request, $handler);

        self::assertEquals(405, $response->getStatusCode());
        self::assertTrue($response->hasHeader('Allow'));
        self::assertEquals('GET', $response->getHeaderLine('Allow'));
    }

    public function testFoundMatchingRoute(): void
    {
        $request = $this->createServerRequest('GET', '/');

        // Create a request handler which can be inspected
        $handler = $this->getMockForAbstractClass(RequestHandlerInterface::class);
        $handler
            ->expects(self::once())
            ->method('handle')
            ->with(self::callback(
                function (ServerRequestInterface $request) {
                    return $request->getAttribute(FastRouteMiddleware::HANDLER_ATTRIBUTE) === 'frontpage';
                }
            ));

        $this->middleware->process($request, $handler);
    }

    public function testNotFoundMatchingRoute(): void
    {
        $request = $this->createServerRequest('GET', '/about');

        $handler = $this->getMockForAbstractClass(RequestHandlerInterface::class);
        $handler
            ->expects(self::once())
            ->method('handle')
            ->with(self::callback(
                function (ServerRequestInterface $request) {
                    return $request->getAttribute(FastRouteMiddleware::HANDLER_ATTRIBUTE) === null;
                }
            ));

        $this->middleware->process($request, $handler);
    }

    public function testUrlParametersAttachedToRequest(): void
    {
        $request = $this->createServerRequest('GET', '/post/12');

        $handler = $this->getMockForAbstractClass(RequestHandlerInterface::class);
        $handler
            ->expects(self::once())
            ->method('handle')
            ->with(self::callback(
                function (ServerRequestInterface $request) {
                    return $request->getAttribute('id') === '12';
                }
            ));

        $this->middleware->process($request, $handler);
    }

    private function createServerRequest(string $method, string $path): ServerRequestInterface
    {
        return $this->factory
            ->createServerRequest($method, $this->factory->createUri($path));
    }
}
