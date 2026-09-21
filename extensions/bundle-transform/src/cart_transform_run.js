// @ts-check

/**
 * @typedef {import("../generated/api").CartTransformRunInput} CartTransformRunInput
 * @typedef {import("../generated/api").CartTransformRunResult} CartTransformRunResult
 */

/**
 * @type {CartTransformRunResult}
 */
const NO_CHANGES = {
  operations: [],
};

/**
 * F-18 Bundles (fixed bundle mode). The merchant sells a single "bundle"
 * product; when it's in the cart, this expands that one line into its
 * component variant lines so inventory and fulfillment track correctly per
 * component. Component list comes from a product metafield the app writes
 * when the bundle feature_config is activated (`$app:bundle-components`),
 * not from the cart -- Cart Transform functions can't see feature_configs
 * directly, only what's exposed on cart/product/shop metafields.
 *
 * Pricing is left as the components' own prices; the bundle-discount
 * Function (a Discount Function) applies the price break on top of this
 * expansion, per spec section 2: "Use expand/merge for bundle lines and a
 * Discount Function for price reductions" (never the Plus-only `update`
 * operation).
 *
 * @param {CartTransformRunInput} input
 * @returns {CartTransformRunResult}
 */
export function cartTransformRun(input) {
  const operations = [];

  for (const line of input.cart.lines) {
    if (line.merchandise.__typename !== "ProductVariant") continue;

    const raw = line.merchandise.product?.metafield?.value;
    if (!raw) continue;

    let components;
    try {
      components = JSON.parse(raw);
    } catch {
      continue;
    }

    if (!Array.isArray(components) || components.length === 0) continue;

    operations.push({
      lineExpand: {
        cartLineId: line.id,
        expandedCartItems: components.map((component) => ({
          merchandiseId: component.variantId,
          quantity: component.quantity * line.quantity,
        })),
      },
    });
  }

  if (operations.length === 0) {
    return NO_CHANGES;
  }

  return { operations };
}
