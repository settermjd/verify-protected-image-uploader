<?php

declare(strict_types=1);

namespace App\Handler;

use App\Service\TwilioVerificationService;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function array_key_exists;
use function array_keys;
use function in_array;

final readonly class LoginHandler implements RequestHandlerInterface
{
    /**
     * @param array<string,string> $users
     */
    public function __construct(
        private TemplateRendererInterface $renderer,
        private TwilioVerificationService $twilioRestService,
        private array $users = [],
    ) {
    }

    /**
     * @inheritDoc
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($request->getMethod() === 'GET') {
            return new HtmlResponse($this->renderer->render('app::login', []));
        }

        $formData = $request->getParsedBody();
        if (
            ! array_key_exists('username', $formData)
            || $formData['username'] === ''
            || $this->users === []
            || ! in_array($formData['username'], array_keys($this->users))
        ) {
            return new RedirectResponse('/login');
        }

        $verificationInstance = $this->twilioRestService->sendVerificationCode($this->users[$formData['username']]);
        if ($verificationInstance->status === 'pending') {
            return new RedirectResponse('/verify');
        }
    }
}
