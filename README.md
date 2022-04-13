# moodle-tool_excimer

![GitHub Workflow Status (branch)](https://img.shields.io/github/workflow/status/catalyst/moodle-tool_excimer/ci/MOODLE_35_STABLE)

* [What is this plugin?](#what-is-this-plugin)
* [What this plugin is not](#what-this-plugin-is-not)
* [Design principles](#design-principles)
* [Branches](#branches)
* [Installation](#installation)
* [Troubleshooting](#troubleshooting)
* [Support](#support)
* [Credits](#credits)

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

![image](https://user-images.githubusercontent.com/46659321/122533874-59b15400-d065-11eb-833c-cd6d00ffbf1c.png)


## What this plugin is not

This plugin does not aim to replace the core Tideways based profiler, they are complimentary.

Because Tideways instruments every single call, it can be used for a variety of
things that Excimer cannot, such as determining in production if a particular code
path was executed or not.

This plugin does also not aim to be a full Application performance management(APM)
solution such as New Relic. But if you don't have or cannot afford an APM this
plugin should be another great tool to have in your tool box.


## Design principles

### 1) Do no harm

This plugin is designed to be running constantly in production profiling everything and
only storing things which are of interest in various ways. So it is critical that this
plugin has an extremely low foot print, in all dimensions: CPU, memory, and with IO to
the DB, file system and even caches. In the vast majority of requests when things are
running smoothly it will discard the profile and it's overall impact should be close
to zero. Even when it is storing and processing profiles, keep the impact as low as
possible and defer things until needed if possible or worse case to a cron task.

An extension of this is 'don't escalate', meaning if something fundamentally goes wrong
at a low level inf level, then attempt to mitigate

### 2) Don't make me think

Rather that a low level tool which you have to drive, the intent of this plugin is to
give clear actionable suggestions about specific changes to code to improve it. There
may be many potential reasons why a particular request is 'interesting' in some way.

This plugin aims to detect a range of opportunities to improve performance such as:

* when a session lock is held too long without session changes
* when a session may be a readonly candidate
* when buffering might be better turned off
* when http headers, or a partial body, should be sent earlier
* and diagnoising slow pages retrospectively
* slow cron tasks
* and this list will evolve over time

### 3) Auto tune configuration

The idea is that this plugin starts with sensible defaults that should work for a
wide range of environments and as it records details around your sites performance it
adjusts to show you the most relevant things.

### 4) It's always current

If you had a bad event 6 months ago and things have been running fine, that information
is less relevant that something last night which wasn't as extreme but still worth knowing
about. As you make changes improvements to code it should be smart enough to prioritise
what matters.


## Branches

| Moodle version    | Branch           | PHP  | Excimer    |
|-------------------|------------------|------|------------|
| Moodle 3.5+       | MOODLE_35_STABLE | 7.2+ | 1.0.2+     |
| Totara 10+        | MOODLE_35_STABLE | 7.2+ | 1.0.2+     |

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
git clone git@github.com:catalyst/moodle-tool_excimer.git admin/tool/excimer
```

Then login as admin (it should detect the extension), and click through the upgrade process.

## Troubleshooting

**ExcimerProfiler class does not exist**.
If you use containers, and install the package via apt/PECL, you may see this error. When this happens, you may need to stop and start up the container again, as it sometimes does not load installed packages fully whilst running, and afterwards it should work.


## Support

If you have issues please log them in GitHub here

https://github.com/catalyst/moodle-tool_excimer/issues

Please note our time is limited, so if you need urgent support or want to
sponsor a new feature then please contact Catalyst IT Australia:

https://www.catalyst-au.net/contact-us


## Credits

Thanks in particular to the to the Wikimedia Foundation for building the awesome Excimer profiler:

https://github.com/wikimedia/php-excimer/


This plugin was developed by Catalyst IT Australia:

https://www.catalyst-au.net/

<img alt="Catalyst IT" src="https://cdn.rawgit.com/CatalystIT-AU/moodle-auth_saml2/master/pix/catalyst-logo.svg" width="400">

