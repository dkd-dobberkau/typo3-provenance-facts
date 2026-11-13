# Changelog

All notable changes to this project are documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Fixed

- Labels of the facts field and tab resolve instead of showing raw keys.

### Added

- "Fact" button for CKEditor 5 (RTE preset `provenance`): creates a fact for
  the selected words through a backend AJAX route with the editor's
  permissions.
- Documentation pointing to the manual of the core extension (milestone M7).
- Facts on content elements (milestone M6): data model, page anchors with
  Claim Microdata, `claim` list in the provenance record, Web Annotations
  under `/.well-known/content-provenance/facts/{factId}`, conformance level 4
  together with signing and audit.
- Repository skeleton: composer.json, ext_emconf.php, Services.yaml,
  extension icon, contribution guide (milestone M1).
