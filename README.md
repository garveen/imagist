## Imagist

Image the packagist

### Introduction

Due to the Internet connection may not so stable, you want to have a cached `packagist` for using `composer`.

### Installation

#### Installer

```
composer global require acabin/imagist
```

You may have put the  `~/.composer/vendor/bin` directory in your PATH. If not, do so.

Once installed, the `imagist new` command can be used for create your own site.

```
imagist new my_packagist
```

#### Composer

```
composer require composer/composer:^1.0@alpha acabin/imagist
```

### Usage

1. Now you have a `public` directory in your site. You can set up a web server as usual, just by using the `public` as root.

2. Set up a `crontab` job to update the packages.json:

```
*/5 * * * * php /path/to/public/index.php packages.json >/dev/null 2>&1
```

3. Configure your `composer` to use this amazing site:

```
composer config [--global] repo.packagist composer https://yoursite
```

Notice: You should **NOT** use `imagist` globally on the machine which you run it.
