<?php

/** @var array $config */

require_once '../lib/boot.php';

use Photobooth\Image;
use Photobooth\Collage;
use Photobooth\Enum\FolderEnum;
use Photobooth\Service\DatabaseManagerService;
use Photobooth\Service\LoggerService;
use Photobooth\Service\RemoteStorageService;
use Photobooth\Utility\ImageUtility;
use Photobooth\Utility\PathUtility;
use Symfony\Component\Filesystem\Filesystem;

header('Content-Type: application/json');

checkCsrfOrFail($_POST);

$logger = LoggerService::getInstance()->getLogger('main');
$logger->debug(basename($_SERVER['PHP_SELF']));

$database = DatabaseManagerService::getInstance();
$remoteStorage = RemoteStorageService::getInstance();

$processor = null;

try {
    if (empty($_POST['file'])) {
        throw new \Exception('No file provided');
    }

    $vars['fileName'] = basename((string)$_POST['file']);
    if ($vars['fileName'] === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $vars['fileName'])) {
        throw new \Exception('Invalid file name provided');
    }

    if (!isset($_POST['style']) || !in_array($_POST['style'], ['collage', 'custom'])) {
        throw new \Exception('Invalid or missing style parameter');
    }

    if (isset($_POST['collageLayout'])) {
        $config['collage']['layout'] = $_POST['collageLayout'];
    }

    $limitData = Collage::calculateLimit($config['collage'], $logger);
    $logger->debug('calculateLimit');

    $config['collage']['limit'] = $limitData['limit'];
    $config['collage']['placeholder'] = $limitData['placeholderEnabled'];

    $vars['style'] = $_POST['style'];

} catch (\Exception $e) {
    // Handle the exception
    $logger->error($e->getMessage(), $_POST);
    echo json_encode(['error' => $e->getMessage()]);
    die();
}

$vars['isCollage'] = $_POST['style'] === 'collage';

$vars['srcVideos'] = [];
$vars['srcVideos'][] = $vars['fileName'];


try {
    $vars['tmpFile'] = FolderEnum::TEMP->absolute() . DIRECTORY_SEPARATOR . $vars['fileName'];

    $vars['collageSrcVideoPaths'] = Collage::getCollageVideoFiles($config['collage'], $vars['tmpFile']);

    $vars['resultFile'] = FolderEnum::VIDEOS->absolute() . DIRECTORY_SEPARATOR . substr($vars['fileName'], 0, -4) . ".mp4";
    [$cmd, $tmpFiles] = Collage::createCollageVideo($config, $vars['collageSrcVideoPaths'], $vars['resultFile']);
    if (is_null($cmd)) {
        throw new \Exception('Error creating collage video.');
    }

    try {
        $cmd .= ' 2>&1'; //Redirect stderr to stdout, otherwise error messages get lost.

        exec($cmd, $output, $returnValue);

        $logger->debug('exec: ' . $cmd);
        if ($returnValue) {
            $errorData = [
                'error' => 'createCollageVideo returned with an error code',
                'cmd' => $cmd,
                'returnValue' => $returnValue,
                'output' => $output,
            ];
            $logger->error('createCollageVideo returned with an error code', $errorData);
        }
    } catch (\Exception $e) {
        $logger->error($e->getMessage());
    }
    finally {
        $filesystem = (new Filesystem());
        foreach ($tmpFiles as $tmpFile) {
            $filesystem->remove($tmpFile);
        }
    }
} catch (\Exception $e) {
    // Handle the exception
    $logger->error($e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
    die();
}

$data = [
    'file' => $vars['fileName'],
    'images' => $vars['srcVideos'],
];
$logger->debug('Video collage effects applied', $data);
echo json_encode($data);
exit();
