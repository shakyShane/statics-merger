<?php

namespace Jh\StaticsMergerTest {

    use Composer\Package\Package;
    use Composer\Package\PackageInterface;
    use Composer\Package\RootPackage;
    use Composer\Repository\RepositoryManager;
    use Composer\Repository\WritableArrayRepository;
    use Composer\Script\CommandEvent;
    use Composer\Util\Filesystem;
    use Jh\StaticsMerger\StaticsMergerPlugin;
    use Composer\Test\TestCase;
    use Composer\Composer;
    use Composer\Config;
    use Composer\Script\ScriptEvents;
    use ReflectionObject;

    /**
     * Class StaticsMergerPluginTest
     *
     * @author Aydin Hassan <aydin@hotmail.co.uk>
     */
    class StaticsMergerPluginTest extends \PHPUnit_Framework_TestCase
    {
        public static $throwSymlinkException = false;
        protected $plugin;
        protected $composer;
        protected $config;
        protected $io;
        protected $repoManager;
        protected $localRepository;
        protected $projectRoot;

        public function setUp()
        {
            $this->plugin   = new StaticsMergerPlugin();
            $this->config   = new Config();
            $this->composer = new Composer();
            $this->composer->setConfig($this->config);

            $root = $this->createFolderStructure();
            chdir($root);

            $this->config->merge(array(
                'config' => array(
                    'vendor-dir' => $this->projectRoot . "/vendor",
                    'bin-dir'    => $this->projectRoot . "/vendor/bin",
                ),
            ));

            $this->io               = $this->getMock('Composer\IO\IOInterface');
            $this->repoManager      = new RepositoryManager($this->io, $this->config);
            $this->localRepository  = new WritableArrayRepository();
            $this->composer->setRepositoryManager($this->repoManager);
            $this->repoManager->setLocalRepository($this->localRepository);
        }

        public function tearDown()
        {
            $dir = sys_get_temp_dir() . "/static-merge-test";

            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    $dir,
                    \RecursiveDirectoryIterator::SKIP_DOTS
                ),
                \RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($files as $file) {

                if ($file->isLink() || !$file->isDir()) {
                    unlink($file->getPathname());
                } else {
                    rmdir($file->getPathname());
                }
            }

            rmdir($dir);
        }

        /**
         * @return string
         */
        private function createFolderStructure()
        {
            $sysDir = realpath(sys_get_temp_dir());

            $htdocs = $sysDir . "/static-merge-test/htdocs";

            if (!is_dir($htdocs)) {
                mkdir($htdocs, 0777, true);
            }

            $vendor = $sysDir . "/static-merge-test/vendor";
            if (!is_dir($vendor)) {
                mkdir($vendor);
            }

            $bin = $sysDir . "/static-merge-test/vendor/bin";
            if (!is_dir($bin)) {
                mkdir($bin);
            }

            $this->projectRoot = $sysDir . "/static-merge-test";

            return $this->projectRoot;
        }

        /**
         * @param $includeMageDir
         * @return RootPackage
         */
        public function createRootPackage($includeMageDir = true)
        {
            $package = new RootPackage("root/package", "1.0.0", "root/package");

            if ($includeMageDir) {
                $extra = array(
                    'magento-root-dir' => 'htdocs'
                );

                $package->setExtra($extra);
            }

            $this->composer->setPackage($package);

            return $package;
        }

        public function activatePlugin()
        {
            $this->plugin->activate($this->composer, $this->io);
        }

        /**
         * @param string $name
         * @param string $theme
         * @param array  $extra
         * @param bool   $createAssets
         * @param bool   $addGlob
         * @param bool   $addFiles
         * @return Package
         */
        public function createStaticPackage(
            $name = 'some/static',
            $theme = 'package/theme',
            $extra = array(),
            $createAssets = true,
            $addGlob = false,
            $addFiles = false
        )
        {
            $package = new Package($name, "1.0.0", $name);
            $package->setExtra($extra);
            $package->setType('static');

            $rootPackageExtra = array_merge_recursive($this->composer->getPackage()->getExtra(), array(
                'static-map' => array(
                    $name => array(
                        $theme => array(
                            array(
                                'src'  => 'assets',
                                'dest' => 'assets'
                            )
                        )
                    )
                ),
            ));

            $this->composer->getPackage()->setExtra($rootPackageExtra);

            if ($createAssets) {
                $this->createStaticPackageAssets($package);
            }

            if ($addGlob) {
                $this->addGlobFiles($package);
            }

            if ($addFiles) {
                $this->addStandardFiles($package);
            }

            return $package;
        }

