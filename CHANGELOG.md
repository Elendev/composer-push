# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.1] - 05.04.2022
 * Due to the backward compatibility issues with Composer 2.3, specify in composer.json that it's not yet supported.

## [1.0.0] - 09.02.2022
 * Drop support of Symfony 3 #63 thanks to @tm1000
 * Add support for Symfony 6 #63 thanks to @tm1000

## [0.8.1] - 30.10.2021
 * Throw error when the version is not specified #57 thanks to @LeJeanbono
 * Display the correct repository type instead of always Nexus #58 - Thanks to @hexa2k9 and @LeJeanbono

## [0.8.0] - 13.10.2021
 * Add support for access tokens #51 #55 - Thanks to @LeJeanbono
 * Use version from composer.json if none is specified in the CLI #10 #56 - Thanks to @LeJeanbono

## [0.7.0] - 21.07.2021
 * Rename from `elendev/nexus-composer-push` to `elendev/composer-push`. #50
 * Add `Apache-2.0` to `composer.json`. #50

## [0.6.1] - 21.07.2021
 * Add `artifactory` support by using `"type": "artyfactory"` in configuration. #49
 * Last version of `elendev/nexus-composer-push`, use `elendev/composer-push` instead.
 * Change namespace from `Elendev\NexusComposerPush` to `Elendev\ComposerPush`. #49

## [0.6.0] - 14.07.2021
 * `nexus-push` command is now **deprecated**, use `push` instead.
 * `nexus-push` configuration in the `composer.json` file is **deprecated**, use `push` instead
 * Support of composer `<1.10` dropped, composer versions supported: `^1.10|^2.0`
 * Add options to support multiple repository types. Currently only `nexus` is supported.
 * Add `ssl-verify` parameter in configuration
