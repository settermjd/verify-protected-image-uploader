<?php

declare(strict_types=1);

namespace AppTest\Handler;

use App\Handler\VerifyHandler;
use App\Service\TwilioVerificationService;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Template\TemplateRendererInterface;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Twilio\Rest\Verify\V2\Service\VerificationCheckInstance;

class VerifyHandlerTest extends TestCase
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
            ->with('app::verify', [])
            ->willReturn('');

        $this->request
            ->expects($this->once())
            ->method('getMethod')
            ->willReturn('GET');

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

        $response = new VerifyHandler($this->template, $twilioService, $users)->handle($this->request);
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
}