        public function createStaticPackageAssets(PackageInterface $package)
        {
            $packageLocation = $this->projectRoot . "/vendor/" . $package->getName();
            mkdir($packageLocation . "/assets", 0777, true);
            touch($packageLocation . "/assets/asset1.jpg");
            touch($packageLocation . "/assets/asset2.jpg");
        }

        public function addGlobFiles(PackageInterface $package, $theme = 'package/theme')
        {
            $packageLocation = $this->projectRoot . "/vendor/" . $package->getName();
            touch($packageLocation . "/favicon1");
            touch($packageLocation . "/favicon3");
            touch($packageLocation . "/favicon2");
            mkdir($packageLocation . "/assets/images/catalog", 0777, true);
            touch($packageLocation . "/assets/images/catalog/image1.jpg");
            touch($packageLocation . "/assets/images/catalog/image2.jpg");
            touch($packageLocation . "/assets/images/catalog/picture1.jpg");

            $rootPackageExtra = array_merge_recursive($this->composer->getPackage()->getExtra(), array(
                'static-map' => array(
                    $package->getName() => array(
                        $theme => array(
                            array(
                                'src'  => 'favicon*',
                                'dest' => '/'
                            )
                        )
                    )
                ),
            ));

            $this->composer->getPackage()->setExtra($rootPackageExtra);
        }

        public function addStandardFiles(PackageInterface $package, $theme = 'package/theme')
        {
            $packageLocation = $this->projectRoot . "/vendor/" . $package->getName();
            mkdir($packageLocation . "/assets/images/catalog", 0777, true);
            touch($packageLocation . "/assets/images/catalog/image1.jpg");
            touch($packageLocation . "/assets/images/catalog/image2.jpg");

            $rootPackageExtra = array_merge_recursive($this->composer->getPackage()->getExtra(), array(
                'static-map' => array(
                    $package->getName() => array(
                        $theme => array(
                            array(
                                'src'  => 'assets/images/catalog',
                                'dest' => 'images/catalog'
                            )
                        )
                    )
                ),
            ));

            $this->composer->getPackage()->setExtra($rootPackageExtra);
        }

        public function addStandardFilesNoDest(PackageInterface $package, $theme = 'package/theme')
        {
            $packageLocation = $this->projectRoot . "/vendor/" . $package->getName();
            mkdir($packageLocation . "/assets/images/catalog", 0777, true);
            touch($packageLocation . "/assets/images/catalog/image1.jpg");
            touch($packageLocation . "/assets/images/catalog/image2.jpg");
            touch($packageLocation . "/assets/image3.jpg");

            $rootPackageExtra = array_merge_recursive($this->composer->getPackage()->getExtra(), array(
                'static-map' => array(
                    $package->getName() => array(
                        $theme => array(
                            array(
                                'src'  => 'assets/images/catalog',
                                'dest' => ''
                            ),
                            array(
                                'src'  => 'assets/image3.jpg',
                                'dest' => ''
                            )
                        )
                    )
                ),
            ));

            $this->composer->getPackage()->setExtra($rootPackageExtra);
        }

        public function addGlobsWithDest(PackageInterface $package, $theme = 'package/theme')
        {
            $packageLocation = $this->projectRoot . "/vendor/" . $package->getName();
            mkdir($packageLocation . "/assets/images/catalog", 0777, true);
            touch($packageLocation . "/assets/images/catalog/image1.jpg");
            touch($packageLocation . "/assets/images/catalog/image2.jpg");
            touch($packageLocation . "/assets/images/catalog/picture1.jpg");

            $rootPackageExtra = array_merge_recursive($this->composer->getPackage()->getExtra(), array(
                'static-map' => array(
                    $package->getName() => array(
                        $theme => array(
                            array(
                                'src'  => 'assets/images/catalog/image*',
                                'dest' => 'images'
                            )
                        )
                    )
                ),
            ));

            $this->composer->getPackage()->setExtra($rootPackageExtra);
        }

