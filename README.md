# NPF PHP Framework

## Composer JSON file
You wil need to create a `composer.json` file with the following keys at minimum:
```json
{
    "require": {
        "php-nf\/npf": "^1.0"
    },
    "scripts": {
        "post-install-cmd": [
            "Npf\\Build\\Setup::Install"
        ],
        "post-update-cmd": [
            "Npf\\Build\\Setup::Install"
        ]
    },
    "autoload": {
        "psr-4": {
            "App\\": "App\/",
            "Config\\": "Config\/",
            "Model\\": "Model\/",
            "Module\\": "Module\/",
            "Exception\\": "Exception\/",
            "Template\\": "Template\/"
        }
    }
}
```