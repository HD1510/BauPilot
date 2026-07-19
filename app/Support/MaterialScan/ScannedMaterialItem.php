<?php

namespace App\Support\MaterialScan;

/**
 * Eine erkannte Position aus einer Preisliste, Rechnung oder einem
 * Lieferschein — Grundlage für einen neuen Artikel.
 */
final readonly class ScannedMaterialItem
{
    public function __construct(
        public string $name,
        public ?string $articleNo = null,
        public ?string $unit = null,
        public ?float $priceNet = null,
    ) {}

    /**
     * @return array{name: string, article_no: string|null, package_unit: string|null, price_net: float|null}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'article_no' => $this->articleNo,
            'package_unit' => $this->unit,
            'price_net' => $this->priceNet,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): ?self
    {
        $name = is_string($data['name'] ?? null) ? trim($data['name']) : '';

        if ($name === '') {
            return null;
        }

        $price = $data['price_net'] ?? null;

        return new self(
            name: mb_substr($name, 0, 255),
            articleNo: is_string($data['article_no'] ?? null) && trim($data['article_no']) !== ''
                ? mb_substr(trim($data['article_no']), 0, 100)
                : null,
            unit: is_string($data['package_unit'] ?? null) && trim($data['package_unit']) !== ''
                ? mb_substr(trim($data['package_unit']), 0, 100)
                : null,
            priceNet: is_int($price) || is_float($price) ? (float) $price : null,
        );
    }
}
