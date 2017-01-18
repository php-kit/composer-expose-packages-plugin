<?php

namespace PhpKit\ComposerExposedPackagesPlugin\Util;

trait ExtIO
{
  use AbstractExtIO;

  protected function info ()
  {
    if ($this->io ()->isVerbose ())
      call_user_func_array ([$this, 'write'], func_get_args ());
    return $this;
  }

  protected function write ()
  {
    foreach (func_get_args () as $msg) {
      $lines = explode (PHP_EOL, $msg);
      if ($lines) {
        $this->io ()->write ($this->IOLead ());
        $msg = implode (PHP_EOL . '    ', $lines);
        $this->io ()->write ("    $msg");
      }
    }
    return $this;
  }

}
