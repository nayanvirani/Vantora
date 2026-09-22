/**
 * Placeholder-div IDs for the tools that render via the single "Vantora:
 * Cart Widgets" app embed (extensions/theme-extension/blocks/
 * vantora-cart-widgets.liquid) instead of their own app block -- a
 * merchant asked for something simpler than either an app block (can't
 * reach most themes' cart drawer markup -- confirmed for this store's
 * own Dawn theme, whose cart-drawer.liquid section has no {% schema %}
 * at all) or full copy-paste code. Pasting just this one empty div
 * anywhere in the theme (Online Store > Themes > Edit code), including
 * directly inside snippets/cart-drawer.liquid, is enough -- the embed's
 * script finds it by ID and renders into it automatically.
 */
export const PLACEHOLDER_IDS: Record<string, string> = {
  shipping_bar: 'vantora-shipping-bar',
  goal_tracker: 'vantora-cart-goal-tracker',
  cart_upsell: 'vantora-cart-upsell',
};
