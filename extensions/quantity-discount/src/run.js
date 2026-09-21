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
 * F-17 Quantity Discount. Configuration written by the app to the
 * `$app:quantity-discount` metafield on the discount node, shaped as:
 * {
 *   "tiers": [{ "minQuantity": 2, "percentage": 10 }, { "minQuantity": 3, "percentage": 15 }],
 *   "productIds": ["gid://shopify/Product/123"],   // empty/omitted = applies store-wide
 *   "message": "Buy more, save more"
 * }
 * Tiers are matched by the highest minQuantity a line's quantity satisfies.
 *
 * @param {RunInput} input
 * @returns {FunctionRunResult}
 */
export function run(input) {
  const configuration = JSON.parse(
    input?.discountNode?.metafield?.value ?? "{}"
  );

  const tiers = Array.isArray(configuration.tiers) ? configuration.tiers : [];
  if (tiers.length === 0) {
    return EMPTY_DISCOUNT;
  }

  const sortedTiers = [...tiers].sort((a, b) => b.minQuantity - a.minQuantity);
  const productIds = Array.isArray(configuration.productIds) ? configuration.productIds : [];

  const discounts = [];

  for (const line of input?.cart?.lines ?? []) {
    if (line.merchandise.__typename !== "ProductVariant") continue;

    if (productIds.length > 0 && !productIds.includes(line.merchandise.product.id)) {
      continue;
    }

    const tier = sortedTiers.find((t) => line.quantity >= t.minQuantity);
    if (!tier) continue;

    discounts.push({
      message: configuration.message ?? "Quantity discount applied",
      targets: [{ cartLine: { id: line.id } }],
      value: { percentage: { value: tier.percentage } },
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
