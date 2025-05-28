<?php

declare(strict_types=1);

namespace AppTest\Handler;

use App\Handler\LoginHandler;
use App\Service\TwilioVerificationService;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Template\TemplateRendererInterface;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Twilio\Rest\Verify\V2\Service\VerificationInstance;

class LoginHandlerTest extends TestCase
{
    private TemplateRendererInterface&MockObject $template;
    private ServerRequestInterface&MockObject $request;

    public function setUp(): void
    {
        $this->template = $this->createMock(TemplateRendererInterface::class);
        $this->request  = $this->createMock(ServerRequestInterface::class);
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
        $this->request
            ->expects($this->once())
            ->method('getMethod')
            ->willReturn('POST');
        $this->request
            ->expects($this->once())
            ->method('getParsedBody')
            ->willReturn(['username' => 'user@example.org']);

        $users = [
            'user@example.org' => '+61493123456',
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
            ->with($users['user@example.org'], "SMS")
            ->willReturn($verificationInstance);

        $response = new LoginHandler($this->template, $twilioService, $users)->handle($this->request);
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/verify', $response->getHeaderLine('Location'));
    }
}
