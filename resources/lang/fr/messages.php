<?php

declare(strict_types=1);

return [
    'sources' => [
        'woocommerce' => 'Importez une copie de votre boutique WooCommerce via son API REST.',
    ],

    'entities' => [
        'categories' => 'Catégories',
        'collections' => 'Collections',
        'tags' => 'Étiquettes',
        'brands' => 'Marques',
        'attributes' => 'Attributs',
        'products' => 'Produits',
        'customers' => 'Clients',
        'discounts' => 'Remises',
        'orders' => 'Commandes',
    ],

    'woocommerce' => [
        'title' => 'Importer depuis WooCommerce',
        'description' => 'Connectez votre boutique WooCommerce pour migrer vos données dans Shopper.',
        'steps' => [
            'connection' => 'Connexion',
            'selection' => 'Données à migrer',
        ],
        'selection_description' => 'Choisissez les types de données à transférer vers Shopper.',
        'store_url' => 'URL de la boutique',
        'consumer_key' => 'Clé client',
        'consumer_secret' => 'Secret client',
        'credentials_helper' => 'Générez des clés API en lecture seule dans WooCommerce > Réglages > Avancé > API REST.',
        'test_connection' => 'Tester la connexion',
        'connection_ok' => 'Connexion réussie',
        'connection_failed' => 'Échec de la connexion',
        'products_found' => ':count produits trouvés dans votre boutique.',
        'submit' => 'Démarrer la migration',
    ],

    'wizard' => [
        'title' => 'Importer depuis :source',
        'steps' => [
            'connection' => 'Connexion',
            'selection' => 'Données à migrer',
        ],
        'selection_description' => 'Choisissez les types de données à transférer vers Shopper.',
        'test_connection' => 'Tester la connexion',
        'connection_failed' => 'Échec de la connexion',
        'products_found' => ':count produits trouvés dans votre boutique.',
        'submit' => 'Démarrer la migration',
    ],

    'progress' => [
        'title' => 'Migration de boutique',
        'description' => 'Vos données sont en cours de migration. Vous pouvez fermer ce panneau, la migration continue en arrière-plan.',
        'empty' => 'Aucune migration n\'a encore été lancée.',
        'finished' => 'La migration est terminée.',
        'count' => ':migrated sur :total',
        'failed' => '{1} :count enregistrement en échec.|[2,*] :count enregistrements en échec.',
    ],


    'errors' => [
        'unauthorized' => 'La boutique a rejeté les identifiants API. Vérifiez la clé et le secret client.',
        'unreachable' => 'La boutique est injoignable (HTTP :status). Vérifiez l\'URL de la boutique.',
        'request_failed' => 'La requête vers :endpoint a échoué (HTTP :status).',
        'insecure_url' => 'L\'URL de la boutique doit utiliser HTTPS.',
        'forbidden_host' => 'L\'URL de la boutique pointe vers un hôte non autorisé (:host).',
        'too_many_attempts' => 'Trop de tentatives. Patientez un instant avant de réessayer.',
    ],
];
