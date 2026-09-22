/**
 * Placeholder-div classes for the tools that render via the single
 * "Vantora: Cart Widgets" app embed (extensions/theme-extension/blocks/
 * vantora-cart-widgets.liquid) instead of their own app block -- a
 * merchant asked for something simpler than either an app block (can't
 * reach most themes' cart drawer markup -- confirmed for this store's
 * own Dawn theme, whose cart-drawer.liquid section has no {% schema %}
 * at all) or full copy-paste code. Pasting just this one empty div
 * anywhere in the theme (Online Store > Themes > Edit code), including
 * directly inside snippets/cart-drawer.liquid, is enough -- the embed's
 * script finds every element with this class and renders into it
 * automatically.
 *
 * Classes, not IDs: cart drawer markup is typically included globally
 * (every page, not just /cart), so a merchant who also wants a copy on
 * the dedicated cart page ends up with two placeholders in the same
 * page's DOM at once -- IDs must be unique per page, so a second one
 * would silently never hydrate. A class lets the same placeholder appear
 * more than once.
 */
export const PLACEHOLDER_CLASSES: Record<string, string> = {
  shipping_bar: 'vantora-shipping-bar',
  goal_tracker: 'vantora-cart-goal-tracker',
  cart_upsell: 'vantora-cart-upsell',
};
