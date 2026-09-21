<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * F-19 AI Product Optimizer's model backend. Uses the Anthropic Messages
 * API (https://docs.claude.com/en/api/messages). Requires AI_PROVIDER_API_KEY
 * to be set -- it's an empty/reserved env var until then, and calls will
 * throw rather than silently no-op, so the job surfaces as failed instead
 * of pretending to have generated something.
 */
class AiProviderClient
{
    public function generateProductCopy(array $product): array
    {
        $apiKey = config('services.ai_provider.key');

        if (! $apiKey) {
            throw new \RuntimeException('AI_PROVIDER_API_KEY is not configured.');
        }

        $prompt = <<<PROMPT
            You are writing Shopify product copy. Given this product data, suggest an
            improved title, description, three bullet points, and two FAQ entries.
            Respond with ONLY JSON matching this shape, no prose:
            {"title": "...", "description": "...", "bullets": ["...", "...", "..."], "faq": [{"question": "...", "answer": "..."}, {"question": "...", "answer": "..."}]}

            Product data:
            {$this->formatProduct($product)}
            PROMPT;

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])
            ->timeout(60)
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => config('services.ai_provider.model', 'claude-sonnet-5'),
                'max_tokens' => 1024,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ])
            ->throw();

        $text = $response->json('content.0.text');
        $tokens = ($response->json('usage.input_tokens') ?? 0) + ($response->json('usage.output_tokens') ?? 0);

        $parsed = json_decode($text, true);
        if (! is_array($parsed)) {
            throw new \RuntimeException('AI provider returned non-JSON output.');
        }

        return ['output' => $parsed, 'tokens' => $tokens];
    }

    protected function formatProduct(array $product): string
    {
        return json_encode([
            'title' => $product['title'] ?? '',
            'description' => $product['description'] ?? '',
            'product_type' => $product['product_type'] ?? '',
        ], JSON_PRETTY_PRINT);
    }
}
