<?php

namespace PhpKit\ComposerSharedPackagesPlugin\Util;

function get (array $a = null, $k, $def = null)
{
  return isset($a[$k]) ? $a[$k] : $def;
}

function shortenPath ($path)
{
  return str_replace (getenv ('HOME'), '~', $path);
}

function expandPath ($path)
{
  return str_replace ('~', getenv ('HOME'), $path);
}

function toAbsolutePath ($path)
{
  return !$path || $path[0] == '/' || $path[0] == '\\' ? $path : getcwd () . DIRECTORY_SEPARATOR . $path;
}

function globMatchAny (array $rules, $target)
{
  foreach ($rules as $rule)
    if (fnmatch ($rule, $target))
      return true;
  return false;
}
