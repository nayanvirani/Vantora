import { BlockStack, InlineStack, Text } from '@shopify/polaris';

/** A labeled usage-vs-limit bar, red past 90% -- unlimited (limit null) shows a filled bar with no fraction. */
export default function UsageBar({ label, used, limit }: { label: string; used: number; limit: number | null }) {
  const pct = limit ? Math.min(100, (used / limit) * 100) : 100;
  const nearLimit = !!limit && used / limit >= 0.9;

  return (
    <BlockStack gap="150">
      <InlineStack align="space-between">
        <Text as="span" tone="subdued">
          {label}
        </Text>
        <Text as="span" fontWeight="medium">
          {used}
          {limit ? ` / ${limit}` : ' (unlimited)'}
        </Text>
      </InlineStack>
      <div style={{ height: 8, borderRadius: 999, background: 'var(--p-color-bg-surface-tertiary, #f1f1f1)', overflow: 'hidden' }}>
        <div
          style={{
            height: '100%',
            width: `${Math.max(limit ? 3 : 100, pct)}%`,
            borderRadius: 999,
            background: nearLimit ? 'var(--p-color-bg-fill-critical, #d72c0d)' : 'var(--p-color-bg-fill-success, #008060)',
            transition: 'width .2s ease',
          }}
        />
      </div>
    </BlockStack>
  );
}
