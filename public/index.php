<?php

declare(strict_types=1);

use Laminas\ConfigAggregator\ConfigAggregator;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\TextResponse;
use Laminas\ServiceManager\ServiceManager;
use Asgrim\MiniMezzio\AppFactory;
use Mezzio\Router\FastRouteRouter;
use Mezzio\Router\Middleware\DispatchMiddleware;
use Mezzio\Router\Middleware\RouteMiddleware;
use Mezzio\Template\TemplateRendererInterface;
use Mezzio\Twig\ConfigProvider as MezzioTwigConfigProvider;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

require __DIR__ . '/../vendor/autoload.php';

$config = new ConfigAggregator([
    MezzioTwigConfigProvider::class,
    new class()
    {
        public function __invoke(): array {
            return [
                'templates'    => [
                    'paths' => [
                        'app'    => [__DIR__ . '/../templates/app'],
                        'error'  => [__DIR__ . '/../templates/error'],
                        'layout' => [__DIR__ . '/../templates/layout'],
                    ],
                ],
            ];
        }
    },
])->getMergedConfig();
$dependencies                       = $config['dependencies'];
$dependencies['services']['config'] = $config;
$container = new ServiceManager($dependencies);

$router = new FastRouteRouter();
$app = AppFactory::create($container, $router);
$app->pipe(new RouteMiddleware($router));
$app->pipe(new DispatchMiddleware());

/**
 * Define the application's routes
 */
$app->route(
    '/login',
    new class($container->get(TemplateRendererInterface::class)) implements RequestHandlerInterface {
        public function __construct(private TemplateRendererInterface $renderer)
        {
        }

        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            if ($request->getMethod() === 'GET') {
                return new HtmlResponse($this->renderer->render('app::login', []));
            }
        }
    },
    ['GET', 'POST'],
    'login'
);

$app->route(
    '/verify',
    new class($container->get(TemplateRendererInterface::class)) implements RequestHandlerInterface {
        public function __construct(private TemplateRendererInterface $renderer)
        {
        }

        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            if ($request->getMethod() === 'GET') {
                return new HtmlResponse($this->renderer->render('app::verify', []));
            }
        }
    },
    ['GET', 'POST'],
    'verify');

$app->route(
    '/upload',
    new class ($container->get(TemplateRendererInterface::class))implements RequestHandlerInterface {
        public function __construct(private TemplateRendererInterface $renderer)
        {
        }

        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            if ($request->getMethod() === 'GET') {
                return new HtmlResponse($this->renderer->render('app::upload', []));
            }
        }
    },
    ['GET', 'POST'],
    'upload'
);

$app->run();
