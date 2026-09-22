import {
  CartIcon,
  DeliveryIcon,
  ShieldCheckMarkIcon,
  QuestionCircleIcon,
  DiscountIcon,
  CartDiscountIcon,
  GiftCardIcon,
  PackageIcon,
  ProductAddIcon,
  TargetIcon,
} from '@shopify/polaris-icons';
import type { IconSource } from '@shopify/polaris';

export type FeatureTypeMeta = {
  type: string;
  label: string;
  /** One line shown under the label in the tool list -- what it does, in plain merchant language. */
  description: string;
  icon: IconSource;
  category: 'storefront' | 'offers' | 'funnels';
  proOnly?: boolean;
};

export const FEATURE_TYPES: FeatureTypeMeta[] = [
  {
    type: 'sticky_atc',
    label: 'Sticky Add to Cart',
    description: 'Keeps an Add to Cart bar on screen while shoppers scroll the product page.',
    icon: CartIcon,
    category: 'storefront',
  },
  {
    type: 'shipping_bar',
    label: 'Free Shipping Bar',
    description: 'Shows shoppers how close they are to a free shipping threshold.',
    icon: DeliveryIcon,
    category: 'storefront',
  },
  {
    type: 'trust_badges',
    label: 'Trust Badges',
    description: 'Displays secure checkout, money-back guarantee and other trust icons.',
    icon: ShieldCheckMarkIcon,
    category: 'storefront',
  },
  {
    type: 'faq',
    label: 'Product FAQ',
    description: 'Adds an expandable Q&A section with SEO-friendly markup to product pages.',
    icon: QuestionCircleIcon,
    category: 'storefront',
  },
  {
    type: 'quantity_discount',
    label: 'Quantity Discount',
    description: 'Rewards bigger orders with tiered savings, e.g. 10% off 3 or more.',
    icon: DiscountIcon,
    category: 'offers',
  },
  {
    type: 'bogo',
    label: 'BOGO Offer',
    description: 'Buy X, get Y free or discounted -- automatically applied at checkout.',
    icon: CartDiscountIcon,
    category: 'offers',
  },
  {
    type: 'free_gift',
    label: 'Free Gift',
    description: 'Unlocks a free product once a spend amount or trigger product is met.',
    icon: GiftCardIcon,
    category: 'offers',
  },
  {
    type: 'bundle',
    label: 'Bundle',
    description: 'Sells a fixed set of products together as one discounted offer.',
    icon: PackageIcon,
    category: 'offers',
  },
  {
    type: 'cart_upsell',
    label: 'Cart Upsell',
    description: 'Suggests hand-picked add-on products right in the cart.',
    icon: ProductAddIcon,
    category: 'funnels',
    proOnly: true,
  },
  {
    type: 'goal_tracker',
    label: 'Cart Goal Tracker',
    description: 'Multi-tier progress bar, e.g. free shipping at $50, free gift at $100.',
    icon: TargetIcon,
    category: 'funnels',
    proOnly: true,
  },
];

export function featureLabel(type: string): string {
  return FEATURE_TYPES.find((f) => f.type === type)?.label ?? type;
}

export function featureIcon(type: string): IconSource | undefined {
  return FEATURE_TYPES.find((f) => f.type === type)?.icon;
}
