import type { Shop } from '../lib/api';
import CategoryPage from './CategoryPage';

export default function Storefront({ shop, initialType }: { shop: Shop; initialType?: string | null }) {
  return <CategoryPage category="storefront" shop={shop} initialType={initialType} />;
}
