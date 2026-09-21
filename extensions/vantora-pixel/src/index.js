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
 * `_vantora_source` in sticky-add-to-cart.liquid, cart-upsell.liquid and
 * frequently-bought-together.liquid) -- checkout_completed line items carry
 * that property through to checkout (Checkout Extensibility shops only),
 * and the backend reads it there.
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

  analytics.subscribe("product_viewed", (event) => {
    report("product_viewed", event, {
      product_id: event.data?.productVariant?.product?.id,
    });
  });

  analytics.subscribe("product_added_to_cart", (event) => {
    report("product_added_to_cart", event, {
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
