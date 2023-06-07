<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'OAuth OpenID Connect Single Sign-On (SSO)',
    'description' => 'Typo3 OAuth / OpenID Connect Single Sign-On SSO extension by miniOrange allows login (Single Sign-On) into Typo3 with Azure AD, Azure B2C, AWS Cognito, Office 365, WSO2, Okta, Salesforce, Discord, Keycloak, Amazon, Twitch, LinkedIn, Invision Community, Slack, Discord, PingFederate, OneLogin or other custom OAuth 2.0 providers. Typo3 OAuth / OIDC extension can be used for authorization and authentication purposes with any OAuth / OIDC Provider that conforms to the OAuth 2.0 and OpenID Connect (OIDC) standards.
<br>
       Typo3 OAuth / OpenID Connect Single Sign-On SSO provides user authentication with OAuth & OpenID Connect protocol and allows authorized users to login into the Typo3 site. We provide features like Attribute Mapping & Role Mapping which help to map user data returned from your OAuth Provider to Typo3.',
    'category' => 'plugin',
    'author' => 'Miniorange',
    'author_email' => 'info@xecurify.com',
    'state' => 'stable',
    'uploadfolder' => 0,
    'createDirs' => '',
    'clearCacheOnLoad' => 0,
    'version' => '1.0.4',
    'constraints' => [
        'depends' => [
            'typo3' => '8.7.0-11.5.27',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
		'autoload' => [
				'psr-4' => [
						'Miniorange\\MiniorangeOidc\\' => 'Classes',
						'Miniorange\\Helper\\' => 'Helper',
				]
		],

];