<?php

/**
 * @copyright  Copyright (c) Flipbox Digital Limited
 * @license    https://github.com/flipboxfactory/patron-salesforce/blob/master/LICENSE
 * @link       https://github.com/flipboxfactory/patron-salesforce
 */

namespace flipbox\patron\salesforce\connections;

use Craft;
use craft\helpers\ArrayHelper;
use flipbox\craft\integration\connections\AbstractSaveableConnection;
use flipbox\craft\salesforce\connections\SavableConnectionInterface;
use flipbox\craft\salesforce\Force;
use flipbox\patron\records\Provider;
use Psr\Http\Message\RequestInterface;
use Stevenmaguire\OAuth2\Client\Provider\Salesforce;
use Zend\Diactoros\Uri;

/**
 * @author Flipbox Factory <hello@flipboxfactory.com>
 * @since 1.0.0
 */
class PatronConnection extends AbstractSaveableConnection implements SavableConnectionInterface
{
    use AccessTokenAuthorizationTrait;

    /**
     * @var string
     */
    public $version;

    /**
     * @var string|int
     */
    public $provider;

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
                        'version',
                        'provider'
                    ],
                    'required'
                ],
                [
                    [
                        'version',
                        'provider'
                    ],
                    'safe',
                    'on' => [
                        static::SCENARIO_DEFAULT
                    ]
                ]
            ]
        );
    }

    /**
     * @inheritdoc
     * @throws \Throwable
     */
    public function afterSave(bool $isNew, array $changedAttributes)
    {
        // Delete existing lock
        if (null !== ($provider = ArrayHelper::getValue($changedAttributes, 'provider'))) {
            $condition = [
                (is_numeric($provider) ? 'id' : 'handle') => $provider,
                'environment' => null,
                'enabled' => null
            ];

            if (null !== ($provider = Provider::findOne($condition))) {
                $provider->removeLock(Force::getInstance());
            }
        }

        $this->getRecord(false)->addLock(Force::getInstance());

        parent::afterSave($isNew, $changedAttributes);
    }

    /**
     * @inheritdoc
     * @throws \yii\base\InvalidConfigException
     */
    public function getResourceUrl(): string
    {
        $version = $this->version;

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
                'connection' => $this,
                'providers' => $providers->all()
            ]
        );
    }

    /**
     * @param bool $restricted
     * @return Provider
     */
    protected function getRecord(bool $restricted = true): Provider
    {
        // Get provider from settings
        if (null !== ($provider = $this->provider ?? null)) {
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
