/**
 * F-21 Basic Analytics: reports impression/click events for Vantora blocks
 * that Shopify's standard Customer Events don't cover (only the block
 * itself knows when it rendered or was interacted with). Shared across
 * blocks via asset_url so the reporting logic lives in one place.
 */
window.VantoraAnalytics = window.VantoraAnalytics || {
  report: function (type, featureType) {
    if (!window.Shopify || !Shopify.shop) return;

    // Must be absolute: this script runs on the shop's own storefront
    // domain, and a relative '/pixel/events' resolves there instead of
    // the backend -- 404s every time (confirmed live in a merchant's
    // browser console), silently losing every impression/click this file
    // was supposed to report for all 7 theme blocks.
    fetch('https://vantora-production.up.railway.app/pixel/events', {
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
