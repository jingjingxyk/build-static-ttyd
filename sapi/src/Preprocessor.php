<?php

namespace SwooleCli;

use MJS\TopSort\CircularDependencyException;
use MJS\TopSort\ElementNotFoundException;
use MJS\TopSort\Implementations\StringSort;
use RuntimeException;

class Preprocessor
{
    public const VERSION = '1.6';
    public const IMAGE_NAME = 'phpswoole/swoole-cli-builder';
    public const CONTAINER_NAME = 'swoole-cli-builder';

    protected static ?Preprocessor $instance = null;

    protected string $osType = 'linux';
    protected array $libraryList = [];
    protected array $extensionList = [];

    protected string $cCompiler = 'clang';
    protected string $cppCompiler = 'clang++';
    protected string $lld = 'ld.lld';

    protected array $libraryMap = [];
    protected array $extensionMap = [];
    /**
     * 仅用于预处理阶段
     * @var string
     */
    protected string $rootDir;
    protected string $libraryDir;
    protected string $extensionDir;
    protected array $pkgConfigPaths = [];
    protected string $phpSrcDir;
    protected string $dockerVersion = 'latest';
    /**
     * 指向 swoole-cli 所在的目录，在构建阶段使用
     * $workDir/pool/ext 存放扩展
     * $workDir/pool/lib 存放依赖库
     */
    protected string $workDir = '/work';
    /**
     * 依赖库的构建目录，在构建阶段使用
     * @var string
     */
    protected string $buildDir = '/work/thirdparty';
    /**
     * 编译后.a静态库文件安装目录的全局前缀，在构建阶段使用
     * @var string
     */
    protected string $globalPrefix = '/usr';

    protected string $extraLdflags = '';
    protected string $extraOptions = '';
    protected string $extraCflags = '';

    protected array $variables = [];

    protected array $exportVariables = [];
    protected int $maxJob = 8;
    protected bool $installLibrary = true;
    protected array $inputOptions = [];

    protected array $binPaths = [];
    /**
     * Extensions enabled by default
     * @var array|string[]
     */
    protected array $extEnabled = [
        'opcache',
        'curl',
        'iconv',
        'bz2',
        'bcmath',
        'pcntl',
        'filter',
        'session',
        'tokenizer',
        'mbstring',
        'ctype',
        'zlib',
        'zip',
        'posix',
        'sockets',
        'pdo',
        'sqlite3',
        'phar',
        'mysqlnd',
        'mysqli',
        'intl',
        'fileinfo',
        'pdo_mysql',
        'pdo_sqlite',
        'soap',
        'xsl',
        'gmp',
        'exif',
        'sodium',
        'openssl',
        'readline',
        'xml',
        'gd',
        'redis',
        'swoole',
        'yaml',
        'imagick',
        'mongodb',
    ];

    protected array $endCallbacks = [];
    protected array $extCallbacks = [];
    protected string $configureVarables;

    protected function __construct()
    {
        switch (PHP_OS) {
            default:
            case 'Linux':
                $this->setOsType('linux');
                break;
            case 'Darwin':
                $this->setOsType('macos');
                break;
            case 'WINNT':
                $this->setOsType('win');
                break;
        }
    }

    public static function getInstance(): static
    {
        if (!self::$instance) {
            self::$instance = new static();
        }
        return self::$instance;
    }

    protected function setOsType(string $osType)
    {
        $this->osType = $osType;
    }

    public function getOsType(): string
    {
        return $this->osType;
    }

    public function getSystemArch(): string
    {
        $uname = \posix_uname();
        switch ($uname['machine']) {
            case 'x86_64':
                return 'x64';
            case 'aarch64':
                return 'arm64';
            default:
                return $uname['machine'];
        }
    }

    public function getImageTag(): string
    {
        $arch = $this->getSystemArch();
        if ($arch == 'x64') {
            return self::VERSION;
        } else {
            return self::VERSION . '-' . $arch;
        }
    }

    public function getBaseImageTag(): string
    {
        $arch = $this->getSystemArch();
        if ($arch == 'x64') {
            return 'base';
        } else {
            return 'base' . '-' . $arch;
        }
    }

