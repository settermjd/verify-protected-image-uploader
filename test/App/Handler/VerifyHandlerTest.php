<?php

declare(strict_types=1);

namespace AppTest\Handler;

use App\Handler\VerifyHandler;
use App\Service\TwilioVerificationService;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Flash\FlashMessagesInterface;
use Mezzio\Session\SessionInterface;
use Mezzio\Template\TemplateRendererInterface;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Twilio\Rest\Verify\V2\Service\VerificationCheckInstance;

class VerifyHandlerTest extends TestCase
{
    private FlashMessagesInterface&MockObject $flashMessage;
    private ServerRequestInterface&MockObject $request;
    private SessionInterface&MockObject $session;
    private TemplateRendererInterface&MockObject $template;

    public function setUp(): void
    {
        $this->flashMessage = $this->createMock(FlashMessagesInterface::class);
        $this->request      = $this->createMock(ServerRequestInterface::class);
        $this->session      = $this->createMock(SessionInterface::class);
        $this->template     = $this->createMock(TemplateRendererInterface::class);
    }

    public function testRendersTemplateOnGetRequest(): void
    {
        $username = 'user@example.org';

        $this->template
            ->expects($this->once())
            ->method('render')
            ->with('app::verify', [
                'username' => $username,
            ])
            ->willReturn('');

        $this->request
            ->expects($this->once())
            ->method('getMethod')
            ->willReturn('GET');
        $this->request
            ->expects($this->once())
            ->method('getAttribute')
            ->willReturn($this->session);

        $this->session
            ->expects($this->once())
            ->method('has')
            ->with('username')
            ->willReturn(true);
        $this->session
            ->expects($this->once())
            ->method('get')
            ->with('username')
            ->willReturn($username);

        $twilioService = $this->createMock(TwilioVerificationService::class);

        $response = new VerifyHandler($this->template, $twilioService)->handle($this->request);
        $this->assertInstanceOf(HtmlResponse::class, $response);
    }

    public function testValidatesVerificationCodeIfFormIsValid(): void
    {
        $username         = 'user@example.org';
        $users            = [$username => '+61493123456'];
        $verificationCode = '123456';

        $this->request
            ->expects($this->once())
            ->method('getMethod')
            ->willReturn('POST');
        $this->request
            ->expects($this->once())
            ->method('getParsedBody')
            ->willReturn([
                'username'          => $username,
                'verification_code' => $verificationCode,
            ]);

        $checkInstance = $this->createMock(VerificationCheckInstance::class);
        $checkInstance
            ->expects($this->once())
            ->method('__get')
            ->with('status')
            ->willReturn('approved');

        $twilioService = $this->createMock(TwilioVerificationService::class);
        $twilioService
            ->expects($this->once())
            ->method('validateVerificationCode')
            ->with($users[$username], $verificationCode)
            ->willReturn($checkInstance);

        $response = new VerifyHandler($this->template, $twilioService, $users)
            ->handle($this->request);
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/upload', $response->getHeaderLine('Location'));
    }

    /**
     * @param array<string,string> $formData
     * @param array<string,string> $users
     * @throws Exception
     */
    #[TestWith([
        [
            'username'          => 'user@example.org',
            'verification_code' => '123456',
        ],
        [],
    ])]
    #[TestWith([
        [
            'username'          => 'user@example.org',
            'verification_code' => '123456',
        ],
        [
            'user@example.com' => '+61493123456',
        ],
    ])]
    #[TestWith([
        [
            'verification_code' => '123456',
        ],
        [
            'user@example.org' => '+61493123456',
        ],
    ])]
    #[TestWith([
        [
            'username' => 'user@example.org',
        ],
        [
            'user@example.org' => '+61493123456',
        ],
    ])]
    #[TestWith([
        [
            'username'          => 'user@example.org',
            'verification_code' => '',
        ],
        [
            'user@example.org' => '+61493123456',
        ],
    ])]
    public function testWillRedirectToVerifyFormIfFormDataIsInvalid(array $formData, array $users): void
    {
        $username = 'user@example.org';

        $this->request
            ->expects($this->once())
            ->method('getMethod')
            ->willReturn('POST');
        $this->request
            ->expects($this->once())
            ->method('getParsedBody')
            ->willReturn($formData);

        $twilioService = $this->createMock(TwilioVerificationService::class);

        $response = new VerifyHandler($this->template, $twilioService, $users)->handle($this->request);
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/verify', $response->getHeaderLine('Location'));
    }

    public function testWillRedirectToLoginFormIfUsernameNotAvailableInRequestHeader(): void
    {
        $this->flashMessage
            ->expects($this->once())
            ->method('flash')
            ->with('error', 'Username not available in request');

        $this->session
            ->expects($this->once())
            ->method('has')
            ->with('username')
            ->willReturn(false);

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

        $response = new VerifyHandler($this->template, $twilioService, [])
            ->handle($this->request);
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/login', $response->getHeaderLine('Location'));
    }
}
