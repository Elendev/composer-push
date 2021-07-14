# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.6.0] - 14.07.2021
    * `nexus-push` command is now **deprecated**, use `push` instead.
    * `nexus-push` configuration in the `composer.json` file is **deprecated**, use `push` instead
    * Support of composer `<1.10` dropped, composer versions supported: `^1.10|^2.0`
    * Add options to support multiple repository types. Currently only `nexus` is supported.
