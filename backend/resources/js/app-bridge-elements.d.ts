// App Bridge web components (https://cdn.shopify.com/shopifycloud/app-bridge.js).
// These render into Shopify Admin's own chrome outside our iframe, not as
// plain DOM nodes -- declared here so TSX can use them as intrinsic elements.
import type { DetailedHTMLProps, HTMLAttributes } from 'react';

type AppBridgeElementProps = DetailedHTMLProps<HTMLAttributes<HTMLElement>, HTMLElement>;

declare global {
  namespace JSX {
    interface IntrinsicElements {
      's-app-nav': AppBridgeElementProps;
      's-link': AppBridgeElementProps & { href?: string; rel?: string };
    }
  }
}

export {};
