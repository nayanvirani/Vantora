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
 * F-16 BOGO Offer. Configuration written by the app to the
 * `$app:bogo-discount` metafield on the discount node, shaped as:
 * {
 *   "buyQuantity": 2,
 *   "buyProductIds": ["gid://shopify/Product/1"],
 *   "getProductIds": ["gid://shopify/Product/1"],   // omit to default to buyProductIds (same-product BOGO)
 *   "getDiscountPercentage": 100,                     // 100 = free
 *   "maxRewardsPerOrder": null,                        // optional cap on reward units
 *   "message": "Buy 2 get 1 free"
 * }
 *
 * @param {RunInput} input
 * @returns {FunctionRunResult}
 */
export function run(input) {
  const configuration = JSON.parse(
    input?.discountNode?.metafield?.value ?? "{}"
  );

  const buyQuantity = configuration.buyQuantity;
  const buyProductIds = Array.isArray(configuration.buyProductIds) ? configuration.buyProductIds : [];
  const getProductIds = Array.isArray(configuration.getProductIds) && configuration.getProductIds.length > 0
    ? configuration.getProductIds
    : buyProductIds;
  const discountPercentage = configuration.getDiscountPercentage ?? 100;

  if (!buyQuantity || buyProductIds.length === 0) {
    return EMPTY_DISCOUNT;
  }

  const lines = input?.cart?.lines ?? [];
  const variantLine = (line) => line.merchandise.__typename === "ProductVariant";

  const totalBuyQty = lines
    .filter(variantLine)
    .filter((line) => buyProductIds.includes(line.merchandise.product.id))
    .reduce((sum, line) => sum + line.quantity, 0);

  let rewardUnits = Math.floor(totalBuyQty / buyQuantity);
  if (typeof configuration.maxRewardsPerOrder === "number") {
    rewardUnits = Math.min(rewardUnits, configuration.maxRewardsPerOrder);
  }

  if (rewardUnits <= 0) {
    return EMPTY_DISCOUNT;
  }

  const discounts = [];
  let remaining = rewardUnits;

  for (const line of lines) {
    if (remaining <= 0) break;
    if (!variantLine(line)) continue;
    if (!getProductIds.includes(line.merchandise.product.id)) continue;

    const rewardQty = Math.min(line.quantity, remaining);
    remaining -= rewardQty;

    discounts.push({
      message: configuration.message ?? "Buy X get Y",
      targets: [{ cartLine: { id: line.id, quantity: rewardQty } }],
      value: { percentage: { value: discountPercentage } },
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
