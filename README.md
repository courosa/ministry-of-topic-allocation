# Ministry of Topic Allocation

A deliberately overdramatic presentation-topic chooser by **Dr. Alec Couros**. One topic. Disproportionate ceremony.

The illustrated academic ministry includes a mechanical split-flap countdown, escalating bureaucratic notices, animated authorization stamps, and first-come topic reservations.

[View the demonstration](https://couros.ca/chooserdemo/)

## What it does

- Opens signup using the server clock, with no manual refresh required.
- Lets a participant enter a recognizable name, choose a topic, and confirm it.
- Uses SQLite transactions to prevent two people claiming the same topic.
- Rejects additional claims associated with the same browser cookie or normalized name.
- Provides a password-protected instructor dashboard with CSV export and individual reservation release.
- Respects reduced-motion preferences.

## Install

Requires PHP 8.1 or newer with PDO SQLite, and an HTTPS web server. Apache can use the included `.htaccess`; configure equivalent rules on other servers. **GitHub Pages cannot run this PHP application.**

1. Upload only the contents of `public/` to your desired web folder, such as `/chooser/`.
2. Create a private writable directory outside every public web document root. Keep the SQLite database and PHP sessions there.
3. Copy `config.example.php` to `config.local.php` on the server. Set `CHOOSER_DATA_DIR` to that private absolute path and `CHOOSER_OPEN_AT` to an ISO 8601 timestamp with an explicit timezone offset. Saskatchewan example: `2026-09-09T18:00:00-06:00`.
4. Generate an administrator password hash locally: `php -r 'echo password_hash(trim(fgets(STDIN)), PASSWORD_DEFAULT), PHP_EOL;'`. Enter your chosen password when prompted. Put the resulting hash in `CHOOSER_ADMIN_PASSWORD_HASH`; do not commit it.
5. Edit `topics.json`, keeping unique IDs. Change the visible opening date in `index.html` to match the configured server date. The repository includes the original course topics as examples.
6. Update course titles, dates, and page text for your own context. Open `admin.php` to manage results.
7. Test with a separate private data directory and earlier opening date before your event. Restore your actual date and use an empty private data directory for the real event.

Environment variables with the same names override the local configuration. Optional `CHOOSER_SESSION_DIR` overrides the default `sessions` subdirectory of the private data directory. Every installation gets a distinct browser cookie and administrator session name.

## Important behavior

The browser restriction is a convenience, not identity verification. Clearing cookies, private browsing, or another device can bypass it. An authenticated identity system is needed for a strict one-person limit. Distinctive names avoid accidental name collisions. Public responses expose taken topic IDs, not participant names.

Changing the opening date does not erase existing reservations. Release test reservations through the dashboard or use a separate test installation. Protect and back up private data appropriately. The included login throttling is session-based; add server-level rate limiting for wider public deployment.

The visual clock follows the server time estimate. Network latency can affect when a visitor sees availability; the server makes the final opening and reservation decision.

## Attribution and licenses

- Code (PHP, JavaScript, HTML markup, CSS, configuration examples): [MIT](LICENSE).
- Written course content, documentation prose, and artwork: [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/). See [CONTENT-LICENSE.md](CONTENT-LICENSE.md).

Suggested credit: “Ministry of Topic Allocation by Dr. Alec Couros, adapted under CC BY 4.0. Code licensed under MIT.” Identify your changes when adapting the content.

The artwork and code were developed with generative AI assistance under Alec Couros’s direction. The content license applies to rights held by the licensor; it does not assert exclusive rights over purely AI-generated material. The crest is fictional, not an official university seal. No participant data, live credentials, or production password hashes are included.

## Publish updates to the original sites

In cPanel **Git Version Control**, manage **Ministry of Topic Allocation**, select **Pull or Deploy**, click **Update from Remote**, then **Deploy HEAD Commit**. This is a manual deployment workflow. A GitHub commit alone does not change the websites.

The deployment updates all three original installations, makes a private backup, and retains each opening time, visible opening label, demo banner, topics, reservations, browser identity, and administrator settings. Private settings are migrated once to an untracked `config.local.php`. To change topics or dates, update each installation intentionally. Reusable source is in `public/`.

The `.cpanel.yml` and `deploy.php` target Alec's existing hosting folders. Forks must change these destinations before using them. Only site files are published, never this deployment script or the Git checkout.