        public function testErrorIsPrintedIfNoStaticMaps()
        {
            $this->createRootPackage();

            $this->io
                ->expects($this->once())
                ->method('write')
                ->with('<info>No static maps defined</info>');

            $this->activatePlugin();
        }

        public function testErrorIsPrintedIfMagentoRootNotSet()
        {
            $this->createRootPackage(false);
            $this->localRepository->addPackage($this->createStaticPackage());

            $this->io
                ->expects($this->once())
                ->method('write')
                ->with('<info>Magento root dir not defined</info>');

            $this->activatePlugin();
        }

        public function testSymLinkStaticsCorrectlySymLinksStaticFiles()
        {
            $this->createRootPackage();
            $event = new CommandEvent('event', $this->composer, $this->io);
            $this->localRepository->addPackage($this->createStaticPackage());

            $this->activatePlugin();
            $this->plugin->symlinkStatics($event);

            $this->assertFileExists("{$this->projectRoot}/htdocs/skin/frontend/package/theme");
            $this->assertTrue(is_link("{$this->projectRoot}/htdocs/skin/frontend/package/theme/assets"));
        }

        public function testFileGlobAreAllCorrectlySymLinkedToRoot()
        {
            $this->createRootPackage();
            $this->localRepository->addPackage(
                $this->createStaticPackage('some/static', 'package/theme', array(), true, true)
            );

            // Add extra glob mappings
            $rootPackageExtra = array_merge_recursive($this->composer->getPackage()->getExtra(), array(
                'static-map' => array(
                    'some/static' => array(
                        'package/theme' => array(
                            array(
                                'src'  => 'assets/images/catalog/image*',
                                'dest' => '/'
                            )
                        )
                    )
                ),
            ));

            $this->composer->getPackage()->setExtra($rootPackageExtra);

            $event = new CommandEvent('event', $this->composer, $this->io);
            $this->activatePlugin();
            $this->plugin->symlinkStatics($event);

            // Favicons linked from root
            $this->assertFileExists("{$this->projectRoot}/htdocs/skin/frontend/package/theme");
            $this->assertFileExists("{$this->projectRoot}/htdocs/skin/frontend/package/theme/favicon1");
            $this->assertFileExists("{$this->projectRoot}/htdocs/skin/frontend/package/theme/favicon2");
            $this->assertFileExists("{$this->projectRoot}/htdocs/skin/frontend/package/theme/favicon3");
            $this->assertTrue(is_link("{$this->projectRoot}/htdocs/skin/frontend/package/theme/assets"));
            $this->assertTrue(is_link("{$this->projectRoot}/htdocs/skin/frontend/package/theme/favicon1"));
            $this->assertTrue(is_link("{$this->projectRoot}/htdocs/skin/frontend/package/theme/favicon2"));

            // Images linked from image dir
            $this->assertFileExists("{$this->projectRoot}/htdocs/skin/frontend/package/theme/image1.jpg");
            $this->assertFileExists("{$this->projectRoot}/htdocs/skin/frontend/package/theme/image2.jpg");
            $this->assertTrue(is_link("{$this->projectRoot}/htdocs/skin/frontend/package/theme/image1.jpg"));
            $this->assertTrue(is_link("{$this->projectRoot}/htdocs/skin/frontend/package/theme/image2.jpg"));
            $this->assertFileNotExists("{$this->projectRoot}/htdocs/skin/frontend/package/theme/picture1.jpg");
        }

