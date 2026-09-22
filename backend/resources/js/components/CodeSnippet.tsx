import { BlockStack, InlineStack, Text, Button, Box, Banner } from '@shopify/polaris';
import { useState } from 'react';

/**
 * Placement instructions for tools rendered by the "Vantora: Cart
 * Widgets" app embed -- one placeholder div, nothing else. See
 * lib/codeSnippets.ts for why this replaced full copy-paste code.
 */
export default function CodeSnippet({ placeholderClass }: { placeholderClass: string }) {
  const [copied, setCopied] = useState(false);
  const div = `<div class="${placeholderClass}"></div>`;

  const copy = async () => {
    try {
      await navigator.clipboard.writeText(div);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    } catch {
      // Clipboard API can be unavailable -- the line is still selectable by hand below.
    }
  };

  return (
    <BlockStack gap="300">
      <Text as="h3" variant="headingSm">
        Where this shows
      </Text>
      <Text as="p" tone="subdued">
        1. Turn on the <Text as="span" fontWeight="semibold">Vantora: Cart Widgets</Text> app embed once (Theme Editor
        &gt; App embeds) -- it&apos;s what looks for the placeholder below and renders into it.
      </Text>
      <Text as="p" tone="subdued">
        2. Paste this one line wherever you want it to appear (Online Store &gt; Themes &gt; Edit code) -- the cart
        page, or directly inside your theme&apos;s cart drawer file if it has one. No other code needed. It&apos;s
        safe to paste in more than one place at once (e.g. both the cart page and the drawer).
      </Text>
      <Box background="bg-surface-secondary" padding="300" borderRadius="200">
        <pre style={{ margin: 0, fontSize: 13, fontFamily: 'monospace' }}>{div}</pre>
      </Box>
      <InlineStack>
        <Button onClick={copy}>{copied ? 'Copied!' : 'Copy'}</Button>
      </InlineStack>
      <Banner tone="info">
        Reads whatever you save in this screen automatically -- change settings here any time, no need to touch the
        theme code again.
      </Banner>
    </BlockStack>
  );
}
