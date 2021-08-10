<?php

# Includes the autoloader for libraries installed with composer
require __DIR__ . '/vendor/autoload.php';

use Psr\Http\Message\ServerRequestInterface;

use Google\CloudFunctions\CloudEvent;
use Frc\Satis\Builder\JsonBuilder;
use Composer\Satis\Builder;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;

use Google\Cloud\Storage\StorageClient;


function satis_build(CloudEvent $cloudevent): void
//function satis_build(ServerRequestInterface $request)
{


//    $log = fopen(getenv('LOGGER_OUTPUT') ?: 'php://stderr', 'wb');
    $data = $cloudevent->getData();
/*    fwrite($log, "Event: " . $cloudevent->getId() . PHP_EOL);
    fwrite($log, "Event Type: " . $cloudevent->getType() . PHP_EOL);
    fwrite($log, "Bucket: " . $data['bucket'] . PHP_EOL);
    fwrite($log, "File: " . $data['name'] . PHP_EOL);
    fwrite($log, "Metageneration: " . $data['metageneration'] . PHP_EOL);
    fwrite($log, "Created: " . $data['timeCreated'] . PHP_EOL);
    fwrite($log, "Updated: " . $data['updated'] . PHP_EOL);*/

//    error_log(print_r($data, 1));

    $file = $data['name'];

    if ( pathinfo($file, PATHINFO_EXTENSION) != 'zip' ) {
       exit;
    }

    /**
     * Make Cloud Storage discoverable
     */
    $storage = new StorageClient();
    $storage->registerStreamWrapper();

    $filesystem = new Filesystem();

    /**********
     * Prepare temp build folder
     *********/

    try {
        $filesystem->mirror(getenv('TO_FOLDER'), getenv('SATIS_OUTPUT'));
    } catch (IOExceptionInterface $exception) {
        echo "An error occurred while moving your directory at ".$exception->getPath();
    }

    /**********
     * Run JSON builder
     *********/

    $JsonBuilderArguments = [
        'command' => "build",
        '--from' => getenv('FROM_FOLDER'),
        '--external' => getenv('EXTERNAL_DEPENDENCIES'),
        '--output' => getenv('JSON_OUTPUT'),
        '--name' => getenv('REPOSITORY_NAME'),
        '--homepage' => getenv('HOMEPAGE')
    ];

    $JsonInput = new ArrayInput($JsonBuilderArguments);
    $JsonBuilder = new Frc\Satis\Console\Application();
    $JsonBuilder->setAutoExit(false);
    $JsonBuilder->run($JsonInput, new Symfony\Component\Console\Output\NullOutput);

    /**********
     * Run Satis
     **********/

    $SatisArguments = [
        'command' => 'build',
        'output-dir' => getenv('SATIS_OUTPUT'),
        'file' => getenv('JSON_OUTPUT')
    ];

    $SatisInput = new ArrayInput($SatisArguments);
    $Satis = new Composer\Satis\Console\Application();
    $Satis->setAutoExit(false);
    $Satis->run($SatisInput);


    /*********
     * Move files to Cloud Storage
     */

    try {
        $filesystem->mirror(getenv('SATIS_OUTPUT'), getenv('TO_FOLDER'));
    } catch (IOExceptionInterface $exception) {
        echo "An error occurred while moving your directory at ".$exception->getPath();
    }

    /*******
     * Remove temp files
     */
//    try {
//        $filesystem->remove(getenv('SATIS_OUTPUT'));
//    } catch (IOExceptionInterface $exception) {
//        echo "An error occurred while removing your directory at ".$exception->getPath();
//    }
}
