<?php

namespace Kovagoz\Http;

use FastRoute\Dispatcher;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;

class FastRouteMiddleware implements MiddlewareInterface
{
    /**
     * Route handler is stored in this request attribute.
     */
    public const HANDLER_ATTRIBUTE = '__handler';

    private Dispatcher               $dispatcher;
    private ResponseFactoryInterface $responseFactory;

    public function __construct(Dispatcher $dispatcher, ResponseFactoryInterface $responseFactory)
    {
        $this->dispatcher      = $dispatcher;
        $this->responseFactory = $responseFactory;
    }

    public function process(Request $request, Handler $handler): Response
    {
        $routeInfo = $this->dispatcher->dispatch(
            $request->getMethod(),
            $request->getUri()->getPath()
        );

        if ($routeInfo[0] === Dispatcher::METHOD_NOT_ALLOWED) {
            return $this->responseFactory
                ->createResponse(405)
                ->withHeader('Allow', implode(',', $routeInfo[1]));
        }

        if ($routeInfo[0] === Dispatcher::FOUND) {
            $request = $request->withAttribute(self::HANDLER_ATTRIBUTE, $routeInfo[1]);
            // Attach URL parameters to request as attributes.
            foreach ($routeInfo[2] as $key => $value) {
                $request = $request->withAttribute($key, $value);
            }
        }

        return $handler->handle($request);
    }
}
