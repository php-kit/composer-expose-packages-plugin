<?php
namespace PhpKit\ComposerSharedPackagesPlugin;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Package\PackageInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Util\Filesystem as FilesystemUtil;
use PhpKit\ComposerSharedPackagesPlugin\Util\ExtIO;
use PhpKit\ComposerSharedPackagesPlugin\Util\PluginArguments;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Supported command-line options:
 * - shared:refresh - boolean - Force Refresh Mode.<br>
 *   When true, packages (re)installed by Composer will overwrite the shared (symlinked) packages, allowing them to be
 *   synchronized to the project's required version. The package's git repo's configuration is preserved.
 */
class Plugin implements PluginInterface, EventSubscriberInterface
{
  const DEFAULT_SHARED_DIR = '~/shared-packages';
  use PluginArguments, ExtIO;
  const EXTRA_KEY      = 'shared-packages';
  const RULES_KEY      = 'match';
  const SHARED_DIR_KEY = 'sharedDir';
  protected static $AVAILABLE_OPTIONS = [
    'refresh' => 'Force refresh mode',
  ];
  /** @var Composer */
  protected $composer;
  /** @var IOInterface */
  protected $io;
  protected $refreshOption = false;
  /** @var PackageInterface[] */
  private $packages;
  /** @var string[] */
  private $rules;
  private $sharedDir;

  public static function getSubscribedEvents ()
  {
    return [
      'post-install-cmd' => [['onPostUpdate', 0]],
      'post-update-cmd'  => [['onPostUpdate', 0]],
      'pre-update-cmd'   => [['onPreUpdate', 0]],
      'command'          => [['parsePluginArguments', 0]],
    ];
  }

  private static function get (array $a = null, $k, $def = null)
  {
    return isset($a[$k]) ? $a[$k] : $def;
  }

  private static function globMatchAny (array $rules, $target)
  {
    foreach ($rules as $rule)
      if (fnmatch ($rule, $target))
        return true;
    return false;
  }

  public function activate (Composer $composer, IOInterface $io)
  {
    $this->composer = $composer;
    $this->io       = $io;
  }

  public function onPostUpdate (Event $event)
  {
    $this->init ();

    $this->iteratePackages (function ($package, $packageName, $packagePath, $sharedPath) {
      $fsUtil = new FilesystemUtil;
      $fs     = new Filesystem();

      if ($fsUtil->isSymlinkedDirectory ($packagePath))
        $this->info ("Skipped package <info>$packageName</info> (already symlinked)");
      else {
        $this->info ("Handling package <info>$packageName</info>");
        if (!file_exists ($sharedPath)) {
          $fsUtil->copyThenRemove ($packagePath, $sharedPath);
          $fs->symlink ($sharedPath, $packagePath);
          $this->info ("Installed on shared directory. The project symlinks to it");
        }
        else {
          // The shared package already exists, so update it to match the installed one.
          if (!file_exists ("$packagePath/.git"))
            $this->info ("Can't link to the shared package; the project's package has no git repo");
          else {
            $this->removeDir ($sharedPath);
            if (@!rename ($packagePath, $sharedPath))
              throw new \RuntimeException("Couldn't move the package to the shared directory <fg=cyan;bg=red>$sharedPath</>");
            $fs->symlink ($sharedPath, $packagePath);
            $this->info ("Updated the shared directory and symlinked to it");
          }
        }
      }
    });
  }

  public function onPreUpdate (Event $event)
  {
    $this->init ();

    $this->iteratePackages (function ($package, $packageName, $packagePath, $sharedPath) {
      $fsUtil = new FilesystemUtil;
      $fs     = new Filesystem();

      if (file_exists ($sharedPath)) {

        if (file_exists ($packagePath)) {
          if ($fsUtil->isSymlinkedDirectory ($packagePath))
            $fsUtil->unlink ($packagePath);
          else $this->removeDir ($packagePath);
        }
        // copy the shared package to the vendor dir, so that it may be updated by Composer
        $this->info ("Copying shared package <info>$packageName</info> to project");
        // We keep the original shared package, in case something goes wrong further ahead
        $fs->mirror ($sharedPath, $packagePath);
      }
    });
  }

  protected function args_getFriendlyName ($argName)
  {
    return self::get (self::$AVAILABLE_OPTIONS, $argName);
  }

  protected function args_getPrefix ()
  {
    return 'shared';
  }

  protected function args_set ($name, $value)
  {
    if (isset(self::$AVAILABLE_OPTIONS[$name])) {
      $this->{$name . 'Option'} = $value;
      return true;
    }
    return false;
  }

  protected function getGlobalConfig ()
  {
    $globalHome    = $this->composer->getConfig ()->get ('home') . '/composer.json';
    $globalCfgJson = new JsonFile($globalHome);
    return $globalCfgJson->exists () ? $globalCfgJson->read () : false;
  }

  protected function init ()
  {
    if (isset($this->packages))
      return;

    // Load global plugin configuration

    $globalCfg = $this->getGlobalConfig ();
    if ($globalCfg) {
      $extra    = self::get ($globalCfg, 'extra', []);
      $myConfig = self::get ($extra, self::EXTRA_KEY, []);
      if ($myConfig)
        $this->info ("Global configuration loaded");
    }
    else $myConfig = [];

    // Merge project-specific configuration.
    // Ignore it if Composer is running in global mode.

    $package = $this->composer->getPackage ();
    if ($package->getName () != '__root__') {
      $projCfg  = self::get ($package->getExtra (), self::EXTRA_KEY, []);
      $myConfig = array_merge_recursive ($myConfig, $projCfg);
      $this->info ("Project-specific configuration loaded");
    }

    // Setup

    $this->rules     = array_unique (self::get ($myConfig, self::RULES_KEY, []));
    $this->sharedDir =
      str_replace ('~', getenv ('HOME'), self::get ($myConfig, self::SHARED_DIR_KEY, self::DEFAULT_SHARED_DIR));
    $packages        = $this->composer->getRepositoryManager ()->getLocalRepository ()->getCanonicalPackages ();
    $rulesInfo       = implode (', ', $this->rules);
    $this->info ("Shared directory: <info>$this->sharedDir</info>");
    $this->info ("Match packages: <info>$rulesInfo</info>");

    $this->packages = $packages;
  }

  protected function io ()
  {
    return $this->io;
  }

  protected function ioLead ()
  {
    return "<comment>[shared-packages-plugin]</comment>";
  }

  private function getInstallPath (PackageInterface $package)
  {
    return $this->composer->getInstallationManager ()->getInstallPath ($package);
  }

  private function iteratePackages (callable $callback)
  {
    $count = 0;
    foreach ($this->packages as $package) {
      $packagePath = $this->getInstallPath ($package);
      $packageName = $package->getName ();
      if (self::globMatchAny ($this->rules, $packageName)) {
        // packagePath is the installed package's path
        // sharedPath is the shared package's path
        $sharedPath = "$this->sharedDir/$packageName";
        $callback($package, $packageName, $packagePath, $sharedPath);
        ++$count;
      }
    }
    if (!$count)
      $this->info ("No packages matched");
  }

  /**
   * Remove a directory, even if some files in it are undeletable.
   *
   * @param string $path
   */
  private function removeDir ($path)
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
