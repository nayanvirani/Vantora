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
 * F-18 Mix & Match Bundle. Unlike bundle-discount (which pairs with a
 * Cart Transform expanding a virtual "bundle product" and scales by a
 * quantity multiplier), Mix & Match has no bundle product and no Cart
 * Transform -- the shopper picks live in the storefront picker, which
 * adds each chosen product as its own real cart line. This function
 * grants the discount once enough DISTINCT pool-member products are
 * present in cart, matching by product-ID membership the same way
 * bundle-discount does, but counting distinct products instead of a
 * per-component quantity floor-division.
 *
 * v1 scoping: discounts exactly one complete set (no multiplier for
 * extra sets in cart) -- matches bundle-discount's percentage-per-line /
 * single-fixed-amount-total branching for the discountType.
 *
 * Configuration written by the app to the `$app:mix-and-match-discount`
 * metafield on the discount node, shaped as:
 * {
 *   "poolProductIds": ["gid://shopify/Product/1", "gid://shopify/Product/2", ...],
 *   "pickCount": 3,
 *   "discountType": "percentage" | "fixed_amount",
 *   "discountValue": 15,
 *   "message": "Mix & match discount applied"
 * }
 *
 * @param {RunInput} input
 * @returns {FunctionRunResult}
 */
export function run(input) {
  const configuration = JSON.parse(
    input?.discountNode?.metafield?.value ?? "{}"
  );

  const poolProductIds = Array.isArray(configuration.poolProductIds) ? configuration.poolProductIds : [];
  const pickCount = configuration.pickCount;
  const discountType = configuration.discountType;
  const discountValue = configuration.discountValue;

  if (poolProductIds.length === 0 || !pickCount || pickCount < 2 || !discountType || !discountValue) {
    return EMPTY_DISCOUNT;
  }

  const lines = input?.cart?.lines ?? [];
  const variantLine = (line) => line.merchandise.__typename === "ProductVariant";

  // One matched cart line per distinct pool product present in cart --
  // quantity-agnostic, one unit of each of `pickCount` distinct products
  // satisfies the threshold.
  const matchedLineByProduct = new Map();
  for (const line of lines) {
    if (!variantLine(line)) continue;
    const productId = line.merchandise.product.id;
    if (!poolProductIds.includes(productId)) continue;
    if (!matchedLineByProduct.has(productId)) {
      matchedLineByProduct.set(productId, line);
    }
  }

  if (matchedLineByProduct.size < pickCount) {
    return EMPTY_DISCOUNT;
  }

  const chosen = Array.from(matchedLineByProduct.values()).slice(0, pickCount);
  const message = configuration.message ?? "Mix & match discount applied";
  const discounts = [];

  if (discountType === "percentage") {
    for (const line of chosen) {
      discounts.push({
        message,
        targets: [{ cartLine: { id: line.id, quantity: 1 } }],
        value: { percentage: { value: discountValue } },
      });
    }
  } else {
    discounts.push({
      message,
      targets: chosen.map((line) => ({ cartLine: { id: line.id, quantity: 1 } })),
      value: {
        fixedAmount: {
          amount: discountValue,
          appliesToEachItem: false,
        },
      },
    });
  }

  return {
    discountApplicationStrategy: DiscountApplicationStrategy.All,
    discounts,
  };
}
