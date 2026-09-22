/**
 * One field schema per feature type, grouped into the sections every
 * merchant-facing settings screen should have consistently (content,
 * appearance, placement/devices). Drives both the GUI form
 * (components/SettingsForm.tsx) and the live preview
 * (components/ToolPreview.tsx) off the same data instead of hand-writing
 * per-type React forms -- adding a field here is enough, no new component
 * needed.
 *
 * Field keys match what extensions/theme-extension's Liquid blocks and
 * the Discount Functions' run.js configs actually read (see each block's
 * {% schema %} / each function's docblock) so a value saved here is never
 * a name mismatch away from doing nothing.
 */
export type FieldDef =
  | { type: 'text'; key: string; label: string; default?: string; helpText?: string }
  | { type: 'textarea'; key: string; label: string; default?: string; helpText?: string }
  | { type: 'number'; key: string; label: string; default?: number; min?: number; max?: number; prefix?: string }
  | { type: 'color'; key: string; label: string; default?: string }
  | { type: 'select'; key: string; label: string; options: Array<{ label: string; value: string }>; default?: string; helpText?: string }
  | { type: 'checkbox'; key: string; label: string; default?: boolean }
  | { type: 'range'; key: string; label: string; min: number; max: number; default?: number }
  | { type: 'product'; key: string; label: string; helpText?: string }
  | { type: 'variant'; key: string; label: string; helpText?: string }
  | { type: 'products'; key: string; label: string; helpText?: string }
  | { type: 'picks'; key: string; label: string; helpText?: string }
  | { type: 'quantity_tiers'; key: string; label: string; helpText?: string }
  | { type: 'bundle_components'; key: string; label: string; helpText?: string };

export type FieldSection = { title: string; fields: FieldDef[] };

export type TypeSchema = {
  preview: 'sticky_bar' | 'shipping_bar' | 'goal_tracker' | 'badges' | 'faq' | 'upsell_list' | 'discount_tag';
  sections: FieldSection[];
  /**
   * Only needed when the form's flat field keys don't already match
   * feature_configs.settings 1:1 (free_gift's nested `trigger` object,
   * bundle's bundle_product_id + components). Everything else is a
   * straight passthrough.
   */
  toSettings?: (form: Record<string, unknown>) => Record<string, unknown>;
  fromSettings?: (settings: Record<string, unknown>) => Record<string, unknown>;
};

const APPEARANCE_TEXT_COLOR: FieldDef = { type: 'color', key: 'text_color', label: 'Text color', default: '#ffffff' };

// Built-in badge icon library (spec F-13: "Badge library plus custom
// upload" -- this covers the library half; custom image upload needs
// Shopify's staged-upload flow and isn't built yet). Values match the SVG
// keys trust-badges.liquid renders, so a value saved here is never a
// naming mismatch away from showing a blank icon.
export const TRUST_BADGE_ICONS = [
  { label: 'Shield', value: 'shield' },
  { label: 'Lock', value: 'lock' },
  { label: 'Truck', value: 'truck' },
  { label: 'Refresh / Returns', value: 'refresh' },
  { label: 'Check', value: 'check' },
  { label: 'Star', value: 'star' },
  { label: 'Card', value: 'card' },
  { label: 'Support', value: 'support' },
  { label: 'None', value: '' },
];
const DEVICE_SECTION: FieldSection = {
  title: 'Devices',
  fields: [
    { type: 'checkbox', key: 'show_desktop', label: 'Show on desktop', default: true },
    { type: 'checkbox', key: 'show_mobile', label: 'Show on mobile', default: true },
  ],
};