    public function getBaseImageDockerFile(): string
    {
        $arch = $this->getSystemArch();
        if ($arch == 'x64') {
            return 'Dockerfile';
        } else {
            return 'Dockerfile' . '-' . $arch;
        }
    }

    public function setPhpSrcDir(string $phpSrcDir)
    {
        $this->phpSrcDir = $phpSrcDir;
    }

    public function getPhpSrcDir(): string
    {
        return $this->phpSrcDir;
    }

    public function setGlobalPrefix(string $prefix)
    {
        $this->globalPrefix = $prefix;
    }

    public function getGlobalPrefix(): string
    {
        return $this->globalPrefix;
    }

    public function setRootDir(string $rootDir)
    {
        $this->rootDir = $rootDir;
    }

    public function getRootDir(): string
    {
        return $this->rootDir;
    }

    public function setLibraryDir(string $libraryDir)
    {
        $this->libraryDir = $libraryDir;
    }

    public function setExtensionDir(string $extensionDir)
    {
        $this->extensionDir = $extensionDir;
    }

    public function setWorkDir(string $workDir)
    {
        $this->workDir = $workDir;
    }

    public function setBuildDir(string $buildDir)
    {
        $this->buildDir = $buildDir;
    }

    public function getBuildDir(): string
    {
        return $this->buildDir;
    }

    public function getWorkDir(): string
    {
        return $this->workDir;
    }

    public function setExtraLdflags(string $flags)
    {
        $this->extraLdflags = $flags;
    }

    public function setExtraCflags(string $flags)
    {
        $this->extraCflags = $flags;
    }

    public function setConfigureVarables(string $varables)
    {
        $this->configureVarables = $varables;
    }

    public function setExtraOptions(string $options)
    {
        $this->extraOptions = $options;
    }

    /**
     * make -j {$n}
     * @param int $n
     */
    public function setMaxJob(int $n)
    {
        $this->maxJob = $n;
    }

    public function donotInstallLibrary()
    {
        $this->installLibrary = false;
    }


    /**
     * @param string $url
     * @param string $file
     * @param string $md5sum
     * @throws Exception
     */
    protected function downloadFile(string $url, string $file, string $md5sum)
    {
        # $userAgent = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/108.0.0.0 Safari/537.36';
        # echo `curl --user-agent '{$userAgent}' --connect-timeout 15 --retry 5 --retry-delay 5  -Lo '{$file}' '{$url}' `;

        $retry_number = DOWNLOAD_FILE_RETRY_NUMBE;
        $user_agent = DOWNLOAD_FILE_USER_AGENT;
        $wait_retry = DOWNLOAD_FILE_WAIT_RETRY;
        echo $cmd = "wget   {$url}  -O {$file}  -t {$retry_number} --wait={$wait_retry} -T 15 --user-agent='{$user_agent}'";
        echo PHP_EOL;
        echo `$cmd`;
        echo PHP_EOL;

        // 下载失败
        if (!is_file($file) or filesize($file) == 0) {
            throw new Exception("Downloading file[" . basename($file) . "] from url[$url] failed");
        }
        // 下载文件的 MD5 不一致
        if (!empty($md5sum) and !$this->checkFileMd5sum($file, $md5sum)) {
            throw new Exception("The md5 of downloaded file[$file] is inconsistent with the configuration");
        }
    }

    /**
     * @param string $cacheDir
     * @param string $downloadScript
     * @return void
     */
    protected function execDownloadScript(string $cacheDir, string $downloadScript): void
    {
        echo PHP_EOL;
        echo $downloadScript;
        echo PHP_EOL;
        $this->mkdirIfNotExists($cacheDir);
        echo `$downloadScript`;
        echo PHP_EOL;
    }

    /**
     * @param string $path
     * @param string $md5
     * @return bool
     */
    protected function checkFileMd5sum(string $path, string $md5): bool
    {
        // md5 不匹配，删除文件
        if ($md5 != md5_file($path)) {
            unlink($path);
            return false;
        }
        return true;
    }

