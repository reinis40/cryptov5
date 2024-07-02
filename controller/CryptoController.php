<?php

namespace App\Controller;

use App\Service\CryptoManager;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class CryptoController
{
    private CryptoManager $cryptoManager;
    private Environment $twig;

    public function __construct(CryptoManager $cryptoManager, Environment $twig)
    {
        $this->cryptoManager = $cryptoManager;
        $this->twig = $twig;
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function getTopCryptos(): string
    {
        $cryptos = $this->cryptoManager->getCryptoListings();
        return $this->twig->render('top.twig', ['cryptos' => $cryptos, 'current_page' => 'top']);
    }
    public function home() {

        echo $this->twig->render('home.twig');
    }
    public function searchCryptos(): string
    {
        $symbol = $_GET['symbol'] ?? '';
        $result = null;

        if ($symbol) {
            $result = $this->cryptoManager->searchCryptos($symbol);
        }

        return $this->twig->render('search.twig', ['result' => $result, 'symbol' => $symbol, 'current_page' => 'search']);
    }

    public function buyCrypto(): string
    {
        $symbol = $_GET['symbol'] ?? '';
        $amountEUR = $_GET['amount'] ?? '';

        if ($symbol && $amountEUR) {
            $result = $this->cryptoManager->buyCrypto($symbol, $amountEUR);
        } else {
            $result = "Error: Symbol and amount must be provided.";
        }

        return $this->twig->render('buy.twig', ['result' => $result, 'current_page' => 'buy']);
    }

    public function sellCrypto(): string
    {
        $symbol = $_GET['symbol'] ?? '';

        if ($symbol) {
            $result = $this->cryptoManager->sellCrypto($symbol);
        } else {
            $result = "Error: Symbol must be provided.";
        }

        return $this->twig->render('sell.twig', ['result' => $result, 'current_page' => 'sell']);
    }

    public function showWallet(): string
    {
        $walletEntries = $this->cryptoManager->getWallet();
        return $this->twig->render('wallet.twig', ['walletEntries' => $walletEntries]);
    }

    public function showTransactions(): string
    {
        $transactions = $this->cryptoManager->getTransactions();
        return $this->twig->render('transactions.twig', ['transactions' => $transactions, 'current_page' => 'transactions']);
    }
}
