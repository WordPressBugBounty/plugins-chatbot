/**
 * Node IDs double as the secret in public trigger URLs (Incoming Webhook, Facebook Lead Ads),
 * so they must be unguessable: 32 random hex characters. getRandomValues also works on
 * plain-HTTP admin pages, where crypto.randomUUID is unavailable.
 */
export function newNodeId() {
  const bytes = new Uint8Array(16);
  window.crypto.getRandomValues(bytes);
  return "node-" + Array.from(bytes, (b) => b.toString(16).padStart(2, "0")).join("");
}
