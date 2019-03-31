<?php

namespace Hn\HauptsacheVideo\Converter;


use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\RejectedPromise;
use GuzzleHttp\Psr7\Response;
use function GuzzleHttp\Psr7\try_fopen;
use function GuzzleHttp\Psr7\uri_for;
use GuzzleHttp\Psr7\UriResolver;
use Hn\HauptsacheVideo\Exception\ConversionException;
use Hn\HauptsacheVideo\FormatRepository;
use Hn\HauptsacheVideo\Processing\VideoProcessingTask;
use Psr\Http\Message\UriInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Locking;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class CloudConvertConverter implements VideoConverterInterface
{
    const DB_TABLE = 'tx_hauptsachevideo_cloudconvert_process';

    /**
     * @var \GuzzleHttp\Client
     */
    private $guzzle;

    /**
     * @var Locking\LockFactory
     */
    private $lockFactory;

    /**
     * @var \TYPO3\CMS\Core\Database\Connection
     */
    protected $db;

    /**
     * This decides if this typo3 instance is publicly available.
     *
     * if defined
     *  - files are downloaded by cloudconvert so no blocking php process is required
     *  - callback urls can be used to notify about finished tasks
     * if null
     *  - files will be uploaded by php which blocks processes and therefor won't be done during requests.
     *  - polling has to be used to figure out if the process is done
     *
     * @var UriInterface|null
     */
    private $baseUrl;

    /**
     * @param string $apiKey
     * @param string|null $baseUrl
     */
    public function __construct(string $apiKey, string $baseUrl = null)
    {
        $this->guzzle = GeneralUtility::makeInstance(\GuzzleHttp\Client::class, [
            'base_uri' => 'https://api.cloudconvert.com/',
            'timeout' => 5.0,
            'headers' => [
                'User-Agent' => 'hauptsache_video typo3 extension',
                'Authorization' => 'Bearer ' . $apiKey,
            ],
        ]);
        $this->lockFactory = GeneralUtility::makeInstance(Locking\LockFactory::class);
        $this->db = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable(self::DB_TABLE);
        $this->baseUrl = $baseUrl ? uri_for($baseUrl) : null;
    }

    public function isPublic(): bool
    {
        return $this->baseUrl !== null;
    }

    public function start(VideoProcessingTask $task): void
    {
        // if the instance is public than the process can start immediately.
        if ($this->isPublic()) {
            $this->process($task);
        }
    }

    public function process(VideoProcessingTask $task): void
    {
        $formatRepository = GeneralUtility::makeInstance(FormatRepository::class);
        $definition = $formatRepository->findFormatDefinition($task->getConfiguration());
        if ($definition === null) {
            throw new ConversionException("Can't find format for: " . print_r($task->getConfiguration(), true));
        }

        $info = $this->getInfo($task);
        if ($info === null) {
            return;
        }

        $buildParameters = $formatRepository->buildParameters($task->getConfiguration(), $info['streams']);
        $parameterStr = implode(' ', array_map('escapeshellarg', $buildParameters));
        $command = "-i {INPUTFILE} $parameterStr {OUTPUTFILE}";
        $result = $this->pollProcess($task, 'convert', ["command" => $command]);
        if ($result === null) {
            return;
        }

        if ($result['step'] !== 'finished' || !isset($result['output']['url'])) {
            return;
        }

        // actually download the file
        // TODO i need to make sure only one person is doing this
        // but adding a lock here would leave me with the strange situation
        // in which i can't easily access the file some other process downloaded.
        // i should just make sure process creates a lock and be done with it
        $tempFilename = tempnam(sys_get_temp_dir(), 'video_result');
        $this->guzzle->get($result['output']['url'], [
            'sink' => $tempFilename,
            'timeout' => $task->getSourceFile()->getSize() / 1024 / 1024,
        ]);

        $processedFile = $task->getTargetFile();
        $processedFile->setName($task->getTargetFilename());
        $processedFile->updateProperties([
            'checksum' => $task->getConfigurationChecksum(),

            // TODO figure out the real resolution
            'width' => intval($task->getConfiguration()['width'] ?? 0),
            'height' => intval($task->getConfiguration()['height'] ?? 0),
        ]);

        $processedFile->updateWithLocalFile($tempFilename);
        $task->setExecuted(true);
    }

    public function getInfo(VideoProcessingTask $task): ?array
    {
        $result = $this->pollProcess($task, 'info');
        if ($result['step'] !== 'finished' || !isset($result['info'])) {
            return null;
        }

        return $result['info'];
    }

    protected function pollProcess(VideoProcessingTask $task, string $mode, array $converteroptions = []): ?array
    {
        $serializedOptions = serialize($converteroptions);
        $serializedOptionsLength = strlen($serializedOptions);
        if ($serializedOptionsLength > 767) {
            $msg = "The options passed to create this job were $serializedOptionsLength bytes long.";
            $msg .= " There is a limit of 767 bytes for the mysql unique key to work. Sorry.";
            throw new \RuntimeException($msg);
        }

        $statement = $this->db->select(
            ['uid', 'status', 'failed'],
            self::DB_TABLE, [
            'file' => $task->getSourceFile()->getUid(),
            'mode' => $mode,
            'options' => $serializedOptions,
        ]);

        $info = $statement->fetch() ?: [];
        if (isset($info['status'])) {
            $info['status'] = unserialize($info['status']);
        }

        if ($info['failed'] ?? false) {
            throw new ConversionException("Process error: " . print_r($info, true), 1554038915);
        }

        if (isset($info['status']['step']) && $info['status']['step'] === 'finished') {
            return $info['status'];
        }

        // TODO check expired

        try {
            $identifier = $task->getSourceFile()->getSha1() . $mode . sha1($serializedOptions);
            $lockMode = Locking\LockingStrategyInterface::LOCK_CAPABILITY_EXCLUSIVE | Locking\LockingStrategyInterface::LOCK_CAPABILITY_NOBLOCK;
            $lock = $this->lockFactory->createLocker($identifier, $lockMode);
            if (!$lock->acquire($lockMode)) {
                // if another process is working on this conversion than just stop right here
                return null;
            }

            // TODO handle edge case in which the lock could be aquired just after the last process finished
            // this would result in us creating another process which we don't want

        } catch (Locking\Exception $e) {
            // it seems that the noblock implementation is not really tested
            // passing noblock to acquire will not block on supported platforms
            // and will block on unsuported platforms while throwing this exception afterwards
            // i simply ignore this problem for now and don't do anything without a lock
            return null;
        }

        if (isset($info['status'])) {
            $promise = $this->updateCloudConvertProcess($info['status']['url']);
        } else {
            $promise = $this->createCloudConvertProcess($task, $mode, $converteroptions);
        }

        $promise->then(function (array $response) {
            // https://cloudconvert.com/api/conversions#status
            // TODO test this step check
            if (in_array($response['step'], ['input', 'wait', 'convert', 'output', 'finished'])) {
                return $response;
            }

            if ($response['step'] === 'error') {
                throw new ConversionException("Conversion failed. Message: {$response['message']}");
            }

            throw new ConversionException("Unknown step: {$response['step']}. Message: {$response['message']}");
        });

        // save the result from the promise
        $promise = $promise->then(
            function (array $response) use ($task, $mode, $serializedOptions, $info, $lock) {
                $values = [
                    'file' => $task->getSourceFile()->getUid(),
                    'mode' => $mode,
                    'options' => $serializedOptions,
                    'status' => serialize($response),
                    'failed' => 0,
                ];
                if (isset($info['uid'])) {
                    $this->db->update(self::DB_TABLE, $values, ['uid' => $info['uid']]);
                } else {
                    $this->db->insert(self::DB_TABLE, $values);
                }
                $lock->release();
                return $response;
            },
            function (\Exception $error) use ($task, $mode, $serializedOptions, $info, $lock) {
                if (
                    $error instanceof ServerException
                    && $error->hasResponse()
                    && $error->getResponse()->getStatusCode() === 503
                ) {
                    // TODO logging
                    // just ignore that for now
                    return null;
                }

                $values = [
                    'file' => $task->getSourceFile()->getUid(),
                    'mode' => $mode,
                    'options' => $serializedOptions,
                    'status' => serialize(['message' => $error->getMessage(), 'step' => 'exception']),
                    'failed' => 1,
                ];
                if (isset($info['uid'])) {
                    $this->db->update(self::DB_TABLE, $values, ['uid' => $info['uid']]);
                } else {
                    $this->db->insert(self::DB_TABLE, $values);
                }
                $lock->release();
                return new RejectedPromise(new ConversionException("Communication Error", 1554565455, $error));
            }
        );

        return $promise->wait();
    }

    private function getJsonDecodeResponseClosure(): \Closure
    {
        return static function (Response $response) {
            $body = json_decode($response->getBody(), true);
            if (json_last_error()) {
                throw new ConversionException(json_last_error_msg());
            }

            return $body;
        };
    }

    protected function createCloudConvertProcess(VideoProcessingTask $task, string $mode, array $converteroptions = []): PromiseInterface
    {
        $createOptions = [];
        $startOptions = [];

        $createOptions['inputformat'] = $task->getSourceFile()->getExtension();

        if ($mode === 'convert') {
            $createOptions['outputformat'] = $task->getTargetFileExtension();
            $startOptions['outputformat'] = $task->getTargetFileExtension();
            $startOptions['converteroptions'] = $converteroptions;
        } else {
            $createOptions['mode'] = $mode;
            $startOptions['mode'] = $mode;
        }

        if ($this->isPublic()) {
            $startOptions += [
                'input' => 'download',
                // TODO ensure that this file has the domain prepended
                'file' => UriResolver::resolve($this->baseUrl, uri_for($task->getSourceFile()->getPublicUrl())),
                'filename' => $task->getSourceFile()->getName(),
            ];
        } else {
            $startOptions += [
                'input' => 'upload',
                'filename' => $task->getSourceFile()->getName(),
            ];
        }

        return $this->guzzle->postAsync('/process', ['json' => $createOptions])
            ->then($this->getJsonDecodeResponseClosure())
            ->then(function (array $response) use ($task, $mode, $startOptions) {
                // TODO maxconcurrent? is there anything i should check there?

                $sizeInMb = ceil($task->getSourceFile()->getSize() / 1024 / 1024);
                if ($sizeInMb > $response['maxsize']) {
                    $msg = "File to big for cloud convert. Max size is {$response['maxsize']} MB.";
                    throw new ConversionException($msg);
                }

                return $this->guzzle->postAsync($response['url'], ['json' => $startOptions])
                    ->then($this->getJsonDecodeResponseClosure());
            })
            // if cloud convert gives us an upload url, than upload the file there
            ->then(function (array $response) use ($task) {
                if (empty($response['upload']['url'])) {
                    return $response;
                }

                // upload the file if necessary

                $resource = try_fopen($task->getSourceFile()->getForLocalProcessing(false), 'rb');
                $uploadUrl = rtrim($response['upload']['url'], '/') . '/' . $task->getSourceFile()->getName();

                $uploadOptions = [
                    'body' => $resource,
                    'timeout' => fstat($resource)['size'] / 1024 / 1024 // expect at least 1 mb/s
                ];

                return $this->guzzle->putAsync($uploadUrl, $uploadOptions)
                    ->then($this->getJsonDecodeResponseClosure())
                    ->then(function (array $uploadResponse) use ($task, $response) {
                        $expectedSize = $task->getSourceFile()->getSize();
                        $uploadedSize = $uploadResponse['size'];
                        if ($uploadedSize !== $expectedSize) {
                            $msg = "The uploaded filesize mismatches, expected $expectedSize but got $uploadedSize.";
                            throw new ConversionException($msg);
                        }

                        // return the last response since it contains the process status information
                        // the actual upload does not contain this information
                        return $response;
                    });
            });
    }

    protected function updateCloudConvertProcess(string $processUrl): PromiseInterface
    {
        return $this->guzzle->getAsync($processUrl)
            ->then($this->getJsonDecodeResponseClosure());
    }
}
