import { useEffect, useState } from 'react';
import { Page, Layout, Card, Text, Button, BlockStack, InlineStack, Badge, Collapsible, Divider } from '@shopify/polaris';
import { ChevronDownIcon, ChevronUpIcon } from '@shopify/polaris-icons';
import { api, type BillingStatus, type Shop, type UsageStatus } from '../lib/api';
import { featureLabel } from '../lib/featureTypes';
import UsageBar from '../components/UsageBar';

const FAQ: Array<{ q: string; a: string }> = [
  {
    q: 'How does billing work?',
    a: "Billing runs entirely through Shopify's own billing system -- there's no separate account or card to manage. \"Manage plan\" below opens Shopify's hosted page where you choose or change your plan; charges appear on your regular Shopify invoice.",
  },
  {
    q: 'What happens if I hit the Starter plan limit?',
    a: 'Starter allows one active configuration per storefront tool (Sticky Add to Cart, Shipping Bar, etc.). Trying to activate a second one prompts an upgrade to Pro rather than silently failing -- your existing configuration keeps working either way.',
  },
  {
    q: "A tool is active but I don't see it on my storefront -- why?",
    a: "Most Vantora tools are Theme App Embeds, which need to be turned on once in your theme (Online Store > Themes > Customize > App embeds). Activating a tool in Vantora saves its settings; the embed switch is what makes it render.",
  },
  {
    q: 'If I downgrade from Pro to Starter, do I lose my settings?',
    a: "No configuration is ever deleted on downgrade. Vantora keeps your oldest active configuration per tool and pauses the rest, and Pro-only funnels (Cart Upsell, FBT, Goal Tracker) turn off until you upgrade again -- everything reactivates instantly on upgrade.",
  },
  {
    q: 'Do discounts from different offers stack?',
    a: 'Each offer (Quantity Discount, BOGO, Free Gift, Bundle) is its own Shopify discount and follows the discount combination rules you set for it in Shopify -- Vantora doesn\'t force them to stack or exclude each other.',
  },
];

function FaqItem({ q, a }: { q: string; a: string }) {
  const [open, setOpen] = useState(false);

  return (
    <BlockStack gap="200">
      <Button
        variant="plain"
        textAlign="left"
        fullWidth
        onClick={() => setOpen((v) => !v)}
        icon={open ? ChevronUpIcon : ChevronDownIcon}
      >
        {q}
      </Button>
      <Collapsible open={open} id={`faq-${q}`}>
        <Text as="p" tone="subdued">
          {a}
        </Text>
      </Collapsible>
    </BlockStack>
  );
}

export default function Settings({ shop, billing }: { shop: Shop; billing: BillingStatus }) {
  const [usage, setUsage] = useState<UsageStatus | null>(null);

  useEffect(() => {
    api.get<UsageStatus>('/api/billing/usage').then(setUsage).catch(() => {});
  }, []);

  // Best-effort deep link into the theme editor's App embeds panel --
  // unconfirmed against shopify.dev as a documented parameter, but an
  // unrecognized query param is harmless (falls back to the plain editor),
  // and the written steps below work regardless.
  const themeEditorUrl = `https://${shop.domain}/admin/themes/current/editor?context=apps`;

  return (
    <Page title="Settings">
      <Layout>
        <Layout.Section>
          <Card>
            <BlockStack gap="300">
              <Text as="h2" variant="headingMd">
                Plan and billing
              </Text>
              <InlineStack gap="200" blockAlign="center">
                <Badge tone={shop.plan === 'pro' ? 'success' : undefined}>{shop.plan === 'pro' ? 'Pro' : 'Starter'}</Badge>
              </InlineStack>
              <Text as="p" tone="subdued">
                Managed by Shopify — change or cancel your plan on Shopify's own billing page.
              </Text>
              <InlineStack>
                <Button variant="primary" onClick={() => window.open(billing.manage_plan_url, '_top')}>
                  Manage plan
                </Button>
              </InlineStack>

              {usage && (
                <>
                  <Divider />
                  <Text as="h3" variant="headingSm">
                    Usage
                  </Text>
                  <BlockStack gap="300">
                    {Object.entries(usage.features)
                      .filter(([, u]) => u.limit !== null || u.used > 0)
                      .map(([type, u]) => (
                        <UsageBar key={type} label={featureLabel(type)} used={u.used} limit={u.limit} />
                      ))}
                    <UsageBar label="AI product optimizations this month" used={usage.ai.cap - (usage.ai.remaining ?? 0)} limit={usage.ai.cap} />
                  </BlockStack>
                </>
              )}
            </BlockStack>
          </Card>
        </Layout.Section>

        <Layout.Section>
          <Card>
            <BlockStack gap="300">
              <Text as="h2" variant="headingMd">
                Store
              </Text>
              <Text as="p">{shop.domain}</Text>
              <Text as="p" tone="subdued">
                {shop.shopify_plan}
                {shop.is_plus ? ' · Shopify Plus' : ''}
              </Text>
            </BlockStack>
          </Card>
        </Layout.Section>

        <Layout.Section>
          <Card>
            <BlockStack gap="300">
              <Text as="h2" variant="headingMd">
                Theme setup
              </Text>
              <Text as="p" tone="subdued">
                Most Vantora tools render through Theme App Embeds, which need to be switched on once per theme. If a tool
                is active but not showing on your storefront, this is almost always why.
              </Text>
              <BlockStack gap="100">
                <Text as="p">1. Open your theme editor</Text>
                <Text as="p">2. Go to App embeds (bottom of the left sidebar)</Text>
                <Text as="p">3. Turn on the Vantora blocks you want live, then save</Text>
              </BlockStack>
              <InlineStack>
                <Button onClick={() => window.open(themeEditorUrl, '_blank')}>Open theme editor</Button>
              </InlineStack>
            </BlockStack>
          </Card>
        </Layout.Section>

        <Layout.Section>
          <Card>
            <BlockStack gap="300">
              <Text as="h2" variant="headingMd">
                FAQ
              </Text>
              <BlockStack gap="400">
                {FAQ.map((item) => (
                  <FaqItem key={item.q} {...item} />
                ))}
              </BlockStack>
            </BlockStack>
          </Card>
        </Layout.Section>

        <Layout.Section>
          <Card>
            <BlockStack gap="200">
              <Text as="h2" variant="headingMd">
                Support
              </Text>
              <Text as="p" tone="subdued">
                {shop.plan === 'pro' ? 'Priority support' : 'Standard support'} —{' '}
                <a href="mailto:support@vantora.app">support@vantora.app</a>
              </Text>
            </BlockStack>
          </Card>
        </Layout.Section>
      </Layout>
    </Page>
  );
}