        public function testFileGlobAreAllCorrectlySymLinkedWithSetDest()
        {
            $this->createRootPackage();
            $package = $this->createStaticPackage();

            $this->addGlobsWithDest($package);
            $this->localRepository->addPackage($package);

            $event = new CommandEvent('event', $this->composer, $this->io);
            $this->activatePlugin();
            $this->plugin->symlinkStatics($event);

            $this->assertFileExists("{$this->projectRoot}/htdocs/skin/frontend/package/theme");
            $this->assertFileExists("{$this->projectRoot}/htdocs/skin/frontend/package/theme/images");
            $this->assertTrue(is_dir("{$this->projectRoot}/htdocs/skin/frontend/package/theme/images"));
            $this->assertFileExists("{$this->projectRoot}/htdocs/skin/frontend/package/theme/images/image1.jpg");
            $this->assertFileExists("{$this->projectRoot}/htdocs/skin/frontend/package/theme/images/image2.jpg");
            $this->assertTrue(is_link("{$this->projectRoot}/htdocs/skin/frontend/package/theme/images/image1.jpg"));
            $this->assertTrue(is_link("{$this->projectRoot}/htdocs/skin/frontend/package/theme/images/image2.jpg"));
            $this->assertFileNotExists("{$this->projectRoot}/htdocs/skin/frontend/package/theme/images/picture1.jpg");
        }

        public function testStandardFilesAreAllCorrectlySymLinked()
        {
            $this->createRootPackage();
            $this->localRepository->addPackage(
                $this->createStaticPackage('some/static', 'package/theme', array(), true, false, true)
            );

            $event = new CommandEvent('event', $this->composer, $this->io);
            $this->activatePlugin();
            $this->plugin->symlinkStatics($event);

            $this->assertFileExists("{$this->projectRoot}/htdocs/skin/frontend/package/theme");
            $this->assertFileExists("{$this->projectRoot}/htdocs/skin/frontend/package/theme/images/catalog");
            $this->assertTrue(is_link("{$this->projectRoot}/htdocs/skin/frontend/package/theme/images/catalog"));
            $this->assertTrue(file_exists("{$this->projectRoot}/htdocs/skin/frontend/package/theme/images/catalog/image1.jpg"));
            $this->assertTrue(file_exists("{$this->projectRoot}/htdocs/skin/frontend/package/theme/images/catalog/image2.jpg"));
        }

        public function testCurrentSymlinksAreUnlinked()
        {
            $this->createRootPackage();
            $package = $this->createStaticPackage('some/static', 'package/theme', array(), true, false, true);
            $this->localRepository->addPackage($package);

            $packageLocation = $this->projectRoot . "/vendor/" . $package->getName();
            mkdir($packageLocation . '/assets/testdir');
            mkdir("{$this->projectRoot}/htdocs/skin/frontend/package/theme/images/", 0777, true);

            symlink(
                $packageLocation . '/assets/testdir',
                "{$this->projectRoot}/htdocs/skin/frontend/package/theme/images/catalog"
            );

            $this->assertFileExists("{$this->projectRoot}/htdocs/skin/frontend/package/theme/images/catalog");
            $this->assertTrue(is_link("{$this->projectRoot}/htdocs/skin/frontend/package/theme/images/catalog"));
            $this->assertEquals(
                $packageLocation . '/assets/testdir',
                readLink("{$this->projectRoot}/htdocs/skin/frontend/package/theme/images/catalog")
            );

            $event = new CommandEvent('event', $this->composer, $this->io);
            $this->activatePlugin();
            $this->plugin->symlinkStatics($event);

            $this->assertFileExists("{$this->projectRoot}/htdocs/skin/frontend/package/theme/images/catalog");
            $this->assertTrue(is_link("{$this->projectRoot}/htdocs/skin/frontend/package/theme/images/catalog"));
        }

        public function testFilesAndFolderErrorWithoutDestinationSet()
        {
            $this->createRootPackage();

            $package = $this->createStaticPackage();

            $this->addStandardFilesNoDest($package);
            $this->localRepository->addPackage($package);

            $message = '<error>Full path is required for: "assets/images/catalog" </error>';

            $this->io
                ->expects($this->once())
                ->method('write')
                ->with($message);

            $event = new CommandEvent('event', $this->composer, $this->io);
            $this->activatePlugin();
            $this->plugin->symlinkStatics($event);

            $this->assertFileExists("{$this->projectRoot}/htdocs/skin/frontend/package/theme");
            $this->assertFileNotExists("{$this->projectRoot}/htdocs/skin/frontend/package/theme/image1.jpg");
            $this->assertFileNotExists("{$this->projectRoot}/htdocs/skin/frontend/package/theme/image2.jpg");
            $this->assertFileNotExists("{$this->projectRoot}/htdocs/skin/frontend/package/theme/image3.jpg");
        }

