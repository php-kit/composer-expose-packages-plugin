<?php

namespace PhpKit\ComposerSharedPackagesPlugin;

require "Util/util.php";

use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\IO\IOInterface;
use Composer\Package\CompletePackage;
use Composer\Plugin\Capability\CommandProvider;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Util\Filesystem as FilesystemUtil;
use PhpKit\ComposerSharedPackagesPlugin\Util\CommonAPI;
use Symfony\Component\Filesystem\Filesystem;
use function PhpKit\ComposerSharedPackagesPlugin\Util\shortenPath;

/**
 * Supported command-line options:
 * - shared:refresh - boolean - Force Refresh Mode.<br>
 *   When true, packages (re)installed by Composer will overwrite the shared (symlinked) packages, allowing them to be
 *   synchronized to the project's required version. The package's git repo's configuration is preserved.
 */
class Plugin implements PluginInterface, EventSubscriberInterface, Capable, CommandProvider
{
  use CommonAPI;

  /** @var Composer */
  protected $composer;
  /** @var IOInterface */
  protected $io;
  /** @var string[] */
  private $forced = [];
  private $report = [];
  /** @var string[] */
  private $tail   = [];

  public static function getSubscribedEvents ()
  {
    return [
      'pre-install-cmd'      => [['onInit', 0]],
      'pre-update-cmd'       => [['onInit', 0]],
      'post-install-cmd'     => [['onFinish', 0]],
      'post-update-cmd'      => [['onFinish', 0]],
      'post-package-install' => [['onPostPackageInstall', 0]],
    ];
  }

  public function activate (Composer $composer, IOInterface $io)
  {
    $this->composer = $composer;
    $this->io       = $io;
  }

  public function getCapabilities ()
  {
    return [
      'Composer\Plugin\Capability\CommandProvider' => self::class,
    ];
  }

  public function getCommands ()
  {
    return [new OriginalCommand];
  }

  public function onFinish (Event $event)
  {
    if ($this->forced) {
      $this->info ('Package installation source was overriden for <info>' . implode (', ', $this->forced) . '</info>');
    }
    if ($this->report) {
      $m = 0;
      foreach ($this->report as $r) {
        $l = strlen ($r[0]);
        if ($l > $m) $m = $l;
      }
      $o = [];
      foreach ($this->report as $r)
        $o[] = sprintf ("Symlinked <info>%-{$m}s</info> to package <info>%s</info>", $r[0], $r[1]);
      $this->info (implode (PHP_EOL, $o));
    }
    if ($this->tail) {
      $this->info (implode (PHP_EOL, $this->tail));
    }
  }

  public function onInit (Event $event)
  {
    $this->init ();
  }

  public function onPostPackageInstall (PackageEvent $event)
  {
    /** @var InstallOperation $op */
    $op = $event->getOperation ();
    /** @var CompletePackage $package */
    $package = $op->getPackage ();

    if ($this->packageIsEligible ($package)) {
      $fsUtil = new FilesystemUtil;
      $fs     = new Filesystem();

      $name        = $package->getName ();
      $packagePath = $this->getInstallPath ($package);
      $sharedPath  = "$this->sharedDir/$name";
      $sourcePath  = "$this->sourceDir/$name";

      // Install source repository if it was not installed.
      if ($package->getInstallationSource () == 'dist') {
        $this->forced[] = $name;
        $this->composer->getDownloadManager ()->download ($package, $packagePath, true);
      }

      // Backup package to source package directory.
      if (!$fs->exists ($sourcePath)) {
        $fsUtil->ensureDirectoryExists (dirname ($sourcePath));
        $fs->mkdir ($sourcePath);
        $fs->mirror ($packagePath, $sourcePath);
        $this->tail (sprintf ("<info>%s</info> copied to source directory <info>%s</info>", $name,
          shortenPath ($sourcePath)));
      }

      // Symlink shared directory.
      if (!$fs->exists ($sharedPath) && !$fsUtil->isSymlinkedDirectory ($sharedPath))
        $this->tail ("<error>Directory $sharedPath already exists and it will not be replaced by a symlink</error>");
      else {
        $fsUtil->ensureDirectoryExists (dirname ($sharedPath));
        $fs->symlink ($packagePath, $sharedPath);
        $this->report[] = [shortenPath ($sharedPath), $name];
      }
    }
  }

  private function tail ($msg)
  {
    $this->tail[] = $msg;
  }

}
