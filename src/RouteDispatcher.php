<?php
/**
 * @author    jan huang <bboyjanhuang@gmail.com>
 * @copyright 2016
 *
 * @link      https://www.github.com/janhuang
 * @link      http://www.fast-d.cn/
 */

namespace FastD\Routing;


use FastD\Http\Response;
use FastD\Http\ServerRequest;
use FastD\Middleware\Delegate;
use FastD\Middleware\Dispatcher;
use FastD\Middleware\ServerMiddleware;
use FastD\Middleware\ServerMiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class RouteDispatcher
 * @package FastD\Routing
 */
class RouteDispatcher extends Dispatcher
{
    /**
     * @var RouteCollection
     */
    protected $routeCollection;

    /**
     * RouteDispatcher constructor.
     *
     * @param RouteCollection $routeCollection
     * @param $stack
     */
    public function __construct(RouteCollection $routeCollection, $stack = [])
    {
        $this->routeCollection = $routeCollection;

        parent::__construct($stack);
    }

    /**
     * @param Route $route
     * @return ServerMiddlewareInterface
     */
    protected function createRouteMiddleware(Route $route, array $params = [])
    {
        return new ServerMiddleware(function (ServerRequest $request, Delegate $next) use ($route, $params) {
            $params = array_merge($params, $route->getParameters());
            if (is_string(($callback = $route->getCallback()))) {
                list($class, $method) = explode('@', $callback);
                $response = call_user_func_array([$class, $method], $params);
            } else if (is_callable($callback)) {
                $response = call_user_func_array($callback, $params);
            } else {
                $response = new Response('Don\'t support callback, Please setting callable function or class@method.');
            }

            try {
                return $next($request);
            } catch (\Exception $e) {
                return $response;
            }
        });
    }

    /**
     * @param ServerRequestInterface $request
     * @param array $params
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function dispatch(ServerRequestInterface $request, array $params = [])
    {
        $route = $this->routeCollection->match($request->getMethod(), $request->getUri()->getPath());
        // scan all middleware
        foreach ($route->getMiddleware() as $middleware) {
            $this->stack->withAddMiddleware($middleware);
        }

        $this->stack->withAddMiddleware($this->createRouteMiddleware($route, $params));

        return parent::dispatch($request);
    }
}