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
 * F-15 Free Gift Offer. Discount Functions can't add cart lines, so the
 * storefront block is responsible for adding the gift product to the cart;
 * this function makes it free once the trigger condition is met. One gift
 * per order, regardless of how many gift units are in the cart.
 *
 * Configuration written by the app to the `$app:free-gift-discount`
 * metafield on the discount node, shaped as:
 * {
 *   "trigger": { "type": "spend_threshold", "amount": 100 }
 *            | { "type": "product", "productId": "gid://shopify/Product/1" },
 *   "giftVariantId": "gid://shopify/ProductVariant/999",
 *   "discountPercentage": 100,
 *   "message": "You've earned a free gift!"
 * }
 *
 * @param {RunInput} input
 * @returns {FunctionRunResult}
 */
export function run(input) {
  const configuration = JSON.parse(
    input?.discountNode?.metafield?.value ?? "{}"
  );

  const trigger = configuration.trigger;
  const giftVariantId = configuration.giftVariantId;

  if (!trigger || !giftVariantId) {
    return EMPTY_DISCOUNT;
  }

  const lines = input?.cart?.lines ?? [];
  const variantLine = (line) => line.merchandise.__typename === "ProductVariant";

  const giftLine = lines.find(
    (line) => variantLine(line) && line.merchandise.id === giftVariantId
  );

  if (!giftLine) {
    // Gift hasn't been added to the cart yet -- nothing to discount.
    return EMPTY_DISCOUNT;
  }

  let triggerMet = false;

  if (trigger.type === "spend_threshold") {
    const subtotal = parseFloat(input?.cart?.cost?.subtotalAmount?.amount ?? "0");
    triggerMet = subtotal >= trigger.amount;
  } else if (trigger.type === "product") {
    triggerMet = lines.some(
      (line) =>
        variantLine(line) &&
        line.merchandise.id !== giftVariantId &&
        line.merchandise.product.id === trigger.productId
    );
  }

  if (!triggerMet) {
    return EMPTY_DISCOUNT;
  }

  return {
    discountApplicationStrategy: DiscountApplicationStrategy.All,
    discounts: [
      {
        message: configuration.message ?? "Free gift unlocked",
        targets: [{ cartLine: { id: giftLine.id, quantity: 1 } }],
        value: { percentage: { value: configuration.discountPercentage ?? 100 } },
      },
    ],
  };
}