    /**
     * @param Library $lib
     * @throws Exception
     */
    public function addLibrary(Library $lib): void
    {
        if (empty($lib->file)) {
            $lib->file = basename($lib->url);
            if ($lib->enableDownloadScript) {
                $lib->file = $lib->name . '.tar.gz';
            }
        }

        if ($lib->enableDownloadWithMirrorURL && !empty($this->getInputOption('with-download-mirror-url'))) {
            $lib->url = $this->getInputOption('with-download-mirror-url') . '/libraries/' . $lib->file;
            $lib->enableDownloadScript = false;
        }

        $lib->path = $this->libraryDir . '/' . $lib->file;
        if (!empty($lib->md5sum) and is_file($lib->path)) {
            // 本地文件被修改，MD5 不一致，删除后重新下载
            $this->checkFileMd5sum($lib->path, $lib->md5sum);
        }

        $skip_download = ($this->getInputOption('skip-download'));
        if (!$skip_download) {
            if (is_file($lib->path)) {
                echo "[Library] file cached: " . $lib->file . PHP_EOL;
            } else {
                if ($lib->enableDownloadScript) {
                    $cacheDir = $this->getWorkDir() . '/var/tmp';
                    $workDir = $this->getWorkDir();
                    $lib->downloadScript = <<<EOF
                mkdir -p {$cacheDir}
                cd {$cacheDir}
                test -d {$lib->downloadDirName} && rm -rf {$lib->downloadDirName}
                {$lib->downloadScript}
                cd {$lib->downloadDirName}
                test -f {$lib->path} || tar   -zcf {$lib->path} ./
                cd {$workDir}

EOF;

                    $this->execDownloadScript($cacheDir, $lib->downloadScript);
                } else {
                    echo "[Library] {$lib->file} not found, downloading: " . $lib->url . PHP_EOL;
                    $this->downloadFile($lib->url, $lib->path, $lib->md5sum);
                }
            }
        }

        if (!empty($lib->pkgConfig)) {
            $this->pkgConfigPaths[] = $lib->pkgConfig;
        }

        if (!empty($lib->binPath)) {
            $this->binPaths[] = $lib->binPath;
        }

        if (empty($lib->license)) {
            throw new Exception("require license");
        }

        $this->libraryList[] = $lib;
        $this->libraryMap[$lib->name] = $lib;
    }

    public function addExtension(Extension $ext): void
    {
        if (!$this->getInputOption('skip-download')) {
            if ($ext->peclVersion || $ext->enableDownloadScript) {
                if ($ext->enableDownloadScript) {
                    if (!empty($ext->peclVersion)) {
                        $ext->file = $ext->name . '-' . $ext->peclVersion . '.tgz';
                    }
                    if (empty($ext->peclVersion) && empty($ext->file)) {
                        $ext->file = $ext->name . '.tgz';
                    }
                    $ext->path = $this->extensionDir . '/' . $ext->file;
                } else {
                    $ext->file = $ext->name . '-' . $ext->peclVersion . '.tgz';
                    $ext->path = $this->extensionDir . '/' . $ext->file;
                    $ext->url = "https://pecl.php.net/get/{$ext->file}";
                }

                if ($ext->enableDownloadWithMirrorURL && !empty($this->getInputOption('with-download-mirror-url'))) {
                    $ext->url = $this->getInputOption('with-download-mirror-url') . '/extensions/' . $ext->file;
                    if ($ext->enableDownloadScript) {
                        $ext->enableDownloadScript = false;
                    }
                }

                $workDir = $this->getWorkDir();
                if (!file_exists($ext->path)) {
                    if ($ext->enableDownloadScript) {
                        $cacheDir = $this->getWorkDir() . '/var/tmp';
                        $ext->downloadScript = <<<EOF
                                mkdir -p {$cacheDir}
                                cd {$cacheDir}
                                test -d {$ext->downloadDirName} && rm -rf {$ext->downloadDirName}
                                {$ext->downloadScript}
                                cd {$ext->downloadDirName}
                                test -f {$ext->path} ||  tar  -zcf {$ext->path} ./
                                cd {$workDir}


EOF;

                        $this->execDownloadScript($cacheDir, $ext->downloadScript);
                    } else {
                        // 检查文件的 MD5，若不一致删除后重新下载
                        if (!empty($ext->md5sum) and is_file($ext->path)) {
                            // 本地文件被修改，MD5 不一致，删除后重新下载
                            $this->checkFileMd5sum($ext->path, $ext->md5sum);
                        }

                        if (!is_file($ext->path)) {
                            echo "[Extension] {$ext->file} not found, downloading: " . $ext->url . PHP_EOL;
                            $this->downloadFile($ext->url, $ext->path, $ext->md5sum);
                        }
                    }
                } else {
                    echo "[Extension] file cached: " . $ext->file . PHP_EOL;
                }

                $dst_dir = "{$this->rootDir}/ext/{$ext->name}";
                $this->mkdirIfNotExists($dst_dir, 0777, true);

                echo `tar --strip-components=1 -C $dst_dir -xf {$ext->path}`;
            }
        }
        $this->extensionList[] = $ext;
        $this->extensionMap[$ext->name] = $ext;
    }

