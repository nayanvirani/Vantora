import { BlockStack, InlineStack, Text } from '@shopify/polaris';

/**
 * View -> cart -> checkout funnel, Clarity/GA-style: each stage a full-width
 * track with a proportional fill and a drop-off % from the previous stage.
 * One color (identity is the stage label, not a categorical hue per bar).
 */
export default function FunnelChart({ stages }: { stages: Array<{ label: string; value: number }> }) {
  const max = Math.max(1, stages[0]?.value ?? 1);

  return (
    <BlockStack gap="300">
      {stages.map((stage, i) => {
        const pct = max > 0 ? (stage.value / max) * 100 : 0;
        const prev = i > 0 ? stages[i - 1].value : null;
        const dropOff = prev && prev > 0 ? Math.round(((prev - stage.value) / prev) * 100) : null;

        return (
          <BlockStack gap="100" key={stage.label}>
            <InlineStack align="space-between">
              <Text as="span">{stage.label}</Text>
              <InlineStack gap="150">
                {dropOff !== null && dropOff > 0 && (
                  <Text as="span" tone="critical">
                    -{dropOff}%
                  </Text>
                )}
                <Text as="span" fontWeight="medium">
                  {stage.value}
                </Text>
              </InlineStack>
            </InlineStack>
            <div style={{ height: 20, borderRadius: 6, background: 'var(--p-color-bg-surface-tertiary, #f1f1f1)', overflow: 'hidden' }}>
              <div
                style={{
                  height: '100%',
                  width: `${Math.max(stage.value > 0 ? 2 : 0, pct)}%`,
                  borderRadius: 6,
                  background: 'var(--p-color-bg-fill-info, #1f6feb)',
                  transition: 'width .2s ease',
                }}
              />
            </div>
          </BlockStack>
        );
      })}
    </BlockStack>
  );
}
