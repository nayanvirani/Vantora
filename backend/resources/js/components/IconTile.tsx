import { Icon } from '@shopify/polaris';
import type { IconSource } from '@shopify/polaris';

/**
 * A colored rounded-square icon chip, the leading visual for tool rows
 * across the Storefront/Offers/Funnels lists -- Polaris's own Avatar only
 * supports a photo/initials/person icon, not an arbitrary icon, so this
 * fills that gap rather than every list falling back to plain text rows.
 */
export default function IconTile({ icon, tone = 'default' }: { icon: IconSource; tone?: 'default' | 'success' }) {
  const background = tone === 'success' ? 'var(--p-color-bg-fill-success-secondary, #dcf5e5)' : 'var(--p-color-bg-fill-secondary, #f1f1f1)';
  const iconTone = tone === 'success' ? 'success' : 'base';

  return (
    <div
      style={{
        width: 40,
        height: 40,
        borderRadius: 8,
        background,
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        flexShrink: 0,
      }}
    >
      <Icon source={icon} tone={iconTone} />
    </div>
  );
}
