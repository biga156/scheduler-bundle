# Changelog

All notable changes to this project will be documented in this file.

## [1.1.1] - 2026-05-03

### Fixed

- Fixed an incorrect Bootstrap 5.3.3 CSS SRI hash in [templates/base.html.twig](templates/base.html.twig#L7).
- This prevented stylesheet loading in strict browsers and could cause broken Scheduler UI rendering.
- Bootstrap JS integrity remained unchanged.
