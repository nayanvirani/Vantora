export type FeatureTypeMeta = {
  type: string;
  label: string;
  category: 'storefront' | 'offers' | 'funnels';
  proOnly?: boolean;
  /** JSON shape hint shown under the settings editor for types without a dedicated form. */
  settingsHint?: string;
};

export const FEATURE_TYPES: FeatureTypeMeta[] = [
  { type: 'sticky_atc', label: 'Sticky Add to Cart', category: 'storefront' },
  { type: 'shipping_bar', label: 'Free Shipping Bar', category: 'storefront' },
  { type: 'trust_badges', label: 'Trust Badges', category: 'storefront' },
  { type: 'faq', label: 'Product FAQ', category: 'storefront' },
  {
    type: 'quantity_discount',
    label: 'Quantity Discount',
    category: 'offers',
    settingsHint: '{"tiers":[{"minQuantity":2,"percentage":10}],"product_ids":[]}',
  },
  {
    type: 'bogo',
    label: 'BOGO Offer',
    category: 'offers',
    settingsHint: '{"buy_quantity":2,"buy_product_ids":["gid://shopify/Product/123"],"get_discount_percentage":100}',
  },
  {
    type: 'free_gift',
    label: 'Free Gift',
    category: 'offers',
    settingsHint: '{"trigger":{"type":"spend_threshold","amount":100},"gift_variant_id":"gid://shopify/ProductVariant/123"}',
  },
  {
    type: 'bundle',
    label: 'Bundle',
    category: 'offers',
    settingsHint: '{"bundle_product_id":"gid://shopify/Product/1","components":[{"productId":"gid://shopify/Product/2","quantity":1}],"discount_type":"percentage","discount_value":15}',
  },
  {
    type: 'cart_upsell',
    label: 'Cart Upsell',
    category: 'funnels',
    proOnly: true,
    settingsHint: '{"max_items":3,"picks":[{"product_id":"123","variant_id":"456","title":"...","price":"$19.99","image":"https://..."}]}',
  },
  {
    type: 'fbt',
    label: 'Frequently Bought Together',
    category: 'funnels',
    proOnly: true,
    settingsHint: '{"sets":[{"trigger_product_id":"123","picks":[{"variant_id":"456","title":"...","price":"$19.99","image":"https://..."}]}]}',
  },
  { type: 'goal_tracker', label: 'Cart Goal Tracker', category: 'funnels', proOnly: true },
];

export function featureLabel(type: string): string {
  return FEATURE_TYPES.find((f) => f.type === type)?.label ?? type;
}
