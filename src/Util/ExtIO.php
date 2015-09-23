<?php
namespace PhpKit\ComposerSharedPackagesPlugin\Util;

trait ExtIO
{
  use AbstractExtIO;

  protected function info ()
  {
    if ($this->io ()->isDebug ())
      call_user_func_array ([$this, 'write'], func_get_args ());
    return $this;
  }

  protected function write ()
  {
    foreach (func_get_args () as $msg) {
      $lines = explode (PHP_EOL, $msg);
      if ($lines) {
        $this->io ()->write ($this->IOLead () . ' ' . array_shift ($lines));
        if ($lines) {
          $msg = implode (PHP_EOL . '    ', $lines);
          $this->io ()->write ("    $msg");
        }
      }
    }
    return $this;
  }

}
