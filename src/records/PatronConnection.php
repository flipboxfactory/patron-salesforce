<?php

/**
 * @copyright  Copyright (c) Flipbox Digital Limited
 * @license    https://github.com/flipboxfactory/patron-salesforce/blob/master/LICENSE
 * @link       https://github.com/flipboxfactory/patron-salesforce
 */

namespace flipbox\patron\salesforce\records;

use Craft;
use craft\helpers\ArrayHelper;
use flipbox\craft\salesforce\Force;
use flipbox\craft\salesforce\records\Connection;
use flipbox\patron\queries\ProviderQuery;
use flipbox\patron\records\Provider;
use Flipbox\Salesforce\Connections\ConnectionInterface;
use Psr\Http\Message\RequestInterface;
use Stevenmaguire\OAuth2\Client\Provider\Salesforce;
use yii\validators\RequiredValidator;
use Zend\Diactoros\Uri;

/**
 * @author Flipbox Factory <hello@flipboxfactory.com>
 * @since 1.0.0
 */
class PatronConnection extends Connection implements ConnectionInterface
{
    use AccessTokenAuthorizationTrait;

    /**
     * @var Provider
     */
    private $provider;

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('patron-salesforce', 'Patron OAuth Token');
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return array_merge(
            parent::rules(),
            [
                [
                    [
                        'settings'
                    ],
                    'validateSettings',
                    'skipOnError' => false
                ]
            ]
        );
    }

    /**
     * @param $attribute
     */
    public function validateSettings($attribute)
    {
        $settings = $this->{$attribute};

        $validator = new RequiredValidator();

        $requiredSettings = ['version', 'provider'];
        foreach ($requiredSettings as $requiredSetting) {
            $error = null;
            if (false === ($validator->validate(($settings[$requiredSetting] ?? null), $error))) {
                $this->addError('settings.' . $requiredSetting, $error);
            }
        }
    }

    /**
     * @param bool $insert
     * @param array $changedAttributes
     * @throws \Throwable
     */
    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);

        // Delete existing lock
        if (null !== ($provider = ArrayHelper::getValue($changedAttributes, 'settings.provider'))) {
            $condition = [
                (is_numeric($provider) ? 'id' : 'handle') => $provider,
                'environment' => null,
                'enabled' => null
            ];

            if (null !== ($provider = Provider::findOne($condition))) {
                $provider->removeLock(Force::getInstance());
            }
        }

        $this->getPatronProvider(false)->addLock(Force::getInstance());
    }

    /**
     * @inheritdoc
     * @throws \yii\base\InvalidConfigException
     */
    public function getResourceUrl(): string
    {
        $version = $this->getSettingsValue('version');

        return $this->getInstanceUrl() .
            '/services/data' .
            (!empty($version) ? ('/' . $version) : '');
    }

    /**
     * @inheritdoc
     *
     * @throws \Twig_Error_Loader
     * @throws \yii\base\Exception
     */
    public function getSettingsHtml(): string
    {
        $providers = Provider::find()
            ->class(Salesforce::class)
            ->environment(null)
            ->enabled(null);

        return Craft::$app->view->renderTemplate(
            'patron-salesforce/connections/configuration',
            [
                'provider' => $this->getPatronProvider(false),
                'connection' => $this,
                'providers' => $providers->all()
            ]
        );
    }

    /**
     * @param bool $restricted
     * @return Provider
     */
    protected function getPatronProvider(bool $restricted = true): Provider
    {
        $settings = $this->settings;

        // Get provider from settings
        if (null !== ($provider = $settings['provider'] ?? null)) {
            $condition = [
                (is_numeric($provider) ? 'id' : 'handle') => $provider
            ];

            if ($restricted !== true) {
                $condition['environment'] = null;
                $condition['enabled'] = null;
            }

            $provider = Provider::findOne($condition);
        }

        if (!$provider instanceof Provider) {
            $provider = new Provider();
        }

        $provider->class = Salesforce::class;

        return $provider;
    }

    /**
     * @return Salesforce
     * @throws \yii\base\InvalidConfigException
     */
    public function getProvider(): Salesforce
    {
        if ($this->provider === null) {
            $settings = $this->settings;

            // Get provider from settings
            if (null !== ($provider = $settings['provider'] ?? null)) {
                $condition = [
                    (is_numeric($provider) ? 'id' : 'handle') => $provider
                ];
                $provider = (new ProviderQuery($condition))->one();
            }

            if (!$provider instanceof Salesforce) {
                $provider = new Salesforce();
            }

            $this->provider = $provider;
        }

        return $this->provider;
    }

    /**
     * @inheritdoc
     * @throws \yii\base\InvalidConfigException
     */
    public function getInstanceUrl(): string
    {
        return rtrim($this->getProvider()->getDomain(), '/');
    }

    /**
     * @param RequestInterface $request
     * @return RequestInterface
     * @throws \yii\base\InvalidConfigException
     */
    public function prepareInstanceRequest(RequestInterface $request): RequestInterface
    {
        $request = $request->withUri(
            new Uri($this->getResourceUrl())
        );

        foreach ($this->getProvider()->getHeaders() as $key => $value) {
            $request = $request->withAddedHeader($key, $value);
        }

        return $request;
    }
}