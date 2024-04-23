<?php

namespace Hn\Video\Rendering;

use TYPO3\CMS\Core\Resource\FileInterface;

final class SourceTag
{
    private FileInterface $file;
    private string $url;

    public function __construct(FileInterface $file, string $url)
    {
        $this->file = $file;
        $this->url = $url;
    }

    public function __toString()
    {
        return sprintf(
            '<source src="%s" type="%s" />',
            htmlspecialchars($this->url, ENT_QUOTES),
            htmlspecialchars($this->file->getMimeType())
        );
    }
}
