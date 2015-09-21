## Composer shared packages plugin
Makes package development easier by sharing them between projects

### Introduction

This Composer plugin simplifies the development of packages that are being used on multiple projects.

It updates the package installation directory (or directories) of each project to replace some packages with symlinks to a central installation location.

This is quite useful when you want to:

  - edit a package on its own repository or on any of the projects that share it;
  - see your changes to a package applied immediately to any installed project that shares that package;
  - define an authenticated `origin` remote with write permissions only once for each package being developed;
  - register only one VCS directory on your VCS GUI client for each package being developed.

This plugin also supports custom package installation directories, where some packages may use installer plugins that install them to alternative locations (other than the default `vendor` directory).

It also supports customizing the vendor directory location using the `config.vendor-dir` standard Composer setting.

#### Versioning support

Currently, the plugin does not support multiple syde-by-side installations of the same package, with different versions.

When performing a `composer update` on a project, the shared packages will be updated to the versions required by that project. That may break other installed projects that require different versions.

You will need to rembember to manually update a project using `composer update` before you start development on it.

If you perform a `composer update` on a project where a shared package has uncommited changes, Composer will warn you and allow you to either keep, discard or commit those changes before the installation proceeds.

### Requirements

- PHP version >= 5.4
- Operating System: Mac OS X or Linux

### Installing

Add `php-kit/composer-shared-packages-plugin` to the `composer.json`'s `require-dev` setting of projects that will use shared packages.

Run `composer update`

> In production, this plugin will not be installed and no packages wil be shared if you run `composer install -no-dev`. That is why the plugin name should be added to `required-dev` and not to `require`.

### Configuring

Add an extra `shared-packages` configuration section to the `composer.json` of each project where you want to use share packages, specifying which packages you want to be shared.

You can specify a list of `vendor/packages` names or glob patterns.

If no name or pattern is specified, no packages will be shared.

You should also specify the directory path of the central installation location for shared packages. It can be a relative or absolute path and you can also use `~` as an alias to the user's home directory. The default is `~/shared-packages`.

> These configuration settings only apply to the root `composer.json` of your project. Settings specified on packages will have no effect.

> You can use Wikimedia's `composer-merge-plugin` if you need to also merge those settings with the ones from the main project.

#### Example

###### root composer.json

```json
"require-dev": {
  "php-kit/composer-shared-packages-plugin": "dev-master"
},
"extra": {
  "shared-packages": {
    "match": [
      "vendor/package",
      "vendor/prefix-*"
      "vendor/package-*-whatever"
      "vendor/wildcards??"
      "vendor/*"
      "*"
    ],
    "sharedDir": "~/shared-packages"
  }
}
```

## License

This library is open-source software licensed under the [MIT license](http://opensource.org/licenses/MIT).

Copyright &copy; 2015 by Impactwave Lda <impactwave@impactwave.com>
