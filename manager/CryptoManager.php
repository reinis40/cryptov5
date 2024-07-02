<?php

namespace App\Service;

use App\user\User;
use CoinMarketCapApi;
use PDO;
use TransactionLogger;

class CryptoManager
{
    private $api;
    private $db;
    private $user;
    private $logger;

    public function __construct(CoinMarketCapApi $api, PDO $db, User $user, TransactionLogger $logger)
    {
        $this->api = $api;
        $this->db = $db;
        $this->user = $user;
        $this->logger = $logger;
    }

    public function getCryptoListings(): array
    {
        $url = 'https://pro-api.coinmarketcap.com/v1/cryptocurrency/listings/latest';
        $parameters = [
              'start' => '1',
              'limit' => '10',
              'convert' => 'EUR'
        ];
        $response = $this->api->getApiData($url, $parameters);
        $cryptoList = [];

        foreach ($response['data'] as $cryptoData) {
            $cryptoList[] = [
                  'name' => $cryptoData['name'],
                  'symbol' => $cryptoData['symbol'],
                  'quote' => $cryptoData['quote']['EUR']['price'],
            ];
        }

        return $cryptoList;
    }

    public function searchCryptos(string $symbol):? array
    {
        $url = 'https://pro-api.coinmarketcap.com/v1/cryptocurrency/listings/latest';
        $parameters = [
              'start' => '1',
              'limit' => '5000', //
              'convert' => 'EUR'
        ];
        $response = $this->api->getApiData($url, $parameters);

        foreach ($response['data'] as $cryptoData) {
            if (strcasecmp($cryptoData['symbol'], $symbol) == 0) {
                return [
                      'name' => $cryptoData['name'],
                      'symbol' => $cryptoData['symbol'],
                      'quote' => $cryptoData['quote']['EUR']['price'],
                ];
            }
        }

        return null;
    }

    public function buyCrypto(string $symbol, float $amountEUR): string
    {
        $cryptoListings = $this->getCryptoListings();
        $price = null;

        foreach ($cryptoListings as $crypto) {
            if (strcasecmp($crypto['symbol'], $symbol) === 0) {
                $price = $crypto['quote'];
                break;
            }
        }

        if ($price !== null) {
            $amountCrypto = $amountEUR / $price;

            $stmt = $this->db->prepare("SELECT amount FROM wallet WHERE user_id = :user_id AND currency = 'EUR'");
            $stmt->bindParam(':user_id', $this->user->id);
            $stmt->execute();
            $currentEurAmount = $stmt->fetchColumn();

            if ($currentEurAmount >= $amountEUR) {
                $newEurAmount = $currentEurAmount - $amountEUR;

                $stmt = $this->db->prepare("UPDATE wallet SET amount = :amount WHERE user_id = :user_id AND currency = 'EUR'");
                $stmt->bindParam(':amount', $newEurAmount);
                $stmt->bindParam(':user_id', $this->user->id);
                $stmt->execute();

                $stmt = $this->db->prepare("INSERT INTO wallet (user_id, currency, amount, bought_price)
                                            VALUES (:user_id, :currency, :amount, :bought_price)
                                            ON CONFLICT(user_id, currency) DO UPDATE
                                            SET amount = amount + :amount");
                $stmt->bindParam(':user_id', $this->user->id);
                $stmt->bindParam(':currency', $symbol);
                $stmt->bindParam(':amount', $amountCrypto);
                $stmt->bindParam(':bought_price', $price);
                $stmt->execute();

                $this->logger->logTransaction($this->user->id, 'buy', $symbol, $amountCrypto, $amountEUR);
                return "Bought $amountCrypto of $symbol at €$price each.";
            } else {
                return "Insufficient funds to buy €$amountEUR of $symbol.";
            }
        } else {
            return "Error: Crypto quote not found for symbol '$symbol'.";
        }
    }

    public function sellCrypto(string $symbol): string
    {
        $cryptoListings = $this->getCryptoListings();
        $price = null;

        foreach ($cryptoListings as $crypto) {
            if (strcasecmp($crypto['symbol'], $symbol) === 0) {
                $price = $crypto['quote'];
                break;
            }
        }

        if ($price !== null) {
            $stmt = $this->db->prepare("SELECT amount FROM wallet WHERE user_id = :user_id AND currency = :currency");
            $stmt->bindParam(':user_id', $this->user->id);
            $stmt->bindParam(':currency', $symbol);
            $stmt->execute();
            $amountCrypto = $stmt->fetchColumn();

            if ($amountCrypto > 0) {
                $stmt = $this->db->prepare("DELETE FROM wallet WHERE user_id = :user_id AND currency = :currency");
                $stmt->bindParam(':user_id', $this->user->id);
                $stmt->bindParam(':currency', $symbol);
                $stmt->execute();

                $eurValue = $amountCrypto * $price;
                $stmt = $this->db->prepare("UPDATE wallet SET amount = amount + :eurValue WHERE user_id = :user_id AND currency = 'EUR'");
                $stmt->bindParam(':user_id', $this->user->id);
                $stmt->bindParam(':eurValue', $eurValue);
                $stmt->execute();

                $this->logger->logTransaction($this->user->id, 'sell', $symbol, $amountCrypto, $eurValue);
                return "Sold $amountCrypto of $symbol at €$price each.";
            } else {
                return "Error: You do not own any $symbol.";
            }
        } else {
            return "Error: Crypto quote not found for symbol '$symbol'.";
        }
    }

    public function getWallet(): array
    {
        $stmt = $this->db->prepare("SELECT * FROM wallet WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $this->user->id);
        $stmt->execute();
        $walletData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $walletEntries = [];
        foreach ($walletData as $walletEntry) {
            $symbol = $walletEntry['currency'];
            $amount = $walletEntry['amount'];
            $boughtPrice = $walletEntry['bought_price'];
            $currentPrice = $symbol === 'EUR' ? 1 : $this->getCurrentPrice($symbol);
            $valueInEur = $amount * $currentPrice;
            $profitLoss = $currentPrice && $boughtPrice ? (($currentPrice - $boughtPrice) / $boughtPrice) * 100 : 0;

            $walletEntries[] = [
                  'symbol' => $symbol,
                  'amount' => $amount,
                  'valueInEur' => number_format($valueInEur, 2),
                  'boughtPrice' => number_format($boughtPrice, 2),
                  'profitLoss' => $symbol === 'EUR' ? '-' : number_format($profitLoss, 2) . '%'
            ];
        }

        return $walletEntries;
    }

    public function getTransactions(): array
    {
        $stmt = $this->db->query("SELECT * FROM transactions ORDER BY date DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getCurrentPrice(string $symbol): float
    {
        $cryptoListings = $this->getCryptoListings();
        foreach ($cryptoListings as $crypto) {
            if (strcasecmp($crypto['symbol'], $symbol) === 0) {
                return $crypto['quote'];
            }
        }

        return 0.0;
    }
}
