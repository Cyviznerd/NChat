# NChat Changelog

## 1.4.0 — unreleased (changes since `master` / 1.3.2)

### Added
- **SMF 2.1 support.** `install.xml` now anchors on `function template_main() {` — a line that is byte‑identical in SMF 2.0 and 2.1's `BoardIndex.template.php` — so a single package installs cleanly on either version with no `error="skip"` fallbacks and no red X on the test screen.
- **Runtime SMF version detection** in `NChatBoardIndex.php` (`SMF_VERSION` guard) picks the correct theme markup at render time.
- **Curve2‑matched frame styling** on SMF 2.1 (`cat_bar` / `sub_bar` / `roundframe`) so the shoutbox blends into the default 2.1 theme.
- **Flexbox editor toolbar** for the input row (bold / italic / underline / colour picker / smileys / save): wraps cleanly, no more cramped buttons.
- **Themed confirmation modal for delete.** Clicking a message's `[X]` no longer shows the browser's plain `confirm()`. Instead a Curve2‑styled overlay appears with a bordered preview box rendering the message in the **same format used in the shoutbox** — `[timestamp] Name: coloured body` including the sender's chosen name and text colours, BBC formatting, and profile link — followed by "Delete this message?" and Yes / No buttons in the lower‑left / lower‑right corners.
- **Confirmation‑number challenge on Clean All.** The "Clean All" shortcut used to fire a single `confirm()`; now it opens the same styled modal with a freshly generated 4‑digit random number displayed prominently, and Yes stays `disabled` until the exact number is typed into an input field. This makes an accidental full wipe effectively impossible while still letting an admin intentionally clear the shoutbox in a few seconds.
- **Bundled smiley sets**: `default/`, `aaron/`, `akyhne/` (24 smileys each) plus a large collection of novelty smileys (flags, drinking, wizards, etc.).
- **Paginated smiley picker.** The old picker only showed the first 20 smileys and left a dangling "More..." link that pointed nowhere. Added `nchat_show_more_smile` / `nchat_show_no_smile` JS handlers and a new `$txt['nchat_less_smileys'] = 'Less...';` string so the picker now expands to the full set on "More...", collapses back on "Less...", and closes cleanly.
- **Session-expired hint on the client.** When the new CSRF check rejects an AJAX write / delete / mute (typically because the browser tab sat idle past SMF's session timeout), the shoutbox surfaces `$txt['nchat_session_expired'] = 'Your session timed out, please refresh the page and try again.';` in the same channel as `nchat_empty_mess` — no more silent failures where the input just stops accepting messages.
- **`uninstall.php`** for clean removal of settings and permissions.
- **`readme.txt`** shipped inside the package.
- New helper functions in `NChatHandle.php`:
  - `nchatCheckSession()` — CSRF/session validation for AJAX and form modes.
  - `nchatJsEscape()`, `nchatCleanField()`, `isHex()` — input sanitisation.
  - `nchatReadStore()`, `nchatUpdateStore()`, `nchatDecodeStore()`, `nchatTouchLast()` — flat‑file store abstraction with atomic updates.
  - `nchatGetProtectedGroups()` — resolves admin/mod groups that can't be muted.
  - `getGroupOnlineColors()`, `getSmilesList()` — theming / smiley enumeration.
  - `nchatTxt()` — language string lookup with default fallback.
  - `nchatNote()` — internal message helper.

### Fixed
- **HTTP 405 Method Not Allowed on IIS.** The polling loop used to POST to a static `last.html` file; IIS's `StaticFileHandler` rejects that. Polls are now `GET` requests with a `_=<timestamp>` cache‑buster; only actual writes/deletes use `POST`.
- **`require_once` failing after install** on Windows hosts. The distributable zip is now built with forward‑slash entry names via `System.IO.Compression.ZipArchive`; the previous `Compress-Archive` output used backslashes, which SMF's PHP extractor stored verbatim, leaving files unreachable at their expected paths.
- **Unstable BoardIndex anchor.** The old `// Show some statistics if stat info is off.` search text doesn't exist in SMF 2.1's `BoardIndex.template.php`. Replaced with a stable `function template_main()` anchor per SMF branch.
- **Race conditions on shoutbox / mute store writes.** Every write path in `NChatHandle.php` used to be `fopen('w') → fwrite → fclose` with no locking and no error checking, so two simultaneous shouts could corrupt `nchatMess` / `nchatMutelist`. All I/O now goes through `nchatReadStore()` / `nchatUpdateStore()`, which use `flock(LOCK_SH)` for reads, `flock(LOCK_EX)` for writes, check every `fopen` / `flock` / `fwrite` return value, and release the lock on every exit path.
- **Corrupt‑store fatal errors.** `unserialize()` was called on raw file contents with no error handling — a truncated file would fatal‑error the whole AJAX request and freeze the chat. Now uses `@unserialize()` with return‑value checks and falls back to an empty store.
- **Multiple routes firing on one request.** `index.php` had a chain of independent `if(!empty($_REQUEST['action']))` blocks, so a crafted request could hit `.js`, `mutelist`, and `room` handlers back‑to‑back. Converted to `if / elseif / elseif` so exactly one route runs.
- **Missing `Content-Type` on AJAX write responses.** The header was only emitted on the `.js` action, so browsers occasionally sniffed the shout response as HTML. Header is now sent once, up front, as `text/javascript; charset=UTF-8` — also fixes mojibake for non‑ASCII usernames and smileys.
- **PHP 8 warnings.** `shorten_subject($subject, $modSettings['nchat_lenght'])` now casts to `(int)`; `additional_groups` parsing uses `array_map('intval', array_filter(explode(',', …), 'strlen'))` instead of implicit string→int coercion.
- **Permission checks were inverted / partial.** Handlers used `if(allowedTo(...)) { do work }` with no `else`, so a denied action silently returned an empty page mid‑write. Refactored to `if(!allowedTo(...)) die(nchatNote(...));` at the top of each handler — no partial state changes are possible when the user lacks the permission.
- **Muted‑user rejection path** was a hand‑built `var nchat = new Array("#ff00ff|!|…")` string; now uses the same `nchatNote()` protocol as every other server → client message.
- **Offline warning never fired when the network dropped.** The poll only reacted to `readyState == 4`, so a completely offline browser (no route to the server) either hung on an open request or fired `onerror` — both bypassing the banner. `xmlhttp.timeout` (max of 5 s or `refreshtime × 2`), `ontimeout`, and `onerror` handlers now feed the same "unavailable" banner with a specific reason (`timeout`, `network error`, or `Status: N`).
- **Empty title bar / missing UI text.** SMF doesn't auto‑load `Modifications.english.php`, so `$txt['nchat_*']` keys were undefined at render time and the `cat_bar` `<h3>` collapsed to zero content (thin blue stripe with no "Chat Box" text, empty B/I/U buttons, missing Save/Clean All labels). `NChatBoardIndex.php` now calls `loadLanguage('Modifications')` before rendering.
- **Language strings still blank after the `loadLanguage` fix.** `install.xml` inserts the `require_once` **before** `template_main()`'s `global $context, $txt, $scripturl;` line, so the required file executed in a scope where `$txt` (and `$context`, `$modSettings`, `$scripturl`) were fresh empty locals. `loadLanguage` correctly populated the real global `$txt`, but the echoed markup was reading the empty local one. `NChatBoardIndex.php` now declares its own `global $context, $txt, $scripturl, $modSettings, $boarddir, $boardurl, $settings, $user_info;` on line 3, so it works regardless of the exact anchor position in the theme file.

### Security
- **CSRF protection on all state‑changing actions.** `nchatCheckSession()` is now called before `write`, `clean`, `setmute`, and `mutelist?u=<id>` (mute removal). Previously any logged‑in user could be tricked into posting, deleting, or muting via a cross‑site form.
- **XSS hardening.** All echoed user‑controlled strings (shout subject, mute reason, muted username, profile link text) now use `htmlspecialchars($v, ENT_QUOTES)` — the old code used the default `ENT_COMPAT` which leaves single quotes unescaped, breaking out of `'…'` attributes.
- **Control‑character stripping.** New `nchatCleanField()` removes `\x00-\x1F\x7F` and collapses whitespace on every field before it hits the store, killing terminal‑control / zero‑width injection tricks.
- **Non‑AJAX fatal fallback.** Errors outside AJAX context now go through SMF's `fatal_error($msg, false)` instead of a blank die, so admins get a proper themed error page.
- **Number‑challenge friction on Clean All.** The full‑shoutbox wipe endpoint (`nchat=clean` with no `nchat_mess`) can no longer be triggered by a single stray click — the client‑side flow now requires the user to read and re‑type a random 4‑digit number before the AJAX call fires. `nchat_delete` permission is still the server‑side gate, so nothing changes for scripted attackers, but accidental total wipes by a legitimate admin are effectively eliminated.

### Changed
- Version bumped `1.3.2` → `1.4.0` in both `install.xml` and `package-info.xml`.
- `NChatSmiles.js` and `NChat/index.php` refreshed for the new store/session helpers.
- `$txt['nchat_confirm_clean']` reworded from *"Clear the whole chat box?"* to *"To clear the whole chat box, type the confirmation number shown below:"* to match the new challenge flow. Yes / No button labels resolve via SMF's core `$txt['yes']` / `$txt['no']` so every language pack that ships with SMF is automatically covered — no per‑mod duplicates.

### Documentation
- Documented the previously undocumented **`[Label:https://url]` link syntax** (implemented by `url2_regex` in `NChatBoardIndex.php` since at least 1.3.2). `readme.txt` now has a "Formatting messages" section covering: toolbar buttons (bold / italic / underline / colour), smileys, bare‑URL auto‑linking, and the labelled‑link form with its grammar rules (label may not contain a colon; URL must be `http://`, `https://`, or `ftp://`; whole expression enclosed in `[…]`). Both auto‑link forms depend on **Modification Settings → NChat Shoutbox → Auto‑link = Enable**.

### Cleanup
- **Precomputed smiley regex table.** `nchat_parser` used to call `new RegExp(…)` inside its per‑message loop, once for every smiley — up to ~2,400 regex allocations per redraw with 24 smileys × 100 messages. The regex table is now built lazily on the first parse and reused for every subsequent poll.
- **Shared helper file.** `nchatJsEscape()` and `nchatTxt()` used to be defined twice (once in `NChatHandle.php`, once behind `function_exists` guards in `NChatBoardIndex.php`) because the two entry points don't share an include chain. Both helpers are now hosted in `NChatUtils.php` and pulled in via `require_once(__DIR__ . '/NChatUtils.php')` from each entry point.
- **`var` declarations** added to loop counters (`i`, `j`, `s`) and locals (`nchat_small_mess`, `shut_it_down`) that previously leaked into the browser's global namespace. Would also have thrown under `"use strict"`.
- **`isHex()` doc corrected.** Comment claimed the `#` prefix was optional; the regex requires it. Comment now matches the code (`#rgb` / `#rrggbb`).
- **Redundant `clearTimeout(shut_it_down)`** removed from inside its own callback (a `setTimeout` clears itself when it fires; the extra call was a no‑op).
- **Dead `confirm()` fallback** removed from `nchat_open_confirm`. The custom modal is always emitted whenever `nchat_delete` permission is granted, and both callers (`nchat_delete` / `nchat_clean_all`) require that same permission — the fallback path was unreachable.
- **Style unified.** `if(load == true)` / `if(show_err != true)` replaced with straightforward truthy checks throughout `nchat_ajax`.

### Upgrade notes
- **In-place upgrade from 1.3.2 is now supported and preserves chat history.** The package declares `<upgrade for="2.0 - 2.99.99" from="1.3.2">` in `package-info.xml`. From SMF's *Admin → Package Manager → Browse Packages*, click **Apply Upgrade** on the uploaded row; SMF runs the `<upgrade>` block (require-dir + database seed) and skips the `<uninstall>`/`<modification>` steps that would touch SMF core files or wipe data.
- **`NChatMess.php` and `NChatMuteList.php` are no longer shipped inside the zip.** `<require-dir>` used to overwrite the live message store with the packaged stub on every install and upgrade. On fresh installs, `install.php` seeds those files only when they don't exist; on upgrades, the code files land in `NChat/` and the data files are left alone.
- **Clicking `Install` from the upload preview when NChat is already installed now aborts with an explanation.** A new `<code>install-check.php</code>` step runs before `<modification>` and `<require-dir>`: if 1.3.2 is detected it tells you to use *Apply Upgrade* instead; if any other version is detected it tells you to uninstall first (warning that uninstall deletes `NChatMess.php`).
- `install.php` uses `db_insert('ignore', …)` throughout, so reinstalling never duplicates settings rows.

## Post-1.4.0 iterations (shipped as `1.4.1`)

### Added
- **Inline BBCode formatting.** Bold/italic/underline/colour are no longer whole-line toggles — the toolbar buttons wrap the current input selection (or drop an empty tag pair at the caret) with `[b]…[/b]`, `[i]…[/i]`, `[u]…[/u]`, `[color=#RRGGBB]…[/color]`, matching SMF's own BBCode. The client-side `nchat_apply_markers` regex expands these to safe HTML on render.
- **Link-on-paste.** Pasting a bare URL into the compose box opens a prompt asking for a display name (pre-filled with the URL itself). Accepting the URL as-is inserts `[url]https://…[/url]`; typing a name inserts `[url=https://…]name[/url]`. Cancelling inserts nothing.
- **Smiley picker modal.** The old inline "More…/Less…" expand-in-place picker was replaced with a centred modal dialog with an × close button, backdrop-click dismiss, and a scrollable grid showing every configured smiley at once.
- **`<upgrade>` package block** + `install-check.php` guard (see Upgrade notes above).
- **Configurable chat-box height.** New `nchat_height` setting (default `400`, clamped to `100`–`2000` px) under *Admin → Modification Settings → NChat Shoutbox*. Fresh installs get the field in the UI; upgraders from 1.3.2 get the DB row seeded and can either edit `ManageSettings.php` to add the picker or set the value straight in `settings` — the renderer falls back to `400` when the row is missing.
- **Per-member chat text size.** New `nchat_text_size` (default `14`, clamped to `10`–`32` px). Two layers, resolved in order:
  1. Members can pick their own value on *Profile → Modify Profile → Look and Layout* — added via the `integrate_theme_options` SMF integration hook, so no SMF core files are edited. The hook is registered in `install.php` and dropped in `uninstall.php`; per-member rows in `{db_prefix}themes` are cleaned up on uninstall.
  2. Site default under *Admin → Modification Settings → NChat Shoutbox* (`nchat_text_size` int field, same UI-picker caveat as `nchat_height` on 1.3.2 upgrades).

  The renderer also emits `-webkit-text-size-adjust:100%;text-size-adjust:100%;` on `#nchat_admin_shoutbox` (stops iOS Safari's automatic text inflation in scrollable containers) and pins `#nchat_input` to `max(nchat_text_size, 16)` px (stops iOS Safari's focus-zoom that never fully unzooms).

### Removed
- **Server-side link title lookup.** The `nchat=title` endpoint and its outbound HTTP fetch (`NChatFetchTitle`, `nchatFetchUrl`, `nchatExtractPageTitle`, meta/og:title extraction) are gone. The paste prompt now shows the URL as the suggested name and never touches the network. Eliminates the SSRF surface where any `nchat_write` user could probe internal networks via the server.
- **Auto-linking of bare URLs** and the `[Label:https://url]` labelled-link syntax. Both the client-side rendering and the `nchat_auto_link` admin setting are removed; use the inline `[url]…[/url]` markers instead. Removed strings: `nchat_auto_link`, `nchat_disable_auto_link`, `nchat_enable_auto_link`.
- **Old smiley "More…/Less…" toggle strings and handlers** (`nchat_more_smileys`, `nchat_less_smileys`, `nchat_show_more_smile`, `nchat_show_no_smile`) — superseded by the modal.
- **`isHex()` helper.** No callers after the whole-line colour mode was retired.
- **Stale dead code**: `nchat_txt_save`, `nchat_txt_cancel` JS globals; a couple of unused `$txt` entries; the `nchat_disable_consensor`/`nchat_enable_consensor` strings.

### Changed
- **Data-file stubs no longer ship in the zip** (see Upgrade notes) — install-only seeding via `install.php` handles fresh installs.
- **Paste-prompt UX**: URL is now shown inside the prompt body so users always see where the link points before naming it. Cancel inserts nothing.
- **`readme.txt`** trimmed to a short SMF-package-style description; feature-level detail moved to this changelog.

### Fixed
- **`nchat_apply_markers` regex** now accepts inline markers anywhere in the message body, not just around the whole line. Legacy `<b>/<i>/<u>` outer wrappers on old rows are rewritten to inline markers when the user opens the row for editing.
- **"Your session timed out" banner on legitimate sends.** Root cause: the 1.4.0 CSRF check relied on SMF's `$_SESSION['session_var']` / `session_value` pair, which is only meaningful when the browser sends the same PHP session cookie on the page render and on the subsequent AJAX POST. In practice that assumption breaks constantly — mobile networks flipping between Wi-Fi and 4G/5G, carrier NAT, Edge's sleeping-tabs waking on a different route, cookie path scoping when the forum lives at a subpath, and cross-scheme redirects can all land AJAX writes on a fresh server session with a different token. There is no client-side workaround because each POST could hit yet another new session. **The check is now cookie- and session-independent:** `nchatCheckSession()` verifies the request came from our own origin using browser-set headers instead of a rotating token. AJAX writes/edits/deletes/mutes must carry `X-Requested-With: XMLHttpRequest` (a header that a cross-site `<form>`, `<img>`, or no-cors `fetch` physically cannot forge — same-origin XHR/fetch is the only way to set it, and CORS preflight blocks the cross-origin case since the server never advertises `Access-Control-Allow-*`). Both AJAX writes and the mutelist removal link additionally require `Origin` (or, as a fallback, `Referer`) to match the current `HTTP_HOST` — modern browsers guarantee `Origin` on every state-changing request and never allow an attacker's page to override it. Result: writes/edits/deletes/mutes just work on any network with any ping, latency, or routing quirks, while the CSRF surface is at least as strong as the old token check — in fact stronger, because the origin/header pair also blocks CSRF attempts that ride on a valid session cookie (session-fixation-style attacks the old check would have accepted). Client-side token refresh, retry queue, `nchatSession` variable, and the `nchat_session_token` server broadcast are gone; the state-change AJAX URLs no longer append `session_var=session_value`. The auth-mismatch soft-fail (two consecutive `nchat_current_uid=0` reads required to lock) is retained as a genuine "you were logged out" signal.

### Security
- **`unserialize()` hardened.** `nchatDecodeStore()` now passes `['allowed_classes' => false]` when decoding `NChatMess.php` / `NChatMuteList.php`, so any serialized object planted in the store files is refused before it can trigger a `__wakeup`/`__destruct` gadget chain. The legitimate stores only ever hold arrays of scalars, so no functional impact.

### Changed
- Version bumped `1.4.0` → `1.4.1` in `install.xml` and `package-info.xml`.
- `package-info.xml` gains a second `<upgrade from="1.4.0">` block (code-only refresh — no `<modification>` and no `<database>` because there are no SMF file mods or DB changes between 1.4.0 and 1.4.1).
- `install-check.php` recognises 1.4.0 as an upgrade-eligible source and steers the admin to *Apply Upgrade* rather than allowing a second *Install* over an existing 1.4.0 install.

