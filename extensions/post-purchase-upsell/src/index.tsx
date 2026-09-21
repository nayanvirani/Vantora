/**
 * F-33 Post-purchase One-Click Upsell. Depends on Shopify granting
 * post-purchase extension access (beta, access-by-request per spec
 * section 2/13) -- UNTESTED, since this environment has no access grant
 * or dev store to exercise it against. Structure follows the documented
 * post-purchase-ui-extensions-react API; verify against shopify.dev before
 * relying on it.
 *
 * Flow: ShouldRender asks the app server for the best-matching offer for
 * this order (by cart value/product/collection rules, see
 * app/Http/Controllers/Api/... offer matching -- not yet built, this calls
 * a placeholder endpoint); Render shows it with a countdown; accepting
 * calls calculateChangeset/applyChangeset so the item is added without
 * card re-entry, per the post-purchase changeset flow (spec section 5.4).
 */
import React, { useEffect, useState } from 'react';

import {
  extend,
  render,
  useExtensionInput,
  BlockStack,
  Button,
  CalloutBanner,
  Heading,
  Image,
  Layout,
  TextBlock,
  TextContainer,
  View,
} from '@shopify/post-purchase-ui-extensions-react';

const APP_URL = 'https://vantora-production.up.railway.app';

extend('Checkout::PostPurchase::ShouldRender', async ({ storage, inputData }) => {
  try {
    const response = await fetch(`${APP_URL}/post-purchase/best-offer`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        shop: inputData.shop.domain,
        reference_id: inputData.initialPurchase.referenceId,
        total_price: inputData.initialPurchase.totalPriceSet?.presentmentMoney?.amount,
      }),
    });

    if (!response.ok) {
      return { render: false };
    }

    const offer = await response.json();
    if (!offer?.id) {
      return { render: false };
    }

    await storage.update({ offer });

    return { render: true };
  } catch {
    return { render: false };
  }
});

render('Checkout::PostPurchase::Render', App);

export function App() {
  const { storage, calculateChangeset, applyChangeset, done } = useExtensionInput();
  const offer = storage.initialData?.offer;
  const [accepting, setAccepting] = useState(false);
  const [changeset, setChangeset] = useState(null);

  useEffect(() => {
    if (!offer) return;

    calculateChangeset({
      changes: [{ type: 'add_variant', variantId: offer.variant_id, quantity: 1 }],
    }).then((result) => setChangeset(result.calculatedPurchase));
  }, [offer]);

  if (!offer) {
    return null;
  }

  const accept = async () => {
    setAccepting(true);
    await applyChangeset({
      changes: [{ type: 'add_variant', variantId: offer.variant_id, quantity: 1 }],
    });
    done();
  };

  return (
    <BlockStack spacing="loose">
      <CalloutBanner title="One more thing before you go">
        {offer.headline ?? 'A special offer, just for you.'}
      </CalloutBanner>
      <Layout
        maxInlineSize={0.95}
        media={[
          { viewportSize: 'small', sizes: [1, 30, 1] },
          { viewportSize: 'medium', sizes: [300, 30, 0.5] },
          { viewportSize: 'large', sizes: [400, 30, 0.33] },
        ]}
      >
        <View>
          <Image source={offer.image ?? 'https://cdn.shopify.com/static/images/examples/img-placeholder-1120x1120.png'} />
        </View>
        <View />
        <BlockStack spacing="xloose">
          <TextContainer>
            <Heading>{offer.title}</Heading>
            <TextBlock>{offer.description}</TextBlock>
            {changeset && (
              <TextBlock>Add for {changeset.updatedShipping?.value?.amount ?? offer.price}</TextBlock>
            )}
          </TextContainer>
          <Button submit loading={accepting} onPress={accept}>
            Add to my order
          </Button>
          <Button subdued onPress={() => done()}>
            No thanks
          </Button>
        </BlockStack>
      </Layout>
    </BlockStack>
  );
}
