# Route map: UI contract → new routes

Namespace for all new routes: `wpbot-crm/v1`. Request/response bodies are exactly as in `API-CONTRACT.md` (# = row in its §4 table).
All routes: `permission_callback` = `current_user_can( 'manage_options' )`, except `POST /subscribe` (public).

| # | Method | Old UI path (`/qcwpbot/v1/…`) | New path (`/wpbot-crm/v1/…`) | Controller | Owner |
|---|---|---|---|---|---|
| 1 | GET | email/analytics/overview | analytics/overview | AnalyticsController | Agent B |
| 2 | GET | email/campaigns | campaigns | CampaignsController | B |
| 3 | POST | email/campaigns | campaigns | CampaignsController | B |
| 4 | GET | email/campaigns/{id} | campaigns/{id} | CampaignsController | B |
| 5 | PUT | email/campaigns/{id} | campaigns/{id} | CampaignsController | B |
| 6 | DELETE | email/campaigns/{id} | campaigns/{id} | CampaignsController | B |
| 7 | POST | email/campaigns/{id}/duplicate | campaigns/{id}/duplicate | CampaignsController | B |
| 8 | POST | email/campaigns/{id}/send | campaigns/{id}/send | CampaignsController | B |
| 9 | POST | email/campaigns/{id}/process | campaigns/{id}/process | CampaignsController | B |
| 10 | POST | email/ai/generate-template | ai/generate-email | AiController | D |
| 11 | POST | email/ai/test-connection | ai/test | AiController | D |
| 12 | GET | email/subscribers | contacts | ContactsController | C |
| 13 | POST | email/subscribers | contacts | ContactsController | C |
| 14 | PUT | email/subscribers/{id} | contacts/{id} | ContactsController | C |
| 15 | POST | email/subscribers/import | contacts/import | ContactsController | C |
| 16–19 | GET/POST/PUT/DELETE | email/lists[/{id}] | lists[/{id}] | GroupsController (type=list) | C |
| 20–23 | GET/POST/PUT/DELETE | email/tags[/{id}] | tags[/{id}] | GroupsController (type=tag) | C |
| 24–27 | GET/POST/PUT/DELETE | email/templates[/{id}] | templates[/{id}] | TemplatesController | B |
| 28–32 | GET/POST/GET/PUT/DELETE | email/automations[/{id}] | automations[/{id}] | AutomationsController | C |
| 33 | PUT | email/automations/{id}/sequences | automations/{id}/steps | AutomationsController | C |
| 34 | GET | email/smtp | smtp | SmtpController | B |
| 35 | POST | email/smtp | smtp | SmtpController | B |
| 36 | GET | email/settings | settings | SettingsController | C |
| 37 | POST | email/settings | settings | SettingsController | C |
| 38 | GET | leadfinder/settings | leads/settings | LeadsController | D |
| 39 | POST | leadfinder/settings | leads/settings | LeadsController | D |
| 40 | GET | email/leads/search | leads/search | LeadsController | D |
| 41 | POST | email/leads/import | leads/import | LeadsController | D |
| 42 | POST | email/leads/autopilot/run | leads/autopilot/run | LeadsController | D |
| 43 | POST | email/leads/autopilot/reset-pointer | leads/autopilot/reset | LeadsController | D |
| 44 | GET | email/thirdparty/status | integrations/status | IntegrationsController | Lead engineer |
| 45 | POST | email/thirdparty/capture | integrations/capture | IntegrationsController | Lead engineer |
| 46 | POST | email/thirdparty/sync | integrations/sync | IntegrationsController | Lead engineer |
| — | POST | (new, shortcode) | subscribe | SubscribeController (public) | C |

Body for #33 keeps the key `sequences` (the UI sends `{ "sequences": [...] }`).
