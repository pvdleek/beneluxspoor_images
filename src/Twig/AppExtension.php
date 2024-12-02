<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class AppExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('fileTimestamp', [$this, 'fileTimestampFilter']),
        ];
    }

    public function fileTimestampFilter(string $filename): string
    {
        return (string) \filemtime(__DIR__.'/../../html/'.$filename);
    }
}
