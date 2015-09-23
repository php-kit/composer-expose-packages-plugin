<?php
namespace PhpKit\ComposerSharedPackagesPlugin\Util;
use Composer\Plugin\CommandEvent;

/**
 * Allows the user to specify extra command line arguments for specific plugins on the `install` and `update` commands.
 */
trait PluginArguments
{
  use AbstractExtIO;

  public function parsePluginArguments (CommandEvent $event)
  {
    $name = $event->getCommandName ();
    switch ($name) {
      case 'update':
      case 'install':
        $myPrefix = $this->args_getPrefix ();
        $args     = $event->getInput ()->getArgument ('packages');
        $newArgs  = [];
        foreach ($args as $arg) {
          if (preg_match ('/@([\w\-]+):([\w\-]+)(?:=(\S+))?/', $arg, $m)) {
            $m[] = true;
            list ($all, $prefix, $name, $value) = $m;
            if ($prefix == $myPrefix) {
              if ($this->args_set ($name, $value))
                $this->info ($this->args_getFriendlyName ($name) .
                             ($value === true ? ": <info>enabled</info>" : ": <info>$value</info>"));
              else throw new \RuntimeException("Invalid argument <fg=cyan;bg=red>$name</> for plugin <fg=cyan;bg=red>$prefix</>");
            }
          }
          else $newArgs[] = $arg;
        }
        if ($newArgs != $args)
          $event->getInput ()->setArgument ('packages', $newArgs);
        break;
    }
  }

  /**
   * Returns a human-friendly name for the specified argument.
   * > Ex: `'Force update'`
   * @param string $argName
   * @return string
   */
  protected abstract function args_getFriendlyName ($argName);

  /**
   * Returns the prefix for arguments of this plugin.
   * > Ex: `'myPlugin'` for arguments with syntax `myPlugin:argName`.
   * @return string
   */
  protected abstract function args_getPrefix ();

  /**
   * @param string $name
   * @param string $value
   * @return boolean True if the argument is supported by the plugin. Otherwise, an error message will be displayed.
   */
  protected abstract function args_set ($name, $value);


}
