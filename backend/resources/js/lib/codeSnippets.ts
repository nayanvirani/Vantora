/**
 * Copy-pasteable Liquid+JS for tools whose ideal placement (the cart
 * drawer) most themes don't expose as an addable section -- confirmed
 * directly against this shop's own theme (Dawn): its cart-drawer.liquid
 * section has no {% schema %} at all (it just renders a snippet), so a
 * Shopify app block genuinely cannot reach in there via Theme Editor
 * placement. A merchant asked for raw code they (or their developer) can
 * paste anywhere via Online Store > Themes > Edit code -- including
 * directly inside the theme's own cart drawer file -- instead.
 *
 * Self-contained by design: no asset_url references (those only resolve
 * inside this app's own extension context, not a plain theme file the
 * merchant pastes this into), so the shared analytics/cart-watcher logic
 * is inlined into each snippet rather than loaded from our hosted
 * assets. Reads the same `vantora` shop metafield the app blocks do, so
 * whatever's configured in the app applies here too with no extra setup.
 */

const SHARED_HELPERS = `  window.VantoraAnalytics = window.VantoraAnalytics || {
    report: function (type, featureType) {
      if (!window.Shopify || !Shopify.shop) return;
      fetch('https://vantora-production.up.railway.app/pixel/events', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        keepalive: true,
        body: JSON.stringify({ shop: Shopify.shop, type: type, feature_type: featureType, occurred_at: new Date().toISOString() }),
      }).catch(function () {});
    },
  };

  if (!window.__vantoraCartWatcherInstalled) {
    window.__vantoraCartWatcherInstalled = true;
    var _vantoraFetch = window.fetch;
    var _vantoraCartRe = /\\/cart\\/(add|change|update|clear)(\\.js)?(\\?|$)/;
    window.fetch = function (input, init) {
      var url = typeof input === 'string' ? input : (input && input.url) || '';
      var isMutation = _vantoraCartRe.test(url);
      return _vantoraFetch.apply(this, arguments).then(function (response) {
        if (isMutation && response.ok) {
          document.dispatchEvent(new CustomEvent('vantora:cart-updated'));
          document.dispatchEvent(new CustomEvent('cart:refresh'));
        }
        return response;
      });
    };
  }`;

