# moodle-tool_excimer

NOTE: This plugin currently in early stage development and is NOT production ready yet, stay tuned!

## What is this plugin?

This is a Moodle admin plugin that provides developers with insights into not
only what pages in your site are slow, but why. It uses the the Excimer sampling
php profiler to so.

It is complementary to the profiler in core which uses Tideways. The key downside
to Tideway is that it has a substantial performance hit and can't be used in
production to capture everything and only later decide what to keep or analyse.

Looking at your web server logs tells you what is slow but not why. Using Tideways
is a critical tool if you can reproduce an issue, but cannot be used to say why
something was slow retrospecively last Friday out of hours and you only found out
a few days later.

## What this plugin is not

This plugin does not aim to replace the core Tideways based profiler, they are complimentary.

Because Tideways instruments every single call, it can be used for a variety of
things that Excimer cannot, such as determining in production if a particular code
path was executed or not.

This plugin does also not aim to be a full Application performance management(APM)
solution such as New Relic. But if you don't have or cannot afford an APM this
plugin should be another great tool to have in your tool box.


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

## Configuration

## Support

If you have issues please log them in github here

https://github.com/catalyst/moodle-tool-excimer/issues

Please note our time is limited, so if you need urgent support or want to
sponsor a new feature then please contact Catalyst IT Australia:

https://www.catalyst-au.net/contact-us


## Credits

Thanks in particular to the to the Wikimedia Foundation for building the awseome Excimer profiler:

https://github.com/wikimedia/php-excimer/


This plugin was developed by Catalyst IT Australia:

https://www.catalyst-au.net/

<img alt="Catalyst IT" src="https://cdn.rawgit.com/CatalystIT-AU/moodle-auth_saml2/master/pix/catalyst-logo.svg" width="400">

