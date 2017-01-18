<?php

namespace PhpKit\ComposerExposedPackagesPlugin\Util;

use Composer\IO\IOInterface;

trait AbstractExtIO
{
  /**
   * @param string ... Strings to output.
   * @return $this
   */
  abstract protected function info ();

  /**
   * @return IOInterface
   */
  abstract protected function io ();

  /**
   * @return string
   */
  abstract protected function ioLead ();

  /**
   * @param string ... Strings to output.
   * @return $this
   */
  abstract protected function write ();

}
