<?php

declare(strict_types=1);

namespace App\Service;

use Twilio\Rest\Client;
use Twilio\Rest\Verify\V2\Service\VerificationCheckInstance;
use Twilio\Rest\Verify\V2\Service\VerificationInstance;
use Twilio\Rest\Verify\V2\ServiceContext;

readonly class TwilioVerificationService
{
    private ServiceContext $verifyService;

    public function __construct(private Client $client, private string $serviceId)
    {
        $this->verifyService = $this->client
            ->verify
            ->v2
            ->services($this->serviceId);
    }

    public function sendVerificationCode(string $recipient, string $channel = 'SMS'): VerificationInstance
    {
        return $this->verifyService
            ->verifications
            ->create($recipient, $channel);
    }

    public function validateVerificationCode(
        string $recipient,
        string $code
    ): VerificationCheckInstance {
        return $this->verifyService
            ->verificationChecks
            ->create([
                "to"   => $recipient,
                "code" => $code,
            ]);
    }
}
