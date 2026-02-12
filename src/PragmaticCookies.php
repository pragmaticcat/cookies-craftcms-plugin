<?php

namespace pragmatic\cookies;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\events\RegisterUrlRulesEvent;
use craft\events\TemplateEvent;
use craft\web\UrlManager;
use craft\events\RegisterCpNavItemsEvent;
use craft\web\twig\variables\Cp;
use craft\web\twig\variables\CraftVariable;
use craft\web\View;
use pragmatic\cookies\models\Settings;
use pragmatic\cookies\services\CategoriesService;
use pragmatic\cookies\services\ConsentService;
use pragmatic\cookies\services\CookiesService;
use pragmatic\cookies\services\ScannerService;
use pragmatic\cookies\twig\PragmaticCookiesTwigExtension;
use pragmatic\cookies\variables\PragmaticCookiesVariable;
use yii\base\Event;

/**
 * @property CategoriesService $categories
 * @property CookiesService $cookies
 * @property ScannerService $scanner
 * @property ConsentService $consent
 */
class PragmaticCookies extends Plugin
{
    public static PragmaticCookies $plugin;

    public bool $hasCpSection = true;
    public bool $hasCpSettings = false;
    public string $schemaVersion = '1.0.0';

    public function init(): void
    {
        parent::init();
        self::$plugin = $this;

        Craft::$app->i18n->translations['pragmatic-cookies'] = [
            'class' => \yii\i18n\PhpMessageSource::class,
            'basePath' => __DIR__ . '/translations',
            'forceTranslation' => true,
        ];

        $this->setComponents([
            'categories' => CategoriesService::class,
            'cookies' => CookiesService::class,
            'scanner' => ScannerService::class,
            'consent' => ConsentService::class,
        ]);

        $this->_registerCpRoutes();
        $this->_registerSiteRoutes();
        $this->_registerNavItem();
        $this->_registerTwigExtensions();
        $this->_registerVariables();
        $this->_registerFrontendInjection();
    }

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    public function getCpNavItem(): ?array
    {
        return null;
    }

    private function _registerCpRoutes(): void
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['pragmatic-cookies'] = 'pragmatic-cookies/default/index';
                $event->rules['pragmatic-cookies/general'] = 'pragmatic-cookies/default/general';
                $event->rules['pragmatic-cookies/options'] = 'pragmatic-cookies/default/options';
                $event->rules['pragmatic-cookies/categories'] = 'pragmatic-cookies/default/categories';
                $event->rules['pragmatic-cookies/categories/new'] = 'pragmatic-cookies/default/edit-category';
                $event->rules['pragmatic-cookies/categories/<categoryId:\d+>'] = 'pragmatic-cookies/default/edit-category';
                $event->rules['pragmatic-cookies/cookies'] = 'pragmatic-cookies/default/cookies';
                $event->rules['pragmatic-cookies/scanner'] = 'pragmatic-cookies/scan/index';
                $event->rules['pragmatic-cookies/scanner/results/<scanId:\d+>'] = 'pragmatic-cookies/scan/results';
            }
        );
    }

    private function _registerSiteRoutes(): void
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['pragmatic-cookies/consent/save'] = 'pragmatic-cookies/consent/save';
            }
        );
    }

    private function _registerNavItem(): void
    {
        Event::on(
            Cp::class,
            Cp::EVENT_REGISTER_CP_NAV_ITEMS,
            function(RegisterCpNavItemsEvent $event) {
                $toolsLabel = Craft::t('pragmatic-cookies', 'Tools');
                $groupKey = null;
                foreach ($event->navItems as $key => $item) {
                    if (($item['label'] ?? '') === $toolsLabel && isset($item['subnav'])) {
                        $groupKey = $key;
                        break;
                    }
                }

                if ($groupKey === null) {
                    $newItem = [
                        'label' => $toolsLabel,
                        'url' => 'pragmatic-cookies',
                        'icon' => __DIR__ . '/icons/icon.svg',
                        'subnav' => [],
                    ];

                    $afterKey = null;
                    $insertAfter = ['users', 'assets', 'categories', 'entries'];
                    foreach ($insertAfter as $target) {
                        foreach ($event->navItems as $key => $item) {
                            if (($item['url'] ?? '') === $target) {
                                $afterKey = $key;
                                break 2;
                            }
                        }
                    }

                    if ($afterKey !== null) {
                        $pos = array_search($afterKey, array_keys($event->navItems)) + 1;
                        $event->navItems = array_merge(
                            array_slice($event->navItems, 0, $pos, true),
                            ['pragmatic' => $newItem],
                            array_slice($event->navItems, $pos, null, true),
                        );
                        $groupKey = 'pragmatic';
                    } else {
                        $event->navItems['pragmatic'] = $newItem;
                        $groupKey = 'pragmatic';
                    }
                }

                $event->navItems[$groupKey]['subnav']['cookies'] = [
                    'label' => 'Cookies',
                    'url' => 'pragmatic-cookies/general',
                ];
            }
        );
    }

    private function _registerTwigExtensions(): void
    {
        Craft::$app->view->registerTwigExtension(new PragmaticCookiesTwigExtension());
    }

    private function _registerVariables(): void
    {
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function (Event $event) {
                $event->sender->set('pragmaticCookies', PragmaticCookiesVariable::class);
            }
        );
    }

    private function _registerFrontendInjection(): void
    {
        if (Craft::$app->getRequest()->getIsSiteRequest() && !Craft::$app->getRequest()->getIsConsoleRequest()) {
            Event::on(
                View::class,
                View::EVENT_AFTER_RENDER_PAGE_TEMPLATE,
                function (TemplateEvent $event) {
                    $settings = $this->getSettings();
                    if ($settings->autoShowPopup === 'true') {
                        $event->output = $this->consent->injectPopup($event->output);
                    }
                }
            );
        }
    }
}
