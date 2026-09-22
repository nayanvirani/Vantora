import '@shopify/ui-extensions/preact';
import { render } from 'preact';
import { useEffect, useState } from 'preact/hooks';

const APP_URL = 'https://vantora-production.up.railway.app';

/**
 * F-18 checkout-stage Bundle upsell. Offers one active Bundle config as
 * an add-on, if its bundle product isn't already in the cart. Data comes
 * from our own backend (CheckoutUpsellController), not shopify.query(),
 * mirroring extensions/thank-you-blocks's proven network_access +
 * session-token pattern -- checkout has no Liquid, so the storefront
 * block's direct metafield read isn't available here.
 *
 * Plan gating happens server-side (PlanGateService::canAccessModule) --
 * the backend just returns no data for a non-Pro shop, so this renders
 * nothing rather than trying to check the plan itself.
 */
export default async () => {
  render(<Extension />, document.body);
};

function Extension() {
  const [bundle, setBundle] = useState(null);
  const [adding, setAdding] = useState(false);
  const [added, setAdded] = useState(false);
  const [error, setError] = useState(null);

  const canAddCartLine = shopify.instructions.value.lines.canAddCartLine;

  useEffect(() => {
    if (!canAddCartLine) return;

    const cartLineProductIds = shopify.lines.value
      .map((line) => line.merchandise?.product?.id)
      .filter(Boolean);

    shopify.sessionToken.get().then((token) =>
      fetch(`${APP_URL}/checkout/bundle-upsell`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${token}` },
        body: JSON.stringify({ cart_line_product_ids: cartLineProductIds }),
      })
        .then((res) => (res.ok ? res.json() : null))
        .then(setBundle)
        .catch(() => setBundle(null))
    );
  }, [canAddCartLine]);

  if (!canAddCartLine || !bundle) {
    return null;
  }

  const addBundle = async () => {
    setAdding(true);
    setError(null);

    const result = await shopify.applyCartLinesChange({
      type: 'addCartLine',
      merchandiseId: bundle.bundle_variant_id,
      quantity: 1,
      attributes: [{ key: '_vantora_source', value: 'checkout_bundle_upsell' }],
    });

    setAdding(false);

    if (result.type === 'error') {
      setError(result.message);
      return;
    }

    setAdded(true);
  };

  return (
    <s-banner heading={bundle.heading || 'Bundle & Save'}>
      <s-stack direction="block" gap="tight">
        {bundle.message && <s-text>{bundle.message}</s-text>}

        <s-stack direction="inline" gap="tight" alignItems="center">
          {bundle.bundle_price != null && <s-text type="strong">{formatMoney(bundle.bundle_price)}</s-text>}
          {bundle.regular_total != null && bundle.regular_total > bundle.bundle_price && (
            <s-text type="strikethrough">{formatMoney(bundle.regular_total)}</s-text>
          )}
          {bundle.savings_percent > 0 && <s-badge tone="success">Save {bundle.savings_percent}%</s-badge>}
        </s-stack>

        {error && <s-text tone="critical">{error}</s-text>}
        {added ? (
          <s-text>Added to your order.</s-text>
        ) : (
          <s-button onClick={addBundle} loading={adding} disabled={adding}>
            Add bundle
          </s-button>
        )}
      </s-stack>
    </s-banner>
  );
}

function formatMoney(amount) {
  const currencyCode = shopify.localization.currency.value.isoCode;
  const language = shopify.localization.language.value.isoCode;

  return new Intl.NumberFormat(language, { style: 'currency', currency: currencyCode }).format(Number(amount));
}
