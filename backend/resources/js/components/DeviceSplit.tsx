import { BlockStack, InlineStack, Text } from '@shopify/polaris';

/** Mobile vs desktop as one segmented bar -- two categorical values, fixed hue order, direct-labeled so no legend is needed. */
export default function DeviceSplit({ mobile, desktop }: { mobile: number; desktop: number }) {
  const total = Math.max(1, mobile + desktop);
  const mobilePct = Math.round((mobile / total) * 100);
  const desktopPct = 100 - mobilePct;

  return (
    <BlockStack gap="200">
      <div style={{ display: 'flex', height: 20, borderRadius: 6, overflow: 'hidden' }}>
        <div style={{ width: `${mobilePct}%`, background: 'var(--p-color-bg-fill-info, #1f6feb)' }} />
        <div style={{ width: `${desktopPct}%`, background: 'var(--p-color-bg-fill-magic, #8e5cf6)' }} />
      </div>
      <InlineStack gap="400">
        <InlineStack gap="150" blockAlign="center">
          <div style={{ width: 10, height: 10, borderRadius: 3, background: 'var(--p-color-bg-fill-info, #1f6feb)' }} />
          <Text as="span">Mobile {mobilePct}%</Text>
        </InlineStack>
        <InlineStack gap="150" blockAlign="center">
          <div style={{ width: 10, height: 10, borderRadius: 3, background: 'var(--p-color-bg-fill-magic, #8e5cf6)' }} />
          <Text as="span">Desktop {desktopPct}%</Text>
        </InlineStack>
      </InlineStack>
    </BlockStack>
  );
}
