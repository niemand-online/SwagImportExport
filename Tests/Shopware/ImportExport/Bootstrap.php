<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Tests\Helper\CommandTestHelper;

include_once __DIR__ . '/../../../../../../../../../tests/Functional/bootstrap.php';

class ImportExportTestKernel extends TestKernel
{
    public static function start()
    {
        parent::start();

        if (!self::assertPlugin('SwagImportExport')) {
            echo "Plugin SwagImportExport is not active." . PHP_EOL;
            exit();
        }

        Shopware()->Loader()->registerNamespace('Tests\Helper', __DIR__ . '/../../Helper/');
        Shopware()->Loader()->registerNamespace('Tests\Shopware\ImportExport', __DIR__ . '/');
        Shopware()->Loader()->registerNamespace('Shopware\Subscriber', __DIR__ . '/../../../Subscriber/');
        Shopware()->Loader()->registerNamespace('Shopware\Components', __DIR__ . '/../../../Components/');
        Shopware()->Loader()->registerNamespace('Shopware\CustomModels', __DIR__ . '/../../../Models/');

        self::registerResources();
    }

    /**
     * Registers all necessary classes to the di container.
     */
    private static function registerResources()
    {
        Shopware()->Container()->set(
            'swag_import_export.tests.command_test_helper',
            new CommandTestHelper(Shopware()->Container()->get('models'))
        );
    }

    /**
     * @param string $name
     * @return boolean
     */
    private static function assertPlugin($name)
    {
        $sql = 'SELECT 1 FROM s_core_plugins WHERE name = ? AND active = 1';

        return (boolean) Shopware()->Container()->get('dbal_connection')->fetchColumn($sql, [$name]);
    }
}

ImportExportTestKernel::start();
