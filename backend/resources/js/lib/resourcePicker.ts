import type { PickedProduct } from './api';

/** Opens Shopify's own product picker (App Bridge). Returns null if the merchant cancelled. */
export async function pickProduct(options: { multiple?: boolean } = {}): Promise<PickedProduct[] | null> {
  const selection = await window.shopify.resourcePicker({
    type: 'product',
    action: 'select',
    multiple: options.multiple ?? false,
    filter: { variants: true, draft: false, archived: false },
  });

  return selection && selection.length > 0 ? selection : null;
}

export function numericId(gid: string): string {
  return gid.split('/').pop() ?? gid;
}
