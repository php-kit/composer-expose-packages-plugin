<?php

namespace PhpKit\ComposerExposePackagesPlugin;

use Composer\Command\BaseCommand;
use Composer\Composer;
use Composer\IO\IOInterface;
use PhpKit\ComposerExposePackagesPlugin\Util\CommonAPI;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function PhpKit\ComposerExposePackagesPlugin\Util\shortenPath;
use function PhpKit\ComposerExposePackagesPlugin\Util\toRelativePath;

class ExposeCommand extends BaseCommand
{
  use CommonAPI;

  const DESCRIPTION = 'Reexposes all exposable packages on the current project.';

  /** @var Composer */
  protected $composer;
  /** @var IOInterface */
  protected $io;

  protected function configure ()
  {
    $this->setName ('expose');
    $this->setDescription (self::DESCRIPTION);
    $this->setHelp (self::DESCRIPTION . "

Each exposable package will be:
 - symlinked from the junction directory;
 - copied to the source directory if it's not already there.
 
This command is intended for reexposing packages that were previously exposed on another project.
As such, it will only create symlinks for them on the junction directory. It will not install package source repositories.
A full exposure is only performed during a <info>composer install</info> or <info>composer update</info> when packages are being installed for the first time.

Use the <info>-v</info> option to display detailed information on what was performed.");
  }

  protected function execute (InputInterface $input, OutputInterface $output)
  {
    $this->composer = $this->getComposer ();
    $this->io       = $this->getIO ();
    $this->init ();

    $o = [];
    $this->iteratePackages (function ($package, $packageName, $packagePath, $exposurePath, $sourcePath) use (&$o) {
      $this->link ($packagePath, $exposurePath);
      $o[] = [shortenPath ($exposurePath), toRelativePath ($packagePath)];
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
