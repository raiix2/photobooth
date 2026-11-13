<?php

namespace Photobooth\Service;

use Photobooth\Logger\NamedLogger;
use Photobooth\Utility\PathUtility;

class GoogleDriveService
{
    protected array $config;
    protected NamedLogger $logger;
    protected string $storageFolder;

    public function __construct()
    {
        $this->config = ConfigurationService::getInstance()->getConfiguration()['google_drive'];
        $this->logger = LoggerService::getInstance()->getLogger('main');
    }

    public function executeCmd(string $cmd, ?string $fileName = null): array
    {
        $storageFilePath = sprintf('Photobooth:%s', $this->config['baseFolder']);
        $cmd = sprintf('rclone %s %s/%s', $cmd, $storageFilePath, (!is_null($fileName) ? $fileName : ""));
        exec($cmd, $output, $returnValue);
        $this->logger->debug('Cmd ' . $cmd);

        if ($returnValue != 0) {
            // Handle the exception
            $data = [
                'error' => 'Upload picture command returned an error code',
                'cmd' => $cmd,
                'returnValue' => $returnValue,
                'output' => json_encode($output),
            ];
            $this->logger->error('Upload picture command returned an error code', $data);
            echo json_encode($data);
            die();
        }

        return [$output, $returnValue];
        
    }

    public function fileExists(string $fileName): bool
    {   [$output, $returnValue] = $this->executeCmd('lsf', $fileName);
        return $returnValue === 0;
    }

    public function write(string $srcPath): string
    {
        $fileName = basename($srcPath);
        $cmd = sprintf(
            'copy %s', $srcPath
        );

        $this->executeCmd($cmd);
        $this->logger->debug('Uploaded ' . $fileName);

        [$output, $returnValue] = $this->executeCmd('link', $fileName);

        return $output[0];
    }

    public function delete(string $fileName): void
    {
        $this->logger->debug('Deleting: ' . $fileName);
        if ($this->fileExists($fileName)) {
            $this->executeCmd('delete', $fileName);
        }
    }

    public static function getInstance(): self
    {
        if (!isset($GLOBALS[self::class])) {
            $GLOBALS[self::class] = new self();
        }

        return $GLOBALS[self::class];
    }
}
