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
            __DIR__ . '/../../Resources/File.mp4',
            $this->resourceStorage->getRootLevelFolder(),
            'File.mp4',
            DuplicationBehavior::REPLACE,
            false
        );
    }

    protected function tearDown()
    {
        parent::tearDown();
        unset($GLOBALS['BE_USER']);
        GeneralUtility::purgeInstances();
    }

    public function __sleep()
    {
        return [];
    }

    public function testProcessing()
    {
        $videoConverter = $this->createMock(LocalVideoConverter::class);
        $videoConverter->expects($this->once())->method('start');
        GeneralUtility::addInstance(LocalVideoConverter::class, $videoConverter);
        $processedFile = $this->resourceStorage->processFile($this->file, 'Video.CropScale', []);

        $this->assertTrue($processedFile->isProcessed());
        $this->assertEquals('mp4', $processedFile->getExtension());
        //$this->assertFileExists($processedFile->getForLocalProcessing(false));
    }

    public function testReprocessAsLongAsNotFinished()
    {
        $videoConverter = $this->createMock(LocalVideoConverter::class);
        $videoConverter->expects($this->exactly(2))->method('start');

        GeneralUtility::addInstance(LocalVideoConverter::class, $videoConverter);
        GeneralUtility::addInstance(LocalVideoConverter::class, $videoConverter);

        $path = $this->file->getForLocalProcessing(false);
        $contentBefore = $this->file->getContents();
        $processedFile1 = $this->resourceStorage->processFile($this->file, 'Video.CropScale', []);
        $processedFile2 = $this->resourceStorage->processFile($this->file, 'Video.CropScale', []);
        $this->assertEquals($path, $this->file->getForLocalProcessing(false));
        $this->assertEquals($contentBefore, $this->file->getContents());

        //$this->assertEquals($processedFile1->getIdentifier(), $processedFile2->getIdentifier());
        $this->assertTrue($processedFile1->isProcessed());
        $this->assertTrue($processedFile2->isProcessed());
        $this->assertEquals('mp4', $processedFile1->getExtension());
        $this->assertEquals('mp4', $processedFile2->getExtension());
        $this->assertSame($processedFile1->getOriginalFile(), $processedFile2->getOriginalFile());
        //$this->assertFileExists($processedFile2->getForLocalProcessing(false));

        $this->markTestIncomplete("it must be prevented that every call creates a new processed file.");
    }
}
