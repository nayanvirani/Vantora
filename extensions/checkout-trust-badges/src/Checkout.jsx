import '@shopify/ui-extensions/preact';
import { render } from 'preact';

/**
 * F-39 Checkout Trust Badges: payment icons / guarantee text / secure-
 * checkout badges, placed via the Checkout Editor (spec: "configurable
 * placement"). Content comes from the extension's own settings (configured
 * per-placement in the Checkout Editor) rather than a network call to the
 * app server, since this needs to render reliably with no round trip.
 */
export default async () => {
  render(<Extension />, document.body);
};

function Extension() {
  const settings = shopify.settings.value;

  const labels = [settings.badge_1_label, settings.badge_2_label, settings.badge_3_label].filter(Boolean);

  if (labels.length === 0) {
    return null;
  }

  return (
    <s-stack direction="inline" gap="base" alignItems="center">
      {labels.map((label) => (
        <s-stack key={label} direction="inline" gap="tight" alignItems="center">
          <s-icon type="lock" />
          <s-text size="small">{label}</s-text>
        </s-stack>
      ))}
    </s-stack>
  );
}
