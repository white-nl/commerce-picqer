Craft Picqer Plugin
===================

Picqer plugin for Craft CMS, official version by WHITE Digital Agency



This plugin provides order and product stock synchronization between Craft Commerce and Picqer.

It allows you to pull product stock from Picqer to Craft, push orders from Craft to Picqer and pull order status changes back to Craft.

It uses order **Reference** field to match orders between Craft and Picqer, and to synchronize product stock, it uses **SKU**
of the product variant on the Craft side and matches it to the **Product Code** on the Picqer side.


Requirements
------------

* This plugin requires Craft CMS 4.0.0 or later
* This plugin requires Craft Commerce version 4.0
* A valid Picqer account is required
* The Craft website should be publicly accessible
* Changing settings should be allowed in Craft ([allow admin changes](https://craftcms.com/docs/3.x/config/config-settings.html#allowadminchanges)), and a user who is an Admin in Craft.
* The plugin should be able to create a custom table in your database
* This plugin is compatible with Composer 2.0


## Installation

1. Install the composer package: `composer require white-nl/commerce-picqer`
2. Install the plugin in Craft admin.
3. Go to the plugin settings and configure your Picqer credentials.
4. After that, you will be able to further configure the integration on the plugin settings page.


## Cron and console commands

You can use this console command to pull all product stock from Picqer to Craft:
```
php craft commercepicqer/import-product-stock
```

More options available. To see them, run the command with the `--help` argument.
If you want to run it on schedule, feel free to add this command to your crontab, by default it doesn't produce any output.


## Debugging and logs

This plugin produces its logs into a separate log category, `commerce-picqer`.
To extract its logs into a separate file, you can configure your `config/app.php` file like this:

```php
return [
    '*' => [
        'components' => [
            'log' => function() {
                $config = craft\helpers\App::logConfig();
                if ($config) {
                    $config['targets']['commerce-picqer'] = [
                        'class' => \craft\log\FileTarget::class,
                        'logFile' => '@storage/logs/commerce-picqer.log',
                        'categories' => ['commerce-picqer'],
                        //'levels' => Logger::LEVEL_ERROR | Logger::LEVEL_WARNING,
                        'logVars' => [],
                    ];
                }
                return $config ? Craft::createObject($config) : null;
            },
        ]
    ]
];
```

## Documentation
https://white.nl/en/craft-plugins/picqer/docs/

Picqer for Craft CMS is brought to you by WHITE Digital Agency