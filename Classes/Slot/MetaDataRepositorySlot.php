<?php

namespace Hn\Video\Slot;

use Hn\Video\VideoMetadataExtractor;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Resource\Index\MetaDataRepository;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This hook will extract metadata from videos even if they are not new.
 * For images typo3 has that behavior hardcoded.
 *
 * @see \TYPO3\CMS\Core\Resource\Index\MetaDataRepository::findByFile
 */
class MetaDataRepositorySlot implements SingletonInterface
{
    /**
     * This hook may trigger itself during processing.
     * Or more precisely the VideoMetadataExtractor does by checking if dimensions already exist.
     * To prevent recursion, do not process a file while it is being processed.
     *
     * This is usually prevented by checking the "newlyCreated" property.
     * But since this extension might be installed after files were uploaded I need to check even existing files.
     */
    private array $currentlyProcessing = [];

    private FileRepository $fileRepository;

    public function __construct()
    {
        $this->fileRepository = GeneralUtility::makeInstance(FileRepository::class);
    }

    public function recordPostRetrieval(\ArrayObject $data): void
    {
        if (isset($this->currentlyProcessing[$data['file']])) {
            return;
        }

        if (!empty($data['video_metadata_extraction_tried'])) {
            return;
        }

        $this->currentlyProcessing[$data['file']] = true;
        $file = $this->fileRepository->findByUid($data['file']);
        if (!$file instanceof File) {
            return;
        }

        $videoMetadataExtractor = GeneralUtility::makeInstance(VideoMetadataExtractor::class);
        if (!$videoMetadataExtractor->canProcess($file)) {
            return;
        }

        // don't try to extract metadata again
        $data['video_metadata_extraction_tried'] = 1;
        MetaDataRepository::getInstance()->update($file->getUid(), ['video_metadata_extraction_tried' => 1]);

        $extractedMetaData = $videoMetadataExtractor->extractMetaData($file, $data->getArrayCopy());
        MetaDataRepository::getInstance()->update($file->getUid(), $extractedMetaData);

        // add the new metadata to the retrieved infos
        foreach ($extractedMetaData as $key => $value) {
            $data[$key] = $value;
        }

        unset($this->currentlyProcessing[$data['file']]);
    }
}
