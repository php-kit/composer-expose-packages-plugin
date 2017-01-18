<?php

namespace PhpKit\ComposerExposePackagesPlugin;

use Composer\Command\BaseCommand;
use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Util\Filesystem as FilesystemUtil;
use PhpKit\ComposerExposePackagesPlugin\Util\CommonAPI;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use function PhpKit\ComposerExposePackagesPlugin\Util\shortenPath;

class OriginalCommand extends BaseCommand
{
  use CommonAPI;

  /** @var Composer */
  protected $composer;
  /** @var IOInterface */
  protected $io;

  protected function configure ()
  {
    $this->setName ('expose-original');
    $this->setDescription ('Retargets exposed package symlinks to the original repositories on the source directory.');
  }

  protected function execute (InputInterface $input, OutputInterface $output)
  {
    $this->composer = $this->getComposer ();
    $this->io       = $this->getIO ();
    $this->init ();

    $o = [];
    $this->iteratePackages (function ($package, $packageName, $packagePath, $exposurePath, $sourcePath) use (&$o) {
      $fsUtil = new FilesystemUtil;
      $fs     = new Filesystem();

      if ($fs->exists ($exposurePath) && !$fsUtil->isSymlinkedDirectory ($exposurePath))
        $this->info ("<error>File/directory $exposurePath already exists and it will not be replaced by a symlink</error>");
      else {
        $fsUtil->ensureDirectoryExists (dirname ($exposurePath));
        $fs->symlink ($sourcePath, $exposurePath);

        $o[] = [shortenPath ($exposurePath), shortenPath ($sourcePath)];
      }
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
  }

}
