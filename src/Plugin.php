<?php

namespace PhpKit\ComposerExposedPackagesPlugin;

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
use PhpKit\ComposerExposedPackagesPlugin\Util\CommonAPI;
use Symfony\Component\Filesystem\Filesystem;
use function PhpKit\ComposerExposedPackagesPlugin\Util\shortenPath;
use function PhpKit\ComposerExposedPackagesPlugin\Util\toRelativePath;

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
  private $tail = [];

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
    return [new ExposeCommand, new OriginalCommand, new StatusCommand()];
  }

  public function onFinish (Event $event)
  {
    if ($this->forced) {
      $this->info ('Package installation source was overriden for <info>' . implode (', ', $this->forced) . '</info>');
    }
    if ($this->report) {
      ksort ($this->report);
      $m = 0;
      foreach ($this->report as $r) {
        $l = strlen ($r[0]);
        if ($l > $m) $m = $l;
      }
      $o = [];
      foreach ($this->report as $r)
        $o[] = sprintf ("Symlinked <info>%-{$m}s</info> to <info>%s</info>", $r[0], $r[1]);
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

      $name         = $package->getName ();
      $packagePath  = $this->getInstallPath ($package);
      $exposurePath = "$this->exposureDir/$name";
      $sourcePath   = "$this->sourceDir/$name";

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

      // Symlink exposure directory.
      if ($fs->exists ($exposurePath) && !$fsUtil->isSymlinkedDirectory ($exposurePath))
        $this->tail ("<error>Directory $exposurePath already exists and it will not be replaced by a symlink</error>");
      else {
        $fsUtil->ensureDirectoryExists (dirname ($exposurePath));
        $fs->symlink ($packagePath, $exposurePath);
        $this->report[$name] = [shortenPath ($exposurePath), toRelativePath ($packagePath)];
      }
    }
  }

  private function tail ($msg)
  {
    $this->tail[] = $msg;
  }

}
