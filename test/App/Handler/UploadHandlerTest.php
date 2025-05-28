<?php

declare(strict_types=1);

namespace AppTest\Handler;

use App\Handler\UploadHandler;
use InvalidArgumentException;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Laminas\Diactoros\Stream;
use Laminas\Diactoros\UploadedFile;
use Mezzio\Template\TemplateRendererInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;
use stdClass;

use function basename;
use function filesize;
use function fopen;

use const UPLOAD_ERR_CANT_WRITE;
use const UPLOAD_ERR_EXTENSION;
use const UPLOAD_ERR_FORM_SIZE;
use const UPLOAD_ERR_INI_SIZE;
use const UPLOAD_ERR_NO_FILE;
use const UPLOAD_ERR_NO_TMP_DIR;
use const UPLOAD_ERR_OK;
use const UPLOAD_ERR_PARTIAL;

class UploadHandlerTest extends TestCase
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
            ->with('app::upload', [])
            ->willReturn('');

        $this->request
            ->expects($this->once())
            ->method('getMethod')
            ->willReturn('GET');

        $response = new UploadHandler($this->template)->handle($this->request);
        $this->assertInstanceOf(HtmlResponse::class, $response);
    }

    public function testCanUploadValidImageFileIfFormDataIsValid(): void
    {
        $this->request
            ->expects($this->once())
            ->method('getMethod')
            ->willReturn('POST');

        $filename = __DIR__ . '/../../data/files/upload.png';
        $fhandle  = fopen($filename, 'r');

        $this->request
            ->expects($this->once())
            ->method('getUploadedFiles')
            ->willReturn([
                'file' => new UploadedFile(
                    streamOrFile: new Stream($fhandle),
                    size: filesize($filename),
                    errorStatus: UPLOAD_ERR_OK,
                    clientFilename: 'upload.png',
                ),
            ]);

        $uploadConfig = [
            'upload_dir' => __DIR__ . '/../../../data/uploads',
        ];

        $response = new UploadHandler($this->template, $uploadConfig)->handle($this->request);
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/upload', $response->getHeaderLine('Location'));
    }

    #[DataProvider('invalidUploadDataProvider')]
    public function testRedirectsToUploadFormIfNoFilesOrInvalidFilesWereUploaded(array|null $uploadData = []): void
    {
        $this->request
            ->expects($this->once())
            ->method('getMethod')
            ->willReturn('POST');

        $this->request
            ->expects($this->once())
            ->method('getUploadedFiles')
            ->willReturn($uploadData);

        $uploadConfig = [
            'upload_dir' => __DIR__ . '/../../../data/uploads',
        ];

        $response = new UploadHandler($this->template, $uploadConfig)->handle($this->request);
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/upload', $response->getHeaderLine('Location'));
    }

    /**
     * @return array<array<string,null|string,UploadedFile,object>
     */
    public static function invalidUploadDataProvider(): array
    {
        $filename = __DIR__ . '/../../data/files/upload.png';
        $fhandle  = fopen($filename, 'r');

        return [
            [
                [
                    'file' => null,
                ],
            ],
            [
                [
                    'file' => '',
                ],
            ],
            [
                [
                    'file' => new UploadedFile(
                        streamOrFile: new Stream($fhandle),
                        size: filesize($filename),
                        errorStatus: UPLOAD_ERR_OK,
                        clientFilename: basename($filename),
                    ),
                ],
            ],
            [
                [
                    'file' => new stdClass(),
                ],
            ],
        ];
    }

    #[TestWith([InvalidArgumentException::class])]
    #[TestWith([RuntimeException::class])]
    public function testRedirectsToUploadRouteIfFileCannotBeUploaded(string $exception): void
    {
        $this->request
            ->expects($this->once())
            ->method('getMethod')
            ->willReturn('POST');

        $uploadedFile = $this->createMock(UploadedFileInterface::class);
        $uploadedFile
            ->expects($this->once())
            ->method('getError')
            ->willReturn(UPLOAD_ERR_OK);
        $uploadedFile
            ->expects($this->once())
            ->method('moveTo')
            ->willThrowException(new $exception());

        $this->request
            ->expects($this->once())
            ->method('getUploadedFiles')
            ->willReturn([
                'file' => $uploadedFile,
            ]);

        $uploadConfig = [
            'upload_dir' => __DIR__ . '/../../../data/uploads',
        ];

        $response = new UploadHandler($this->template, $uploadConfig)->handle($this->request);
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/upload', $response->getHeaderLine('Location'));
    }

    #[TestWith([UPLOAD_ERR_CANT_WRITE])]
    #[TestWith([UPLOAD_ERR_EXTENSION])]
    #[TestWith([UPLOAD_ERR_FORM_SIZE])]
    #[TestWith([UPLOAD_ERR_INI_SIZE])]
    #[TestWith([UPLOAD_ERR_NO_FILE])]
    #[TestWith([UPLOAD_ERR_NO_TMP_DIR])]
    #[TestWith([UPLOAD_ERR_PARTIAL])]
    public function testRedirectsToUploadRouteIfFileWasNotAnUploadedFile(int $uploadError): void
    {
        $this->request
            ->expects($this->once())
            ->method('getMethod')
            ->willReturn('POST');

        $uploadedFile = $this->createMock(UploadedFileInterface::class);
        $uploadedFile
            ->expects($this->once())
            ->method('getError')
            ->willReturn($uploadError);

        $this->request
            ->expects($this->once())
            ->method('getUploadedFiles')
            ->willReturn([
                'file' => $uploadedFile,
            ]);

        $uploadConfig = [
            'upload_dir' => __DIR__ . '/../../../data/uploads',
        ];

        $response = new UploadHandler($this->template, $uploadConfig)->handle($this->request);
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/upload', $response->getHeaderLine('Location'));
    }
}
