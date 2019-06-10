<?php

namespace Hn\Video\Tests\Unit\Converter;


use Doctrine\DBAL\Statement;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\RejectedPromise;
use GuzzleHttp\Psr7\Response;
use Hn\Video\Converter\CloudConvertConverter;
use Hn\Video\FormatRepository;
use Hn\Video\Processing\VideoProcessingTask;
use Hn\Video\Tests\Unit\UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Locking\LockFactory;
use TYPO3\CMS\Core\Locking\LockingStrategyInterface;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use function GuzzleHttp\Promise\queue;

class CloudConvertConverterTest extends UnitTestCase
{
    /** @var Connection|MockObject */
    protected $db;
    /** @var File|MockObject */
    protected $file;
    /** @var ProcessedFile|MockObject */
    protected $processedFile;
    /** @var Client|MockObject */
    protected $client;
    /** @var LockingStrategyInterface|MockObject */
    protected $lock;
    /** @var CloudConvertConverter */
    protected $converter;

    protected function setUp()
    {
        parent::setUp();

        $connectionPool = $this->createMock(ConnectionPool::class);
        GeneralUtility::addInstance(ConnectionPool::class, $connectionPool);
        $this->db = $this->createMock(Connection::class);
        $connectionPool->method('getConnectionForTable')->willReturn($this->db);

        $lockFactory = $this->createMock(LockFactory::class);
        GeneralUtility::setSingletonInstance(LockFactory::class, $lockFactory);
        $this->lock = $this->createMock(LockingStrategyInterface::class);
        $lockFactory->method('createLocker')->willReturn($this->lock);

        $this->file = $this->createMock(File::class);
        $this->processedFile = $this->createMock(ProcessedFile::class);
        $this->processedFile->method('getOriginalFile')->willReturn($this->file);
        $this->file->method('getUid')->willReturn(5);
        $this->file->method('getName')->willReturn('example.mp4');
        $this->file->method('getExtension')->willReturn('mp4');
        $this->file->method('getPublicUrl')->willReturn('fileadmin/example.mp4');

        $this->client = $this->createMock(Client::class);
        GeneralUtility::addInstance(Client::class, $this->client);

        $this->converter = new CloudConvertConverter('key');
    }

    protected function tearDown()
    {
        queue()->run(); // this is the only way to process all tasks
        GeneralUtility::purgeInstances();
        parent::tearDown();
    }

    public static function setUpBeforeClass()
    {
        queue()->disableShutdown();
    }

    protected function assertRequests(array ...$requests)
    {
        $this->client
            ->expects($this->exactly(count($requests)))
            ->method('requestAsync')
            ->willReturnArgument(...array_map(function (array $request) {
                return [$request[0], $request[1], $request[2] ? ['json' => $request[2]] : []];
            }, $requests))
            ->willReturnOnConsecutiveCalls(...array_map(function (array $request) {
                if ($request[3] instanceof \Exception) {
                    return new RejectedPromise($request[3]);
                } else {
                    return new FulfilledPromise($request[3]);
                }
            }, $requests));
    }

    protected function assertDbSelects(array ...$selects)
    {
        $this->db
            ->expects($this->exactly(count($selects)))
            ->method('select')
            ->withConsecutive(...array_map(function (array $select) {
                return [
                    ['uid', 'status', 'failed'],
                    CloudConvertConverter::DB_TABLE,
                    ['file' => 5, 'mode' => $select[0], 'options' => serialize($select[1])],
                ];
            }, $selects))
            ->willReturnOnConsecutiveCalls(...array_map(function (array $select) {
                return $this->createConfiguredMock(Statement::class, ['fetch' => $select[2]]);
            }, $selects));
    }