    public function getLibrary(string $name): ?Library
    {
        if (!isset($this->libraryMap[$name])) {
            return null;
        }
        return $this->libraryMap[$name];
    }

    public function getLibraryPackages(): array
    {
        $packages = [];
        /**
         * @var $item Library
         */
        foreach ($this->libraryList as $item) {
            if (!empty($item->pkgNames) && ($item->enablePkgNames == 'yes')) {
                $packages = array_merge($packages, $item->pkgNames);
            }
        }
        return $packages;
    }

    public function withVariable(string $key, string $value): void
    {
        $this->variables[] = [$key => $value];
    }

    public function withExportVariable(string $key, string $value): void
    {
        $this->exportVariables[] = [$key => $value];
    }

    public function getExtension(string $name): ?Extension
    {
        if (!isset($this->extensionMap[$name])) {
            return null;
        }
        return $this->extensionMap[$name];
    }

    public function existsLibrary(string $name): bool
    {
        return isset($this->libraryMap[$name]);
    }

    public function existsExtension(string $name): bool
    {
        return isset($this->extensionMap[$name]);
    }

    public array $extensionDependentLibraryMap = [];

    public array $extensionDependentPackageNameMap = [];

    public array $extensionDependentPackageNames = [];

    public function setExtensionDependency(): void
    {
        $extensionDepsMap = [];
        foreach ($this->extensionList as $extension) {
            if (empty($extension->deps)) {
                $this->extensionDependentLibraryMap[$extension->name] = [];
            } else {
                $extensionDepsMap[$extension->name] = $extension->deps;
            }
        }

        foreach ($extensionDepsMap as $extensionName => $deps) {
            $pkgNames = [];
            foreach ($deps as $libraryName) {
                $packages = [];
                $this->getLibraryDependenciesByName($libraryName, $packages);
                foreach ($packages as $item) {
                    if (empty($item)) {
                        continue;
                    } else {
                        $pkgNames[] = trim($item);
                    }
                }
                $this->extensionDependentLibraryMap[$extensionName][] = $libraryName;
            }
            $this->extensionDependentPackageNameMap[$extensionName] = $pkgNames;
        }
        $pkgNames = [];
        foreach ($this->extensionDependentPackageNameMap as $extensionName => $value) {
            $pkgNames = array_merge($pkgNames, $value);
            $this->extensionDependentPackageNameMap[$extensionName] = array_values(array_unique($value));
        }
        $this->extensionDependentPackageNames = array_values(array_unique($pkgNames));
    }

    private function getLibraryDependenciesByName($libraryName, &$packages): void
    {
        if (!isset($this->libraryMap[$libraryName])) {
            throw new RuntimeException('library ' . $libraryName . ' no found');
        }
        $lib = $this->libraryMap[$libraryName];
        if (!empty($lib->pkgNames)) {
            $packages = array_merge($packages, $lib->pkgNames);
        }
        if (!empty($lib->deps)) {
            foreach ($lib->deps as $name) {
                $this->getLibraryDependenciesByName($name, $packages);
            }
        }
    }

    public function addEndCallback($fn)
    {
        $this->endCallbacks[] = $fn;
    }

    public function setExtCallback($name, $fn)
    {
        $this->extCallbacks[$name] = $fn;
    }

