<?php

namespace PhpKit\ComposerSharedPackagesPlugin;

use Composer\Command\BaseCommand;
use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Util\Filesystem as FilesystemUtil;
use PhpKit\ComposerSharedPackagesPlugin\Util\CommonAPI;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use function PhpKit\ComposerSharedPackagesPlugin\Util\shortenPath;

class OriginalCommand extends BaseCommand
{
  use CommonAPI;

  /** @var Composer */
  protected $composer;
  /** @var IOInterface */
  protected $io;

  protected function configure ()
  {
    $this->setName ('original');
    $this->setDescription ('Symlinks all shared package folders to the corresponding source package directories.');
  }

  protected function execute (InputInterface $input, OutputInterface $output)
  {
    $this->composer = $this->getComposer ();
    $this->io       = $this->getIO ();
    $this->init ();

    $o = [];
    $this->iteratePackages (function ($package, $packageName, $packagePath, $sharedPath, $sourcePath) use (&$o) {
      $fsUtil = new FilesystemUtil;
      $fs     = new Filesystem();

      if ($fs->exists ($sharedPath) && !$fsUtil->isSymlinkedDirectory ($sharedPath))
        throw new \RuntimeException("Directory $sharedPath already exists and I won't replace it by a symlink");

      $fsUtil->ensureDirectoryExists (dirname ($sharedPath));
      $fs->symlink ($sourcePath, $sharedPath);

      $o[] = [shortenPath ($sharedPath), shortenPath ($sourcePath)];

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
