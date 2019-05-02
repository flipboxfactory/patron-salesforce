<?php

/**
 * @copyright  Copyright (c) Flipbox Digital Limited
 * @license    https://github.com/flipboxfactory/patron-salesforce/blob/master/LICENSE
 * @link       https://github.com/flipboxfactory/patron-salesforce/
 */

namespace flipbox\patron\salesforce;

use craft\base\Plugin;
use flipbox\craft\ember\modules\LoggerTrait;
use flipbox\craft\salesforce\cp\Cp as ForceCp;
use flipbox\craft\salesforce\events\RegisterConnectionsEvent;
use flipbox\patron\cp\Cp as PatronCp;
use flipbox\patron\events\RegisterProviderInfo;
use flipbox\patron\events\RegisterProviders;
use flipbox\patron\events\RegisterProviderSettings;
use flipbox\patron\salesforce\connections\PatronConnection;
use flipbox\patron\salesforce\settings\SalesforceSettings;
use Stevenmaguire\OAuth2\Client\Provider\Salesforce;
use yii\base\Event;

/**
 * @author Flipbox Factory <hello@flipboxfactory.com>
 * @since 1.0.0
 */
class Patron extends Plugin
{
    use LoggerTrait;

    /**
     * @return string
     */
    protected static function getLogFileName(): string
    {
        return 'patron';
    }

    /**
     * @inheritdocfind
     */
    public function init()
    {
        parent::init();

        // OAuth2 Provider
        Event::on(
            PatronCp::class,
            RegisterProviders::REGISTER_PROVIDERS,
            function (RegisterProviders $event) {
                $event->providers[] = Salesforce::class; //
            }
        );

        // OAuth2 Provider Settings
        Event::on(
            Salesforce::class,
            RegisterProviderSettings::REGISTER_SETTINGS,
            function (RegisterProviderSettings $event) {
                $event->class = SalesforceSettings::class;
            }
        );

        // OAuth2 Provider Icon
        Event::on(
            PatronCp::class,
            RegisterProviderInfo::REGISTER_INFO,
            function (RegisterProviderInfo $event) {
                $event->info[Salesforce::class] = [
                    'name' => 'Salesforce',
                    'icon' => '@vendor/flipboxfactory/patron-salesforce/icons/salesforce.svg'
                ];
            }
        );

        // OAuth2 Provider Icon
        Event::on(
            ForceCp::class,
            RegisterConnectionsEvent::REGISTER_CONNECTIONS,
            function (RegisterConnectionsEvent $event) {
                $event->connections[] = PatronConnection::class;
            }
        );
    }
}
