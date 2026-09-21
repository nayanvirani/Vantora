// @ts-check
import { DiscountApplicationStrategy } from "../generated/api";

/**
 * @typedef {import("../generated/api").RunInput} RunInput
 * @typedef {import("../generated/api").FunctionRunResult} FunctionRunResult
 */

/**
 * @type {FunctionRunResult}
 */
const EMPTY_DISCOUNT = {
  discountApplicationStrategy: DiscountApplicationStrategy.All,
  discounts: [],
};

/**
 * F-18 Bundles (Fixed bundle / Mix & Match / Frequently Bought Together).
 * Pairs with a Cart Transform (expand/merge) on the bundle line grouping;
 * this function applies the price break once every component product is
 * present at its required quantity. `bundleMultiplier` scales the discount
 * when the cart holds more than one full set of components.
 *
 * Configuration written by the app to the `$app:bundle-discount` metafield
 * on the discount node, shaped as:
 * {
 *   "components": [
 *     { "productId": "gid://shopify/Product/1", "quantity": 1 },
 *     { "productId": "gid://shopify/Product/2", "quantity": 1 }
 *   ],
 *   "discountType": "percentage" | "fixed_amount",
 *   "discountValue": 15,
 *   "message": "Bundle discount applied"
 * }
 *
 * @param {RunInput} input
 * @returns {FunctionRunResult}
 */
export function run(input) {
  const configuration = JSON.parse(
    input?.discountNode?.metafield?.value ?? "{}"
  );

  const components = Array.isArray(configuration.components) ? configuration.components : [];
  const discountType = configuration.discountType;
  const discountValue = configuration.discountValue;

  if (components.length === 0 || !discountType || !discountValue) {
    return EMPTY_DISCOUNT;
  }

  const lines = input?.cart?.lines ?? [];
  const variantLine = (line) => line.merchandise.__typename === "ProductVariant";

  const qtyByProduct = (productId) =>
    lines
      .filter(variantLine)
      .filter((line) => line.merchandise.product.id === productId)
      .reduce((sum, line) => sum + line.quantity, 0);

  const bundleMultiplier = Math.min(
    ...components.map((c) => Math.floor(qtyByProduct(c.productId) / c.quantity))
  );

  if (!Number.isFinite(bundleMultiplier) || bundleMultiplier < 1) {
    return EMPTY_DISCOUNT;
  }

  const message = configuration.message ?? "Bundle discount applied";
  const discounts = [];
  const fixedTargets = [];

  for (const component of components) {
    let remaining = component.quantity * bundleMultiplier;

    for (const line of lines) {
      if (remaining <= 0) break;
      if (!variantLine(line)) continue;
      if (line.merchandise.product.id !== component.productId) continue;

      const targetQty = Math.min(line.quantity, remaining);
      remaining -= targetQty;

      if (discountType === "percentage") {
        discounts.push({
          message,
          targets: [{ cartLine: { id: line.id, quantity: targetQty } }],
          value: { percentage: { value: discountValue } },
        });
      } else {
        fixedTargets.push({ cartLine: { id: line.id, quantity: targetQty } });
      }
    }
  }

  if (discountType === "fixed_amount" && fixedTargets.length > 0) {
    discounts.push({
      message,
      targets: fixedTargets,
      value: {
        fixedAmount: {
          amount: discountValue * bundleMultiplier,
          appliesToEachItem: false,
        },
      },
    });
  }

  if (discounts.length === 0) {
    return EMPTY_DISCOUNT;
  }

  return {
    discountApplicationStrategy: DiscountApplicationStrategy.All,
    discounts,
  };
}
