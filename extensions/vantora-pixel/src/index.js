import { register } from "@shopify/web-pixels-extension";

/**
 * F-21 Basic Analytics / F-38 Funnel Analytics data source. Subscribes to
 * Shopify's standard Customer Events (no custom event wiring needed) and
 * reports them to the app's own ingestion endpoint. This runs in Shopify's
 * sandboxed pixel context, which can only reach the network directly (not
 * through the shop's own domain), so it POSTs straight to the Laravel app
 * rather than through the signed App Proxy that the theme blocks use.
 *
 * Order-level revenue attribution to a specific Vantora feature relies on
 * line item properties the theme blocks set when they add to cart (see
 * `_vantora_source` in sticky-add-to-cart.liquid and free-gift.liquid) --
 * checkout_completed line items carry that property through to checkout
 * (Checkout Extensibility shops only), and the backend reads it there.
 *
 * Covers all customer-journey events Shopify's Web Pixels API exposes that
 * are useful for future campaign targeting (page_viewed, collection_viewed,
 * search_submitted, product_removed_from_cart, in addition to the checkout
 * funnel events above) -- everything PixelEventController now persists in
 * full (events.data), not just counted.
 *
 * "Holding time" / time-on-page isn't something this pixel can measure
 * directly: it runs in Shopify's `strict` sandbox, which has no access to
 * `document`/`window`/`navigator` (confirmed against the Web Pixels API
 * docs -- only the `analytics`/`browser`/`init`/`settings` objects passed
 * into register() are available, no visibilitychange/pagehide/sendBeacon).
 * The standard way analytics platforms derive it without that access is
 * from the gap between consecutive events' timestamps within the same
 * clientId session, which page_viewed's occurred_at now supports server-side.
 */
register(({ analytics, init }) => {
  const endpoint = "https://vantora-production.up.railway.app/pixel/events";
  const shop = init?.data?.shop?.myshopifyDomain;

  function report(type, event, data) {
    if (!shop) return;

    fetch(endpoint, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      keepalive: true,
      body: JSON.stringify({
        shop,
        type,
        client_id: event.clientId,
        occurred_at: new Date().toISOString(),
        data,
      }),
    }).catch(() => {});
  }

  analytics.subscribe("page_viewed", (event) => {
    const doc = event.context?.document;
    const win = event.context?.window;
    report("page_viewed", event, {
      url: doc?.location?.href,
      path: doc?.location?.pathname,
      title: doc?.title,
      referrer: doc?.referrer,
      // Device split (mobile vs desktop), same breakpoint the theme
      // blocks' own CSS uses (max-width: 749px) -- no separate device
      // API in this sandbox, so viewport width is the proxy.
      viewport_width: win?.innerWidth ?? null,
    });
  });

  analytics.subscribe("collection_viewed", (event) => {
    report("collection_viewed", event, {
      collection_id: event.data?.collection?.id,
      collection_title: event.data?.collection?.title,
    });
  });

  analytics.subscribe("search_submitted", (event) => {
    report("search_submitted", event, {
      query: event.data?.searchResult?.query,
      result_count: event.data?.searchResult?.productVariants?.length ?? null,
    });
  });

  analytics.subscribe("product_viewed", (event) => {
    report("product_viewed", event, {
      product_id: event.data?.productVariant?.product?.id,
      product_title: event.data?.productVariant?.product?.title,
    });
  });

  analytics.subscribe("product_added_to_cart", (event) => {
    report("product_added_to_cart", event, {
      product_id: event.data?.cartLine?.merchandise?.product?.id,
      quantity: event.data?.cartLine?.quantity,
    });
  });

  analytics.subscribe("product_removed_from_cart", (event) => {
    report("product_removed_from_cart", event, {
      product_id: event.data?.cartLine?.merchandise?.product?.id,
      quantity: event.data?.cartLine?.quantity,
    });
  });

  analytics.subscribe("cart_viewed", (event) => {
    report("cart_viewed", event, {
      total: event.data?.cart?.cost?.totalAmount?.amount,
    });
  });

  analytics.subscribe("checkout_started", (event) => {
    report("checkout_started", event, {
      total: event.data?.checkout?.totalPrice?.amount,
    });
  });

  analytics.subscribe("checkout_completed", (event) => {
    const checkout = event.data?.checkout;

    report("checkout_completed", event, {
      order_id: checkout?.order?.id,
      total: checkout?.totalPrice?.amount,
      currency: checkout?.totalPrice?.currencyCode,
      line_items: (checkout?.lineItems ?? []).map((line) => ({
        product_id: line.variant?.product?.id,
        quantity: line.quantity,
        price: line.variant?.price?.amount,
        // Set by our theme blocks via cart/add.js line item properties.
        // Only present on Checkout Extensibility shops -- see CheckoutLineItem.properties.
        vantora_source: (line.properties ?? []).find((p) => p.key === "_vantora_source")?.value ?? null,
      })),
    });
  });
});
