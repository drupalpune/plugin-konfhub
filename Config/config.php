<?php

declare(strict_types=1);

return [
    'name'        => 'Konfhub Integration',
    'description' => 'Integrate KonfHub with Mautic to nurture the leads and enable personalized communication.',
    'version'     => '1.0.0',
    'author'      => 'Rahul Shinde',
    'routes'      => [
        'public' => [
            'mautic_konfhub_webhook' => [
                'path'       => '/plugin/webhook/konfhub',
                'controller' => 'MauticPlugin\KonfhubBundle\Controller\WebhookController::listenAction',
                'method'     => 'POST',
                'format'     => 'json',
            ],
        ],
    ],
];
