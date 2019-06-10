<?php

namespace Hn\Video\Tests\Unit;


use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class UnitTestCase extends \Nimut\TestingFramework\TestCase\UnitTestCase
{
    /**
     * @var LoggerInterface|MockObject
     */
    protected $logger;

    protected function setUp()
    {
        parent::setUp();

        $this->logger = $this->createMock(LoggerInterface::class);

        $logManager = $this->createMock(LogManager::class);
        $logManager->method('getLogger')->willReturn($this->logger);

        GeneralUtility::setSingletonInstance(LogManager::class, $logManager);
    }
}
