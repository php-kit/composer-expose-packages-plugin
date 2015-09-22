<?php
namespace PhpKit\ComposerSharedPackagesPlugin;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Package\PackageInterface;
use Composer\Plugin\CommandEvent;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Util\Filesystem as FilesystemUtil;
use Symfony\Component\Filesystem\Filesystem;

class Plugin implements PluginInterface, EventSubscriberInterface
{
  const DEFAULT_SHARED_DIR = '~/shared-packages';
  const EXTRA_KEY          = 'shared-packages';
  const RULES_KEY          = 'match';
  const SHARED_DIR_KEY     = 'sharedDir';

  protected static $AVAILABLE_OPTIONS = [
    'refresh' => 'Force refresh mode',
  ];

  /** @var Composer */
  protected $composer;
  /** @var IOInterface */
  protected $io;
  protected $lead          = "<comment>[shared-packages-plugin]</comment>";
  protected $optionRefresh = false;

  public static function getSubscribedEvents ()
  {
    return [
      'post-install-cmd' => [['onPostUpdate', 0]],
      'post-update-cmd'  => [['onPostUpdate', 0]],
      'command'          => [['onCommand', 0]],
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

  public function onCommand (CommandEvent $event)
  {
    $name = $event->getCommandName ();
    if ($name == 'update' || $name == 'install') {
      $args    = $event->getInput ()->getArgument ('packages');
      $newArgs = [];
      foreach ($args as $arg) {
        $argName = substr ($arg, 1);
        if ($arg[0] == '@') {
          if (isset(self::$AVAILABLE_OPTIONS[$argName])) {
            $op        = 'option' . ucfirst ($argName);
            $this->$op = true;
            $this->info (self::$AVAILABLE_OPTIONS[$argName] . " <info>enabled</info>");
          }
          else throw new \RuntimeException("Plugin argument $arg is not valid");
        }
        else $newArgs[] = $arg;
      }
      if ($newArgs != $args)
        $event->getInput ()->setArgument ('packages', $newArgs);
    }
  }

  public function onPostUpdate (Event $event)
  {
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

    $rules     = array_unique (self::get ($myConfig, self::RULES_KEY, []));
    $sharedDir =
      str_replace ('~', getenv ('HOME'), self::get ($myConfig, self::SHARED_DIR_KEY, self::DEFAULT_SHARED_DIR));
    $packages  = $this->composer->getRepositoryManager ()->getLocalRepository ()->getCanonicalPackages ();
    $rulesInfo = implode (', ', $rules);
    $this->info ("Shared directory: <info>$sharedDir</info>");
    $this->info ("Match packages: <info>$rulesInfo</info>");

    $fsUtil = new FilesystemUtil;
    $fs     = new Filesystem();

    // Do useful work

    $count = 0;
    foreach ($packages as $package) {
      $srcDir      = $this->getInstallPath ($package);
      $packageName = $package->getName ();
      if (self::globMatchAny ($rules, $packageName) && !$fsUtil->isSymlinkedDirectory ($srcDir)) {
        $destPath = "$sharedDir/$packageName";
        if (!file_exists ($destPath)) {
          $fsUtil->copyThenRemove ($srcDir, $destPath);
          $this->info ("Moved <info>$packageName</info> to shared directory and symlinked to it");
        }
        else {
          $fs->remove ($srcDir);
          $this->info ("Symlinked to existing <info>$packageName</info> on shared directory");
        }
        $fs->symlink ($destPath, $srcDir);
        ++$count;
      }
    }
    if (!$count)
      $this->info ("No packages matched");
  }

  protected function getGlobalConfig ()
  {
    $globalHome    = $this->composer->getConfig ()->get ('home') . '/composer.json';
    $globalCfgJson = new JsonFile($globalHome);
    return $globalCfgJson->exists () ? $globalCfgJson->read () : false;
  }

  protected function info ()
  {
    if ($this->io->isDebug ())
      call_user_func_array ([$this, 'write'], func_get_args ());
  }

  protected function write ()
  {
    foreach (func_get_args () as $msg) {
      $lines = explode (PHP_EOL, $msg);
      if ($lines) {
        $this->io->write ("$this->lead " . array_shift ($lines));
        if ($lines) {
          $msg = implode (PHP_EOL . '    ', $lines);
          $this->io->write ("    $msg");
        }
      }
    }
  }

  private function getInstallPath (PackageInterface $package)
  {
    return $this->composer->getInstallationManager ()->getInstallPath ($package);
  }

}