    /**
     * @expectedException \Hn\Video\Exception\ConversionException
     */
    public function testInfoFailure()
    {
        $task = new VideoProcessingTask($this->processedFile, []);
        $this->assertRequests(
            [
                'post',
                '/process',
                ['inputformat' => 'mp4', 'mode' => 'info'],
                new \Exception("something went wrong"),
            ]
        );

        $this->assertDbSelects(
            ['info', [], false]
        );

        $this->db->expects($this->once())->method('insert')->with(CloudConvertConverter::DB_TABLE, [
            'file' => 5,
            'mode' => 'info',
            'options' => serialize([]),
            'status' => serialize(['message' => "something went wrong", 'step' => 'exception']),
            'failed' => 1,
            'tstamp' => $_SERVER['REQUEST_TIME'],
            'crdate' => $_SERVER['REQUEST_TIME'],
        ]);

        $this->lock->expects($this->once())->method('acquire')->willReturn(true);
        $this->lock->expects($this->once())->method('release')->willReturn(true);

        $this->converter->getInfo($task);
        $this->assertFalse($task->isExecuted());
    }

    /**
     * @expectedException \Hn\Video\Exception\ConversionException
     */
    public function testGetInfoOversize()
    {
        $this->file->expects($this->atLeastOnce())->method('getSize')->willReturn(1024 * 1024 * 1024 * 5);
        $task = new VideoProcessingTask($this->processedFile, []);

        $this->assertRequests(
            [
                'post',
                '/process',
                ['mode' => 'info', 'inputformat' => 'mp4'],
                new Response(200, [], json_encode([
                    "url" => "//esta.infra.cloudconvert.com/process/some-id",
                    "id" => "some-id",
                    "host" => "esta.infra.cloudconvert.com",
                    "expires" => date('Y-m-d H:i:s', strtotime('+10 hours')),
                    "maxsize" => 1024,
                    "maxtime" => 1500,
                    "concurrent" => 5,
                ])),
            ]
        );

        $this->assertDbSelects(
            ['info', [], false]
        );

        $this->db->expects($this->once())->method('insert')->with(CloudConvertConverter::DB_TABLE, [
            'file' => 5,
            'mode' => 'info',
            'options' => serialize([]),
            'status' => serialize([
                'message' => "File to big for cloud convert. Max size is 1024 MB.",
                'step' => 'exception',
            ]),
            'failed' => 1,
            'tstamp' => $_SERVER['REQUEST_TIME'],
            'crdate' => $_SERVER['REQUEST_TIME'],
        ]);

        $this->lock->expects($this->once())->method('acquire')->willReturn(true);
        $this->lock->expects($this->once())->method('release')->willReturn(true);

        $this->converter->getInfo($task);
        $this->assertFalse($task->isExecuted());
    }

    public function testGetInfoFull()
    {
        $this->file->expects($this->atLeastOnce())->method('getSize')->willReturn(1024 * 1024 * 200);
        $task = new VideoProcessingTask($this->processedFile, []);

        $this->assertRequests(
            [
                'post',
                '/process',
                ['mode' => 'info', 'inputformat' => 'mp4'],
                new Response(200, [], json_encode([
                    "url" => "//esta.infra.cloudconvert.com/process/some-id",
                    "id" => "some-id",
                    "host" => "esta.infra.cloudconvert.com",
                    "expires" => date('Y-m-d H:i:s', strtotime('+10 hours')),
                    "maxsize" => 1024,
                    "maxtime" => 1500,
                    "concurrent" => 5,
                ])),
            ],
            [
                'post',
                '//esta.infra.cloudconvert.com/process/some-id',
                [
                    'mode' => 'info',
                    'input' => 'download',
                    'file' => 'http://www.example.com/fileadmin/example.mp4',
                    'filename' => 'example.mp4',
                ],
                new Response(200, [], json_encode($startResponse = [
                    "id" => "some-id",
                    "url" => "//esta.infra.cloudconvert.com/process/some-id",
                    "expire" => strtotime("+10 hours"),
                    "percent" => 0,
                    "message" => "Preparing process",
                    "step" => "input",
                    "starttime" => time(),
                    "input" => [
                        "type" => "download",
                    ],
                ])),
            ],
            [
                'getAsync',
                '//esta.infra.cloudconvert.com/process/some-id',
                null,
                new Response(200, [], json_encode($statusResponse = [
                    "id" => "some-id",
                    "url" => "//esta.infra.cloudconvert.com/process/some-id",
                    "expire" => strtotime("+10 hours"),
                    "percent" => 0,
                    "message" => "File initialized!",
                    "step" => "finished",
                    "starttime" => time(),
                    "input" => [
                        "type" => "download",
                    ],
                    "info" => [
                        "streams" => [
                            ['index' => 0, 'codec_type' => 'video'],
                            ['index' => 1, 'codec_type' => 'audio'],
                        ],
                    ],
                ])),
            ]
        );

        $this->assertDbSelects(
            ['info', [], false],
            ['info', [], ['uid' => 1, 'status' => serialize($startResponse), 'failed' => '0']]
        );

        $this->db->expects($this->once())->method('insert')->with(CloudConvertConverter::DB_TABLE, [
            'file' => 5,
            'mode' => 'info',
            'options' => serialize([]),
            'status' => serialize($startResponse),
            'failed' => 0,
            'tstamp' => $_SERVER['REQUEST_TIME'],
            'crdate' => $_SERVER['REQUEST_TIME'],
        ]);

        $this->db->expects($this->once())->method('update')->with(CloudConvertConverter::DB_TABLE, [
            'file' => 5,
            'mode' => 'info',
            'options' => serialize([]),
            'status' => serialize($statusResponse),
            'failed' => 0,
            'tstamp' => $_SERVER['REQUEST_TIME'],
        ], ['uid' => 1]);

        $this->lock->expects($this->exactly(2))->method('acquire')->willReturn(true);
        $this->lock->expects($this->exactly(2))->method('release')->willReturn(true);

        $this->assertNull($this->converter->getInfo($task));
        $this->assertEquals($statusResponse['info'], $this->converter->getInfo($task));
        $this->assertFalse($task->isExecuted());
    }

