<?php

namespace App\Controller;

use Imagick;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class UploadController
{
    private Environment $twig;
    private LoggerInterface $logger;
    private SluggerInterface $slugger;
    private TranslatorInterface $translator;

    private string $destination_directory;

    public function __construct(Environment $twig, LoggerInterface $logger, SluggerInterface $slugger, TranslatorInterface $translator)
    {
        $this->twig = $twig;
        $this->logger = $logger;
        $this->slugger = $slugger;
        $this->translator = $translator;

        $this->destination_directory = __DIR__ . '/../../bnls';
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

                $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $this->slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

                if ('application/pdf'=== $mime_type) {
                    // Move the file to the destination directory
                    $file->move($this->destination_directory, $newFilename);
                    return new Response($newFilename, 200);
                }

                $image = new Imagick($file->getPathName());
                $profiles = $image->getImageProfiles('icc', true);
                $image->stripImage();
                if(!empty($profiles)) {
                    $image->profileImage('icc', $profiles['icc']);
                }

                $image->writeImage($this->destination_directory . '/' . $newFilename);
                $image->clear();
                $image->destroy();
            } catch (FileException $exception) {
                $this->logger->critical($exception->getMessage());
                return new Response($this->translator->trans('error.generic'), 400);
            } catch (\Exception $exception) {
                $this->logger->warning($exception->getMessage());
                return new Response($this->translator->trans('error.generic'), 400);
            }

            return new Response($newFilename, 200);
        }

        return new Response($this->twig->render('upload.html.twig'));
    }
}
