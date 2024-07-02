<?php
use App\Controller\CryptoController;
return
      [
            ['GET', '/top', [CryptoController::class, 'getTopCryptos']],
            ['GET', '/search', [CryptoController::class, 'searchCryptos']],
            ['GET', '/buy', [CryptoController::class, 'buyCrypto']],
            ['GET', '/sell', [CryptoController::class, 'sellCrypto']],
            ['GET', '/wallet', [CryptoController::class, 'showWallet']],
            ['GET', '/transactions', [CryptoController::class, 'showTransactions']],
            ['GET', '/', [CryptoController::class, 'home']]
      ];