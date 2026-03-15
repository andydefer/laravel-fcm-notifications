<?php

/**
 * Configuration du package Laravel FCM (Firebase Cloud Messaging)
 *
 * Ce fichier permet de configurer tous les aspects du package :
 * - Authentification Firebase
 * - Gestion des tokens FCM
 * - Comportement de logging
 * - File d'attente des notifications
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Firebase Credentials
    |--------------------------------------------------------------------------
    |
    | Définit comment le package s'authentifie auprès de Firebase Cloud Messaging.
    | Vous pouvez soit fournir un chemin vers le fichier JSON des credentials,
    | soit définir la variable d'environnement FIREBASE_CREDENTIALS.
    |
    */
    'credentials' => env('FIREBASE_CREDENTIALS', storage_path('app/firebase-credentials.json')),

    /*
    |--------------------------------------------------------------------------
    | Token Management
    |--------------------------------------------------------------------------
    |
    | Configure la gestion du cycle de vie des tokens FCM :
    | - Durée de validité des tokens inactifs
    | - Limite de tokens par utilisateur/entité notifiable
    | - Nettoyage automatique des tokens expirés
    |
    */
    'tokens' => [

        /**
         * Durée de conservation des tokens inactifs (en jours)
         * Un token est considéré inactif si aucune notification ne lui a été envoyée
         */
        'expire_inactive_days' => 30,

        /**
         * Nombre maximum de tokens autorisés par entité notifiable
         * Évite l'accumulation excessive de tokens pour un même utilisateur
         */
        'max_per_notifiable' => 10,

        /**
         * Active le nettoyage automatique des tokens expirés
         * Utilise le scheduler Laravel pour exécuter la commande de nettoyage
         */
        'auto_clean' => true,

    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Paramètres de journalisation des notifications FCM.
    | Permet de tracer l'envoi des notifications et les éventuelles erreurs.
    |
    */
    'logging' => [

        /**
         * Active/désactive la journalisation des notifications FCM
         */
        'enabled' => env('FCM_LOGGING_ENABLED', true),

        /**
         * Canal de log utilisé (stack, daily, slack, etc.)
         */
        'channel' => env('FCM_LOG_CHANNEL', 'stack'),

    ],

    /*
    |--------------------------------------------------------------------------
    | Default Channel Name
    |--------------------------------------------------------------------------
    |
    | Nom du canal à utiliser dans la méthode via() des notifications.
    | Ce nom doit correspondre à celui défini dans config/services.php
    | pour le driver FCM.
    |
    */
    'channel_name' => 'fcm',

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration de la file d'attente pour les notifications FCM.
    | L'utilisation de la queue est recommandée pour ne pas bloquer
    | la réponse HTTP lors de l'envoi de nombreuses notifications.
    |
    */
    'queue' => [

        /**
         * Active la mise en file d'attente par défaut des notifications FCM
         */
        'enabled' => env('FCM_QUEUE_ENABLED', true),

        /**
         * Connexion de queue à utiliser (redis, database, sqs, etc.)
         */
        'connection' => env('FCM_QUEUE_CONNECTION', 'redis'),

        /**
         * Nom de la file d'attente spécifique pour FCM
         */
        'queue' => env('FCM_QUEUE_NAME', 'default'),

    ],

];
