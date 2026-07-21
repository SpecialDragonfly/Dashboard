<?php

namespace App\Domain;

class Stock
{
    public function __construct(
        private int $id,
        private string $symbol,
        private string $name,
        private float $quantity,
        private float $price,
        private ?string $exDiv,
        private ?string $dividend,
        private float $totalDividendPayments,
    ) {}

    public function getId(): int { return $this->id; }
    public function getSymbol(): string { return $this->symbol; }
    public function getName(): string { return $this->name; }
    public function getQuantity(): float { return $this->quantity; }
    public function getPrice(): float { return $this->price; }
    public function getExDiv(): ?string { return $this->exDiv; }
    public function getDividend(): ?string { return $this->dividend; }
    public function getTotalDividendPayments(): float { return $this->totalDividendPayments; }

    /**
     * @return array{
     *     id: int,
     *     symbol: string,
     *     name: string,
     *     quantity: float,
     *     price: float,
     *     exDiv: ?string,
     *     dividend: ?string,
     *     totalDividendPayments: float,
     * }
     */
    public function toArray(): array
    {
        return [
            'id'                    => $this->id,
            'symbol'                => $this->symbol,
            'name'                  => $this->name,
            'quantity'              => $this->quantity,
            'price'                 => $this->price,
            'exDiv'                 => $this->exDiv,
            'dividend'              => $this->dividend,
            'totalDividendPayments' => $this->totalDividendPayments,
        ];
    }
}
