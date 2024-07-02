<?php

namespace App\Controller;

use App\Service\CryptoManager;
use Twig\Environment;

class CryptoController
{
    private CryptoManager $cryptoManager;
    private Environment $twig;

    public function __construct(CryptoManager $cryptoManager, Environment $twig)
    {
        $this->cryptoManager = $cryptoManager;
        $this->twig = $twig;
    }

    public function getTopCryptos(): array
    {
        return [
              'template' => 'top.twig',
              'data' => [
                    'cryptos' => $this->cryptoManager->getCryptoListings(),
                    'current_page' => 'top'
              ]
        ];
    }

    public function home(): array
    {
        return [
              'template' => 'home.twig',
              'data' => []
        ];
    }

    public function searchCryptos(): array
    {
        $symbol = $_GET['symbol'] ?? '';
        $result = null;

        if ($symbol) {
            $result = $this->cryptoManager->searchCryptos($symbol);
        }

        return [
              'template' => 'search.twig',
              'data' => [
                    'result' => $result,
                    'symbol' => $symbol,
                    'current_page' => 'search'
              ]
        ];
    }

    public function buyCrypto(): array
    {
        $symbol = $_GET['symbol'] ?? '';
        $amountEUR = $_GET['amount'] ?? '';

        if ($symbol && $amountEUR) {
            $result = $this->cryptoManager->buyCrypto($symbol, $amountEUR);
        } else {
            $result = "Error: Symbol and amount must be provided.";
        }

        return [
              'template' => 'buy.twig',
              'data' => [
                    'result' => $result,
                    'current_page' => 'buy'
              ]
        ];
    }

    public function sellCrypto(): array
    {
        $symbol = $_GET['symbol'] ?? '';

        if ($symbol) {
            $result = $this->cryptoManager->sellCrypto($symbol);
        } else {
            $result = "Error: Symbol must be provided.";
        }

        return [
              'template' => 'sell.twig',
              'data' => [
                    'result' => $result,
                    'current_page' => 'sell'
              ]
        ];
    }

    public function showWallet(): array
    {
        return [
              'template' => 'wallet.twig',
              'data' => [
                    'walletEntries' => $this->cryptoManager->getWallet()
              ]
        ];
    }

    public function showTransactions(): array
    {
        return [
              'template' => 'transactions.twig',
              'data' => [
                    'transactions' => $this->cryptoManager->getTransactions(),
                    'current_page' => 'transactions'
              ]
        ];
    }
}
