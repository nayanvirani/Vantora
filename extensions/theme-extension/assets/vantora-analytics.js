/**
 * F-21 Basic Analytics: reports impression/click events for Vantora blocks
 * that Shopify's standard Customer Events don't cover (only the block
 * itself knows when it rendered or was interacted with). Shared across
 * blocks via asset_url so the reporting logic lives in one place.
 */
window.VantoraAnalytics = window.VantoraAnalytics || {
  report: function (type, featureType) {
    if (!window.Shopify || !Shopify.shop) return;

    fetch('/pixel/events', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      keepalive: true,
      body: JSON.stringify({
        shop: Shopify.shop,
        type: type,
        feature_type: featureType,
        occurred_at: new Date().toISOString(),
      }),
    }).catch(function () {});
  },
};
