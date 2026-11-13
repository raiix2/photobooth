<?php

namespace Photobooth\Service;

use Photobooth\Enum\FolderEnum;
use Photobooth\Utility\DatabaseUtility;
use Photobooth\Logger\NamedLogger;

/**
 * Class JsonDatabaseManagerService
 *
 * Manages the json database containing both links to image and video if avilable, including adding and deleting files.
 */
class JsonDatabaseManagerService
{
    private NamedLogger $logger;
    public string $databaseFile = '';
    public string $imageDirectory = '';
    public string $videoDirectory = '';

    public function __construct()
    {
        $this->config = ConfigurationService::getInstance()->getConfiguration();
        $this->logger = LoggerService::getInstance()->getLogger('main');
        $this->databaseFile = FolderEnum::DATA->absolute() . DIRECTORY_SEPARATOR . 'db.json';
        $this->imageDirectory = FolderEnum::IMAGES->absolute();
        $this->videoDirectory = FolderEnum::VIDEOS->absolute();
    }

    /**
     * Get the list of files from the database file.
     */
    public function getContentFromDB(): array
    {
        // check if the database file is defined and non-empty
        if (!isset($this->databaseFile) || empty($this->databaseFile)) {
            throw new \Exception('Database not defined.');
        }

        try {
            // get data from database
            if (!file_exists($this->databaseFile)) {
                file_put_contents($this->databaseFile, "[]");
            }
            $data = file_get_contents($this->databaseFile);

            if ($data === false) {
                throw new \Exception('Failed to read file: ' . $this->databaseFile);
            }
            $decodedData = json_decode($data, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Failed to decode JSON: ' . json_last_error_msg());
            }

            return is_array($decodedData) ? $decodedData : [];
        } catch (\Exception $e) {
            // do nothing
        }

        return [];
    }

    public function appendImageUrlToDB(string $content, string $imageUrl): void
    {
        $this->appendContentToDB($content, $imageUrl, null);   
    }

    public function appendVideoUrlToDB(string $content, string $videoUrl): void
    {
        $this->appendContentToDB($content, null, $videoUrl);   
    }

    /**
     * Append a new content by name to the database file.
     */
    public function appendContentToDB(string $content, ?string $imageUrl, ?string $videoUrl): void
    {
        if (!$content) {
            throw new \Exception('Invalid content.');
        }

        // check if the database file is defined and non-empty
        if (!isset($this->databaseFile) || empty($this->databaseFile)) {
            throw new \Exception('Database not defined.');
        }

        $currContent = $this->getContentFromDB();

        if (!array_key_exists($content, $currContent)) {
            if(!is_null($imageUrl)) {
                $currContent[$content][DatabaseUtility::IMAGE_URL_KEY] = $imageUrl;
            }
            if(!is_null($videoUrl)) {
                $currContent[$content][DatabaseUtility::VIDEO_URL_KEY] = $videoUrl;
            }

            $encoded = json_encode($currContent);
            if ($encoded === false) {
            $this->logger->debug('encode failed : ' . json_last_error_msg());
                throw new \Exception('Failed to encode database content to JSON: ' . json_last_error_msg());
            }
            if (file_put_contents($this->databaseFile, $encoded) === false) {
                throw new \Exception('Failed to write database file: ' . $this->databaseFile);
            }
        }
    }

    /**
     * Delete an entry by name from the database file.
     */
    public function deleteContentFromDB(string $content): void
    {
        if (!$content) {
            throw new \Exception('Invalid filename.');
        }

        // check if the database file is defined and non-empty
        if (!isset($this->databaseFile) || empty($this->databaseFile)) {
            throw new \Exception('Database not defined.');
        }
        $currContent = $this->getContentFromDB();

        if (in_array($content, $currContent)) {
            unset($currContent[array_search($content, $currContent)]);
            $encoded = json_encode(array_values($currContent));
            if ($encoded === false) {
                throw new \Exception('Failed to encode database content to JSON: ' . json_last_error_msg());
            }
            if (file_put_contents($this->databaseFile, $encoded) === false) {
                throw new \Exception('Failed to write database file: ' . $this->databaseFile);
            }
        }

        if (file_exists($this->databaseFile) && empty($currContent)) {
            unlink($this->databaseFile);
        }
    }

    /**
     * Check if an content exists in the database file.
     */
    public function isInDB(string $content): bool
    {
        if (!$content) {
            throw new \Exception('Invalid filename.');
        }

        // check if the database file is defined and non-empty
        if (!isset($this->databaseFile) || empty($this->databaseFile)) {
            throw new \Exception('Database not defined.');
        }

        $currContent = $this->getContentFromDB();

        return array_key_exists($content, $currContent);
    }

    /**
     * Returns the size of the database file in bytes.
     */
    public function getDBSize(): int
    {
        if (file_exists($this->databaseFile)) {
            return (int) filesize($this->databaseFile);
        }
        return 0;
    }

    public static function getInstance(): self
    {
        if (!isset($GLOBALS[self::class])) {
            $GLOBALS[self::class] = new self();
        }

        return $GLOBALS[self::class];
    }
}
