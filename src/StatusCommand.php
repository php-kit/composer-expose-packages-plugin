<?php

namespace PhpKit\ComposerExposedPackagesPlugin;

use Composer\Command\BaseCommand;
use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use PhpKit\ComposerExposedPackagesPlugin\Util\CommonAPI;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class StatusCommand extends BaseCommand
{
  use CommonAPI;

  /** @var Composer */
  protected $composer;
  /** @var IOInterface */
  protected $io;

  protected function configure ()
  {
    $this->setName ('expose-status');
    $this->setDescription ('Displays information about exposable packages.');
  }

  protected function execute (InputInterface $input, OutputInterface $output)
  {
    $this->composer = $this->getComposer ();
    $this->io       = $this->getIO ();
    $this->init ();

    $this->io->write ($this->rules ? sprintf("
Configured package exposition rules:
<info>
  %s</info>
", implode (PHP_EOL . '  ', $this->rules))
      : '
There are no package exposition rules defined.
');

    $o = [];
    $this->iteratePackages (function (PackageInterface $package) use (&$o) {
      $o[] = $package->getName ();
    });
    sort($o);

    $this->io->write ($o ? sprintf("Exposable packages on the current project:
<info>
  %s</info>
", implode (PHP_EOL . '  ', $o))
      : 'Currently there are no exposable packages on this project.
');

  }

}
