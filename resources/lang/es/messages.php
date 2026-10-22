<?php

declare(strict_types=1);

return [
    'sources' => [
        'woocommerce' => 'Importe una copia de su tienda WooCommerce mediante su API REST.',
    ],

    'entities' => [
        'categories' => 'Categorías',
        'collections' => 'Colecciones',
        'tags' => 'Etiquetas',
        'brands' => 'Marcas',
        'attributes' => 'Atributos',
        'products' => 'Productos',
        'customers' => 'Clientes',
        'discounts' => 'Descuentos',
        'orders' => 'Pedidos',
    ],

    'woocommerce' => [
        'title' => 'Importar desde WooCommerce',
        'description' => 'Conecte su tienda WooCommerce para migrar sus datos a Shopper.',
        'steps' => [
            'connection' => 'Conexión',
            'selection' => 'Datos a migrar',
        ],
        'selection_description' => 'Elija los tipos de datos que desea transferir a Shopper.',
        'store_url' => 'URL de la tienda',
        'consumer_key' => 'Clave de cliente',
        'consumer_secret' => 'Secreto de cliente',
        'credentials_helper' => 'Genere claves API de solo lectura en WooCommerce > Ajustes > Avanzado > API REST.',
        'test_connection' => 'Probar la conexión',
        'connection_ok' => 'Conexión exitosa',
        'connection_failed' => 'Error de conexión',
        'products_found' => ':count productos encontrados en su tienda.',
        'submit' => 'Iniciar la migración',
    ],

    'wizard' => [
        'title' => 'Importar desde :source',
        'steps' => [
            'connection' => 'Conexión',
            'selection' => 'Datos a migrar',
        ],
        'selection_description' => 'Elija los tipos de datos que desea transferir a Shopper.',
        'test_connection' => 'Probar la conexión',
        'connection_failed' => 'Error de conexión',
        'products_found' => ':count productos encontrados en su tienda.',
        'submit' => 'Iniciar la migración',
    ],

    'progress' => [
        'title' => 'Migración de tienda',
        'description' => 'Sus datos se están migrando. Puede cerrar este panel, la migración continuará en segundo plano.',
        'empty' => 'Aún no se ha iniciado ninguna migración.',
        'finished' => 'La migración ha finalizado.',
        'count' => ':migrated de :total',
        'failed' => '{1} :count registro falló.|[2,*] :count registros fallaron.',
    ],

    'dock' => [
        'title' => 'Migración en curso',
        'summary' => ':finished de :total tipos de datos migrados.',
    ],

    'errors' => [
        'unauthorized' => 'La tienda rechazó las credenciales de la API. Verifique la clave y el secreto de cliente.',
        'unreachable' => 'No se pudo contactar la tienda (HTTP :status). Verifique la URL de la tienda.',
        'request_failed' => 'La solicitud a :endpoint falló (HTTP :status).',
        'insecure_url' => 'La URL de la tienda debe usar HTTPS.',
        'forbidden_host' => 'La URL de la tienda apunta a un host no permitido (:host).',
        'unknown_host' => 'No se encontró el host de la tienda (:host). Verifique la URL de la tienda.',
        'redirected' => 'La tienda redirige a :host. Use esta dirección como URL de la tienda.',
        'too_many_attempts' => 'Demasiados intentos. Espere un momento antes de volver a intentarlo.',
    ],
];
