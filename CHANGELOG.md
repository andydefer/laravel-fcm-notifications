# Changelog

Toutes les modifications notables apportées à ce package sont documentées dans ce fichier.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/),
et ce projet adhère au [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2024-03-15

### Added
- **Token primaire dynamique** : Le token le plus récemment utilisé (basé sur `last_used_at`) est automatiquement considéré comme primaire
- **Accesseur `is_primary`** sur le modèle `FcmToken` pour une rétrocompatibilité avec l'ancien champ
- **Méthodes `markAsUsed()` et `invalidate()`** sur le modèle `FcmToken` pour une gestion plus explicite des tokens
- **Index optimisés** dans la migration :
  - `fcm_tokens_tokenable_valid_index` pour les recherches de tokens valides
  - `fcm_tokens_last_used_index` pour le nettoyage des tokens inactifs
- **Logging amélioré** avec des messages plus informatifs pour les envois simples et multicast
- **Documentation PHPDoc complète** pour toutes les classes et méthodes publiques
- **Mode verbose** pour la commande `fcm:test-connection` affichant les stack traces
- **Tests de validation** pour le comportement LRU et la gestion des tokens primaires

### Changed
- **Architecture du code** : Extraction de nombreuses méthodes privées pour respecter le principe de responsabilité unique (SRP)
- **Structure des tests** : Tous les tests suivent maintenant le pattern Arrange-Act-Assert avec commentaires explicatifs
- **Nommage des méthodes de test** : Plus explicites et descriptifs
- **Migration** : Le champ `token` passe de `longText` à `string(500)` pour des performances optimales
- **Configuration** : Fichier `config/fcm.php` entièrement documenté en français
- **Messages des commandes** : Plus informatifs avec des conseils de dépannage

### Removed
- **Champ `is_primary` de la base de données** : Remplacé par un accesseur dynamique
- **Scope `primary()`** sur le modèle `FcmToken` (non pertinent avec le nouveau système)
- **Fichier vide** `src/Exceptions/FcmConfigurationException.php`
- **Fixtures inutilisés** : `FcmTokenFixture.php`

### Fixed
- **Gestion des limites de tokens** : Correction de la logique LRU pour respecter correctement `max_per_notifiable`
- **Validation des interfaces** dans le canal FCM avec messages de warning appropriés
- **Chemins des fixtures** dans la configuration de test
- **Index en double** dans la migration des tokens
- **Gestion du token primaire** lors de l'invalidation (bascule automatique vers le suivant)

### Security
- **Validation renforcée** des tokens avant envoi des notifications
- **Nettoyage automatique** des tokens expirés via la commande `fcm:clean-tokens`

---

## [0.1.4] - 2024-02-28

### Added
- Support de Laravel 12
- Tests pour les notifications anonymes

### Fixed
- Correction des types dans les PHPDoc

---

## [0.1.3] - 2024-02-15

### Added
- Commande `fcm:test-connection` pour tester la configuration Firebase
- Traductions françaises et anglaises

### Changed
- Amélioration de la documentation

---

## [0.1.2] - 2024-02-01

### Added
- Support de la file d'attente pour les notifications
- Logging configurable

### Fixed
- Correction de la validation des tokens dans le canal FCM

---

## [0.1.1] - 2024-01-15

### Added
- Commande `fcm:clean-tokens` pour nettoyer les tokens expirés
- Tests unitaires pour le trait `HasFcmNotifications`

### Fixed
- Correction des relations polymorphiques

---

## [0.1.0] - 2024-01-01

### Added
- Version initiale du package
- Canal de notification FCM (`FcmChannel`)
- Modèle `FcmToken` avec relations polymorphiques
- Trait `HasFcmNotifications` pour les modèles
- Contrats `HasFcmToken` et `ShouldFcm`
- Configuration de base (`config/fcm.php`)
- Migration pour la table `fcm_tokens`
- Service provider pour l'intégration Laravel
- Tests de base pour les fonctionnalités principales