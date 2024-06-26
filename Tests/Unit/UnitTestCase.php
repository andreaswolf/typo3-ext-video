<?php

namespace Hn\Video\Tests\Unit;

use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Log\Logger;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

class UnitTestCase extends \Nimut\TestingFramework\TestCase\UnitTestCase
{
    /**
     * @var Logger|MockObject
     */
    protected $logger;

    /**
     * @var TypoScriptFrontendController|MockObject
     */
    protected $tsfe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = $this->createMock(Logger::class);
        $logManager = $this->createMock(LogManager::class);
        $logManager->method('getLogger')->willReturn($this->logger);

        $this->tsfe = $this->createMock(TypoScriptFrontendController::class);
        $GLOBALS['TSFE'] = $this->tsfe;

        GeneralUtility::setSingletonInstance(LogManager::class, $logManager);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        unset($GLOBALS['TSFE']);
    }
}
