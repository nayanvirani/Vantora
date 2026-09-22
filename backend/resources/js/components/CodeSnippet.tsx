import { useState } from 'react';
import { BlockStack, InlineStack, Text, Button, Box, Banner, Collapsible } from '@shopify/polaris';
import { ChevronDownIcon, ChevronUpIcon } from '@shopify/polaris-icons';

/**
 * Copy-pasteable code for tools whose ideal spot (the cart drawer) most
 * themes don't expose as an app-block-addable section -- see
 * lib/codeSnippets.ts for why. Collapsed by default since most merchants
 * will use the app-block version; this is the "my theme/developer needs
 * exact placement" escape hatch.
 */
export default function CodeSnippet({ code }: { code: string }) {
  const [open, setOpen] = useState(false);
  const [copied, setCopied] = useState(false);

  const copy = async () => {
    try {
      await navigator.clipboard.writeText(code);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    } catch {
      // Clipboard API can be unavailable (permissions, older browsers) --
      // the code is still selectable/copyable by hand from the box below.
    }
  };

  return (
    <BlockStack gap="200">
      <Button variant="plain" textAlign="left" fullWidth onClick={() => setOpen((v) => !v)} icon={open ? ChevronUpIcon : ChevronDownIcon}>
        Advanced: add this manually via theme code
      </Button>
      <Collapsible open={open} id="code-snippet">
        <BlockStack gap="300">
          <Text as="p" tone="subdued">
            Most themes' cart drawers aren't set up to accept app blocks placed through the Theme Editor. If yours isn't
            either, paste this into your theme code instead (Online Store &gt; Themes &gt; Edit code) — inside{' '}
            <Text as="span" fontWeight="semibold">
              snippets/cart-drawer.liquid
            </Text>{' '}
            (or wherever you want it to show) works on any theme, since it's plain code rather than an app block.
          </Text>
          <Box background="bg-surface-secondary" padding="300" borderRadius="200">
            <pre style={{ margin: 0, whiteSpace: 'pre-wrap', wordBreak: 'break-word', fontSize: 12, fontFamily: 'monospace', maxHeight: 300, overflow: 'auto' }}>
              {code}
            </pre>
          </Box>
          <InlineStack>
            <Button onClick={copy}>{copied ? 'Copied!' : 'Copy code'}</Button>
          </InlineStack>
          <Banner tone="info">
            This reads the same settings you save in this screen — no separate setup. If you later change settings here,
            the pasted code picks them up automatically.
          </Banner>
        </BlockStack>
      </Collapsible>
    </BlockStack>
  );
}
