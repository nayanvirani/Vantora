import type { PickedProduct, PickedVariant } from './api';

/** Opens Shopify's own product picker (App Bridge). Returns null if the merchant cancelled. */
export async function pickProducts(options: { multiple?: boolean } = {}): Promise<PickedProduct[] | null> {
  const selection = await window.shopify.resourcePicker({
    type: 'product',
    action: 'select',
    multiple: options.multiple ?? false,
    filter: { variants: true, draft: false, archived: false },
  });

  return selection && selection.length > 0 ? (selection as PickedProduct[]) : null;
}

export async function pickProduct(): Promise<PickedProduct | null> {
  const result = await pickProducts({ multiple: false });
  return result?.[0] ?? null;
}

/** Opens Shopify's variant picker -- for offers that need one specific SKU (e.g. a free gift). */
export async function pickVariant(): Promise<PickedVariant | null> {
  const selection = await window.shopify.resourcePicker({
    type: 'variant',
    action: 'select',
    multiple: false,
    filter: { draft: false, archived: false },
  });

  return selection && selection.length > 0 ? (selection[0] as PickedVariant) : null;
}

export function numericId(gid: string): string {
  return gid.split('/').pop() ?? gid;
}
