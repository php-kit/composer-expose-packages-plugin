<?php

namespace PhpKit\ComposerExposePackagesPlugin\Util;

use Composer\Composer;
use Composer\Json\JsonFile;
use Composer\Package\PackageInterface;
use Composer\Package\RootPackage;
use Composer\Util\Filesystem as FilesystemUtil;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @property Composer $composer
 */
trait CommonAPI
{
  use ExtIO;

  static $DEFAULT_JUNCTION_DIR = '~/exposed-packages';
  static $DEFAULT_SOURCE_DIR   = '~/packages';
  static $EXTRA_KEY            = 'expose-packages';
  static $HARD_LINKS_KEY       = 'useHardLinks';
  static $JUNCTION_DIR_KEY     = 'junctionDir';
  static $RULES_KEY            = 'match';
  static $SOURCE_DIR_KEY       = 'sourceDir';
  static $SOURCE_TREE_CFG_PATH = '~/Library/Application Support/SourceTree/browser.plist';

  /** @var string */
  private $exposureDir;
  /** @var PackageInterface[] */
  private $packages;
  /** @var string[] */
  private $rules;
  /** @var string The path to a directory that will hold a master copy of all exposed repositories */
  private $sourceDir;
  /** @var bool Are we on MacOS and the SourceTree application is installed? */
  private $sourceTreeIsInstalled = false;
  /** @var string[] */
  private $tail = [];

  protected function displayTail ()
  {
    if ($this->tail)
      $this->info (implode (PHP_EOL, $this->tail));
  }

  protected function getGlobalConfig ()
  {
    $globalHome    = $this->composer->getConfig ()->get ('home') . '/composer.json';
    $globalCfgJson = new JsonFile($globalHome);
    return $globalCfgJson->exists () ? $globalCfgJson->read () : false;
  }

  protected function getInstallPath (PackageInterface $package)
  {
    return toAbsolutePath ($this->composer->getInstallationManager ()->getInstallPath ($package));
  }

  protected function init ()
  {
    if (isset($this->packages))
      return;
    $msg = '';

    // Load global plugin configuration

    $globalCfg = $this->getGlobalConfig ();
    if ($globalCfg) {
      $extra    = get ($globalCfg, 'extra', []);
      $myConfig = get ($extra, self::$EXTRA_KEY, []);
      if ($myConfig)
        $msg .= "Global configuration loaded
";
    }
    else $myConfig = [];

    // Merge project-specific configuration.
    // Ignore it if Composer is running in global mode.

    /** @var RootPackage $package */
    $package = $this->composer->getPackage ();
    if ($package->getName () != '__root__') {
      $projCfg = get ($package->getExtra (), self::$EXTRA_KEY, []);
      if ($projCfg) {
        $myConfig = array_merge_recursive ($myConfig, $projCfg);
        $msg      .= "Project-specific configuration loaded
";
      }
    }

    // Setup

    $this->rules       = array_unique (get ($myConfig, self::$RULES_KEY, []));
    $this->exposureDir = expandPath (get ($myConfig, self::$JUNCTION_DIR_KEY, self::$DEFAULT_JUNCTION_DIR));
    $this->sourceDir   = expandPath (get ($myConfig, self::$SOURCE_DIR_KEY, self::$DEFAULT_SOURCE_DIR));
    $this->packages    = $this->composer->getRepositoryManager ()->getLocalRepository ()->getCanonicalPackages ();

    $rulesInfo                   = implode (', ', $this->rules);
    $this->sourceTreeIsInstalled = file_exists ($this->getSourceTreeCfgPath ());
    $srcTree                     = $this->sourceTreeIsInstalled ? 'detected' : 'none detected';

    $this->info ($msg . "Exposure directory: <info>$this->exposureDir</info>
Source directory: <info>$this->sourceDir</info>
Match packages: <info>$rulesInfo</info>
SourceTree repositories: <info>$srcTree</info>");
  }

  protected function io ()
  {
    return $this->io;
  }

  protected function ioLead ()
  {
    return "<comment>[expose-packages-plugin]</comment>";
  }

  protected function iteratePackages (callable $callback)
  {
    $o = [];
    foreach ($this->packages as $package)
      $o[$package->getName ()] = $package;
    ksort ($o);

    $count = 0;
    foreach ($o as $package)
      if ($this->packageIsEligible ($package)) {
        $packagePath = toAbsolutePath ($this->getInstallPath ($package));
        $packageName = $package->getName ();
        // packagePath is the installed package's path
        // exposurePath is the exposure package's path
        $exposurePath = "$this->exposureDir/$packageName";
        $sourcePath   = "$this->sourceDir/$packageName";
        $callback($package, $packageName, $packagePath, $exposurePath, $sourcePath);
        ++$count;
      }
    if (!$count)
      $this->info ("No packages matched");
  }

  protected function link ($targetPath, $junctionPath)
  {
    $isWindows        = strtoupper (substr (PHP_OS, 0, 3)) === 'WIN';
    $fsUtil = new FilesystemUtil;
    $fs     = new Filesystem();

    if ($fs->exists ($junctionPath) && !$fsUtil->isSymlinkedDirectory ($junctionPath))
      $this->tail ("<error>File/directory $junctionPath already exists and it will not be replaced by a link</error>");
    else {
      $fsUtil->ensureDirectoryExists (dirname ($junctionPath));
      if (!$isWindows)
      $fs->symlink ($targetPath, $junctionPath);
      else
        // On Windows, create a junction instead of a symlink to avoid requiring administrator permissions.
        // Warning: relative symlinks do not work properly on Windows, so the caller must provide absolute paths!
        exec (sprintf ('mklink /j "%s" "%s"',
          str_replace ('/', '\\', $junctionPath),
          str_replace ('/', '\\', $targetPath)));
      if ($this->sourceTreeIsInstalled)
        $this->updateSourceTree ($targetPath, $junctionPath);
    }
  }

  protected function packageIsEligible (PackageInterface $package)
  {
    return globMatchAny ($this->rules, $package->getName ());
  }

  /**
   * Remove a directory, even if some files in it are undeletable.
   *
   * @param string $path
   */
  protected function removeDir ($path)
  {
    $tmp = sys_get_temp_dir () . '/' . uniqid ();
    if (@!rename ($path, $tmp))
      throw new \RuntimeException("Couldn't remove directory <fg=cyan;bg=red>$path</>");
    $fsUtil = new FilesystemUtil;
    try {
      $fsUtil->removeDirectory ($tmp);
    }
    catch (\RuntimeException $e) {
      $this->info ("Couldn't remove temporary directory <info>$tmp</info>");
    }
  }

  protected function tail ($msg)
  {
    $this->tail[] = $msg;
  }

  private function getSourceTreeCfgPath ()
  {
    return expandPath (self::$SOURCE_TREE_CFG_PATH);
  }

  private function updateSourceTree ($targetPath, $junctionPath)
  {
    $path = $this->getSourceTreeCfgPath ();
    $file = file_get_contents ($path);
    if ($file[0] != '<') {
      $file = `plutil -convert xml1 -o /dev/stdout "$path"`;
      if ($file[0] != '<') {
        $this->tail ("<error>Cannot read SourceTree config file</error>");
        return;
      }
    }
    $file = str_replace ($targetPath, $junctionPath, $file, $count);
    if ($count) {
      file_put_contents ($path, $file);
      $this->tail (sprintf ("SourceTree repository path updated for <info>%s</info>", basename ($targetPath)));
    }
  }

}
