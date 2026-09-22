/**
 * Hydrates every placeholder element a merchant has pasted into their
 * theme -- anywhere, including directly inside their cart drawer
 * template. A merchant asked for something simpler than either app
 * blocks (can't reach most themes' cart drawer markup) or full
 * copy-paste code: just a bare element, nothing else.
 *
 *   <div class="vantora-shipping-bar"></div>
 *   <div class="vantora-cart-goal-tracker"></div>
 *
 * Classes, not IDs: a cart drawer's markup is typically included
 * globally (present on every page, not just /cart), so if a merchant
 * also wants a copy on the dedicated cart page, BOTH placeholders exist
 * in the same page's DOM at once. IDs must be unique per page --
 * getElementById would only ever find and hydrate the first one,
 * leaving the second silently empty. querySelectorAll + a loop hydrates
 * however many instances of each placeholder exist on a given page.
 *
 * Settings come from window.__VANTORA_SETTINGS__, inlined by
 * vantora-cart-widgets.liquid from the same `vantora` shop metafield the
 * admin GUI writes to -- this file has no Liquid access of its own. A
 * placeholder for a tool that isn't configured/active is left untouched
 * (empty), not an error.
 */
(function () {
  var SETTINGS = window.__VANTORA_SETTINGS__ || {};

  function money(cents) {
    return (cents / 100).toLocaleString(undefined, {
      style: 'currency',
      currency: (window.Shopify && Shopify.currency && Shopify.currency.active) || 'USD',
    });
  }

  function forEachPlaceholder(className, fn) {
    document.querySelectorAll('.' + className).forEach(function (el) {
      if (el.dataset.vantoraHydrated) return;
      el.dataset.vantoraHydrated = '1';
      fn(el);
    });
  }

  function initShippingBar(el) {
    var v = SETTINGS.shipping_bar;
    if (!v) return;

    el.innerHTML =
      '<div style="padding:10px 16px;background:' + (v.background_color || '#f4f4f4') + ';color:' + (v.text_color || '#111') + ';text-align:center;font-size:14px;">' +
        '<p class="vantora-w__message" style="margin:0 0 6px;"></p>' +
        '<div style="display:block;box-sizing:border-box;width:100%;height:6px;border-radius:3px;background:rgba(0,0,0,.15);overflow:hidden;">' +
          '<div class="vantora-w__fill" style="display:block;box-sizing:border-box;height:100%;width:0%;border-radius:3px;background:' + (v.progress_color || '#1a7f37') + ';transition:width .3s ease;"></div>' +
        '</div>' +
      '</div>';

    if (window.VantoraAnalytics) VantoraAnalytics.report('impression', 'shipping_bar');

    var thresholdCents = Math.round((v.threshold || 0) * 100);
    var message = el.querySelector('.vantora-w__message');
    var fill = el.querySelector('.vantora-w__fill');

    function render(cartTotal) {
      var pct = thresholdCents > 0 ? Math.min(100, (cartTotal / thresholdCents) * 100) : 100;
      fill.style.width = pct + '%';
      if (cartTotal >= thresholdCents) {
        message.textContent = v.success_message || '';
      } else {
        message.textContent = (v.progress_message || '').replace('%%AMOUNT%%', money(thresholdCents - cartTotal));
      }
    }

    function refresh() {
      fetch('/cart.js').then(function (r) { return r.json(); }).then(function (cart) { render(cart.total_price); }).catch(function () {});
    }

    refresh();
    document.addEventListener('vantora:cart-updated', refresh);
    document.addEventListener('cart:refresh', refresh);
  }

  function initGoalTracker(el) {
    var v = SETTINGS.goal_tracker;
    if (!v) return;

    var tiers = [];
    [1, 2, 3].forEach(function (i) {
      var threshold = v['tier_' + i + '_threshold'];
      if (threshold > 0) tiers.push({ threshold: threshold, label: v['tier_' + i + '_label'] });
    });
    tiers.sort(function (a, b) { return a.threshold - b.threshold; });
    if (!tiers.length) return;

    el.innerHTML =
      '<div style="padding:10px 16px;background:' + (v.background_color || '#f4f4f4') + ';color:' + (v.text_color || '#111') + ';text-align:center;font-size:14px;">' +
        '<p class="vantora-w__message" style="margin:0 0 8px;"></p>' +
        '<div style="display:block;position:relative;box-sizing:border-box;width:100%;height:6px;border-radius:3px;background:rgba(0,0,0,.15);">' +
          '<div class="vantora-w__fill" style="display:block;box-sizing:border-box;height:100%;width:0%;border-radius:3px;background:' + (v.progress_color || '#1a7f37') + ';transition:width .3s ease;"></div>' +
          '<div class="vantora-w__markers" style="position:absolute;inset:0;"></div>' +
        '</div>' +
      '</div>';

    if (window.VantoraAnalytics) VantoraAnalytics.report('impression', 'goal_tracker');

    var maxThreshold = tiers[tiers.length - 1].threshold;
    var message = el.querySelector('.vantora-w__message');
    var fill = el.querySelector('.vantora-w__fill');
    var markers = el.querySelector('.vantora-w__markers');

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
      var nextTier = tiers.filter(function (t) { return cartTotal < t.threshold; })[0];
      if (nextTier) {
        message.textContent = 'Add ' + money((nextTier.threshold - cartTotal) * 100) + ' more to unlock: ' + nextTier.label;
      } else {
        message.textContent = tiers[tiers.length - 1].label + ' unlocked!';
      }
    }

    function refresh() {
      fetch('/cart.js').then(function (r) { return r.json(); }).then(function (cart) { render(cart.total_price); }).catch(function () {});
    }

    refresh();
    document.addEventListener('vantora:cart-updated', refresh);
    document.addEventListener('cart:refresh', refresh);
  }

  forEachPlaceholder('vantora-shipping-bar', initShippingBar);
  forEachPlaceholder('vantora-cart-goal-tracker', initGoalTracker);
})();
