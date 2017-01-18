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
use function PhpKit\ComposerExposePackagesPlugin\Util\toRelativePath;

class ExposeCommand extends BaseCommand
{
  use CommonAPI;

  /** @var Composer */
  protected $composer;
  /** @var IOInterface */
  protected $io;

  protected function configure ()
  {
    $description = 'Reexposes for development all exposable packages on the current project.';
    $this->setName ('expose');
    $this->setDescription ($description);
    $this->setHelp ("$description

Each exposable package will be:
 - symlinked from the junction directory;
 - copied to the source directory if it's not already there.
 
This command is intended for reexposing packages that were recently exposed on another project.
When you run <info>composer install</info> or <info>composer update</info> you will fully expose packages for development, including the installation of package source repositories, which this command does not perform.
");
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
        $this->write ("<error>File/directory $exposurePath already exists and it will not be replaced by a symlink</error>");
      else {
        $fsUtil->ensureDirectoryExists (dirname ($exposurePath));
        $fs->symlink ($packagePath, $exposurePath);

        $o[] = [shortenPath ($exposurePath), toRelativePath ($packagePath)];
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
