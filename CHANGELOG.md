# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project
adheres to [Semantic Versioning](https://semver.org/).

## [0.1.2] - 2026-11-05

### Changed

- Accepts contenir/contenir-extension-api 0.9 as well as 0.8, so the CMS can host 0.9. 0.9 only adds a service a host
  may lend, so nothing in the add-on changes.

## [0.1.1] - 2026-11-04

### Changed

- Accepts contenir/contenir-extension-api 0.8 as well as 0.7, so the CMS can host 0.8. 0.8's changes are
  additions for add-ons, and only hosts must implement the new methods.

## [0.1.0] - 2026-11-04

### Added

- The Campaign Monitor service adapter: connection check, clients, lists and fields; imports of
  up to 1,000 people that resubscribe only fresh consent; updates, unsubscribes and archiving.
- The webhook receiver: registers for deactivations and updates, checks each call is for the
  binding's list, and reads unsubscribes, bounces, deletions and changed addresses.
- The add-on: a settings section with the API key, the client and the webhook's secret, and Test connection, Sync all
  now and Re-register webhooks; the sync-record and remove-record listeners; the drain schedule; the events webhook;
  the library's tables under `ext_campaign_monitor_`; the Sync and Bindings screens; and checks for the setup, the
  connection, the outbox and the webhooks.
