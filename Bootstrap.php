<?php

/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */

class Shopware_Plugins_Frontend_SwagDemoDataEN_Bootstrap extends Shopware_Components_Plugin_Bootstrap
{
    public function getCapabilities()
    {
        return [
            'install' => true,
            'update' => true,
            'enable' => true,
            'secureUninstall' => false
        ];
    }

    public function install()
    {
        if (!$this->assertMinimumVersion('5.4.0')) {
            return false;
        }

        try {
            if ($this->isInstallAllowed()) {
                $this->importMedia();
                $this->importDatabase();
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        return true;
    }

    private function isInstallAllowed(): bool
    {
        if (PHP_SAPI === 'cli') {
            return true;
        }

        $session = $this->get('backendsession');
        if ($session->offsetExists('updateException')) {
            $session->offsetUnset('updateException');

            return true;
        }

        $session->offsetSet('updateException', true);
        throw new RuntimeException('All your data will be lost after the installation. Please start the installation again to confirm these changes.');
    }

    /**
     * Returns a marketing friendly name of the plugin.
     *
     * @return string
     */
    public function getLabel()
    {
        return 'Shopware 5 Demo Data EN';
    }

    /**
     * Returns the version of the plugin
     *
     * @return string|void
     * @throws Exception
     */
    public function getVersion()
    {
        $info = json_decode(file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'plugin.json'), true);
        if ($info) {
            return $info['currentVersion'];
        }

        throw new Exception('The plugin has an invalid version file.');
    }

    private function importMedia(): void
    {
        $rootDir = Shopware()->Container()->getParameter('kernel.root_dir');
        if (Shopware()->Container()->hasParameter('shopware.app.rootdir')) {
            $rootDir = Shopware()->Container()->getParameter('shopware.app.rootdir');
        }
        if (Shopware()->Container()->hasParameter('shopware.app.rootDir')) {
            $rootDir = Shopware()->Container()->getParameter('shopware.app.rootDir');
        }

        $source = __DIR__ . '/resources/media';
        $destination = $rootDir . '/media';

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                mkdir($destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName());
            } else {
                copy($item, $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName());
            }
        }
    }

    /**
     * Imports demo data scripts into the database
     *
     * @return void
     * @throws \Exception
     */
    private function importDatabase(): void
    {
        /** @var $connection \Doctrine\DBAL\Connection */
        $connection = $this->get('dbal_connection');

        $dataPaths = $this->getDataFilesList();

        $connection->executeUpdate('SET FOREIGN_KEY_CHECKS = 0;');
        try {
            foreach ($dataPaths as $dataFile) {
                $sql = file_get_contents($dataFile);
                $connection->executeUpdate($sql);
            }
        } catch (\Exception $exception) {
        }

        $connection->executeUpdate('SET FOREIGN_KEY_CHECKS = 1;');

        if ($exception) {
            throw $exception;
        }
    }

    /**
     * Returns an ordered list of data files, in the order in which they should be imported
     *
     * @return array
     */
    private function getDataFilesList(): array
    {
        $regexPattern = '/^([0-9]*)-.+\.sql/i';

        $dataPath = __DIR__ . '/resources/data/';

        $directoryIterator = new \DirectoryIterator($dataPath);
        $regex = new \RegexIterator($directoryIterator, $regexPattern, \RecursiveRegexIterator::GET_MATCH);

        $dataPaths = [];

        foreach ($regex as $result) {
            $dataPriority = intval($result['1']);
            $dataPaths[$dataPriority] = $dataPath . $result['0'];
        }

        ksort($dataPaths);

        return $dataPaths;
    }
}
