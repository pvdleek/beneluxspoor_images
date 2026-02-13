<?php

namespace App\Controller;

use Imagick;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class UploadController
{
    private string $destination_directory;

    public function __construct(
        private readonly Environment $twig,
        private readonly LoggerInterface $logger,
        private readonly SluggerInterface $slugger,
        private readonly TranslatorInterface $translator,
    ) {
        $this->destination_directory = __DIR__.'/../../bnls_'.\date('Y');
    }

    public function indexAction(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            try {
                $file = $request->files->all()['file'];
                $mime_type = $file->getMimeType();

                if ('application/pdf' !== $mime_type && 'image/' !== substr($mime_type, 0, 6)) {
                    throw new AccessDeniedHttpException($this->translator->trans('error.invalidMimeType'));
                }

                $original_filename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $safe_filename = $this->slugger->slug($original_filename);
                $new_filename = $safe_filename.'-'.\uniqid().'.'.$file->guessExtension();

                if ('application/pdf' === $mime_type) {
                    $file->move($this->destination_directory, $new_filename);

                    return new Response($new_filename, 200);
                }

                $this->writeImage($file, $new_filename);
            } catch (FileException $exception) {
                $this->logger->critical($exception->getMessage());

                return new Response($this->translator->trans('error.generic'), 400);
            } catch (\Exception $exception) {
                $this->logger->warning($exception->getMessage());
                
                return new Response($this->translator->trans('error.generic'), 400);
            }

            return new Response($new_filename, 200);
        }

        return new Response($this->twig->render('upload.html.twig'));
    }

    private function writeImage(UploadedFile $file, string $new_filename): void
    {
        $image = new Imagick($file->getPathname());
        $profiles = $image->getImageProfiles('icc', true);
        $orientation = $image->getImageOrientation();
        $image->stripImage();
        if (!empty($profiles)) {
            $image->profileImage('icc', $profiles['icc']);
        }
        switch($orientation) {
            case Imagick::ORIENTATION_BOTTOMRIGHT:
                $image->rotateImage('#000', 180); // rotate 180 degrees
                break;

            case Imagick::ORIENTATION_RIGHTTOP:
                $image->rotateImage('#000', 90); // rotate 90 degrees CW
                break;

            case Imagick::ORIENTATION_LEFTBOTTOM:
                $image->rotateImage('#000', -90); // rotate 90 degrees CCW
                break;
        }
        $image->scaleImage(min($image->getImageWidth(), 1800), 0);

        $image->writeImage($this->destination_directory.'/'.$new_filename);
        $image->clear();
        $image->destroy();
    }
}
