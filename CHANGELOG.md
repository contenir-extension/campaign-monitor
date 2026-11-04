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
- The add-on: a settings section with the API key, the client and the webhook's secret, and Test connection, Sync all
  now and Re-register webhooks; the sync-record and remove-record listeners; the drain schedule; the events webhook;
  the library's tables under `ext_campaign_monitor_`; the Sync and Bindings screens; and checks for the setup, the
  connection, the outbox and the webhooks.
