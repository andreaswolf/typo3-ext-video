<?php

namespace Hn\HauptsacheVideo\Slot;


use Hn\HauptsacheVideo\VideoMetadataExtractor;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Resource\Index\MetaDataRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class MetaDataRepositorySlot
{
    public function recordPostRetrieval(\ArrayObject $data)
    {
        if (empty($data['newlyCreated'])) {
            return;
        }

        $file = GeneralUtility::makeInstance(FileRepository::class)->findByUid($data['file']);
        if (!$file instanceof File) {
            return;
        }

        $videoMetadataExtractor = GeneralUtility::makeInstance(VideoMetadataExtractor::class);
        if (!$videoMetadataExtractor->canProcess($file)) {
            return;
        }

        $extractedMetaData = $videoMetadataExtractor->extractMetaData($file, $data->getArrayCopy());
        MetaDataRepository::getInstance()->update($file->getUid(), $extractedMetaData);

        // add the new metadata to the retrieved infos
        foreach ($extractedMetaData as $key => $value) {
            $data[$key] = $value;
        }
    }
}
