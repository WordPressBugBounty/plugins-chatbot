/**
 * Hide builder entries that have no backend yet.
 *
 * The server sends window.wpbotAutomator.support = { apps: [...], actions: { app: [...] } }
 * (Registry::get_ui_support()). Apps missing from `apps` are hidden; for apps listed in
 * `actions`, only those action/trigger IDs stay. Without that data the catalog is unchanged.
 */
export function filterSupported(apps) {
  const support = typeof window !== "undefined" ? window.wpbotAutomator?.support : null;
  if (!support || !Array.isArray(support.apps)) {
    return apps;
  }

  const available = new Set(support.apps);
  const allowed = support.actions || {};

  return apps
    .filter((app) => available.has(app.id))
    .map((app) => {
      const ids = allowed[app.id];
      if (!Array.isArray(ids)) {
        return app;
      }
      const keep = new Set(ids);
      const next = { ...app };
      if (app.categories) {
        next.categories = app.categories
          .map((cat) => ({ ...cat, actions: (cat.actions || []).filter((a) => keep.has(a.id)) }))
          .filter((cat) => cat.actions.length > 0);
      }
      if (app.actions) {
        next.actions = app.actions.filter((a) => keep.has(a.id));
      }
      return next;
    });
}

/**
 * Whether every node of a template uses an app/action the backend can run.
 * Templates that need unbuilt apps (e.g. WhatsApp) are hidden instead of failing at run time.
 */
export function isTemplateSupported(template) {
  const support = typeof window !== "undefined" ? window.wpbotAutomator?.support : null;
  if (!support || !Array.isArray(support.apps)) {
    return true;
  }
  const available = new Set(support.apps);
  const allowed = support.actions || {};
  const nodes = template?.workflow_data?.nodes || [];

  return nodes.every((node) => {
    const appId = node.appData?.id || node.data?.appId;
    if (!appId || !available.has(appId)) {
      return false;
    }
    const ids = allowed[appId];
    const actionId = node.data?.actionId;
    return !Array.isArray(ids) || !actionId || ids.includes(actionId);
  });
}
