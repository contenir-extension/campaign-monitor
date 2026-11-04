# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project
adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- The Campaign Monitor service adapter: connection check, clients, lists and fields; imports of
  up to 1,000 people that resubscribe only fresh consent; updates, unsubscribes and archiving.
- The webhook receiver: registers for deactivations and updates, checks each call is for the
  binding's list, and reads unsubscribes, bounces, deletions and changed addresses.
