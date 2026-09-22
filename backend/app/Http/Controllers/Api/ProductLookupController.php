<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Shopify\ShopifyApiClient;
use Illuminate\Http\Request;

/**
 * Resolves product/variant GIDs back to a title (and image/price) for
 * display -- needed because SettingsForm's product/variant picker fields
 * only know the title of whatever was *just* picked in this session; on
 * a fresh page load (editing a previously-saved config) they have nothing
 * but the raw ID, which is exactly what a merchant reported seeing
 * instead of a product name. Called once when a settings screen opens,
 * for every product/variant GID referenced anywhere in that config.
 */
class ProductLookupController extends Controller
{
    public function index(Request $request)
    {
        $ids = array_values(array_filter(explode(',', (string) $request->query('ids', ''))));

        if (empty($ids)) {
            return response()->json([]);
        }

        // A merchant's product/bundle picks could plausibly exceed this on
        // a large config; capped rather than unbounded since this is a
        // single request built from GIDs already in one feature_config's
        // settings, not user-supplied search input.
        $ids = array_slice($ids, 0, 100);

        $shop = $request->attributes->get('shop');
        $client = new ShopifyApiClient($shop);

        $result = $client->graphql(<<<'GQL'
            query nodesLookup($ids: [ID!]!) {
                nodes(ids: $ids) {
                    id
                    ... on Product {
                        title
                        featuredMedia { preview { image { url } } }
                    }
                    ... on ProductVariant {
                        displayName
                        price
                        image { url }
                    }
                }
            }
            GQL, ['ids' => $ids]);

        $lookup = collect($result->json('data.nodes') ?? [])
            ->filter()
            ->mapWithKeys(fn ($node) => [$node['id'] => [
                'title' => $node['title'] ?? $node['displayName'] ?? null,
                'image' => $node['featuredMedia']['preview']['image']['url'] ?? $node['image']['url'] ?? null,
                'price' => $node['price'] ?? null,
            ]]);

        return response()->json($lookup);
    }
}
