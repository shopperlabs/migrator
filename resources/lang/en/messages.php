<?php

declare(strict_types=1);

return [
    'sources' => [
        'woocommerce' => 'Import a copy of your WooCommerce store using its REST API.',
    ],

    'entities' => [
        'categories' => 'Categories',
        'collections' => 'Collections',
        'tags' => 'Tags',
        'brands' => 'Brands',
        'attributes' => 'Attributes',
        'products' => 'Products',
        'customers' => 'Customers',
        'discounts' => 'Discounts',
        'orders' => 'Orders',
    ],

    'woocommerce' => [
        'title' => 'Import from WooCommerce',
        'description' => 'Connect your WooCommerce store to migrate your data into Shopper.',
        'steps' => [
            'connection' => 'Connection',
            'selection' => 'Data to migrate',
        ],
        'selection_description' => 'Choose the types of data you want to move to Shopper.',
        'store_url' => 'Store URL',
        'consumer_key' => 'Consumer key',
        'consumer_secret' => 'Consumer secret',
        'credentials_helper' => 'Generate read-only API keys in WooCommerce > Settings > Advanced > REST API.',
        'test_connection' => 'Test connection',
        'connection_ok' => 'Connection successful',
        'connection_failed' => 'Connection failed',
        'products_found' => ':count products found in your store.',
        'submit' => 'Start migration',
    ],

    'wizard' => [
        'title' => 'Import from :source',
        'steps' => [
            'connection' => 'Connection',
            'selection' => 'Data to migrate',
        ],
        'selection_description' => 'Choose the types of data you want to move to Shopper.',
        'test_connection' => 'Test connection',
        'connection_failed' => 'Connection failed',
        'products_found' => ':count products found in your store.',
        'submit' => 'Start migration',
    ],

    'progress' => [
        'title' => 'Store migration',
        'description' => 'Your data is being migrated. You can close this panel, the migration will continue in the background.',
        'empty' => 'No migration has been started yet.',
        'finished' => 'The migration is finished.',
        'count' => ':migrated of :total',
        'failed' => '{1} :count record failed.|[2,*] :count records failed.',
    ],


    'errors' => [
        'unauthorized' => 'The store rejected the API credentials. Check the consumer key and secret.',
        'unreachable' => 'The store could not be reached (HTTP :status). Check the store URL.',
        'request_failed' => 'The request to :endpoint failed (HTTP :status).',
        'insecure_url' => 'The store URL must use HTTPS.',
        'forbidden_host' => 'The store URL points to a host that is not allowed (:host).',
        'too_many_attempts' => 'Too many attempts. Please wait a moment before trying again.',
    ],
];