export const CODE_SNIPPETS: Record<string, string> = {
  shipping_bar: `{% comment %} Vantora Free Shipping Bar -- paste anywhere, e.g. inside snippets/cart-drawer.liquid {% endcomment %}
{% assign v = shop.metafields.vantora.shipping_bar.value %}
{% if v %}
<div id="vantora-shipping-bar" data-threshold-cents="{{ v.threshold | times: 100 | round }}"
  style="padding:10px 16px;background:{{ v.background_color }};color:{{ v.text_color }};text-align:center;font-size:14px;">
  <p style="margin:0 0 6px;"></p>
  <div style="display:block;box-sizing:border-box;width:100%;height:6px;border-radius:3px;background:rgba(0,0,0,.15);overflow:hidden;">
    <div style="display:block;box-sizing:border-box;height:100%;width:0%;border-radius:3px;background:{{ v.progress_color }};transition:width .3s ease;"></div>
  </div>
</div>
<script>
${SHARED_HELPERS}

  (function () {
    var el = document.getElementById('vantora-shipping-bar');
    if (!el) return;
    if (window.VantoraAnalytics) VantoraAnalytics.report('impression', 'shipping_bar');

    var thresholdCents = parseInt(el.dataset.thresholdCents, 10) || 0;
    var progressTemplate = {{ v.progress_message | json }};
    var successTemplate = {{ v.success_message | json }};
    var message = el.querySelector('p');
    var fill = el.querySelector('div > div');

    function money(cents) {
      return (cents / 100).toLocaleString(undefined, { style: 'currency', currency: (window.Shopify && Shopify.currency && Shopify.currency.active) || 'USD' });
    }

    function render(cartTotal) {
      var pct = thresholdCents > 0 ? Math.min(100, (cartTotal / thresholdCents) * 100) : 100;
      fill.style.width = pct + '%';
      if (cartTotal >= thresholdCents) {
        message.textContent = successTemplate;
      } else {
        message.textContent = progressTemplate.replace('%%AMOUNT%%', money(thresholdCents - cartTotal));
      }
    }

    function refresh() {
      fetch('/cart.js').then(function (r) { return r.json(); }).then(function (cart) { render(cart.total_price); });
    }

    refresh();
    document.addEventListener('vantora:cart-updated', refresh);
    document.addEventListener('cart:refresh', refresh);
  })();
</script>
{% endif %}`,

  goal_tracker: `{% comment %} Vantora Cart Goal Tracker -- paste anywhere, e.g. inside snippets/cart-drawer.liquid {% endcomment %}
{% assign v = shop.metafields.vantora.goal_tracker.value %}
{% if v %}
<div id="vantora-goal-tracker"
  data-tiers='[
    {% if v.tier_1_threshold > 0 %}{"threshold": {{ v.tier_1_threshold }}, "label": {{ v.tier_1_label | json }}}{% endif %}
    {% if v.tier_2_threshold > 0 %},{"threshold": {{ v.tier_2_threshold }}, "label": {{ v.tier_2_label | json }}}{% endif %}
    {% if v.tier_3_threshold > 0 %},{"threshold": {{ v.tier_3_threshold }}, "label": {{ v.tier_3_label | json }}}{% endif %}
  ]'
  style="padding:10px 16px;background:{{ v.background_color }};color:{{ v.text_color }};text-align:center;font-size:14px;">
  <p style="margin:0 0 8px;"></p>
  <div style="display:block;position:relative;box-sizing:border-box;width:100%;height:6px;border-radius:3px;background:rgba(0,0,0,.15);">
    <div style="display:block;box-sizing:border-box;height:100%;width:0%;border-radius:3px;background:{{ v.progress_color }};transition:width .3s ease;"></div>
    <div style="position:absolute;inset:0;"></div>
  </div>
</div>
<script>
${SHARED_HELPERS}

  (function () {
    var el = document.getElementById('vantora-goal-tracker');
    if (!el) return;

    var tiers;
    try { tiers = JSON.parse(el.dataset.tiers).sort(function (a, b) { return a.threshold - b.threshold; }); }
    catch (e) { tiers = []; }
    if (!tiers.length) { el.hidden = true; return; }

    if (window.VantoraAnalytics) VantoraAnalytics.report('impression', 'goal_tracker');

    var maxThreshold = tiers[tiers.length - 1].threshold;
    var message = el.querySelector('p');
    var fill = el.querySelector('div > div');
    var markers = el.querySelector('div > div + div');

    function money(cents) {
      return (cents / 100).toLocaleString(undefined, { style: 'currency', currency: (window.Shopify && Shopify.currency && Shopify.currency.active) || 'USD' });
    }

    function render(cartTotalCents) {
      var cartTotal = cartTotalCents / 100;
      fill.style.width = Math.min(100, (cartTotal / maxThreshold) * 100) + '%';
      markers.innerHTML = '';
      tiers.forEach(function (tier) {
        var marker = document.createElement('div');
        marker.style.cssText = 'position:absolute;top:-3px;width:2px;height:12px;background:rgba(0,0,0,.25);';
        marker.style.left = Math.min(100, (tier.threshold / maxThreshold) * 100) + '%';
        markers.appendChild(marker);
      });
      var nextTier = tiers.find(function (t) { return cartTotal < t.threshold; });
      if (nextTier) {
        message.textContent = 'Add ' + money((nextTier.threshold - cartTotal) * 100) + ' more to unlock: ' + nextTier.label;
      } else {
        message.textContent = tiers[tiers.length - 1].label + ' unlocked!';
      }
    }

    function refresh() {
      fetch('/cart.js').then(function (r) { return r.json(); }).then(function (cart) { render(cart.total_price); });
    }

    refresh();
    document.addEventListener('vantora:cart-updated', refresh);
    document.addEventListener('cart:refresh', refresh);
  })();
</script>
{% endif %}`,

  cart_upsell: `{% comment %} Vantora Cart Upsell -- paste anywhere, e.g. inside snippets/cart-drawer.liquid {% endcomment %}
{% assign v = shop.metafields.vantora.cart_upsell.value %}
{% if v %}
<div id="vantora-cart-upsell" data-max-items="{{ v.max_items }}" style="padding:12px 0;">
  <p style="font-weight:600;margin:0 0 8px;">{{ v.heading }}</p>
  <div class="vantora-cart-upsell__items" style="display:flex;flex-direction:column;gap:8px;" aria-live="polite"></div>
</div>
<script>
${SHARED_HELPERS}

  (function () {
    var root = document.getElementById('vantora-cart-upsell');
    if (!root) return;

    var maxItems = parseInt(root.dataset.maxItems, 10) || 3;
    var container = root.querySelector('.vantora-cart-upsell__items');
    root.hidden = true;

    function render(recommendations) {
      container.innerHTML = '';
      var shown = recommendations.slice(0, maxItems);
      root.hidden = shown.length === 0;
      if (shown.length && window.VantoraAnalytics) VantoraAnalytics.report('impression', 'cart_upsell');

      shown.forEach(function (product) {
        var row = document.createElement('div');
        row.style.cssText = 'display:flex;align-items:center;gap:10px;';
        row.innerHTML =
          '<img src="' + (product.image || '') + '" alt="' + (product.title || '').replace(/"/g, '&quot;') + '" width="48" height="48" loading="lazy" style="border-radius:4px;object-fit:cover;">' +
          '<div style="flex:1;display:flex;flex-direction:column;font-size:13px;">' +
            '<span>' + (product.title || '') + '</span>' +
            '<span>' + (product.price || '') + '</span>' +
          '</div>' +
          '<button type="button" style="background:{{ v.accent_color }};color:#fff;border:0;border-radius:6px;padding:6px 12px;cursor:pointer;font-size:13px;">Add</button>';
        row.querySelector('button').addEventListener('click', function () {
          if (window.VantoraAnalytics) VantoraAnalytics.report('click', 'cart_upsell');
          fetch('/cart/add.js', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: product.variant_id, quantity: 1, properties: { _vantora_source: 'cart_upsell' } }),
          }).then(refresh);
        });
        container.appendChild(row);
      });
    }

    function refresh() {
      fetch('/cart.js')
        .then(function (r) { return r.json(); })
        .then(function (cart) {
          var productIds = cart.items.map(function (item) { return item.product_id; });
          return fetch('/apps/vantora/recommendations?surface=cart&product_ids=' + productIds.join(','));
        })
        .then(function (r) { return r.ok ? r.json() : { recommendations: [] }; })
        .then(function (data) { render(data.recommendations || []); })
        .catch(function () {});
    }

    refresh();
    document.addEventListener('vantora:cart-updated', refresh);
    document.addEventListener('cart:refresh', refresh);
  })();
</script>
{% endif %}`,
};
