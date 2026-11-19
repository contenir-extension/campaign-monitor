# Campaign Monitor for Contenir CMS

[![Continuous Integration](https://github.com/contenir-extension/campaign-monitor/actions/workflows/continuous-integration.yml/badge.svg)](https://github.com/contenir-extension/campaign-monitor/actions/workflows/continuous-integration.yml)

Keeps a Campaign Monitor client's lists in step with a site's sign-ups and CMS records, through
[contenir/contenir-audience](https://github.com/contenir/contenir-audience)'s outbox, and hears back about unsubscribes,
bounces and deletions through a webhook. A sign-up or a save only writes a row in the site's database; the CMS's job
runner sends it within the minute, so a slow or failing Campaign Monitor never slows a page or a save.

## Setting it up

1. Enable the add-on. Its tables are created at the next sign-in, in the site's database, under `ext_campaign_monitor_`.
2. Write the add-on's config file. `config/autoload/extensions/campaign-monitor.global.php` holds the bindings, and
   `campaign-monitor.local.php`, never committed, the pepper the email hashes are made with. The update check offers a
   fresh pepper to copy. A Mezzio site that signs people up through
   [contenir-audience-mezzio](https://github.com/contenir/contenir-audience-mezzio) reads the same two files and must
   use the same pepper.

   ```php
   return ['extensions' => ['campaign-monitor' => ['bindings' => [
       'newsletter' => [
           'list'    => 'a1b2c3…',
           'fields'  => ['Name' => 'name'],
           'consent' => 'Signing up to the newsletter',
           'options' => ['consent_to_track' => 'Yes'],
       ],
       'members' => [
           'list'    => 'd4e5f6…',
           'source'  => 'library.members.member',
           'consent' => 'Members who tick Newsletter',
           'options' => ['consent_field' => 'newsletter'],
       ],
   ]]]];
   ```

3. In Site Settings > Integration > Campaign Monitor, enter the API key. The Client list fills from it as soon as you
   leave the field, or says beside the field that Campaign Monitor refused the key. Choose the client, save, and press
   Test connection. Later, leave the API key empty to keep the saved one while you change the client.

   Until the CMS itself keeps a password field's saved value when it is left empty, bind `keep-api-key` in the site's
   `admin.xml`, or an empty key field clears the saved key:

   ```xml
   <settings><category><integration><section><campaign-monitor>
       <events>
           <listen extension="campaign-monitor" listener="keep-api-key" event="SettingsPreSave"/>
       </events>
   </campaign-monitor></section></integration></category></settings>
   ```

4. For a section binding, bind the listeners in the section's `admin.xml`:

   ```xml
   <events>
       <listen extension="campaign-monitor" listener="sync-record" event="RecordPostSave"/>
       <listen extension="campaign-monitor" listener="remove-record" event="RecordPostDelete"/>
   </events>
   ```

5. Press Re-register webhooks. It makes the webhook's secret the first time, and registers each binding's list at
   `/api/extension/campaign-monitor/events` on the site's own address (`site.url`), so the site's web server must send
   `/api/extension/` to the CMS's `admin.php`, past any Basic Auth.

## What it adds

| Extension point | What |
|---|---|
| Settings section | Integration > Campaign Monitor: the API key (masked, in local settings), the client, chosen from those the key reaches, and the webhook's secret (masked). Buttons: Test connection, Sync all now, Re-register webhooks. |
| Field type and script | `client`: the client's ID, which `public/js/client-field.js` shows as a list of the clients the API key reaches, filled as the key is typed. Without the script it is a text field for the 32-character ID. |
| Endpoint | `clients`, at `/admin/extension/campaign-monitor/clients`, for editors who may update it: a GET answering the clients a key reaches as JSON. The key comes in the `X-Campaign-Monitor-Key` header, never the address; without one, the saved key is asked. A refused key answers 422 with Campaign Monitor's reason. |
| Listeners | `sync-record` on `RecordPostSave` and `remove-record` on `RecordPostDelete`, run as the record is saved, since writing a row is quick. `keep-api-key` on `SettingsPreSave`: an empty API key field keeps the saved key. |
| Schedule | `drain`, every minute: sends what's due for up to 40 seconds, then deletes sent messages after 7 days and failed ones after 30. |
| Webhook | `events`, at `/api/extension/campaign-monitor/events?binding=<name>&key=<secret>`. A call without the secret is refused before anything runs, and a call for another list is refused too (CMN-2). |
| Migrations | The library's outbox, sync state, suppression, consent and webhook tables, under `ext_campaign_monitor_`, on the site's database. |
| Screens | Sync, at `/admin/extension/campaign-monitor/sync`: the counts, and the dead, failed and waiting messages with their last error, to retry or remove. Bindings, at `/admin/extension/campaign-monitor/bindings`: read-only, exactly what each binding sends. |
| Checks | Setup (config file and pepper), connection (with a way to the settings when the key is refused), the outbox (failed and dead messages) and the webhooks (not registered, deleted or switched off at Campaign Monitor). |

### What a saved record does

A section binding names the record's consent checkbox with the option `consent_field`; without one, being in the
section is the consent.

| The save | Queues |
|---|---|
| A new record, or the checkbox just ticked | a subscribe, with the binding's `consent` wording as fresh consent from the record |
| Any other save while ticked | an update, which reaches only someone already on the list |
| The checkbox just unticked | a removal, as `on_delete` says |
| A changed address | a removal of the old one, and the new one as fresh consent while ticked |
| A deleted record | a removal, as `on_delete` says: `unsubscribe` by default, `archive` deletes them from the list, `keep` leaves them |

Someone who unsubscribed, bounced or complained is never subscribed again without fresh consent, or at all after a
complaint or deletion.

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