        public function testAssetSymLinkFailsIfAlreadyExistButNotSymLink()
        {
            $this->createRootPackage();
            $event = new CommandEvent('event', $this->composer, $this->io);

            $this->localRepository->addPackage($this->createStaticPackage());

            mkdir("{$this->projectRoot}/htdocs/skin/frontend/package/theme", 0777, true);
            touch("{$this->projectRoot}/htdocs/skin/frontend/package/theme/assets");

            $message = sprintf('<error>Your static path: "%s/htdocs/skin/frontend/package/theme/assets" is currently not a symlink, please remove first </error>', $this->projectRoot);

            $this->io
                ->expects($this->once())
                ->method('write')
                ->with($message);

            $this->activatePlugin();
            $this->plugin->symlinkStatics($event);
            $this->assertTrue(is_file("{$this->projectRoot}/htdocs/skin/frontend/package/theme/assets"));
        }

        public function testErrorIsReportedIfStaticPackageMissingSpecifiedSource()
        {
            $this->createRootPackage();
            $event = new CommandEvent('event', $this->composer, $this->io);

            $this->localRepository->addPackage(
                $this->createStaticPackage('some/static', 'package/theme', array(), false)
            );

            $message = '<error>The static package does not contain directory: "assets" </error>';

            $this->io
                ->expects($this->once())
                ->method('write')
                ->with($message);

            $this->activatePlugin();
            $this->plugin->symlinkStatics($event);
        }

        public function testSkipsNonStaticPackages()
        {
            $this->createRootPackage();
            $nonStaticPackage = $this->createStaticPackage();
            $nonStaticPackage->setType('not-a-static');
            $this->localRepository->addPackage($nonStaticPackage);

            // Mock the plugin to assert processSymlink is never called
            $staticPlugin = $this->getMockBuilder('Jh\StaticsMerger\StaticsMergerPlugin')
                ->setMethods(array('processSymlink'))
                ->getMock();

            $staticPlugin
                ->expects($this->never())
                ->method('processSymlink');

            $event = new CommandEvent('event', $this->composer, $this->io);
            $staticPlugin->activate($this->composer, $this->io);
            $staticPlugin->symlinkStatics($event);
        }

        public function testWritesErrorWithNoFilePaths()
        {
            $this->createRootPackage(true);
            $emptyPathPackage = $this->createStaticPackage();
            $this->localRepository->addPackage($emptyPathPackage);

            $rootPackageExtra = array_merge($this->composer->getPackage()->getExtra(), array(
                'static-map' => array(
                    $emptyPathPackage->getName() => array(
                        'package/theme' => array()
                    )
                )
            ));

            $this->composer->getPackage()->setExtra($rootPackageExtra);

            $message = sprintf(
                '<error>%s requires at least one file mapping, has none!<error>',
                $emptyPathPackage->getPrettyName()
            );

            $this->io
                ->expects($this->once())
                ->method('write')
                ->with($message);

            $event = new CommandEvent('event', $this->composer, $this->io);
            $this->activatePlugin();
            $this->plugin->symlinkStatics($event);
        }

        public function testSubscribedToExpectedComposerEvents()
        {
            $this->assertSame(array(
                ScriptEvents::PRE_INSTALL_CMD => array(
                    array('staticsCleanup', 0)
                ),
                ScriptEvents::PRE_UPDATE_CMD => array(
                    array('staticsCleanup', 0)
                ),
                ScriptEvents::POST_INSTALL_CMD => array(
                    array('symlinkStatics', 0)
                ),
                ScriptEvents::POST_UPDATE_CMD => array(
                    array('symlinkStatics', 0)
                )
            ), $this->plugin->getSubscribedEvents());
        }

        public function testSymlinkFail()
        {
            $this->createRootPackage();
            $this->localRepository->addPackage($this->createStaticPackage());

            // Set symlink exception flag for function override
            static::$throwSymlinkException = true;

            $message = 'Failed to symlink';

            $this->io
                ->expects($this->once())
                ->method('write')
                ->with($this->stringContains($message));

            $event = new CommandEvent('event', $this->composer, $this->io);
            $this->activatePlugin();
            $this->plugin->symlinkStatics($event);

            // Set the flag back to prevent further exceptions
            static::$throwSymlinkException = false;
        }