    public function testConvert()
    {
        $this->file->expects($this->atLeastOnce())->method('getSize')->willReturn(1024 * 1024 * 200);
        $task = new VideoProcessingTask($this->processedFile, []);

        $statusResponse = [
            "id" => "some-id",
            "url" => "//esta.infra.cloudconvert.com/process/some-id",
            "expire" => strtotime("+10 hours"),
            "percent" => 0,
            "message" => "File initialized!",
            "step" => "finished",
            "starttime" => time(),
            "input" => [
                "type" => "download",
            ],
            "info" => [
                "streams" => [
                    ['index' => 0, 'codec_type' => 'video'],
                    ['index' => 1, 'codec_type' => 'audio'],
                ],
            ],
        ];

        $this->assertRequests(
            [
                'post',
                '/process',
                [
                    'outputformat' => 'mp4',
                    'inputformat' => 'mp4',
                ],
                new Response(200, [], json_encode([
                    "url" => "//esta.infra.cloudconvert.com/process/some-id",
                    "id" => "some-id",
                    "host" => "esta.infra.cloudconvert.com",
                    "expires" => date('Y-m-d H:i:s', strtotime('+10 hours')),
                    "maxsize" => 1024,
                    "maxtime" => 1500,
                    "concurrent" => 5,
                ])),
            ],
            [
                'postAsync',
                '//esta.infra.cloudconvert.com/process/some-id',
                [
                    'outputformat' => 'mp4',
                    'input' => 'download',
                    'file' => 'http://www.example.com/fileadmin/example.mp4',
                    'filename' => 'example.mp4',
                    'converteroptions' => [
                        'command' => $command = "-i {INPUTFILE} -c:v libx264 -y {OUTPUTFILE}",
                    ],
                ],
                new Response(200, [], json_encode($startResponse = [
                    "id" => "some-id",
                    "url" => "//esta.infra.cloudconvert.com/process/some-id",
                    "expire" => strtotime("+10 hours"),
                    "percent" => 0,
                    "message" => "Preparing process",
                    "step" => "input",
                    "starttime" => time(),
                    "input" => [
                        "type" => "download",
                    ],
                ])),
            ]
        );

        $formatRepository = $this->createMock(FormatRepository::class);
        $formatRepository->expects($this->once())
            ->method('buildParameterString')
            ->with('{INPUTFILE}', '{OUTPUTFILE}', [], [['index' => 0, 'codec_type' => 'video'], ['index' => 1, 'codec_type' => 'audio']])
            ->willReturn('-i {INPUTFILE} -c:v libx264 -y {OUTPUTFILE}');
        $formatRepository->expects($this->atLeastOnce())
            ->method('findFormatDefinition')
            ->with([])
            ->willReturn(['fileExtension' => 'mp4']);
        GeneralUtility::setSingletonInstance(FormatRepository::class, $formatRepository);

        $this->assertDbSelects(
            ['info', [], ['uid' => 1, 'status' => serialize($statusResponse), 'failed' => '0']],
            ['convert', ['command' => $command], false]
        );

        $this->db->expects($this->once())->method('insert')->with(CloudConvertConverter::DB_TABLE, [
            'file' => 5,
            'mode' => 'convert',
            'options' => serialize(['command' => $command]),
            'status' => serialize($startResponse),
            'failed' => 0,
            'tstamp' => $_SERVER['REQUEST_TIME'],
            'crdate' => $_SERVER['REQUEST_TIME'],
        ]);

        $this->lock->expects($this->exactly(1))->method('acquire')->willReturn(true);
        $this->lock->expects($this->exactly(1))->method('release')->willReturn(true);

        $this->converter->process($task);
        $this->assertFalse($task->isExecuted());
    }

