# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Send individual invoices from the invoice actions menu using the email form.
  The invoice PDF is attached from the stored document, or generated from the
  configured invoice template when no document is stored. Invoice items support
  the existing bank-transaction substitutions, and drafts retain the invoice context.
- Email templates "AllVariables" and "AlleVariablen" listing all available
  substitutions with English and German variable names, grouped by namespace
  and purpose.

### Fixed

- Substitutions using the translated namespace, e.g. `${MITGLIED::VORNAME}`,
  are now resolved.

## [10.1.0] - 2026-02-13

### Changed

- php -> typescript type injection
- native enums
- use doctrine migrations instead of self-made stuff
- growing automated test-suite.

## [9.1.0] - 2025-06-16

### Added

- invoice table
- taxation statutory sources

### Fixed

- see detailed git changelog

## [9.0.0-dev] - 2025-03-10

### Added

- wrap the legacy app into a Vue envelope and move some more parts to Vue

## [8.0.0] - 2023-01-04

## [7.0.0-rc1] 2021-06-21

## [6.0.0] - 2020-09-18

### Added

- move to Nextcloud

## [5.0.0] - 2016-02-26

## [1.0.0] - 2012-12-04

### Added

- Owncloud integration
- First release
