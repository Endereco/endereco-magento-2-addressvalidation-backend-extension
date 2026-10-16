<?php
declare(strict_types=1);

/**
 * Unit test bootstrap.
 *
 * Like phpstan, the tests take Magento's classes from the installation that
 * fetch_shops.sh downloads into shops/ - the module's own vendor/ (from
 * composer-phpunit.json) only holds PHPUnit and the module autoloader.
 */

use Magento\Framework\Code\Generator\Io;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\TestFramework\Unit\Autoloader\FactoryGenerator;
use Magento\Framework\TestFramework\Unit\Autoloader\GeneratedClassesAutoloader;
use Magento\Framework\TestFramework\Unit\Autoloader\ProxyGenerator;

$moduleRoot = dirname(__DIR__, 2);

$moduleLoader = require $moduleRoot . '/vendor/autoload.php';

$magentoAutoload = $moduleRoot . '/shops/2.4.8/vendor/autoload.php';
if (!is_file($magentoAutoload)) {
    fwrite(STDERR, "Magento not found in shops/2.4.8 - run ./fetch_shops.sh 2.4.8 first.\n");
    exit(1);
}
require $magentoAutoload;

// Composer prepends every class loader it registers, so Magento's loader would
// now win. Put the module's loader (PHPUnit, module classes) first again.
$moduleLoader->unregister();
$moduleLoader->register(true);

// Factories and proxies (e.g. AddressValidationFactory) normally live in
// generated/, which does not exist here - generate them on demand, the same
// way Magento's own unit test bootstrap does.
$generatedDir = sys_get_temp_dir() . '/parc-addressvalidation-unit/generated';
spl_autoload_register([
    new GeneratedClassesAutoloader(
        [new FactoryGenerator(), new ProxyGenerator()],
        new Io(new File(), $generatedDir)
    ),
    'load',
]);
