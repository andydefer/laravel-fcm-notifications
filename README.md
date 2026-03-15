# Laravel FCM Notifications

[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/Laravel-10%2F11%2F12-orange)](https://laravel.com)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

**Laravel FCM Notifications** est un package Laravel puissant et élégant qui intègre Firebase Cloud Messaging (FCM) au système de notifications natif de Laravel. Construit sur la base du package [andydefer/push-notifier](https://github.com/andydefer/push-notifier), il offre une solution complète pour envoyer des notifications push à vos utilisateurs sur Android, iOS et le web.

Ce package suit les principes SOLID et les bonnes pratiques de Laravel, avec une architecture orientée contrat (interfaces) qui le rend extrêmement flexible et testable.

---

## ✨ Fonctionnalités

- **Intégration native** avec le système de notifications de Laravel
- **Gestion automatique des tokens** : enregistrement, validation, invalidation
- **Support multi-appareils** : un utilisateur peut avoir plusieurs tokens
- **Nettoyage automatique** des tokens expirés ou inutilisés
- **File d'attente** par défaut pour ne pas bloquer vos requêtes
- **Logging détaillé** pour faciliter le débogage
- **Tests exhaustifs** : plus de 40 tests unitaires et fonctionnels
- **Traductions** : support multilingue (français, anglais)
- **Commandes artisan** pour la maintenance et les tests

---

## 📦 Installation

### Étape 1 : Installer le package

```bash
composer require andydefer/laravel-fcm-notifications
```

### Étape 2 : Publier les fichiers nécessaires

```bash
# Publier la migration
php artisan vendor:publish --provider="Andydefer\FcmNotifications\FcmNotificationServiceProvider" --tag="fcm-migrations"

# Publier la configuration (optionnel)
php artisan vendor:publish --provider="Andydefer\FcmNotifications\FcmNotificationServiceProvider" --tag="fcm-config"

# Publier les traductions (optionnel)
php artisan vendor:publish --provider="Andydefer\FcmNotifications\FcmNotificationServiceProvider" --tag="fcm-translations"
```

### Étape 3 : Exécuter les migrations

```bash
php artisan migrate
```

### Étape 4 : Configurer Firebase

Placez votre fichier de credentials Firebase (fichier JSON du compte de service) dans un emplacement sécurisé, par exemple `storage/app/firebase-credentials.json`.

Ajoutez ensuite le chemin dans votre fichier `.env` :

```env
FIREBASE_CREDENTIALS=/chemin/absolu/vers/firebase-credentials.json
# ou
FIREBASE_CREDENTIALS=storage_path('app/firebase-credentials.json')
```

---

## ⚙️ Configuration

Le fichier de configuration `config/fcm.php` vous permet de personnaliser le comportement du package :

```php
<?php

return [
    // Chemin vers le fichier de credentials Firebase
    'credentials' => env('FIREBASE_CREDENTIALS', storage_path('app/firebase-credentials.json')),

    // Gestion des tokens
    'tokens' => [
        // Durée de validité d'un token sans activité (en jours)
        'expire_inactive_days' => 30,

        // Nombre maximum de tokens par notifiable
        'max_per_notifiable' => 10,

        // Nettoyage automatique des tokens expirés
        'auto_clean' => true,
    ],

    // Configuration des logs
    'logging' => [
        'enabled' => env('FCM_LOGGING_ENABLED', true),
        'channel' => env('FCM_LOG_CHANNEL', 'stack'),
    ],

    // Nom du canal dans les notifications
    'channel_name' => 'fcm',

    // Configuration de la file d'attente
    'queue' => [
        'enabled' => env('FCM_QUEUE_ENABLED', true),
        'connection' => env('FCM_QUEUE_CONNECTION', 'redis'),
        'queue' => env('FCM_QUEUE_NAME', 'default'),
    ],
];
```

---

## 🚀 Utilisation

### 1. Préparer votre modèle

Ajoutez l'interface `HasFcmToken` et le trait `HasFcmNotifications` à tous les modèles qui peuvent recevoir des notifications push (généralement `User`).

```php
<?php

namespace App\Models;

use Andydefer\FcmNotifications\Contracts\HasFcmToken;
use Andydefer\FcmNotifications\Traits\HasFcmNotifications;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements HasFcmToken
{
    use HasFcmNotifications, Notifiable;

    // ... votre code existant
}
```

### 2. Enregistrer les tokens des appareils

Quand un utilisateur se connecte avec un nouvel appareil, enregistrez son token FCM :

```php
<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;

class FcmTokenController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'device_info' => 'sometimes|array',
        ]);

        /** @var User $user */
        $user = auth()->user();

        $fcmToken = $user->registerFcmToken(
            token: $request->token,
            isPrimary: $request->boolean('is_primary', true),
            metadata: $request->input('device_info', [])
        );

        return response()->json([
            'success' => true,
            'message' => trans('fcm::messages.token_registered'),
            'token' => $fcmToken,
        ]);
    }

    public function destroy(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        /** @var User $user */
        $user = auth()->user();

        $user->invalidateFcmToken($request->token);

        return response()->json([
            'success' => true,
            'message' => trans('fcm::messages.token_invalidated'),
        ]);
    }
}
```

### 3. Créer une notification compatible FCM

Créez une notification Laravel classique en implémentant l'interface `ShouldFcm` et en ajoutant la méthode `toFcm()` :

```php
<?php

namespace App\Notifications;

use Andydefer\FcmNotifications\Contracts\ShouldFcm;
use Andydefer\PushNotifier\Dtos\FcmMessageData;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewMessageNotification extends Notification implements ShouldFcm
{
    use Queueable;

    public function __construct(
        protected string $message,
        protected array $sender
    ) {}

    /**
     * Définit les canaux de notification
     */
    public function via($notifiable): array
    {
        return ['database', 'fcm']; // 'fcm' est le nom par défaut du canal
    }

    /**
     * Prépare la notification pour FCM
     */
    public function toFcm($notifiable): FcmMessageData
    {
        return FcmMessageData::info(
            title: 'Nouveau message',
            body: "Vous avez reçu un message de {$this->sender['name']}",
            data: [
                'sender_id' => (string) $this->sender['id'],
                'message_preview' => substr($this->message, 0, 100),
                'type' => 'new_message',
            ]
        );
    }

    /**
     * Version pour la base de données (optionnel)
     */
    public function toArray($notifiable): array
    {
        return [
            'message' => $this->message,
            'sender' => $this->sender,
        ];
    }
}
```

### 4. Envoyer la notification

```php
<?php

use App\Notifications\NewMessageNotification;
use App\Models\User;

$user = User::find(1);
$user->notify(new NewMessageNotification(
    message: 'Bonjour, comment allez-vous ?',
    sender: ['id' => 2, 'name' => 'Jean']
));
```

Et voilà ! La notification sera automatiquement envoyée à tous les appareils de l'utilisateur via FCM.

---

## 📊 Gestion avancée des tokens

### Méthodes disponibles sur les modèles

Lorsque vous utilisez le trait `HasFcmNotifications`, votre modèle dispose des méthodes suivantes :

```php
// Enregistrer un nouveau token
$user->registerFcmToken(
    token: 'device-token-123',
    isPrimary: true,
    metadata: ['device' => 'iPhone 15', 'os' => 'iOS 17']
);

// Obtenir tous les tokens valides
$tokens = $user->getFcmTokens(); // ['token-1', 'token-2']

// Obtenir le token principal
$primaryToken = $user->getPrimaryFcmToken();

// Vérifier si l'utilisateur a des tokens
if ($user->hasFcmTokens()) {
    // ...
}

// Invalider un token spécifique
$user->invalidateFcmToken('token-1');

// Invalider tous les tokens
$user->invalidateAllFcmTokens();

// Relation avec les tokens
$tokens = $user->fcmTokens()->get();
```

### Gestion automatique des tokens invalides

Le canal FCM invalide automatiquement les tokens quand Firebase retourne une erreur `UNREGISTERED` (token invalide). Vous n'avez rien à faire !

---

## 🎛 Commandes Artisan

### Nettoyer les tokens expirés

```bash
# Nettoyer les tokens inactifs depuis plus de 30 jours
php artisan fcm:clean-tokens

# Spécifier une durée personnalisée
php artisan fcm:clean-tokens --days=15

# Simuler le nettoyage sans supprimer
php artisan fcm:clean-tokens --dry-run
```

### Tester la connexion Firebase

```bash
# Tester avec un token réel
php artisan fcm:test-connection device-token-123

# Avec titre et corps personnalisés
php artisan fcm:test-connection device-token-123 --title="Test" --body="Ceci est un test"
```

---

## 🔍 Gestion des erreurs

Le package gère différents types d'erreurs avec des exceptions spécifiques :

```php
use Andydefer\PushNotifier\Exceptions\FcmSendException;
use Andydefer\PushNotifier\Exceptions\FirebaseAuthException;
use Andydefer\PushNotifier\Exceptions\InvalidConfigurationException;

try {
    $user->notify(new NewMessageNotification($message, $sender));
} catch (FcmSendException $e) {
    // Erreur lors de l'envoi FCM
    Log::error('Erreur FCM', [
        'code' => $e->getErrorCode(),
        'message' => $e->getMessage(),
        'status' => $e->getStatusCode(),
    ]);
} catch (FirebaseAuthException $e) {
    // Problème d'authentification Firebase
    Log::critical('Authentification Firebase échouée', [
        'message' => $e->getMessage(),
    ]);
} catch (InvalidConfigurationException $e) {
    // Configuration Firebase invalide
    Log::error('Configuration Firebase invalide', [
        'message' => $e->getMessage(),
    ]);
}
```

---

## 📝 Logs

Si le logging est activé, vous trouverez des entrées utiles dans vos logs :

```
[info] FCM notification sent: {"notifiable_type":"App\Models\User","notifiable_id":1,"token":"device-123","message_id":"projects/.../messages/msg-123"}
[info] FCM token invalidated: {"notifiable_type":"App\Models\User","notifiable_id":1,"token":"device-456"}
[error] FCM send error: {"error_code":"UNREGISTERED","status_code":404}
```

---

## 🧪 Tests

Le package est livré avec une suite de tests exhaustive (plus de 40 tests).

### Configuration des tests

```bash
# Créer le fichier de test
cp phpunit.xml.dist phpunit.xml

# Exécuter tous les tests
composer test

# Exécuter un test spécifique
./vendor/bin/phpunit --filter test_can_register_fcm_token
```

### Structure des tests

- **Unitaires** : tests des modèles, traits, canaux
- **Fonctionnels** : tests des flux complets
- **Commandes** : tests des commandes artisan

---

## 🔧 Architecture technique

### Contrats (Interfaces)

- `HasFcmToken` : contrat que vos modèles doivent implémenter
- `ShouldFcm` : contrat que vos notifications doivent implémenter

### Composants principaux

- `FcmChannel` : le canal de notification Laravel
- `FcmToken` : modèle Eloquent pour stocker les tokens
- `HasFcmNotifications` : trait pour vos modèles
- `CleanExpiredTokensCommand` : commande de nettoyage
- `TestFcmConnectionCommand` : commande de test

### Dépendances

Ce package repose sur [andydefer/push-notifier](https://github.com/andydefer/push-notifier), un package PHP robuste pour l'envoi de notifications via FCM.

---

## 🌐 Traductions

Le package supporte le français et l'anglais. Pour personnaliser les messages :

```bash
php artisan vendor:publish --provider="Andydefer\FcmNotifications\FcmNotificationServiceProvider" --tag="fcm-translations"
```

Puis modifiez les fichiers dans `resources/lang/vendor/fcm/`.

---

## 🤝 Contribution

Les contributions sont les bienvenues ! Voici comment contribuer :

1. **Forkez** le projet
2. **Créez une branche** (`git checkout -b feature/ma-fonctionnalite`)
3. **Commitez** vos changements (`git commit -m 'Ajoute une fonctionnalité'`)
4. **Poussez** la branche (`git push origin feature/ma-fonctionnalite`)
5. **Ouvrez une Pull Request**

### Guide de contribution

- Suivez les conventions de code PSR-12
- Ajoutez des tests pour toute nouvelle fonctionnalité
- Assurez-vous que tous les tests passent (`composer test`)
- Documentez les nouvelles fonctionnalités

---

## 📄 Licence

Ce package est open-source et disponible sous la licence [MIT](LICENSE). Vous êtes libre de l'utiliser, le modifier et le distribuer.

---

## 🙏 Remerciements

- [Laravel](https://laravel.com) pour son système de notifications
- [Firebase](https://firebase.google.com) pour FCM
- Tous les contributeurs du package [push-notifier](https://github.com/andydefer/push-notifier)

---

## 🆘 Support

Si vous rencontrez des problèmes :

1. **Consultez la documentation** de ce README
2. **Vérifiez les issues** existantes sur GitHub
3. **Créez une nouvelle issue** avec :
   - Une description claire du problème
   - Les étapes pour reproduire
   - Votre environnement (PHP, Laravel, versions)

---

## 🚀 Exemple complet

Voici un exemple complet d'utilisation dans une application Laravel typique :

```php
<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\OrderConfirmationNotification;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        // ... création de la commande

        $user = User::find($request->user_id);

        // Envoyer une notification (sera délivrée par FCM si l'utilisateur a des tokens)
        $user->notify(new OrderConfirmationNotification(
            orderId: $order->id,
            total: $order->total
        ));

        return response()->json(['message' => 'Commande créée avec succès']);
    }
}

// La notification
namespace App\Notifications;

use Andydefer\FcmNotifications\Contracts\ShouldFcm;
use Andydefer\PushNotifier\Dtos\FcmMessageData;
use Illuminate\Notifications\Notification;

class OrderConfirmationNotification extends Notification implements ShouldFcm
{
    public function __construct(
        protected int $orderId,
        protected float $total
    ) {}

    public function via($notifiable): array
    {
        return ['mail', 'database', 'fcm'];
    }

    public function toFcm($notifiable): FcmMessageData
    {
        return FcmMessageData::success(
            title: 'Commande confirmée',
            body: "Votre commande #{$this->orderId} d'un montant de {$this->total}€ a été confirmée.",
            data: [
                'order_id' => (string) $this->orderId,
                'total' => (string) $this->total,
                'type' => 'order_confirmation',
            ]
        );
    }
}
```

---

**Laravel FCM Notifications** - La façon élégante d'ajouter les notifications push à vos applications Laravel. 🚀📱