        public function testStaticsCleanupCorrectlyRemovesDirs()
        {
            $this->createRootPackage();
            $event = new CommandEvent('event', $this->composer, $this->io);

            $this->localRepository->addPackage($this->createStaticPackage());
            $this->activatePlugin();
            $this->plugin->symlinkStatics($event);

            $this->assertFileExists("{$this->projectRoot}/htdocs/skin/frontend/package/theme");
            $this->assertTrue(is_link("{$this->projectRoot}/htdocs/skin/frontend/package/theme/assets"));

            $this->plugin->staticsCleanup($event);

            $this->assertFileNotExists("{$this->projectRoot}/htdocs/skin/frontend/package/theme");
            $this->assertFileNotExists("{$this->projectRoot}/htdocs/skin/frontend/package");
        }

        public function testStaticsCleanupOutputOnException()
        {
            $this->createRootPackage();
            $event = new CommandEvent('event', $this->composer, $this->io);

            $this->localRepository->addPackage($this->createStaticPackage());
            $this->activatePlugin();
            $this->plugin->symlinkStatics($event);

            $this->assertFileExists("{$this->projectRoot}/htdocs/skin/frontend/package/theme");
            $this->assertTrue(is_link("{$this->projectRoot}/htdocs/skin/frontend/package/theme/assets"));

            $filesystem = $this->getMockBuilder('Composer\Util\Filesystem')
                ->setMethods(array('removeDirectory'))
                ->getMock();

            $filesystem
                ->expects($this->once())
                ->method('removeDirectory')
                ->will($this->throwException(new \RuntimeException()));

            $this->io
                ->expects($this->once())
                ->method('write')
                ->with(sprintf(
                    "<error>Failed to remove some/static from %s/htdocs/skin/frontend/package/theme</error>",
                    realpath(sys_get_temp_dir()) . "/static-merge-test"
                ));

            $refObject   = new ReflectionObject($this->plugin);
            $refProperty = $refObject->getProperty('filesystem');
            $refProperty->setAccessible(true);
            $refProperty->setValue($this->plugin, $filesystem);

            $this->plugin->staticsCleanup($event);

            $this->assertFileExists("{$this->projectRoot}/htdocs/skin/frontend/package/theme");
            $this->assertTrue(is_link("{$this->projectRoot}/htdocs/skin/frontend/package/theme/assets"));
        }

        public function testStaticsCleanupOutputOnPackageRemovalException()
        {
            $this->createRootPackage();
            $event = new CommandEvent('event', $this->composer, $this->io);

            $this->localRepository->addPackage($this->createStaticPackage());
            $this->activatePlugin();
            $this->plugin->symlinkStatics($event);

            $this->assertFileExists("{$this->projectRoot}/htdocs/skin/frontend/package/theme");
            $this->assertTrue(is_link("{$this->projectRoot}/htdocs/skin/frontend/package/theme/assets"));

            $filesystem = $this->getMockBuilder('Composer\Util\Filesystem')
                ->setMethods(array('removeDirectory'))
                ->getMock();

            $themeDir = $themeDir = sprintf(
                '%s/htdocs/skin/frontend/package/theme',
                $this->projectRoot
            );

            $filesystem
                ->expects($this->exactly(2))
                ->method('removeDirectory')
                ->will($this->onConsecutiveCalls(
                    $this->returnCallback(function() use ($themeDir) {
                        $fileSys = new Filesystem();
                        $fileSys->removeDirectory($themeDir);
                    }),
                    $this->throwException(new \RuntimeException())
                ));

            $this->io
                ->expects($this->once())
                ->method('write')
                ->with(sprintf(
                    "<error>Failed to remove some/static from %s/htdocs/skin/frontend/package</error>",
                    realpath(sys_get_temp_dir()) . "/static-merge-test"
                ));

            $refObject   = new ReflectionObject($this->plugin);
            $refProperty = $refObject->getProperty('filesystem');
            $refProperty->setAccessible(true);
            $refProperty->setValue($this->plugin, $filesystem);

            $this->plugin->staticsCleanup($event);

            $this->assertFileNotExists("{$this->projectRoot}/htdocs/skin/frontend/package/theme");
            $this->assertFileExists("{$this->projectRoot}/htdocs/skin/frontend/package");
        }

