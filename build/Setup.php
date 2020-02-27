<?php
/**
 * Created by PhpStorm.
 * User: User
 * Date: 6/20/2018
 * Time: 12:12 AM
 */

namespace Npf\Build;

final class Setup
{
    /**
     * Composer Install Entry Point
     */
    final static public function Install()
    {
        static::composerUpdate();
        static::buildFolder();
        static::initialConfig();
        static::initialException();
        static::initialApp();
        static::initialModel();
        static::initialModule();
        static::initialPublic();
        static::initialTemplate();
        static::initialIgnoreGit();
    }

    /**
     * Composer Update Entry Point
     */
    final static public function Update()
    {
        static::initialIgnoreGit();
        static::updateConfig();
        static::initialModule();
        static::initialModel();
    }

    /**
     * Initial & Build Ignore Git File
     */
    final static private function initialIgnoreGit()
    {
        $gitIgnore = ['.gitignore'];
        static::copyFile($gitIgnore, __DIR__ . '/', './');
    }

    /**
     * Initial App Folder
     */
    final static private function initialApp()
    {
        if (static::isDirEmpty('App/Index')) {
            $appFiles = ['Router.php', 'Index.php', 'Html.php', 'Html.tpl'];
            static::copyFile($appFiles, __DIR__ . '/Index/', 'App/Index/');
        }
    }

    /**
     * Initial Config Files
     */
    final static private function initialConfig()
    {
        if (static::isDirEmpty('Config/Local')) {
            if (!file_exists('Config/Local/DefaultApp'))
                mkdir('Config/Local/DefaultApp');
            $configFiles = ['Db.php', 'General.php', 'Profiler.php', 'Redis.php', 'Session.php', 'Misc.php', 'Twig.php', 'Route.php'];
            static::copyFile($configFiles, __DIR__ . '/Config/', 'Config/Local/DefaultApp/');
        }
    }


    /**
     * Initial Config Files
     */
    final static private function updateConfig()
    {
        $configFiles = ['Db.php', 'General.php', 'Profiler.php', 'Redis.php', 'Session.php', 'Misc.php', 'Twig.php', 'Route.php'];
        if ($handle = opendir('Config')) {
            while (false !== ($envDir = readdir($handle)))
                if ($envDir != "." && $envDir != ".." && is_dir("Config/{$envDir}")) {
                    if ($handle2 = opendir("Config/{$envDir}")) {
                        while (false !== ($appName = readdir($handle2)))
                            if ($appName != "." && $appName != ".." && is_dir("Config/{$envDir}/{$appName}"))
                                static::copyFile($configFiles, __DIR__ . '/Config/', "Config/{$envDir}/{$appName}/");
                        closedir($handle2);
                    }
                }
            closedir($handle);
        }
    }

    /**
     * Initial Public Files
     */
    final static private function initialException()
    {
        if (static::isDirEmpty('Exception')) {
            $appFiles = ['Base.php'];
            static::copyFile($appFiles, __DIR__ . '/Exception/', 'Exception/');
        }
    }

    /**
     * Initial Moder Loader
     */
    final static private function initialModule()
    {
        if (static::isDirEmpty('Module')) {
            $appFiles = ['Module.php'];
            static::copyFile($appFiles, __DIR__ . '/Module/', 'Module/');
        }
    }

    /**
     * Initial Moder Loader
     */
    final static private function initialModel()
    {
        if (static::isDirEmpty('Model')) {
            $appFiles = ['Loader.php'];
            static::copyFile($appFiles, __DIR__ . '/Model/', 'Model/');
        }
    }

    /**
     * Initial Public Files
     */
    final static private function initialPublic()
    {
        if (static::isDirEmpty('Public/Local')) {
            if (!file_exists('Public/Local/DefaultApp'))
                mkdir('Public/Local/DefaultApp');
            $appFiles = ['.htaccess', 'base.php', 'cronjob.php', 'daemon.php', 'index.php'];
            static::copyFile($appFiles, __DIR__ . '/Public/', 'Public/Local/DefaultApp/');
        }
    }

    /**
     * Initial Public Files
     */
    final static private function initialTemplate()
    {
        if (static::isDirEmpty('Template')) {
            if (!file_exists('Template'))
                mkdir('Template');
            $appFiles = ['Sample.twig'];
            static::copyFile($appFiles, __DIR__ . '/Template/', 'Template/');
        }
    }

    /**
     * Copy Necessary File
     * @param array $files
     * @param $srcPath
     * @param $desPath
     */
    final static private function copyFile(array $files, $srcPath, $desPath)
    {
        $nameSpace = strtr(substr($desPath, 0, -1), ['/' => '\\']);
        foreach ($files as $file) {
            if (!file_exists("{$desPath}{$file}") && file_exists("{$srcPath}{$file}")) {
                $destFile = "{$desPath}{$file}";
                $content = file_get_contents("{$srcPath}{$file}");
                $content = strtr($content, ['//namespace' => "namespace", '%%Setup%%' => $nameSpace]);
                file_put_contents($destFile, $content);
            }
        }
    }

    /**
     * Build Necessary Folder
     */
    final static private function buildFolder()
    {
        if (!file_exists('Model'))
            mkdir('Model');

        if (!file_exists('Module'))
            mkdir('Module');

        if (!file_exists('Exception'))
            mkdir('Exception');

        if (!file_exists('Template'))
            mkdir('Template');

        if (!file_exists('Config'))
            mkdir('Config');
        if (!file_exists('Config/Local'))
            mkdir('Config/Local');

        if (!file_exists('App'))
            mkdir('App');
        if (!file_exists('App/Index'))
            mkdir('App/Index');

        if (!file_exists('Public'))
            mkdir('Public');
        if (!file_exists('Public/Local'))
            mkdir('Public/Local');
    }

    /**
     * Update Composer Json
     */
    final static private function composerUpdate()
    {
        $composer = json_decode(file_get_contents('composer.json'), true);
        if (empty($composer['autoload']))
            $composer['autoload'] = ['psr-4' => []];
        if (empty($composer['autoload']['psr-4']))
            $composer['autoload']['psr-4'] = [];
        if (empty($composer['autoload']['psr-4']["App\\"]))
            $composer['autoload']['psr-4']["App\\"] = 'App/';
        if (empty($composer['autoload']['psr-4']["Config\\"]))
            $composer['autoload']['psr-4']["Config\\"] = 'Config/';
        if (empty($composer['autoload']['psr-4']["Model\\"]))
            $composer['autoload']['psr-4']["Model\\"] = 'Model/';
        if (empty($composer['autoload']['psr-4']["Exception\\"]))
            $composer['autoload']['psr-4']["Exception\\"] = 'Exception/';
        if (empty($composer['autoload']['psr-4']["Module\\"]))
            $composer['autoload']['psr-4']["Module\\"] = 'Module/';
        if (empty($composer['autoload']['psr-4']["Template\\"]))
            $composer['autoload']['psr-4']["Template\\"] = 'Template/';
        file_put_contents('composer.json', json_encode($composer, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    /**
     * @param string $directory
     * @return bool
     */
    final static private function isDirEmpty($directory = '')
    {
        if (file_exists($directory) && is_dir($directory)) {
            $lists = array_diff(scandir($directory), ['..', '.']);
            return count($lists) > 0 ? false : true;
        } else
            return false;
    }
}
