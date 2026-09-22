import type { Shop } from '../lib/api';
import CategoryPage from './CategoryPage';

export default function Storefront({ shop }: { shop: Shop }) {
  return <CategoryPage category="storefront" shop={shop} />;
}
