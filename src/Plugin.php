<?php
namespace PhpKit\ComposerSharedPackagesPlugin;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;

class Plugin implements PluginInterface, EventSubscriberInterface
{
  /** @var Composer */
  protected $composer;
  /** @var IOInterface */
  protected $io;
  protected $lead = "  <info>[shared-packages-plugin]</info>";

  public function activate (Composer $composer, IOInterface $io)
  {
    $this->composer = $composer;
    $this->io       = $io;
  }

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

  protected function write ()
  {
    foreach (func_get_args() as $msg) {
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

  protected function info ()
  {
    if ($this->io->isDebug ())
      call_user_func_array([$this, 'write'], func_get_args());
  }

  public function onPostUpdate (Event $event)
  {

    $packages1 = $this->composer->getPackage ();
    $packages2 = $this->composer->getRepositoryManager ()->getLocalRepository ()->getCanonicalPackages ();
    $this->write("Packages 1", $packages1, ''. "Packages 2", $packages2);
  }
  
}
