# Campaign Monitor for Contenir CMS

Keeps a Campaign Monitor list in step with a site's sign-ups and CMS records, through
[contenir/contenir-audience](https://github.com/contenir/contenir-audience)'s outbox, and hears
back about unsubscribes, bounces and deletions through a webhook.

This release holds the service half only: the adapter and the webhook receiver the shared sync
rules call. The add-on itself (settings, listeners, the drain schedule, screens and checks) comes
next.

## How it talks to Campaign Monitor

- **Connecting:** an API key over HTTP Basic, and the client whose lists are kept. An agency's
  key reaches every client it manages; `clients()` lists them.
- **Joining:** imports of up to 1,000 people, without autoresponders. Only people who agreed
  afresh are sent with `Resubscribe`, so someone who unsubscribed isn't quietly taken back.
  Whether they confirm by email first is the list's own setting.
- **Updating, unsubscribing and archiving:** one request per person. Archiving deletes them,
  which Campaign Monitor can undo; someone not on the list is already off it.
- **Consent to track:** `No` unless a field mapped to `ConsentToTrack` or the binding's
  `consent_to_track` option says yes. It is never sent as `Unchanged`.
- **Outcomes:** a suppressed or deleted address (codes 204 and 205) is never retried. A 429 or
  5xx, or a request that never arrived, is tried again after Campaign Monitor's `Retry-After`
  when it gives one. A 401 or 403 stops the run.
- **Webhook:** registered for `Deactivate` and `Update` events as JSON. Campaign Monitor doesn't
  sign its calls, so the add-on keeps a secret in the webhook's address, and each call must be
  for the binding's list. Unsubscribed, bounced and deleted people, and changed addresses, are
  passed to the shared rules; anything else is passed over.

## Testing

Every request is tested against Campaign Monitor's documented replies, including refused keys,
rate limits and partial imports, so the suite never calls the real service.

```bash
composer check
```
