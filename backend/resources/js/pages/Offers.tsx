import type { Shop } from '../lib/api';
import CategoryPage from './CategoryPage';

export default function Offers({ shop }: { shop: Shop }) {
  return <CategoryPage category="offers" shop={shop} />;
}
