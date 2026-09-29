# Contributing to THE WALL

[Deutsch](CONTRIBUTING.de.md)

Contributions are welcome: new panel modes, fixes, better docs, firmware work. This page explains how a contribution travels, how to run everything locally without hardware and without keys, and which checks have to pass.

The technical docs in [`docs/server/`](docs/server) and the project rules in [`CLAUDE.md`](CLAUDE.md) are in German. The step-by-step recipes for new features are in [`docs/server/erweitern.md`](docs/server/erweitern.md). If you work with an AI assistant, point it at those two files first. Claude Code reads `CLAUDE.md` on its own.

## How a contribution travels

1. Fork the repository, create a branch, make your change. Code, docs and checks change together.
2. Open a pull request against `main`. Say what changes and why. For anything on the panel, add a picture made with `node tools/panel-png.mjs frame.json out.png`.
3. The checks in `.github/workflows/checks.yml` run on every pull request. The first pull request of a new contributor waits until the maintainer allows the run.
4. The maintainer reviews it, merges it and deploys it to thewall.godart.lu. Development of that instance happens in a private workshop repository: merged pull requests are taken over there, and later updates come back here as commits named "Update from the workshop".
5. For bigger ideas, open an issue first, so nobody builds the same thing twice.

By contributing you agree that your contribution is published under the licenses of this repository: the MIT License with the Commons Clause for code, CC BY-NC-SA 4.0 for docs and hardware, and the plain MIT License for `homeassistant/`. See [`LICENSE`](LICENSE).

## Run it locally

Everything runs on one machine with SQLite. No hardware, no keys, no config file.

You need:

- PHP 8.5 as command line tool, with pdo_sqlite, mbstring, curl, sodium, openssl, intl, gd and zlib
- Node 18 or newer for the tools
- PlatformIO only for firmware work, Python 3.14 only for the Home Assistant integration

**On Windows** take the zip "VS17 x64 Non Thread Safe" from [windows.php.net](https://windows.php.net/download/), unpack it, copy `php.ini-development` to `php.ini` and remove the semicolon in front of `extension_dir = "ext"` and the lines `extension=curl`, `gd`, `intl`, `mbstring`, `openssl`, `pdo_sqlite` and `sodium`. PHP on Windows has no list of certificate authorities, so outside requests fail until `curl.cainfo` and `openssl.cafile` point to one, for example `cacert.pem` from [curl.se](https://curl.se/docs/caextract.html). Git Bash, which comes with Git for Windows, runs the commands below. If `php` is not the right version, tell the start script which one to use: `PHP_BIN=/c/path/to/php.exe bash tools/dev-server.sh`.

### Server and website

```
bash tools/dev-server.sh
```

The site runs on http://127.0.0.1:8765, the database is `web/.htdata/dev.sqlite` and is created on the first request. Use 127.0.0.1 or localhost in the browser, not a network address: the session cookie is a secure cookie, and browsers only accept it on plain HTTP for these two.

First account:

1. Open http://127.0.0.1:8765/account once. While there is no account, this creates `web/.htdata/secret.php`.
2. Take `setup_token` from that file and open `http://127.0.0.1:8765/account?mode=register&setup=<token>`. The first account becomes admin and is confirmed right away.

Without SMTP every new account is confirmed right away, mails are not sent.

### A device without hardware

- **Test device:** in the admin area under "Accounts", column "Test device". It never polls, but the device page, all settings and the live preview work.
- **Emulated device:** poll like the firmware does, with the API key from `/settings`:

  ```
  curl -s http://127.0.0.1:8765/api/v1/frame -H "Authorization: Bearer <api key>" -H "X-Wall-Id: wall-dev01" -H "X-Wall-Fw: 0.2.1" > frame.json
  node tools/panel-png.mjs frame.json out.png
  ```

  The first request creates the device. It shows a greeting until you press "Apply" on its device page once. At most one frame per second per device. The first time you open the device page of a device that has checked in, a short introduction opens; "Later" closes it. A new device alternates between flight radar and clock every 30 seconds (rotation). To see only the mode you picked, remove the other modes from the rotation on the device page.

### External services

| Service | Locally |
| --- | --- |
| adsb.lol, adsbdb, Open-Meteo, Nominatim | need internet, no key. The local server identifies itself as a development build with the address of this repository |
| Public transport (mobiliteit.lu) | needs a free personal key, see [`docs/server/betrieb.md`](docs/server/betrieb.md). Without it the panel shows NO KEY |
| Spotify | a stand-in: `php -S 127.0.0.1:8766 tools/spotify-mock.php` (needs gd), then start the server with `TW_SPOTIFY_MOCK=http://127.0.0.1:8766`, enter any 32 hex characters as client ID and secret in the admin area and connect on the device page. `/control?mode=pause`, `skip=1`, `delay=1200`, `early=1500` steer it |
| Mail | needs your own SMTP account, otherwise nothing is sent |
| Airline logos | not in this repository, they are trademarks. Without them the panel shows a grey block with the code. `node tools/logos-build.mjs` builds them for your own server |

### Firmware

```
pio run -d firmware -e wall
```

The result is `firmware/.pio/build/wall/firmware.bin`. No keys are needed to build, the device gets its key in its own setup network. To use your own server, build with `-DWALL_SERVER=\"https://your.server\"`: the firmware only talks HTTPS with a certificate from a common authority such as Let's Encrypt, so the local `http://` server does not work for a real device. Details in [`firmware/README.md`](firmware/README.md).

## Checks

Run from the repository root. The same checks run on every pull request.

| Command | What it checks |
| --- | --- |
| `TW_ENV=dev php tools/auth-fixtures.php` | same-origin rules |
| `TW_ENV=dev php tools/device-fixtures.php` | notes, apply, rate limits, frame clock |
| `TW_ENV=dev php tools/flight-fixtures.php` | flight texts, units, map, routes, logos |
| `TW_ENV=dev php tools/timer-fixtures.php` | timers, alarm, ringing, timer corner in every mode |
| `TW_ENV=dev php tools/transit-fixtures.php` | the transit API |
| `TW_ENV=dev php tools/transit-mode-fixtures.php` | the departure board |
| `TW_ENV=dev php tools/spotify-fixtures.php` | covers, texts, transitions |
| `TW_ENV=dev php tools/ha-fixtures.php` | the Home Assistant interface over HTTP, with the local server running |
| `node --check <file>` | every file in `web/assets/js/` and every `tools/*.mjs` |
| `pio run -d firmware -e wall` | the firmware builds |
| in `homeassistant/`: `ruff check .`, `ruff format --check .`, `python -m pytest tests -q` | the integration, after `pip install -r requirements_test.txt ruff` |

A new feature brings its own `check()` lines, see the recipes in [`docs/server/erweitern.md`](docs/server/erweitern.md).

## Rules in short

The full list is in [`CLAUDE.md`](CLAUDE.md).

- Every text for people in English and German: English in the markup, German in `data-de`.
- No em dashes, no emoji, no marketing phrases.
- Accessibility is required: contrast 4.5:1, visible focus, roles for switches, touch targets of 44 pixels, reduced motion respected.
- Never `innerHTML` with values from outside.
- Keys and passwords never in code or firmware. The server fetches everything external, never the device.
- Time zones as IANA names, never a fixed offset.
- Never more than three flashes per second, on the panel too.

## Security

Please do not report security issues in public issues. See [`SECURITY.md`](SECURITY.md).
