/**
 * Shared add-to-cart helper for every Vantora block that adds an item
 * (sticky-add-to-cart, free-gift).
 * A merchant reported Add to cart not opening their cart drawer even with
 * it enabled -- confirmed live: raw fetch('/cart/add.js') has no way to
 * know a drawer exists or how to open it, since Shopify doesn't define a
 * universal "open the cart drawer" API (checked against shopify.dev --
 * cart drawer behavior is entirely theme-specific).
 *
 * Dawn (and themes forked from it, which is a large share of Online
 * Store 2.0 themes) defines a `<cart-drawer>` custom element with an
 * `open()` method, and its own add-to-cart flow fetches
 * '/cart/add.js?sections=cart-drawer,cart-icon-bubble' so the drawer's
 * contents come back in the same response -- verified directly against
 * this store's actual theme assets, not assumed. This detects that
 * element and replicates the same open+render call when present;
 * standard cart:refresh/vantora:cart-updated events still fire either
 * way for any theme/listener that already relies on those.
 */
/**
 * Cart watcher: a merchant reported progress bars (shipping bar, goal
 * tracker) not updating in real time -- confirmed cause: they only
 * refreshed on our own vantora:cart-updated/cart:refresh events, which
 * only fire when *our* code adds to cart. A shopper using the theme's own
 * native +/- quantity controls or remove button in the cart drawer never
 * touches our code at all, so nothing told the bar to refresh.
 *
 * Fix is theme-agnostic rather than guessing at Dawn's (or any theme's)
 * own event conventions: every cart mutation, from anywhere, has to go
 * through Shopify's cart AJAX endpoints (/cart/add, /cart/change,
 * /cart/update, /cart/clear) -- that's the actual protocol-level
 * guarantee, not a theme convention. Patching window.fetch once to watch
 * for those URLs catches every mutation regardless of who triggered it.
 */
(function () {
  if (window.__vantoraCartWatcherInstalled) return;
  window.__vantoraCartWatcherInstalled = true;

  var originalFetch = window.fetch;
  var CART_MUTATION_RE = /\/cart\/(add|change|update|clear)(\.js)?(\?|$)/;

  window.fetch = function (input, init) {
    var url = typeof input === 'string' ? input : (input && input.url) || '';
    var isMutation = CART_MUTATION_RE.test(url);

    return originalFetch.apply(this, arguments).then(function (response) {
      if (isMutation && response.ok) {
        document.dispatchEvent(new CustomEvent('vantora:cart-updated'));
        document.dispatchEvent(new CustomEvent('cart:refresh'));
      }
      return response;
    });
  };
})();

window.VantoraCart = window.VantoraCart || {
  /**
   * options.openDrawer defaults to true (a shopper just clicked an Add
   * button and expects to see the cart). free-gift.liquid adds silently
   * in the background whenever the cart happens to already qualify --
   * popping the drawer open with no click to explain it would be jarring
   * there, so it passes { openDrawer: false } and just keeps the drawer's
   * contents in sync if it's already open.
   */
  add: function (item, options) {
    options = options || {};
    var openDrawer = options.openDrawer !== false;
    var cartDrawer = document.querySelector('cart-drawer');
    var body = Object.assign({}, item);
    if (cartDrawer) {
      body.sections = 'cart-drawer,cart-icon-bubble';
    }

    return fetch('/cart/add.js', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (data.status) {
          throw new Error(data.description || data.message || 'Could not add to cart');
        }

        if (cartDrawer && data.sections) {
          try {
            if (data.sections['cart-drawer']) {
              var drawerDoc = new DOMParser().parseFromString(data.sections['cart-drawer'], 'text/html');
              var newInner = drawerDoc.querySelector('#CartDrawer');
              var currentInner = document.querySelector('#CartDrawer');
              if (newInner && currentInner) currentInner.innerHTML = newInner.innerHTML;
            }
            if (data.sections['cart-icon-bubble']) {
              var bubbleDoc = new DOMParser().parseFromString(data.sections['cart-icon-bubble'], 'text/html');
              var newBubble = bubbleDoc.querySelector('.shopify-section');
              var currentBubble = document.getElementById('cart-icon-bubble');
              if (newBubble && currentBubble) currentBubble.innerHTML = newBubble.innerHTML;
            }
          } catch (e) {
            // Best-effort re-render -- opening the drawer below still works even if this fails.
          }
        }

        if (openDrawer && cartDrawer && typeof cartDrawer.open === 'function') {
          cartDrawer.open();
        }

        document.dispatchEvent(new CustomEvent('vantora:cart-updated'));
        document.dispatchEvent(new CustomEvent('cart:refresh'));

        return data;
      });
  },
};
