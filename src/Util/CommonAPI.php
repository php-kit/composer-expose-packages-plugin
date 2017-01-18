<?php

namespace PhpKit\ComposerSharedPackagesPlugin\Util;

use Composer\Composer;
use Composer\Json\JsonFile;
use Composer\Package\PackageInterface;
use Composer\Package\RootPackage;
use Composer\Util\Filesystem as FilesystemUtil;

/**
 * @property Composer $composer
 */
trait CommonAPI
{
  use ExtIO;

  static $DEFAULT_SHARED_DIR = '~/shared-packages';
  static $DEFAULT_SOURCE_DIR = '~/original-packages';
  static $EXTRA_KEY          = 'shared-packages';
  static $RULES_KEY          = 'match';
  static $SHARED_DIR_KEY     = 'sharedDir';
  static $SOURCE_DIR_KEY     = 'sourceDir';
//  protected static $AVAILABLE_OPTIONS = [
//    'refresh' => 'Force refresh mode',
//  ];

  /** @var PackageInterface[] */
  private $packages;
  /** @var string[] */
  private $rules;
  /** @var string */
  private $sharedDir;
  /** @var string The path to a directory that will hold a master copy of all shared repositoories */
  private $sourceDir;

  protected function getGlobalConfig ()
  {
    $globalHome    = $this->composer->getConfig ()->get ('home') . '/composer.json';
    $globalCfgJson = new JsonFile($globalHome);
    return $globalCfgJson->exists () ? $globalCfgJson->read () : false;
  }

  protected function getInstallPath (PackageInterface $package)
  {
    return $this->composer->getInstallationManager ()->getInstallPath ($package);
  }

  protected function init ()
  {
    if (isset($this->packages))
      return;

    // Load global plugin configuration

    $globalCfg = $this->getGlobalConfig ();
    if ($globalCfg) {
      $extra    = get ($globalCfg, 'extra', []);
      $myConfig = get ($extra, self::$EXTRA_KEY, []);
      if ($myConfig)
        $this->info ("Global configuration loaded");
    }
    else $myConfig = [];

    // Merge project-specific configuration.
    // Ignore it if Composer is running in global mode.

    /** @var RootPackage $package */
    $package = $this->composer->getPackage ();
    if ($package->getName () != '__root__') {
      $projCfg  = get ($package->getExtra (), self::$EXTRA_KEY, []);
      $myConfig = array_merge_recursive ($myConfig, $projCfg);
      $this->info ("Project-specific configuration loaded");
    }

    // Setup

    $this->rules     = array_unique (get ($myConfig, self::$RULES_KEY, []));
    $this->sharedDir = expandPath (get ($myConfig, self::$SHARED_DIR_KEY, self::$DEFAULT_SHARED_DIR));
    $this->sourceDir = expandPath (get ($myConfig, self::$SOURCE_DIR_KEY, self::$DEFAULT_SOURCE_DIR));
    $rulesInfo       = implode (', ', $this->rules);
    $this->info ("Shared directory: <info>$this->sharedDir</info>");
    $this->info ("Source directory: <info>$this->sourceDir</info>");
    $this->info ("Match packages: <info>$rulesInfo</info>");
    $this->packages = $this->composer->getRepositoryManager ()->getLocalRepository ()->getCanonicalPackages ();
  }

  protected function io ()
  {
    return $this->io;
  }

  protected function ioLead ()
  {
    return "<comment>[shared-packages-plugin]</comment>";
  }

  protected function iteratePackages (callable $callback)
  {
    $count = 0;
    foreach ($this->packages as $package)
      if ($this->packageIsEligible ($package)) {
        $packagePath = toAbsolutePath ($this->getInstallPath ($package));
        $packageName = $package->getName ();
        // packagePath is the installed package's path
        // sharedPath is the shared package's path
        $sharedPath = "$this->sharedDir/$packageName";
        $sourcePath = "$this->sourceDir/$packageName";
        $callback($package, $packageName, $packagePath, $sharedPath, $sourcePath);
        ++$count;
      }
    if (!$count)
      $this->info ("No packages matched");
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

}