    public function parseArguments(int $argc, array $argv)
    {
        // parse the parameters passed in by the user
        for ($i = 1; $i < $argc; $i++) {
            $arg = $argv[$i];
            $op = $arg[0];
            $value = substr($argv[$i], 1);
            if ($op == '+') {
                $this->extEnabled[] = $value;
            } elseif ($op == '-') {
                if ($arg[1] == '-') {
                    $_ = explode('=', substr($arg, 2));
                    $this->inputOptions[$_[0]] = $_[1] ?? true;
                } else {
                    $key = array_search($value, $this->extEnabled);
                    if ($key !== false) {
                        unset($this->extEnabled[$key]);
                    }
                }
            } elseif ($op == '@') {
                $this->setOsType($value);
            }
        }
    }

    /**
     * Get the value of an input option, attempting to read from command-line arguments and environment variables,
     * and returning the default value if not set
     * @param string $key
     * @param string $default
     * @return string
     */
    public function getInputOption(string $key, string $default = ''): string
    {
        if (isset($this->inputOptions[$key])) {
            return $this->inputOptions[$key];
        }
        $env = getenv('SWOOLE_CLI_' . str_replace('-', '_', strtoupper($key)));
        if ($env !== false) {
            return $env;
        }
        return $default;
    }

    /**
     * @throws CircularDependencyException
     * @throws ElementNotFoundException
     */
    protected function sortLibrary(): void
    {
        $libs = [];
        $sorter = new StringSort();
        foreach ($this->libraryList as $item) {
            $libs[$item->name] = $item;
            $sorter->add($item->name, $item->deps);
        }
        $sorted_list = $sorter->sort();
        foreach ($this->extensionList as $item) {
            if ($item->deps) {
                foreach ($item->deps as $lib) {
                    if (!isset($libs[$lib])) {
                        throw new Exception("The ext-{$item->name} depends on $lib, but it does not exist");
                    }
                }
            }
        }

        $libraryList = [];
        foreach ($sorted_list as $name) {
            if (empty($name)) {
                continue;
            }
            $libraryList[] = $libs[$name];
        }
        $this->libraryList = $libraryList;
    }

    protected function mkdirIfNotExists(string $dir, int $permissions = 0777, bool $recursive = false)
    {
        if (!is_dir($dir)) {
            mkdir($dir, $permissions, $recursive);
        }
    }

    /**
     * Scan and load config files in directory
     */
    protected function scanConfigFiles(string $dir, array &$extAvailabled)
    {
        $files = scandir($dir);
        foreach ($files as $f) {
            if ($f == '.' or $f == '..' or substr($f, -4, 4) != '.php') {
                continue;
            }
            $path = $dir . '/' . $f;
            if (is_dir($path)) {
                $this->scanConfigFiles($path, $extAvailabled);
            } else {
                $extAvailabled[basename($f, '.php')] = require $path;
            }
        }
    }

