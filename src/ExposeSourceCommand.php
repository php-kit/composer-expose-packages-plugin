<?php

namespace PhpKit\ComposerExposePackagesPlugin;

use Composer\Command\BaseCommand;
use Composer\Composer;
use Composer\IO\IOInterface;
use PhpKit\ComposerExposePackagesPlugin\Util\CommonAPI;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function PhpKit\ComposerExposePackagesPlugin\Util\shortenPath;

class ExposeSourceCommand extends BaseCommand
{
  use CommonAPI;

  const DESCRIPTION = 'Retargets exposed package symlinks to the repositories on the source directory.';

  /** @var Composer */
  protected $composer;
  /** @var IOInterface */
  protected $io;

  protected function configure ()
  {
    $this->setName ('expose-source');
    $this->setDescription (self::DESCRIPTION);
    $this->setHelp (self::DESCRIPTION . "

Use the <info>-v</info> option to display detailed information on what was performed.");
  }

  protected function execute (InputInterface $input, OutputInterface $output)
  {
    $this->composer = $this->getComposer ();
    $this->io       = $this->getIO ();
    $this->init ();

    $o = [];
    $this->iteratePackages (function ($package, $packageName, $packagePath, $exposurePath, $sourcePath) use (&$o) {
      $this->removeDir ($exposurePath);
      $this->link ($sourcePath, $exposurePath);
      $o[] = [shortenPath ($exposurePath), shortenPath ($sourcePath)];
    });

    $m = 0;
    foreach ($o as $r) {
      $l = strlen ($r[0]);
      if ($l > $m) $m = $l;
    }
    $oo = [];
    foreach ($o as $r)
      $oo[] = sprintf ("Symlinked <info>%-{$m}s</info> to <info>%s</info>", $r[0], $r[1]);
    $this->info (implode (PHP_EOL, $oo));
    $this->displayTail ();
  }

}
