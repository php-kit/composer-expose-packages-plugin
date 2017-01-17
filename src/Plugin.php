<?php

namespace PhpKit\ComposerSharedPackagesPlugin;

require "Util/util.php";

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\Capability\CommandProvider;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Util\Filesystem as FilesystemUtil;
use PhpKit\ComposerSharedPackagesPlugin\Util\CommonAPI;
use Symfony\Component\Filesystem\Filesystem;
use function PhpKit\ComposerSharedPackagesPlugin\Util\get;
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

  public static function getSubscribedEvents ()
  {
    return [
      'post-install-cmd' => [['onPostUpdate', 0]],
      'post-update-cmd'  => [['onPostUpdate', 0]],
      'pre-update-cmd'   => [['onPreUpdate', 0]],
      //      'command'          => [['parsePluginArguments', 0]],
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

  public function onPostUpdate (Event $event)
  {
    $this->init ();

    $o = [];
    $this->iteratePackages (function ($package, $packageName, $packagePath, $sharedPath, $sourcePath) use (&$o) {
      $fsUtil = new FilesystemUtil;
      $fs     = new Filesystem();

      // Copy package nackup to original package directory.
      if (!$fs->exists ($sourcePath)) {
        $fsUtil->ensureDirectoryExists (dirname ($sourcePath));
        $fs->mkdir ($sourcePath);
        $fs->mirror ($packagePath, $sourcePath);
        $this->info (sprintf ("Copied <info>%s</info> to source directory <info>%s</info>", $packageName,
          shortenPath ($sourcePath)));
      }

      if ($fs->exists ($sharedPath) && !$fsUtil->isSymlinkedDirectory ($sharedPath))
        throw new \RuntimeException("Directory $sharedPath already exists and I won't replace it by a symlink");

      $fsUtil->ensureDirectoryExists (dirname ($sharedPath));
      $fs->symlink ($packagePath, $sharedPath);

      $o[] = [shortenPath ($sharedPath), $packageName];
    });

    $m = 0;
    foreach ($o as $r) {
      $l = strlen ($r[0]);
      if ($l > $m) $m = $l;
    }
    foreach ($o as $r)
      $this->info (sprintf ("Symlinked <info>%-{$m}s</info> to package <info>%s</info>", $r[0], $r[1]));
  }

  public function onPreUpdate (Event $event)
  {
    $this->init ();

    $rootConfig       = $this->composer->getPackage ()->getConfig ();
    $preferredInstall = get ($rootConfig, 'preferred-install', 'dist');
    if (is_string ($preferredInstall))
      $preferredInstall = ['*' => $preferredInstall];

    $o = [];
    $this->iteratePackages (function ($package, $packageName, $packagePath, $sharedPath, $sourcePath) use (&$o) {
      $o[$packageName] = 'source';
    });
    $o = array_merge ($o, $preferredInstall);

    $k = array_diff (array_keys ($o), ['*']);
    if ($k) {
      sort ($k);
      $list = implode (', ', $k);
      $this->info ("Forcing installation from source for <info>$list</info>");
    }

    $rootConfig['preferred-install'] = $o;
    $this->composer->getPackage ()->setConfig ($rootConfig);
  }

//  protected function args_getFriendlyName ($argName)
//  {
//    return get (self::$AVAILABLE_OPTIONS, $argName);
//  }
//
//  protected function args_getPrefix ()
//  {
//    return 'shared';
//  }
//
//  protected function args_set ($name, $value)
//  {
//    if (isset(self::$AVAILABLE_OPTIONS[$name])) {
//      $this->{$name . 'Option'} = $value;
//      return true;
//    }
//    return false;
//  }

}