        public function testStaticCleanupWillNotRemoveNonMappedThemesFromPackage()
        {
            $this->createRootPackage();
            $event = new CommandEvent('event', $this->composer, $this->io);

            $this->localRepository->addPackage($this->createStaticPackage());
            $this->activatePlugin();
            $this->plugin->symlinkStatics($event);

            $this->assertFileExists("{$this->projectRoot}/htdocs/skin/frontend/package/theme");
            $this->assertTrue(is_link("{$this->projectRoot}/htdocs/skin/frontend/package/theme/assets"));

            // Create a non mapped static theme
            mkdir(sprintf('%s/htdocs/skin/frontend/package/nonMappedTheme', $this->projectRoot));

            $this->plugin->staticsCleanup($event);

            $this->assertFileNotExists("{$this->projectRoot}/htdocs/skin/frontend/package/theme");
            $this->assertFileExists("{$this->projectRoot}/htdocs/skin/frontend/package");
            $this->assertFileExists("{$this->projectRoot}/htdocs/skin/frontend/package/nonMappedTheme");
        }

        public function testGetStaticMapsWillReturnAll()
        {
            $this->createRootPackage();
            $firstPackage   = $this->createStaticPackage();
            $secondPackage  = $this->createStaticPackage('another/static');

            $this->localRepository->addPackage($firstPackage);
            $this->localRepository->addPackage($secondPackage);

            $this->activatePlugin();
            $this->assertSame(
                array(
                    'some/static'    => array(
                        'package/theme' => array(
                            array(
                                'src'  => 'assets',
                                'dest' => 'assets'
                            )
                        )
                    ),
                    'another/static' => array(
                        'package/theme' => array(
                            array(
                                'src'  => 'assets',
                                'dest' => 'assets'
                            )
                        )
                    )
                ),
                $this->plugin->getStaticMaps());
        }

        public function testGetStaticMapsWillReturnSinglePackageMap()
        {
            $this->createRootPackage();
            $firstPackage   = $this->createStaticPackage();
            $secondPackage  = $this->createStaticPackage('another/static');

            $this->localRepository->addPackage($firstPackage);
            $this->localRepository->addPackage($secondPackage);

            $this->activatePlugin();
            $this->assertSame(
                array(
                    'package/theme' => array(
                        array(
                            'src'  => 'assets',
                            'dest' => 'assets'
                        )
                    )
                ),
                $this->plugin->getStaticMaps('another/static'));
        }

        public function testGetStaticMapWillOutputErrorWhenNoMapFound()
        {
            $this->createRootPackage();
            $firstPackage   = $this->createStaticPackage();
            $secondPackage  = $this->createStaticPackage('another/static');

            $this->localRepository->addPackage($firstPackage);
            $this->localRepository->addPackage($secondPackage);

            $this->activatePlugin();

            $this->io
                ->expects($this->once())
                ->method('write')
                ->with("<error>Mappings for third/static are not defined</error>");

            $result = $this->plugin->getStaticMaps('third/static');

            $this->assertSame(array(), $result);
        }

