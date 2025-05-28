<?php

declare(strict_types=1);

namespace App\Handler;

use InvalidArgumentException;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

use function array_key_exists;

use const UPLOAD_ERR_OK;

readonly class UploadHandler implements RequestHandlerInterface
{
    public function __construct(
        private TemplateRendererInterface $renderer,
        private array $uploadConfig = [],
    ) {
    }

    /**
     * @inheritDoc
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($request->getMethod() === 'GET') {
            return new HtmlResponse($this->renderer->render('app::upload', []));
        }

        $formData = $request->getUploadedFiles();
        if (
            ! array_key_exists('file', $formData)
            || ! $formData['file'] instanceof UploadedFileInterface
        ) {
            return new RedirectResponse('/upload');
        }

        $file = $formData['file'];
        if ($file->getError() !== UPLOAD_ERR_OK) {
            return new RedirectResponse('/upload');
        }

        try {
            $file->moveTo($this->uploadConfig['upload_dir'] . $file->getClientFilename());
        } catch (InvalidArgumentException | RuntimeException $e) {
            return new RedirectResponse('/upload');
        }
        return new RedirectResponse('/upload');
    }
}
