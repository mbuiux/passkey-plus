<?php return array(
    'root' => array(
        'name' => 'passkey-hub/passkey-hub',
        'pretty_version' => 'dev-main',
        'version' => 'dev-main',
        'reference' => 'de97c326ca09917edcb665d53e67e410246d3a76',
        'type' => 'wordpress-plugin',
        'install_path' => __DIR__ . '/../../',
        'aliases' => array(),
        'dev' => true,
    ),
    'versions' => array(
        'lbuchs/webauthn' => array(
            'pretty_version' => 'v2.2.0',
            'version' => '2.2.0.0',
            'reference' => '20adb4a240c3997bd8cac7dc4dde38ab0bea0ed1',
            'type' => 'library',
            'install_path' => __DIR__ . '/../lbuchs/webauthn',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
        'passkey-hub/passkey-hub' => array(
            'pretty_version' => 'dev-main',
            'version' => 'dev-main',
            'reference' => 'de97c326ca09917edcb665d53e67e410246d3a76',
            'type' => 'wordpress-plugin',
            'install_path' => __DIR__ . '/../../',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
    ),
);