        public function testMultipleThemesForOnePackageLinksCorrectly()
        {
            $this->createRootPackage();
            $this->localRepository->addPackage(
                $this->createStaticPackage('some/static', 'package/theme', array(), true, false, true)
            );

            $rootPackageExtra = array_merge_recursive($this->composer->getPackage()->getExtra(), array(
                'static-map' => array(
                    'some/static' => array(
                        'package/theme2' => array(
                            array(
                                'src'  => 'assets',
                                'dest' => 'assets'
                            ),
                            array(
                                'src'  => 'assets/images/catalog',
                                'dest' => 'images/catalog'
                            )
                        ),
                        'package2/theme' => array(
                            array(
                                'src'  => 'assets',
                                'dest' => 'assets'
                            ),
                            array(
                                'src'  => 'assets/images/catalog',
                                'dest' => 'images/catalog'
                            )
                        )
                    )
                )
            ));

            $this->composer->getPackage()->setExtra($rootPackageExtra);

            $event = new CommandEvent('event', $this->composer, $this->io);
            $this->activatePlugin();
            $this->plugin->symlinkStatics($event);

            // package/theme
            $this->assertFileExists("{$this->projectRoot}/htdocs/skin/frontend/package/theme");
            $this->assertFileExists("{$this->projectRoot}/htdocs/skin/frontend/package/theme/assets");
            $this->assertFileExists("{$this->projectRoot}/htdocs/skin/frontend/package/theme/images/catalog");
            $this->assertTrue(is_link("{$this->projectRoot}/htdocs/skin/frontend/package/theme/assets"));
            $this->assertTrue(is_link("{$this->projectRoot}/htdocs/skin/frontend/package/theme/images/catalog"));
            $this->assertTrue(file_exists("{$this->projectRoot}/htdocs/skin/frontend/package/theme/images/catalog/image1.jpg"));
            $this->assertTrue(file_exists("{$this->projectRoot}/htdocs/skin/frontend/package/theme/images/catalog/image2.jpg"));

            // package/theme2
            $this->assertFileExists("{$this->projectRoot}/htdocs/skin/frontend/package/theme2");
            $this->assertFileExists("{$this->projectRoot}/htdocs/skin/frontend/package/theme2/assets");
            $this->assertFileExists("{$this->projectRoot}/htdocs/skin/frontend/package/theme2/images/catalog");
            $this->assertTrue(is_link("{$this->projectRoot}/htdocs/skin/frontend/package/theme2/assets"));
            $this->assertTrue(is_link("{$this->projectRoot}/htdocs/skin/frontend/package/theme2/images/catalog"));
            $this->assertTrue(file_exists("{$this->projectRoot}/htdocs/skin/frontend/package/theme2/images/catalog/image1.jpg"));
            $this->assertTrue(file_exists("{$this->projectRoot}/htdocs/skin/frontend/package/theme2/images/catalog/image2.jpg"));

            // package2/theme
            $this->assertFileExists("{$this->projectRoot}/htdocs/skin/frontend/package2/theme");
            $this->assertFileExists("{$this->projectRoot}/htdocs/skin/frontend/package2/theme/assets");
            $this->assertFileExists("{$this->projectRoot}/htdocs/skin/frontend/package2/theme/images/catalog");
            $this->assertTrue(is_link("{$this->projectRoot}/htdocs/skin/frontend/package2/theme/assets"));
            $this->assertTrue(is_link("{$this->projectRoot}/htdocs/skin/frontend/package2/theme/images/catalog"));
            $this->assertTrue(file_exists("{$this->projectRoot}/htdocs/skin/frontend/package2/theme/images/catalog/image1.jpg"));
            $this->assertTrue(file_exists("{$this->projectRoot}/htdocs/skin/frontend/package2/theme/images/catalog/image2.jpg"));
        }

        /**
         * @param $from
         * @param $to
         * @param $exp
         * @dataProvider relativePathTestDataProvider
         */
        public function testgetRelativeSyminkPathProvidesCorrectPath($from, $to, $exp)
        {
            $this->assertSame($exp, $this->plugin->getRelativePath($from, $to));
        }

        public function relativePathTestDataProvider()
        {
            return array(
                // Can go back
                array('a/short/dir/assets/test',  'a/vendor/module/files/test',   '../../../vendor/module/files/test'),
                // Can go forward
                array('from/same/dir',            'from/same/dir/a/file',         'a/file'),
                // Can stay in same dir
                array('same/dir/assets/test',     'same/dir/assets/file',         './file')
            );
        }
    }
}

// Override the Symlink function
// within the StaticsMerger namespace
// Allows testing the exception handling
namespace Jh\StaticsMerger {

    use Jh\StaticsMergerTest\StaticsMergerPluginTest;

    function symlink($target, $link)
    {
        if (StaticsMergerPluginTest::$throwSymlinkException) {
            throw new \ErrorException('Fail');
        }

        return \symlink($target, $link);
    }
}