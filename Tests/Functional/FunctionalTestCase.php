<?php

namespace Hn\Video\Tests\Functional;

use Hn\Video\Converter\NoopConverter;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Resource\DuplicationBehavior;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

abstract class FunctionalTestCase extends \Nimut\TestingFramework\TestCase\FunctionalTestCase
{
    protected $coreExtensionsToLoad = [
        'recordlist',
    ];

    protected $testExtensionsToLoad = [
        'typo3conf/ext/video',
    ];

    /** @var ObjectManager */
    protected $objectManager;
    /** @var PersistenceManager */
    protected $persistenceManager;
    /** @var StorageRepository */
    protected $storageRepository;
    /** @var ResourceStorage */
    protected $resourceStorage;
    /** @var File */
    protected $file;

    protected function setUp()
    {
        parent::setUp();
        $this->objectManager = GeneralUtility::makeInstance(ObjectManager::class);

        $this->storageRepository = $this->objectManager->get(StorageRepository::class);
        $GLOBALS['BE_USER'] = $this->createConfiguredMock(BackendUserAuthentication::class, [
            'isAdmin' => true,
        ]);

        $this->resourceStorage = $this->storageRepository->findByUid(1);
        $this->file = $this->resourceStorage->addFile(
            __DIR__ . '/../Fixtures/test.mp4',
            $this->resourceStorage->getRootLevelFolder(),
            'File.mp4',
            DuplicationBehavior::REPLACE,
            false
        );

        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['video']['video_converter'] = [NoopConverter::class];
    }

    protected function tearDown()
    {
        // delete processed files after the test to avoid collisions with the next test
        foreach ($this->resourceStorage->getProcessingFolders() as $processingFolder) {
            foreach ($processingFolder->getFiles(0, 0, Folder::FILTER_MODE_NO_FILTERS, true) as $file) {
                $this->assertTrue($file->delete(), 'there was a processed file that could not be deleted');
            }
        }

        GeneralUtility::purgeInstances();
        unset($GLOBALS['BE_USER']);
        parent::tearDown();
    }

    public function __sleep()
    {
        return [];
    }
}
