# moodle-tool_excimer

This is a Moodle admin plugin that adds the Excimer sampling profiler.

## Installation

### PHP Extension

#### Using apt

```sh
sudo apt install php-excimer
```

#### Using PECL

```sh
pecl install excimer
docker-php-ext-enable excimer
```

### Moodle Plugin 

From Moodle siteroot:

```
cd admin/tool
git clone git@github.com:catalyst/moodle-tool_excimer.git excimer
```

Then login as admin (it should detect the extension), and click through the upgrade process.