export const TYPE_SCHEMAS: Record<string, TypeSchema> = {
  sticky_atc: {
    preview: 'sticky_bar',
    sections: [
      {
        title: 'Content',
        fields: [{ type: 'text', key: 'button_text', label: 'Button text', default: 'Add to cart' }],
      },
      {
        title: 'Appearance',
        fields: [
          {
            type: 'select',
            key: 'position',
            label: 'Position',
            default: 'bottom',
            options: [
              { label: 'Top', value: 'top' },
              { label: 'Bottom', value: 'bottom' },
            ],
          },
          { type: 'color', key: 'background_color', label: 'Background color', default: '#111111' },
          APPEARANCE_TEXT_COLOR,
          { type: 'checkbox', key: 'show_image', label: 'Show product image', default: true },
          { type: 'checkbox', key: 'show_price', label: 'Show price', default: true },
          { type: 'checkbox', key: 'show_variant_selector', label: 'Show variant selector', default: true },
        ],
      },
      DEVICE_SECTION,
      {
        title: 'Behavior',
        fields: [
          {
            type: 'checkbox',
            key: 'scroll_trigger',
            label: 'Only show after scrolling past the buy button',
            default: true,
          },
        ],
      },
    ],
  },

  shipping_bar: {
    preview: 'shipping_bar',
    sections: [
      {
        title: 'Content',
        fields: [
          { type: 'number', key: 'threshold', label: 'Free shipping threshold', default: 50, prefix: '$' },
          {
            type: 'text',
            key: 'progress_message',
            label: 'Progress message',
            default: 'Add %%AMOUNT%% more for free shipping!',
            helpText: '%%AMOUNT%% is replaced with the amount left to reach the threshold.',
          },
          { type: 'text', key: 'success_message', label: 'Success message', default: "You've unlocked free shipping!" },
        ],
      },
      {
        title: 'Appearance',
        fields: [
          {
            type: 'select',
            key: 'position',
            label: 'Position',
            default: 'top',
            options: [
              { label: 'Page top', value: 'top' },
              { label: 'Cart page', value: 'cart' },
              { label: 'Cart drawer', value: 'cart_drawer' },
            ],
          },
          { type: 'color', key: 'background_color', label: 'Background color', default: '#f4f4f4' },
          { type: 'color', key: 'progress_color', label: 'Progress bar color', default: '#1a7f37' },
          { type: 'color', key: 'text_color', label: 'Text color', default: '#111111' },
        ],
      },
    ],
  },

  trust_badges: {
    preview: 'badges',
    sections: [
      {
        title: 'Appearance',
        fields: [
          {
            type: 'select',
            key: 'layout',
            label: 'Layout',
            default: 'row',
            options: [
              { label: 'Row', value: 'row' },
              { label: 'Stacked', value: 'stacked' },
            ],
          },
        ],
      },
      {
        title: 'Badges',
        fields: [1, 2, 3, 4, 5, 6].flatMap((i) => [
          {
            type: 'select' as const,
            key: `badge_${i}_icon`,
            label: `Badge ${i} icon`,
            default: i === 1 ? 'lock' : i === 2 ? 'refresh' : i === 3 ? 'truck' : '',
            options: TRUST_BADGE_ICONS,
          },
          {
            type: 'text' as const,
            key: `badge_${i}_label`,
            label: `Badge ${i} label`,
            default: i === 1 ? 'Secure checkout' : i === 2 ? 'Money-back guarantee' : i === 3 ? 'Fast shipping' : '',
          },
        ]),
      },
      {
        title: 'Show on',
        fields: [
          {
            type: 'products',
            key: 'target_product_ids',
            label: 'Specific products',
            helpText: 'Leave empty to show on every product page.',
          },
        ],
      },
    ],
  },

  faq: {
    preview: 'faq',
    sections: [
      {
        title: 'Content',
        fields: [
          { type: 'text', key: 'heading', label: 'Heading', default: 'Frequently asked questions' },
          ...[1, 2, 3, 4].flatMap((i) => [
            { type: 'text' as const, key: `question_${i}`, label: `Question ${i}` },
            { type: 'textarea' as const, key: `answer_${i}`, label: `Answer ${i}` },
          ]),
        ],
      },
      {
        title: 'Show on',
        fields: [
          {
            type: 'products',
            key: 'target_product_ids',
            label: 'Specific products',
            helpText: 'Leave empty to show on every product page.',
          },
        ],
      },
    ],
  },

  goal_tracker: {
    preview: 'goal_tracker',
    sections: [
      {
        title: 'Tiers',
        fields: [
          { type: 'number', key: 'tier_1_threshold', label: 'Tier 1 threshold', default: 50, prefix: '$' },
          { type: 'text', key: 'tier_1_label', label: 'Tier 1 label', default: 'Free shipping' },
          { type: 'number', key: 'tier_2_threshold', label: 'Tier 2 threshold', default: 100, prefix: '$' },
          { type: 'text', key: 'tier_2_label', label: 'Tier 2 label', default: 'Free gift' },
          { type: 'number', key: 'tier_3_threshold', label: 'Tier 3 threshold (optional)', default: 0, prefix: '$' },
          { type: 'text', key: 'tier_3_label', label: 'Tier 3 label' },
        ],
      },
      {
        title: 'Appearance',
        fields: [
          {
            type: 'select',
            key: 'position',
            label: 'Where it shows',
            default: 'cart',
            helpText: 'Only ever shows on the cart page or cart drawer -- never on other pages.',
            options: [
              { label: 'Cart page', value: 'cart' },
              { label: 'Cart drawer', value: 'cart_drawer' },
            ],
          },
          { type: 'color', key: 'background_color', label: 'Background color', default: '#f4f4f4' },
          { type: 'color', key: 'progress_color', label: 'Progress bar color', default: '#1a7f37' },
          { type: 'color', key: 'text_color', label: 'Text color', default: '#111111' },
        ],
      },
    ],
  },

  cart_upsell: {
    preview: 'upsell_list',
    sections: [
      {
        title: 'Content',
        fields: [
          { type: 'text', key: 'heading', label: 'Heading', default: 'You might also like' },
          { type: 'range', key: 'max_items', label: 'Max items shown', min: 1, max: 6, default: 3 },
        ],
      },
      {
        title: 'Products',
        fields: [{ type: 'picks', key: 'picks', label: 'Recommended products', helpText: 'Shown in the cart.' }],
      },
      { title: 'Appearance', fields: [{ type: 'color', key: 'accent_color', label: 'Accent color', default: '#111111' }] },
    ],
  },

  fbt: {
    preview: 'upsell_list',
    sections: [
      {
        title: 'Content',
        fields: [
          { type: 'text', key: 'heading', label: 'Heading', default: 'Frequently bought together' },
          { type: 'text', key: 'button_text', label: 'Button text', default: 'Add all to cart' },
        ],
      },
      {
        title: 'Products',
        fields: [
          { type: 'product', key: 'trigger_product_id', label: 'Show on this product', helpText: 'The product page this set appears on.' },
          { type: 'picks', key: 'picks', label: 'Suggested products' },
        ],
      },
      { title: 'Appearance', fields: [{ type: 'color', key: 'accent_color', label: 'Accent color', default: '#111111' }] },
    ],
  },

  quantity_discount: {
    preview: 'discount_tag',
    sections: [
      { title: 'Tiers', fields: [{ type: 'quantity_tiers', key: 'tiers', label: 'Quantity tiers', helpText: 'Buy this many, save this %.' }] },
      { title: 'Products', fields: [{ type: 'products', key: 'product_ids', label: 'Applies to', helpText: 'Leave empty to apply store-wide.' }] },
      { title: 'Content', fields: [{ type: 'text', key: 'message', label: 'Message shown at checkout', default: 'Quantity discount applied' }] },
    ],
  },

  bogo: {
    preview: 'discount_tag',
    sections: [
      {
        title: 'Offer',
        fields: [
          { type: 'number', key: 'buy_quantity', label: 'Buy quantity', default: 2, min: 1 },
          { type: 'range', key: 'get_discount_percentage', label: 'Discount on reward item (%)', min: 1, max: 100, default: 100 },
          { type: 'number', key: 'max_rewards_per_order', label: 'Max rewards per order (optional)', min: 1 },
        ],
      },
      {
        title: 'Products',
        fields: [
          { type: 'products', key: 'buy_product_ids', label: 'Buy these products' },
          { type: 'products', key: 'get_product_ids', label: 'Reward products', helpText: 'Leave empty to reward the same product.' },
        ],
      },
      { title: 'Content', fields: [{ type: 'text', key: 'message', label: 'Message shown at checkout', default: 'Buy X get Y' }] },
    ],
  },

  free_gift: {
    preview: 'discount_tag',
    sections: [
      {
        title: 'Trigger',
        fields: [
          {
            type: 'select',
            key: 'trigger_type',
            label: 'Unlocks when',
            default: 'spend_threshold',
            options: [
              { label: 'Cart reaches an amount', value: 'spend_threshold' },
              { label: 'A specific product is in the cart', value: 'product' },
            ],
          },
          { type: 'number', key: 'trigger_amount', label: 'Spend threshold', default: 100, prefix: '$' },
          { type: 'product', key: 'trigger_product_id', label: 'Trigger product' },
        ],
      },
      {
        title: 'Gift',
        fields: [
          { type: 'variant', key: 'gift_variant_id', label: 'Gift product/variant', helpText: 'The merchant’s storefront block adds this to cart; this offer makes it free once unlocked.' },
          { type: 'range', key: 'discount_percentage', label: 'Gift discount (%)', min: 1, max: 100, default: 100 },
        ],
      },
      { title: 'Content', fields: [{ type: 'text', key: 'message', label: 'Message shown at checkout', default: "You've earned a free gift!" }] },
    ],
    // Backend (DiscountSyncService::freeGiftPayload + the free-gift-discount
    // Function) expects a nested `trigger: {type, amount|productId}` object,
    // not the flat trigger_type/trigger_amount/trigger_product_id the form
    // edits directly -- everything else here is a 1:1 passthrough.
    toSettings: (form) => {
      const trigger =
        form.trigger_type === 'product'
          ? { type: 'product', productId: form.trigger_product_id }
          : { type: 'spend_threshold', amount: Number(form.trigger_amount) || 0 };

      return {
        trigger,
        gift_variant_id: form.gift_variant_id,
        discount_percentage: form.discount_percentage,
        message: form.message,
      };
    },
    fromSettings: (settings) => {
      const trigger = (settings.trigger as { type?: string; amount?: number; productId?: string }) ?? {};

      return {
        trigger_type: trigger.type ?? 'spend_threshold',
        trigger_amount: trigger.amount ?? 100,
        trigger_product_id: trigger.productId ?? '',
        gift_variant_id: settings.gift_variant_id,
        discount_percentage: settings.discount_percentage,
        message: settings.message,
      };
    },
  },

  bundle: {
    preview: 'discount_tag',
    sections: [
      {
        title: 'Products',
        fields: [
          { type: 'product', key: 'bundle_product_id', label: 'Bundle product', helpText: 'The product a customer adds to buy the whole bundle.' },
          { type: 'bundle_components', key: 'components', label: 'Bundle components' },
        ],
      },
      {
        title: 'Discount',
        fields: [
          {
            type: 'select',
            key: 'discount_type',
            label: 'Discount type',
            default: 'percentage',
            options: [
              { label: 'Percentage off', value: 'percentage' },
              { label: 'Fixed amount off', value: 'fixed_amount' },
            ],
          },
          { type: 'number', key: 'discount_value', label: 'Discount value', default: 15 },
        ],
      },
      { title: 'Content', fields: [{ type: 'text', key: 'message', label: 'Message shown at checkout', default: 'Bundle discount applied' }] },
    ],
  },
};
