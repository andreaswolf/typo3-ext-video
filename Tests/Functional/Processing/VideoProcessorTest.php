<?php

namespace Hn\HauptsacheVideo\Tests\Functional\Processing;


use Hn\HauptsacheVideo\Converter\LocalVideoConverter;
use Nimut\TestingFramework\TestCase\FunctionalTestCase;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Resource\DuplicationBehavior;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;

class VideoProcessorTest extends FunctionalTestCase
{
    protected $testExtensionsToLoad = [
        'typo3conf/ext/hauptsache_video',
    ];

    /** @var ObjectManager */
    protected $objectManager;
    /** @var StorageRepository */
    protected $storageRepository;

    protected function setUp()
    {
        parent::setUp();
        $this->objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        $this->storageRepository = $this->objectManager->get(StorageRepository::class);
        $GLOBALS['BE_USER'] = $this->createConfiguredMock(BackendUserAuthentication::class, [
            'isAdmin' => true,
        ]);
    }

    protected function tearDown()
    {
        parent::tearDown();
        unset($GLOBALS['BE_USER']);
        GeneralUtility::purgeInstances();
    }

    public function testProcessing()
    {
        $resourceStorage = $this->storageRepository->findByUid(1);
        $this->assertInstanceOf(ResourceStorage::class, $resourceStorage);

        $file = $resourceStorage->addFile(
            __DIR__ . '/../../Resources/File.mp4',
            $resourceStorage->getRootLevelFolder(),
            'File.mp4',
            DuplicationBehavior::REPLACE,
            false
        );
        $this->assertInstanceOf(File::class, $file);

        $videoConverter = $this->createMock(LocalVideoConverter::class);
        $videoConverter->expects($this->once())->method('start');
        GeneralUtility::addInstance(LocalVideoConverter::class, $videoConverter);
        $processedFile = $resourceStorage->processFile($file, 'Video.CropScale', []);

        $this->assertTrue($processedFile->isProcessed());
        $this->assertEquals('mp4', $processedFile->getExtension());
    }
}
