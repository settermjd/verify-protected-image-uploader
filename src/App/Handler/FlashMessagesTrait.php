<?php

declare(strict_types=1);

namespace App\Handler;

use Mezzio\Flash\FlashMessagesInterface;
use Psr\Http\Message\ServerRequestInterface;

trait FlashMessagesTrait
{
    public function setFlash(ServerRequestInterface $request, string $key, string $message): void
    {
        /** @var FlashMessagesInterface $flashMessage */
        $flashMessage = $request->getAttribute('flash');
        $flashMessage?->flash($key, $message);
    }

    public function getFlash(ServerRequestInterface $request, string $key): string|null
    {
        /** @var FlashMessagesInterface $flashMessage */
        $flashMessage = $request->getAttribute('flash');
        return $flashMessage?->getFlash($key) ?? null;
    }
}
