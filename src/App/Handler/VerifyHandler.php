<?php

declare(strict_types=1);

namespace App\Handler;

use App\Service\TwilioVerificationService;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Session\SessionInterface;
use Mezzio\Session\SessionMiddleware;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function array_key_exists;
use function array_keys;
use function in_array;

final readonly class VerifyHandler implements RequestHandlerInterface
{
    use FlashMessagesTrait;

    /**
     * @param array<string,string> $users
     */
    public function __construct(
        private TemplateRendererInterface $renderer,
        private TwilioVerificationService $verificationService,
        private array $users = []
    ) {
    }

    /**
     * @inheritDoc
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($request->getMethod() === 'GET') {
            /** @var SessionInterface $session */
            $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
            if (! $session->has('username')) {
                $this->setFlash($request, 'error', 'Username not available in request');
                return new RedirectResponse('/login');
            }

            return new HtmlResponse($this->renderer->render('app::verify', [
                'username' => $session->get('username'),
            ]));
        }

        $formData = $request->getParsedBody();
        if (
            ! array_key_exists('username', $formData)
            || ! array_key_exists('verification_code', $formData)
            || $formData['username'] === ''
            || $formData['verification_code'] === ''
            || ! in_array($formData['username'], array_keys($this->users))
        ) {
            return new RedirectResponse('/verify');
        }

        $result = $this->verificationService->validateVerificationCode(
            $this->users[$formData['username']],
            $formData['verification_code']
        );
        if ($result->status === 'approved') {
            return new RedirectResponse('/upload');
        }
    }
}
