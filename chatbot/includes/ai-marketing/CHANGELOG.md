# Changelog

## 2.0.0-dev.1 (in development)

MK0 Foundations of the rebuilt AI Marketing (plan: https://github.com/qcld/botmaster/blob/feature/ai-marketing-v2/docs/ai-marketing/PLAN.md).

- Newest-copy loader, module boot and run modes (essentials, master, off) read from the host.
- Host contract (`Host\Host_Adapter`, `Host\Lead_Source` for the chatbot's saved subscribers).
- New tables `qcld_wpbot_mk_*`: contacts, identities, consents, lists, memberships, events, templates, campaigns, messages (atomic claim), journeys, journey runs, suggestions (Approval inbox), activity log, suppression, legacy map.
- Copy of the old AI Marketing data (`wpbot_crm_*`): background job, idempotent, merges by email, opt-outs always win, nothing resumes sending; the old tables are never changed.
- Old email links (`?wpbot_crm=o|c|u|v`) keep working; new links `?wpbot_mk=…`.
- REST `wpbot-marketing/v1`: status, settings, migration.
- Admin page in WPBot Core's Shell with the React app; Master items shown as previews outside Master mode.
- Uninstall helper: keeps all data unless the last host is uninstalled and the owner opted in; never touches `wpbot_crm_*` or `wpbot_subscription`.
