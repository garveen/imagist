## Imagist

Image the packagist

### Introduction

Due to the Internet connection may not so stable, you want to have a cached `packagist` for using `composer`.

### Installation

#### Composer

```
composer create-project garveen/imagist imagist
```

#### Source

```
git clone https://github.com/garveen/imagist
```

### Usage

1. Now you have a `public` directory in your site. You can set up a web server as usual, just by using the `public` as root.

2. Set up a `crontab` job to update the packages.json:

```
# only dump packages.json
*/5 * * * * /path/to/imagist packages

# dump packages.json and all package index
*/5 * * * * /path/to/imagist dumpindex

# clone whole site
*/5 * * * * /path/to/imagist dumpall
```

Or you can dump all jsons by

```
imagist dumpall
```

After all, setup your project using

```
composer config [--global] repo.packagist composer https?://yourwebsite
```

#### Note

If you have NOT https server, you should use

```
composer config [--global] repo.packagist composer http://yourwebsite
composer config [--global] secure-http false
```


