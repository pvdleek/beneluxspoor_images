<?php

namespace App\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\String\Slugger\SluggerInterface;
use Twig\Environment;

class UploadController
{
    private Environment $twig;
    private LoggerInterface $logger;
    private SluggerInterface $slugger;

    public function __construct(Environment $twig, LoggerInterface $logger, SluggerInterface $slugger)
    {
        $this->twig = $twig;
        $this->logger = $logger;
        $this->slugger = $slugger;
    }

    public function indexAction(Request $request)
    {
        if ($request->isMethod('POST')) {
            $file = $request->files->all()['file'];

            $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $this->slugger->slug($originalFilename);
            $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

            // Move the file to the destination directory
            try {
                $file->move(__DIR__ . '/../../bnls', $newFilename);
            } catch (FileException $exception) {
                $this->logger->critical($exception->getMessage());
                return new Response('Het is niet gelukt om de afbeelding te plaatsen, probeer het opnieuw', 400);
            }

            return new Response($newFilename, 200);
        }

        return new Response($this->twig->render('upload.html.twig'));
    }
}