    public function testDownload()
    {
        $task = new VideoProcessingTask($this->processedFile, []);

        $statusResponse = [
            "id" => "some-id",
            "url" => "//esta.infra.cloudconvert.com/process/some-id",
            "expire" => strtotime("+10 hours"),
            "percent" => 0,
            "message" => "File initialized!",
            "step" => "finished",
            "starttime" => time(),
            "input" => [
                "type" => "download",
            ],
            "info" => [
                "streams" => [
                    ['index' => 0, 'codec_type' => 'video'],
                    ['index' => 1, 'codec_type' => 'audio'],
                ],
            ],
        ];

        $this->assertRequests(
            [
                'get',
                '//esta.infra.cloudconvert.com/process/some-id',
                false,
                new Response(200, [], json_encode($startResponse = [
                    "id" => "some-id",
                    "url" => "//esta.infra.cloudconvert.com/process/some-id",
                    "expire" => strtotime("+10 hours"),
                    "percent" => 0,
                    "message" => "Finished",
                    "step" => "finished",
                    "starttime" => time(),
                    "input" => [
                        "type" => "download",
                    ],
                    "output" => [
                        "url" => "//esta.infra.cloudconvert.com/process/some-id/file.mp4",
                    ],
                ])),
            ]
        );
        $this->client->expects($this->once())
            ->method('__call')
            ->with('get' /* i don't know how to test tempname */)
            ->willReturn(new Response(200, [], 'hello'));

        $formatRepository = $this->createMock(FormatRepository::class);
        $formatRepository->expects($this->once())
            ->method('buildParameterString')
            ->with('{INPUTFILE}', '{OUTPUTFILE}', [], [['index' => 0, 'codec_type' => 'video'], ['index' => 1, 'codec_type' => 'audio']])
            ->willReturn($command = "-i {INPUTFILE} -c:v libx264 {OUTPUTFILE}");
        $formatRepository->expects($this->atLeastOnce())
            ->method('findFormatDefinition')
            ->with([])
            ->willReturn(['fileExtension' => 'mp4']);
        GeneralUtility::setSingletonInstance(FormatRepository::class, $formatRepository);

        $this->assertDbSelects(
            ['info', [], ['uid' => 1, 'status' => serialize($statusResponse), 'failed' => '0']],
            ['convert', ['command' => $command], ['uid' => 2, 'status' => serialize(['step' => 'convert'] + $startResponse), 'failed' => '0']]
        );

        $this->lock->expects($this->exactly(1))->method('acquire')->willReturn(true);
        $this->lock->expects($this->exactly(1))->method('release')->willReturn(true);

        $this->processedFile->expects($this->once())->method('setName')->withAnyParameters();
        $this->processedFile->expects($this->once())->method('updateProperties')->withAnyParameters();
        $this->processedFile->expects($this->once())->method('updateWithLocalFile')->withAnyParameters();

        $this->converter->process($task);
        $this->assertTrue($task->isExecuted());
        $this->assertTrue($task->isSuccessful());
    }
}
