<?php

declare(strict_types=1);

use App\Handler\LoginHandler;
use App\Handler\UploadHandler;
use App\Handler\VerifyHandler;
use App\Service\TwilioVerificationService;
use Asgrim\MiniMezzio\AppFactory;
use Dotenv\Dotenv;
use Laminas\ConfigAggregator\ConfigAggregator;
use Laminas\ServiceManager\AbstractFactory\ReflectionBasedAbstractFactory;
use Laminas\ServiceManager\ServiceManager;
use Mezzio\Flash\ConfigProvider as MezzioFlashConfigProvider;
use Mezzio\Flash\FlashMessageMiddleware;
use Mezzio\Router\FastRouteRouter;
use Mezzio\Router\Middleware\DispatchMiddleware;
use Mezzio\Router\Middleware\RouteMiddleware;
use Mezzio\Router\RouterInterface;
use Mezzio\Session\ConfigProvider as MezzioSessionConfigProvider;
use Mezzio\Session\Ext\ConfigProvider as MezzioSessionExtConfigProvider;
use Mezzio\Session\SessionMiddleware;
use Mezzio\Twig\ConfigProvider as MezzioTwigConfigProvider;
use Twilio\Rest\Client;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();
$dotenv->required([
    'TWILIO_ACCOUNT_SID',
    'TWILIO_AUTH_TOKEN',
    'VERIFY_SERVICE_SID',
    'UPLOAD_DIRECTORY',
    'YOUR_PHONE_NUMBER',
    'YOUR_USERNAME',
]);

$config                             = new ConfigAggregator([
    MezzioTwigConfigProvider::class,
    MezzioSessionConfigProvider::class,
    MezzioSessionExtConfigProvider::class,
    MezzioFlashConfigProvider::class,
    new class ()
    {
        public function __invoke(): array
        {
            return [
                'templates' => [
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
$dependencies['services']['config']['upload'] = [
    'upload_dir' => __DIR__ . '/../' . $_SERVER['UPLOAD_DIRECTORY'],
];
$dependencies['services']['config']['users']  = [
    $_SERVER['YOUR_USERNAME'] => $_SERVER['YOUR_PHONE_NUMBER'],
];
$container                          = new ServiceManager($dependencies);
$container->addAbstractFactory(new ReflectionBasedAbstractFactory());
$container->setFactory(RouterInterface::class, static function () {
    return new FastRouteRouter();
$container->setFactory(TwilioVerificationService::class, new class {
    public function __invoke(ContainerInterface $container): TwilioVerificationService
    {
        $client = new Client(
            $_ENV['TWILIO_ACCOUNT_SID'],
            $_ENV['TWILIO_AUTH_TOKEN'],
        );
        return new TwilioVerificationService($client, $_ENV['VERIFY_SERVICE_SID']);
    }
});
});

$app = AppFactory::create($container, $container->get(RouterInterface::class));
$app->pipe(RouteMiddleware::class);
$app->pipe(SessionMiddleware::class);
$app->pipe(FlashMessageMiddleware::class);
$app->pipe(DispatchMiddleware::class);

/**
 * Define the application's routes
 */
$app->route('/login', LoginHandler::class, ['GET', 'POST'], 'login');
$app->route('/verify', VerifyHandler::class, ['GET', 'POST'], 'verify');
$app->route('/upload', UploadHandler::class, ['GET', 'POST'], 'upload');

$app->run();
