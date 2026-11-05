# WPBot SEO & AI Search (core module)

"Get found on Google and in AI answers." Replaces the old SEO Analyzer in WPBot. This repository holds the **core module**, bundled unchanged by WPBot Pro now (and WPBot Free later) at `includes/seo-ai-search/`. Pro-only features live in [qcld/wpbot-seo-pro](https://github.com/qcld/wpbot-seo-pro).

- Plan: [docs/feature-plan.md](docs/feature-plan.md) · Progress: [docs/progress.md](docs/progress.md) · Brief: [docs/build-brief.md](docs/build-brief.md)
- Requires WPBot Core (shared services) with the SEO additions, PHP 7.4+, WordPress 6.2+.

## Development
```
composer install && npm install
composer run lint     # PHPCS (WordPress + PHPCompatibility 7.4)
composer run test     # PHPUnit
npm run build         # admin app
```

## External services
| Service | When | What is sent |
| --- | --- | --- |
| Google Search Console API (owner's own Google app, read-only) | Import and nightly update, URL Inspection, sitemaps | Read-only API requests for the owner's property |
| Google Analytics Admin and Data API (owner's own Google app, read-only, optional) | Daily pull | Report requests for the chosen property |
| Google PageSpeed Insights API (optional key) | Speed checks | Page URLs |
| Bing Webmaster Tools API (owner's key, optional) | Link counts | Site and page URLs |
| AI providers through WPBot Core (owner's keys) | Fixes, drafts, readiness, AI visibility | Page text needed for the task, tracked prompts; no personal data |
| DataForSEO (api.dataforseo.com), Keywords Everywhere (api.keywordseverywhere.com), Semrush (api.semrush.com) — owner's own key, optional | Only when the owner runs a lookup (cost shown first) or turns on the Pro monthly refresh; billed by the provider to the owner's account | Keywords, country and language, the site's domain, competitor domains |
| External sites | Link checks, prospect pages | Polite requests identifying WPBot and the site URL; robots.txt respected |
| Competitor sites (round 2) | Only when someone clicks "Suggest competitors" (and gives what to compete on and the kind of competitor), the automatic suggestion on an empty Competitor gap field once that brief was given (once per brief), or the Pro monthly re-check | One request to each suggested competitor's home page, to check that it exists (a WPBot user agent with the site URL) |
| Google Ads API — Keyword Planner (Pro, owner's own Google app; its Google Cloud project needs Basic or Standard access) | Keyword lookups and ideas, the Vault fill and monthly refresh, topic gap volumes | Keywords, a seed keyword or site, country and language; read-only planning requests, nothing changes in the Ads account |
| The site itself (llms.txt, round 2) | "Check live file" (on a click, daily and after changes) and the Pro daily link check | Requests to the site's own /llms.txt and listed pages, as a logged-out visitor |
| Bing Webmaster Tools keyword API (round 3, owner's Bing key, free) | Keyword volumes and ideas when no paid keyword provider is set; Get cited by AI searches | Keywords, country and language |
| YouTube Data API (round 3, owner's own Google app, read-only, optional) | Only after the owner turns on YouTube in Get cited by AI and reconnects Google, when places are found | The search text |
| Google Business Profile APIs (round 3, owner's own Google app, optional) | Only after the owner clicks "Connect Business Profile" and reconnects Google: profile health check, reading reviews (on a click and, in Pro, daily), posting a reply the owner wrote or approved, Pro performance numbers | Read requests for the owner's accounts and locations; the reply text the owner posts |
| Pages of other sites (round 3, Get cited by AI) | When the owner asks for a draft for a place | One request to that page (a WPBot user agent; private addresses refused) to read the thread; personal data is removed before any AI call |
| The owner's AI provider, with uploaded documents (WPBot > Business Context) | Only when the owner uploads a document | The document's text with personal data removed; a PDF is sent as a file to providers that read PDFs (OpenAI, Claude, Gemini, OpenRouter). The file is deleted from the site right after |
| Customers' email (round 3, Pro, WooCommerce) | Only when the owner turns on review requests: one email per completed order, after the delay set | Sent by the site's own mail (wp_mail) to the order's billing email, with Google's review link and an unsubscribe link; nothing is sent to an outside service |

## Third-party libraries
| Library | Licence | Source | Used for |
| --- | --- | --- | --- |
| qrcode-generator 2.0.4 (Kazuhiko Arase) | MIT (GPL-compatible) | https://github.com/kazuhikoarase/qrcode-generator (npm `qrcode-generator`) | The QR code of the Google review link in Local SEO > Reviews, made in the browser |

Licence: GPL-2.0-or-later.