    /**
     * @throws CircularDependencyException
     * @throws ElementNotFoundException
     */
    public function execute(): void
    {
        if (empty($this->rootDir)) {
            $this->rootDir = dirname(__DIR__, 2);
        }
        if (empty($this->libraryDir)) {
            $this->libraryDir = $this->rootDir . '/pool/lib';
        }
        if (empty($this->extensionDir)) {
            $this->extensionDir = $this->rootDir . '/pool/ext';
        }
        $this->mkdirIfNotExists($this->libraryDir, 0777, true);
        $this->mkdirIfNotExists($this->extensionDir, 0777, true);
        include __DIR__ . '/constants.php';
        //构建依赖库安装脚本
        libraries_builder($this);
        $extAvailabled = [];
        if (is_dir($this->rootDir . '/conf.d')) {
            $this->scanConfigFiles($this->rootDir . '/conf.d', $extAvailabled);
        }

        $confPath = $this->getInputOption('conf-path');
        if ($confPath) {
            $confDirList = explode(':', $confPath);
            foreach ($confDirList as $dir) {
                if (!is_dir($dir)) {
                    continue;
                }
                $this->scanConfigFiles($dir, $extAvailabled);
            }
        }

        foreach ($this->extEnabled as $ext) {
            if (!isset($extAvailabled[$ext])) {
                echo "unsupported extension[$ext]\n";
                continue;
            }
            ($extAvailabled[$ext])($this);
            if (isset($this->extCallbacks[$ext])) {
                ($this->extCallbacks[$ext])($this);
            }
        }

        $this->pkgConfigPaths[] = '$PKG_CONFIG_PATH';
        $this->pkgConfigPaths = array_unique($this->pkgConfigPaths);
        $this->binPaths[] = '$PATH';
        $this->binPaths = array_unique($this->binPaths);
        //暂时由手工维护，依赖关系
        //$this->sortLibrary();
        $this->setExtensionDependency();

        if ($this->getOsType() == 'macos') {
            $libcpp = '-lc++';
        } else {
            $libcpp = '-lstdc++';
        }

        //$packagesArr = $this->getLibraryPackages();
        $packagesArr = $this->extensionDependentPackageNames;
        if (!empty($packagesArr)) {
            $packages = implode(' ', $packagesArr);
            $this->withVariable('PACKAGES', $packages);
            $this->withVariable('CPPFLAGS', '$CPPFLAGS $(pkg-config --cflags-only-I --static $PACKAGES ) ');
            $this->withVariable('LDFLAGS', '$LDFLAGS $(pkg-config --libs-only-L --static $PACKAGES ) ');
            $this->withVariable('LIBS', '$LIBS $(pkg-config --libs-only-l --static $PACKAGES ) ' . $libcpp);
        }
        if (!empty($this->varables) || !empty($packagesArr)) {
            $this->withExportVariable('CPPFLAGS', '$CPPFLAGS');
            $this->withExportVariable('LDFLAGS', '$LDFLAGS');
            $this->withExportVariable('LIBS', '$LIBS');
        }


        # $packages = implode(' ', $this->getLibraryPackages());
        # $this->withVariable('PACKAGES', $packages);
        # $this->withVariable('CPPFLAGS', '$(pkg-config --cflags-only-I --static ' . $packages . ' ) ');
        # $this->withVariable('CFLAGS', '$(pkg-config  --cflags-only-I --static ' . $packages . ' )');
        # $this->withVariable('LDFLAGS', '$(pkg-config --libs-only-L --static ' . $packages . ' ) $(pkg-config --libs-only-l --static ' . $packages . ' ) ' . $libcpp);


        $this->binPaths[] = '$PATH';
        $this->binPaths = array_unique($this->binPaths);


        if ($this->getInputOption('skip-download')) {
            $this->generateLibraryDownloadLinks();
        }

        ob_start();
        include __DIR__ . '/make.php';
        file_put_contents($this->rootDir . '/make.sh', ob_get_clean());

        ob_start();
        include __DIR__ . '/license.php';
        $this->mkdirIfNotExists($this->rootDir . '/bin');
        file_put_contents($this->rootDir . '/bin/LICENSE', ob_get_clean());

        ob_start();
        include __DIR__ . '/credits.php';
        file_put_contents($this->rootDir . '/bin/credits.html', ob_get_clean());

        copy($this->rootDir . '/sapi/scripts/pack-sfx.php', $this->rootDir . '/bin/pack-sfx.php');

        if ($this->getInputOption('with-dependency-graph')) {
            ob_start();
            include __DIR__ . '/ExtensionDependencyGraph.php';
            file_put_contents(
                $this->rootDir . '/bin/ext-dependency-graph.graphviz.dot',
                ob_get_clean()
            );
        }

        foreach ($this->endCallbacks as $endCallback) {
            $endCallback($this);
        }
        echo '==========================================================' . PHP_EOL;
        echo "Extension count: " . count($this->extensionList) . PHP_EOL;
        echo '==========================================================' . PHP_EOL;
        foreach ($this->extensionList as $item) {
            echo $item->name . PHP_EOL;
        }

        echo '==========================================================' . PHP_EOL;
        echo "Library count: " . count($this->libraryList) . PHP_EOL;
        echo '==========================================================' . PHP_EOL;
        foreach ($this->libraryList as $item) {
            echo "{$item->name}\n";
        }
    }


