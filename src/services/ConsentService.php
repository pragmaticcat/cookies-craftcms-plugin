<?php

namespace pragmatic\cookies\services;

use Craft;
use craft\web\View;
use pragmatic\cookies\assets\ConsentAsset;
use pragmatic\cookies\PragmaticCookies;
use pragmatic\cookies\records\ConsentLogRecord;
use yii\base\Component;

class ConsentService extends Component
{
    private const COOKIE_NAME = 'pragmatic_cookies_consent';

    public function injectPopup(string $output): string
    {
        // Don't inject if consent already given
        if ($this->hasExistingConsent() && !$this->_hasOpenPreferencesTrigger($output)) {
            // Still inject JS/CSS for open-preferences triggers
            $assetHtml = $this->_getAssetTags();
            $output = str_replace('</body>', $assetHtml . '</body>', $output);
            return $output;
        }

        $popupHtml = $this->renderPopup();
        $assetHtml = $this->_getAssetTags();

        // Inject before closing body tag
        $output = str_replace('</body>', $popupHtml . $assetHtml . '</body>', $output);

        return $output;
    }

    public function renderPopup(): string
    {
        $settings = $this->getResolvedSettingsForCurrentSite();
        $categories = PragmaticCookies::$plugin->categories->getAllCategories();
        $consent = $this->getCurrentConsent();

        $view = Craft::$app->getView();
        $oldMode = $view->getTemplateMode();
        $view->setTemplateMode(View::TEMPLATE_MODE_CP);

        $html = $view->renderTemplate('pragmatic-cookies/frontend/_popup', [
            'settings' => $settings,
            'categories' => $categories,
            'consent' => $consent,
        ]);

        $view->setTemplateMode($oldMode);

        return $html;
    }

    public function renderCookieTable(): string
    {
        $grouped = PragmaticCookies::$plugin->cookies->getCookiesGroupedByCategory();

        $view = Craft::$app->getView();
        $oldMode = $view->getTemplateMode();
        $view->setTemplateMode(View::TEMPLATE_MODE_CP);

        $html = $view->renderTemplate('pragmatic-cookies/frontend/_cookie-table', [
            'grouped' => $grouped,
        ]);

        $view->setTemplateMode($oldMode);

        return $html;
    }

    public function hasConsent(string $categoryHandle): bool
    {
        $consent = $this->getCurrentConsent();

        return !empty($consent[$categoryHandle]);
    }

    public function getCurrentConsent(): array
    {
        $cookieValue = $_COOKIE[self::COOKIE_NAME] ?? null;

        if (!$cookieValue) {
            return [];
        }

        $decoded = json_decode(urldecode($cookieValue), true);

        return is_array($decoded) ? $decoded : [];
    }

    public function hasExistingConsent(): bool
    {
        return !empty($_COOKIE[self::COOKIE_NAME]);
    }

    public function logConsent(string $visitorId, array $consent, ?string $ipAddress, ?string $userAgent): void
    {
        $record = new ConsentLogRecord();
        $record->visitorId = $visitorId;
        $record->consent = json_encode($consent);
        $record->ipAddress = $ipAddress;
        $record->userAgent = $userAgent;
        $record->save();
    }

    private function _getAssetTags(): string
    {
        $settings = PragmaticCookies::$plugin->getSettings();
        $categories = PragmaticCookies::$plugin->categories->getAllCategories();
        $consentExpiry = (int)$settings->consentExpiry;
        $logConsent = $settings->logConsent === 'true';

        $saveUrl = '/pragmatic-cookies/consent/save';

        $configJson = json_encode([
            'cookieName' => self::COOKIE_NAME,
            'consentExpiry' => $consentExpiry,
            'logConsent' => $logConsent,
            'saveUrl' => $saveUrl,
            'categories' => array_map(fn($c) => [
                'handle' => $c->handle,
                'isRequired' => $c->isRequired,
            ], $categories),
        ]);

        $bundle = new ConsentAsset();
        $basePath = $bundle->sourcePath;

        $cssContent = file_get_contents($basePath . '/consent.css');
        $jsContent = file_get_contents($basePath . '/consent.js');

        $html = "\n<style>{$cssContent}</style>\n";
        $html .= "<script>window.PragmaticCookiesConfig = {$configJson};</script>\n";
        $html .= "<script>{$jsContent}</script>\n";

        return $html;
    }

    private function _hasOpenPreferencesTrigger(string $output): bool
    {
        return str_contains($output, 'data-pragmatic-open-preferences');
    }

    private function getResolvedSettingsForCurrentSite(): object
    {
        $settings = clone PragmaticCookies::$plugin->getSettings();
        $siteId = (int)Craft::$app->getSites()->getCurrentSite()->id;
        $siteSettings = PragmaticCookies::$plugin->siteSettings->getSiteSettings($siteId);

        $settings->popupTitle = $siteSettings->popupTitle;
        $settings->popupDescription = $siteSettings->popupDescription;
        $settings->acceptAllLabel = $siteSettings->acceptAllLabel;
        $settings->rejectAllLabel = $siteSettings->rejectAllLabel;
        $settings->savePreferencesLabel = $siteSettings->savePreferencesLabel;
        $settings->cookiePolicyUrl = $siteSettings->cookiePolicyUrl;

        return $settings;
    }
}
