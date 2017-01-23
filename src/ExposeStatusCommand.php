<?php

namespace PhpKit\ComposerExposePackagesPlugin;

use Composer\Command\BaseCommand;
use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Util\Filesystem as FilesystemUtil;
use PhpKit\ComposerExposePackagesPlugin\Util\CommonAPI;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use function PhpKit\ComposerExposePackagesPlugin\Util\isHardLink;

class ExposeStatusCommand extends BaseCommand
{
  use CommonAPI;

  const DESCRIPTION = 'Displays information about the current state of each exposable package.';

  /** @var Composer */
  protected $composer;
  /** @var IOInterface */
  protected $io;

  protected function configure ()
  {
    $this->setName ('expose-status');
    $this->setDescription (self::DESCRIPTION);
    $this->setHelp (self::DESCRIPTION . "

Use the <info>-v</info> option to display extended information.");
  }

  protected function execute (InputInterface $input, OutputInterface $output)
  {
    $this->composer = $this->getComposer ();
    $this->io       = $this->getIO ();
    $this->init ();
    $fsUtil = new FilesystemUtil;
    $fs     = new Filesystem;

    if ($this->io->isVerbose ())
      $this->io->write ($this->rules
        ? sprintf ("
Package matching patterns:
<info>
  %s</info>", implode (PHP_EOL . '  ', $this->rules))
        : '
There are no package exposition rules defined.');

    $o = [];
    $this->iteratePackages (function (PackageInterface $package, $packageName, $packagePath, $exposurePath,
                                      $sourcePath) use (&$o, $fs, $fsUtil) {

      $type = ' ';
      if (file_exists ($exposurePath)) {
        if (isHardLink($exposurePath))
          $type = 'H';
        elseif ($fsUtil->isSymlinkedDirectory ($exposurePath)) {
          $targetPath = readlink ($exposurePath);
          if ($targetPath == $packagePath)
            $type = 'E';
          elseif ($targetPath == $sourcePath)
            $type = 'S';
          else $type = '<error>?</error>';
        }
        else $type = '<error>?</error>';
      }
      $name     = $package->getName ();
      $o[$name] = sprintf ('  <comment>[</comment>%s<comment>]</comment> <info>%s</info>', $type, $name);
    });
    ksort ($o);

    if ($o) {
      $this->io->write (sprintf ("
Exposable packages on the current project:

%s
", implode (PHP_EOL, $o)));
      if ($this->io->isVerbose ())
        $this->io->write ("Legend:

  <comment>[</comment> <comment>]</comment> - Not exposed
  <comment>[</comment>E<comment>]</comment> - Exposed
  <comment>[</comment>S<comment>]</comment> - Source
  <comment>[</comment>H<comment>]</comment> - Hard link
  <comment>[</comment><error>?</error><comment>]</comment> - Unexpected file, directory or foreign symlink at junction
");
    }
    else $this->io->write ('Currently there are no exposable packages on this project.
');

  }

}
