<?php return array(
    'root' => array(
        'name' => 'wp-passkeys/wp-passkeys',
        'pretty_version' => '1.0.0+no-version-set',
        'version' => '1.0.0.0',
        'reference' => null,
        'type' => 'wordpress-plugin',
        'install_path' => __DIR__ . '/../../',
        'aliases' => array(),
        'dev' => false,
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
        'wp-passkeys/wp-passkeys' => array(
            'pretty_version' => '1.0.0+no-version-set',
            'version' => '1.0.0.0',
            'reference' => null,
            'type' => 'wordpress-plugin',
            'install_path' => __DIR__ . '/../../',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
    ),
);
