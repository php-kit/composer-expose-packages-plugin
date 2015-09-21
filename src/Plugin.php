<?php
namespace PhpKit\ComposerSharedPackagesPlugin;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Util\Filesystem as FilesystemUtil;
use Symfony\Component\Filesystem\Filesystem;

function get (array $a = null, $k, $def = null)
{
  return isset($a[$k]) ? $a[$k] : $def;
}

function globMatchAny (array $rules, $target)
{
  foreach ($rules as $rule)
    if (fnmatch ($rule, $target))
      return true;
  return false;
}

class Plugin implements PluginInterface, EventSubscriberInterface
{
  const DEFAULT_SHARED_DIR = 'shared-packages';
  /** @var Composer */
  protected $composer;
  /** @var IOInterface */
  protected $io;
  protected $lead = "<comment>[shared-packages-plugin]</comment>";

  public static function getSubscribedEvents ()
  {
    return [
      'post-install-cmd' => [
        ['onPostUpdate', 0],
      ],
      'post-update-cmd'  => [
        ['onPostUpdate', 0],
      ],
    ];
  }

  public function activate (Composer $composer, IOInterface $io)
  {
    $this->composer = $composer;
    $this->io       = $io;
  }

  public function onPostUpdate (Event $event)
  {
    $cfg       = get ($this->composer->getPackage ()->getExtra (), self::DEFAULT_SHARED_DIR);
    $rules     = get ($cfg, 'match', []);
    $sharedDir = str_replace ('~', getenv ('HOME'), get ($cfg, 'sharedDir', '~/shared-packages'));
    $packages  = $this->composer->getRepositoryManager ()->getLocalRepository ()->getCanonicalPackages ();
    $fsUtil    = new FilesystemUtil;
    $fs        = new Filesystem();
    $this->info ("Shared directory: <info>$sharedDir</info>");

    foreach ($packages as $package) {
      $srcDir      = $this->getInstallPath ($package);
      $packageName = $package->getName ();
      if (globMatchAny ($rules, $packageName) && !$fsUtil->isSymlinkedDirectory ($srcDir)) {
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
      }
    }
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