    protected function generateLibraryDownloadLinks(): void
    {
        $this->mkdirIfNotExists($this->getWorkDir() . '/var/', 0755, true);
        $download_urls = [];
        foreach ($this->libraryList as $item) {
            if (empty($item->url) || $item->enableDownloadScript) {
                continue;
            }
            $url = '';
            $item->mirrorUrls[] = $item->url;
            if (!empty($item->mirrorUrls)) {
                $newMirrorUrls = [];
                foreach ($item->mirrorUrls as $value) {
                    $newMirrorUrls[] = trim($value);
                }
                $url = implode("\t", $newMirrorUrls);
            }
            $download_urls[] = $url . PHP_EOL . " out=" . $item->file;
        }
        file_put_contents($this->getWorkDir() . '/var/download_library_urls.txt', implode(PHP_EOL, $download_urls));

        $download_urls = [];
        foreach ($this->extensionMap as $item) {
            if (empty($item->peclVersion) || $item->enableDownloadScript) {
                continue;
            }
            $item->file = $item->name . '-' . $item->peclVersion . '.tgz';
            $item->path = $this->extensionDir . '/' . $item->file;
            $item->url = "https://pecl.php.net/get/{$item->file}";
            $download_urls[] = $item->url . PHP_EOL . " out=" . $item->file;
        }
        file_put_contents($this->getWorkDir() . '/var/download_extension_urls.txt', implode(PHP_EOL, $download_urls));


        $shell_cmd_header = <<<'EOF'
#!/bin/bash

set -exu
__DIR__=$(
  cd "$(dirname "$0")"
  pwd
)

cd ${__DIR__}
mkdir -p ${__DIR__}/var/tmp
mkdir -p ${__DIR__}/libraries
mkdir -p ${__DIR__}/extensions

EOF;

        $download_scripts = [];
        foreach ($this->libraryList as $item) {
            if (!$item->enableDownloadScript || !$item->enableDownloadWithMirrorURL) {
                continue;
            }
            if (empty($item->file)) {
                $item->file = $item->name . '.tar.gz';
            }
            $cacheDir = '${__DIR__}/var/tmp';
            $workDir = '${__DIR__}/var';
            $downloadScript = <<<EOF
            mkdir -p {$cacheDir}
            cd {$cacheDir}
            test -d {$item->downloadDirName} && rm -rf {$item->downloadDirName}
            {$item->downloadScript}
            cd {$item->downloadDirName}
            test -f {$workDir}/libraries/{$item->file} || tar  -czf {$workDir}/{$item->file} ./
            cp -f {$workDir}/{$item->file} "\${__DIR__}/libraries/"
            cd {$workDir}

EOF;

            $download_scripts[] = $downloadScript . PHP_EOL;
        }
        file_put_contents(
            $this->getWorkDir() . '/var/download_library_use_git.sh',
            $shell_cmd_header . PHP_EOL . implode(PHP_EOL, $download_scripts)
        );
        $download_scripts = [];
        foreach ($this->extensionMap as $item) {
            if (!$item->enableDownloadScript || !$item->enableDownloadWithMirrorURL) {
                continue;
            }
            if (!empty($item->peclVersion)) {
                $item->file = $item->name . '-' . $item->peclVersion . '.tgz';
            }
            if (empty($item->peclVersion) && empty($item->file)) {
                $item->file = $item->name . '.tgz';
            }
            $cacheDir = '${__DIR__}/var/tmp';
            $workDir = '${__DIR__}/var';
            $downloadScript = <<<EOF
                mkdir -p {$cacheDir}
                cd {$cacheDir}
                test -d {$item->downloadDirName} && rm -rf {$item->downloadDirName}
                {$item->downloadScript}
                cd {$item->downloadDirName}
                test -f {$workDir}/extensions/{$item->file} || tar -czf  {$workDir}/{$item->file} ./
                cp -f {$workDir}/{$item->file} "\${__DIR__}/extensions/"
                cd {$workDir}

EOF;

            $download_scripts[] = $downloadScript . PHP_EOL;
        }
        file_put_contents(
            $this->getWorkDir() . '/var/download_extension_use_git.sh',
            $shell_cmd_header . PHP_EOL . implode(PHP_EOL, $download_scripts)
        );
    }
}
