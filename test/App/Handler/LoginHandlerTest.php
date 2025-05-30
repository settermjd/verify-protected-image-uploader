<?php

declare(strict_types=1);

namespace AppTest\Handler;

use App\Handler\LoginHandler;
use App\Service\TwilioVerificationService;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Flash\FlashMessageMiddleware;
use Mezzio\Flash\FlashMessagesInterface;
use Mezzio\Session\SessionInterface;
use Mezzio\Session\SessionMiddleware;
use Mezzio\Template\TemplateRendererInterface;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Twilio\Rest\Verify\V2\Service\VerificationInstance;

class LoginHandlerTest extends TestCase
{
    private FlashMessagesInterface&MockObject $flashMessage;
    private ServerRequestInterface&MockObject $request;
    private SessionInterface&MockObject $session;
    private TemplateRendererInterface&MockObject $template;

    public function setUp(): void
    {
        $this->flashMessage = $this->createMock(FlashMessagesInterface::class);
        $this->request      = $this->createMock(ServerRequestInterface::class);
        $this->template     = $this->createMock(TemplateRendererInterface::class);
        $this->session      = $this->createMock(SessionInterface::class);
    }

    public function testRendersTemplateOnGetRequest(): void
    {
        $this->template
            ->expects($this->once())
            ->method('render')
            ->with('app::login', [])
            ->willReturn('');

        $this->request
            ->expects($this->once())
            ->method('getMethod')
            ->willReturn('GET');
        $this->request
            ->expects($this->atLeast(2))
            ->method('getAttribute')
            ->willReturnOnConsecutiveCalls(
                $this->session,
                $this->flashMessage
            );

        $this->session
            ->expects($this->once())
            ->method('unset')
            ->with('username');

        $twilioService = $this->createMock(TwilioVerificationService::class);

        $response = new LoginHandler($this->template, $twilioService)->handle($this->request);
        $this->assertInstanceOf(HtmlResponse::class, $response);
    }

    public function testRendersTemplateOnGetRequestWithFlashMessageIfSet(): void
    {
        $errorMessage = 'Username not available in request';

        $this->template
            ->expects($this->once())
            ->method('render')
            ->with('app::login', ['error' => $errorMessage])
            ->willReturn('');

        $this->flashMessage
            ->expects($this->once())
            ->method('getFlash')
            ->with('error')
            ->willReturn($errorMessage);

        $this->request
            ->expects($this->atLeast(2))
            ->method('getAttribute')
            ->willReturnOnConsecutiveCalls(
                $this->session,
                $this->flashMessage
            );
        $this->request
            ->expects($this->once())
            ->method('getMethod')
            ->willReturn('GET');

        $twilioService = $this->createMock(TwilioVerificationService::class);

        $response = new LoginHandler($this->template, $twilioService)->handle($this->request);
        $this->assertInstanceOf(HtmlResponse::class, $response);
    }

    /**
     * @param array<string,string> $formData
     * @param array<string,string> $users
     * @throws Exception
     */
    #[TestWith([
        ['user' => 'user@example.org'],
        ['user@example.org' => '+61493123456'],
    ])]
    #[TestWith([
        ['username' => 'user@example.com'],
        ['user@example.org' => '+61493123456'],
    ])]
    #[TestWith([
        ['username' => 'user@example.org'],
        ['user@example.com' => '+61493123456'],
    ])]
    #[TestWith([
        [],
        ['user@example.com' => '+61493123456'],
    ])]
    #[TestWith([
        ['username' => 'user@example.org'],
        [],
    ])]
    public function testRedirectsBackToLoginFormIfUsernameIsMissingOrNotInUsersList(array $formData, array $users): void
    {
        $this->request
            ->expects($this->once())
            ->method('getMethod')
            ->willReturn('POST');
        $this->request
            ->expects($this->once())
            ->method('getParsedBody')
            ->willReturn($formData);

        $twilioService = $this->createMock(TwilioVerificationService::class);

        $response = new LoginHandler($this->template, $twilioService, $users)->handle($this->request);
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/login', $response->getHeaderLine('Location'));
    }

    public function testHandlesLoginRequestWhenFormDataIsValidAndHasMatchingUser(): void
    {
        $username = 'user@example.org';

        $this->session
            ->expects($this->once())
            ->method('set')
            ->with('username', $username);

        $this->request
            ->expects($this->once())
            ->method('getMethod')
            ->willReturn('POST');
        $this->request
            ->expects($this->once())
            ->method('getParsedBody')
            ->willReturn(['username' => $username]);
        $this->request
            ->expects($this->once())
            ->method('getAttribute')
            ->with(SessionMiddleware::SESSION_ATTRIBUTE)
            ->willReturn($this->session);

        $users = [
            $username => '+61493123456',
        ];

        $verificationInstance = $this->createMock(VerificationInstance::class);
        $verificationInstance
            ->expects($this->once())
            ->method('__get')
            ->with('status')
            ->willReturn('pending');

        $twilioService = $this->createMock(TwilioVerificationService::class);
        $twilioService
            ->expects($this->once())
            ->method('sendVerificationCode')
            ->with($users[$username], "sms")
            ->willReturn($verificationInstance);

        $response = new LoginHandler($this->template, $twilioService, $users)->handle($this->request);
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/verify', $response->getHeaderLine('Location'));
    }
}
