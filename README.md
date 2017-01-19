## Composer expose packages plugin
Makes package development easier by editing them on any project via the same central filesystem location.

### Introduction

This Composer plugin simplifies the development of packages that are being used on multiple projects.

During a project's package installation (via `composer install` or `composer update`), it creates symlinks on a "junction directory" for each package that is "exposed".

Which packages are selected for exposure is determined by a set of matching patterns that are defined the project's `composer.json` or on a global `composer.json` (which defines rules for all projects).

The same packages on different projects will always be available on the same location on the junction directory; all you have to do is to run the `composer expose` command whenever you switch projects.

Additionaly, it creates a backup of all exposed packages to a "source directory", which can be exposed on the junction directory by running the `composer expose-source` command.

Finally, you can get status information about which packages on a project are being exposed by running the `composer expose-status` command.

#### Use cases

These features are quite useful when you want to:

  - edit packages on their own repository (the 'source directory') or directly on any of the projects that use them, but always via the same central filesystem location (the junction directory);
  - register a package's repository only once on your VCS GUI client (ex: Git), and reuse that registration for any project you work on that uses that package;
  - automatically configure an authenticated `origin` remote with write permissions, for all exposed packages.

#### Additional Features

This plugin

- Supports custom package installation directories, where some packages may use installer plugins that install them to alternative locations (other than the default `vendor` directory);
- Supports customizing the vendor directory location using the `config.vendor-dir` standard Composer setting;
- supports symlinking on Windows (using "junctions").

### Requirements

- PHP version >= 5.6
- Operating System: Mac OS X, Linux or Windows (Vista, Server 2008 or a newer version)

### Installing

The **recommended** setup is to **install the plugin globally** and set its configuration also globally. This way you avoid polluting each project's `composer.json` with information relative to this plugin which will be of no use neither to other developers nor to users of those projects.

##### To install globally on your development machine

If it doesn't exist yet, create a `composer.json` file on the composer's configuration folder (usually at `~/.composer`).

> Ex: `~/.composer/composer.json`

Add `php-kit/composer-expose-packages-plugin` to the `composer.json`'s `require` setting.

Run `composer global update` anywhere.

> The plugin will be active for **all** projects **on your machine only**.
No packages will be exposed until you configure the plugin to expose **specific** packages and then perform a `composer install|update|expose|expose-source` operation.

##### To install locally, per project

Add `php-kit/composer-expose-packages-plugin` to the `composer.json`'s `require-dev` setting of those projects that will use shared packages.

Delete all packages that you whish to expose and then run `composer update` on the project folder.

> On production, this plugin will not be installed and no packages wil be shared if you run `composer install -no-dev`. That is why the plugin name should be added to `required-dev` and not to `require`.

### Configuring

> From this point on, when mentioning `composer.json`, I mean the global one or the project's one, depending on the type of installation you have chosen.

> The plugin will load both the global configuration (if one exists) and the project configuration (again, if one exists) and merge them.
Project-specific settings will take precedence over global ones.

Add an extra `expose-packages` configuration section to the `composer.json`'s `extra` section.

On that section, you can specify a `match` setting containing an array of `vendor/packages` names or glob patterns.

> If neither a `match` setting exists, nor a name or pattern are specified, no packages will be exposed.

You can also specify a `junctiondDir` setting that defines the directory path of the central access location for exposed packages. It can be a relative or absolute path and you can also use `~` as an alias to the user's home directory. The default is `~/exposed-packages`.

> These configuration settings only apply to the root `composer.json` of your project. Settings specified on packages will have no effect.

> You can use Wikimedia's `composer-merge-plugin` if you need to also merge those settings with the ones from the main project.

Finally, you may specify a `sourceDir` setting that defines the directory path where backup copies of exposed packages are stored.

#### Example

###### global `composer.json` at `~/.composer`

```json
{
  "require": {
    "php-kit/composer-expose-packages-plugin": "^2.0"
  },
  "extra": {
    "expose-packages": {
      "match": [
        "vendor/package",
        "vendor/prefix-*",
        "vendor/package-*-whatever",
        "vendor/wildcards??",
        "vendor/*",
        "*"
      ],
      "junctiondDir": "~/exposed-packages",
      "sourceDir": "~/packages"
    }
  }
}
```

### Debugging

You can run Composer in debug mode with the `-v` flag to enable the output of extended information messages, which can be useful for troubleshooting.

Messages output by this plugin will be prefixed with `[shared-packages-plugin]`.

### TO DO

- Windows support

### License

This library is open-source software licensed under the [MIT license](http://opensource.org/licenses/MIT).

Copyright &copy; 2015 by [Cláudio Silva](claudio.silva@impactwave.com) and [Impactwave Lda](impactwave@impactwave.com).
