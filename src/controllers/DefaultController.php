<?php

namespace pragmatic\cookies\controllers;

use Craft;
use craft\helpers\Cp;
use craft\web\Controller;
use pragmatic\cookies\models\CookieCategoryModel;
use pragmatic\cookies\models\CookieModel;
use pragmatic\cookies\PragmaticCookies;
use yii\web\Response;

class DefaultController extends Controller
{
    protected int|bool|array $allowAnonymous = false;

    // ── Navigation ──

    public function actionIndex(): Response
    {
        return $this->redirect('pragmatic-cookies/general');
    }

    // ── General Settings ──

    public function actionGeneral(): Response
    {
        $selectedSite = Cp::requestedSite() ?? Craft::$app->getSites()->getPrimarySite();
        $selectedSiteId = (int)$selectedSite->id;
        $settings = PragmaticCookies::$plugin->siteSettings->getSiteSettings($selectedSiteId);

        return $this->renderTemplate('pragmatic-cookies/general', [
            'settings' => $settings,
            'selectedSite' => $selectedSite,
            'selectedSiteId' => $selectedSiteId,
        ]);
    }

    public function actionSaveGeneral(): ?Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $siteId = (int)$request->getBodyParam('site', 0);
        if (!$siteId) {
            $siteId = (int)(Cp::requestedSite()?->id ?? Craft::$app->getSites()->getPrimarySite()->id);
        }

        if (!PragmaticCookies::$plugin->siteSettings->saveSiteSettings($siteId, [
            'popupTitle' => $request->getBodyParam('popupTitle'),
            'popupDescription' => $request->getBodyParam('popupDescription'),
            'acceptAllLabel' => $request->getBodyParam('acceptAllLabel'),
            'rejectAllLabel' => $request->getBodyParam('rejectAllLabel'),
            'savePreferencesLabel' => $request->getBodyParam('savePreferencesLabel'),
            'cookiePolicyUrl' => $request->getBodyParam('cookiePolicyUrl'),
        ])) {
            Craft::$app->getSession()->setError('Could not save settings.');
            return null;
        }

        Craft::$app->getSession()->setNotice('Settings saved.');

        return $this->redirectToPostedUrl();
    }

    // ── Options / Appearance ──

    public function actionOptions(): Response
    {
        $settings = PragmaticCookies::$plugin->getSettings();

        return $this->renderTemplate('pragmatic-cookies/options', [
            'settings' => $settings,
        ]);
    }

    public function actionSaveOptions(): ?Response
    {
        $this->requirePostRequest();

        $plugin = PragmaticCookies::$plugin;
        $settings = $plugin->getSettings();

        $settings->popupLayout = Craft::$app->getRequest()->getBodyParam('popupLayout', $settings->popupLayout);
        $settings->popupPosition = Craft::$app->getRequest()->getBodyParam('popupPosition', $settings->popupPosition);
        $settings->primaryColor = Craft::$app->getRequest()->getBodyParam('primaryColor', $settings->primaryColor);
        $settings->backgroundColor = Craft::$app->getRequest()->getBodyParam('backgroundColor', $settings->backgroundColor);
        $settings->textColor = Craft::$app->getRequest()->getBodyParam('textColor', $settings->textColor);
        $settings->overlayEnabled = Craft::$app->getRequest()->getBodyParam('overlayEnabled', $settings->overlayEnabled);
        $settings->autoShowPopup = Craft::$app->getRequest()->getBodyParam('autoShowPopup', $settings->autoShowPopup);
        $settings->consentExpiry = Craft::$app->getRequest()->getBodyParam('consentExpiry', $settings->consentExpiry);
        $settings->logConsent = Craft::$app->getRequest()->getBodyParam('logConsent', $settings->logConsent);

        if (!Craft::$app->getPlugins()->savePluginSettings($plugin, $settings->toArray())) {
            Craft::$app->getSession()->setError('Could not save settings.');
            return null;
        }

        Craft::$app->getSession()->setNotice('Settings saved.');

        return $this->redirectToPostedUrl();
    }

    // ── Categories ──

    public function actionCategories(): Response
    {
        $categories = PragmaticCookies::$plugin->categories->getAllCategories();

        return $this->renderTemplate('pragmatic-cookies/categories/index', [
            'categories' => $categories,
        ]);
    }

    public function actionEditCategory(?int $categoryId = null): Response
    {
        if ($categoryId) {
            $category = PragmaticCookies::$plugin->categories->getCategoryById($categoryId);
            if (!$category) {
                throw new \yii\web\NotFoundHttpException('Category not found');
            }
            $title = 'Edit Category';
        } else {
            $category = new CookieCategoryModel();
            $title = 'New Category';
        }

        return $this->renderTemplate('pragmatic-cookies/categories/_edit', [
            'category' => $category,
            'title' => $title,
        ]);
    }

    public function actionSaveCategory(): ?Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $id = $request->getBodyParam('categoryId');

        if ($id) {
            $model = PragmaticCookies::$plugin->categories->getCategoryById($id);
            if (!$model) {
                throw new \yii\web\NotFoundHttpException('Category not found');
            }
        } else {
            $model = new CookieCategoryModel();
        }

        $model->name = $request->getBodyParam('name', '');
        $model->handle = $request->getBodyParam('handle', '');
        $model->description = $request->getBodyParam('description', '');
        $model->isRequired = (bool)$request->getBodyParam('isRequired', false);

        if (!PragmaticCookies::$plugin->categories->saveCategory($model)) {
            Craft::$app->getSession()->setError('Could not save category.');

            Craft::$app->getUrlManager()->setRouteParams([
                'category' => $model,
            ]);

            return null;
        }

        Craft::$app->getSession()->setNotice('Category saved.');

        return $this->redirectToPostedUrl($model);
    }

    public function actionDeleteCategory(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = Craft::$app->getRequest()->getRequiredBodyParam('id');

        PragmaticCookies::$plugin->categories->deleteCategory($id);

        return $this->asJson(['success' => true]);
    }

    public function actionReorderCategories(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $ids = Craft::$app->getRequest()->getRequiredBodyParam('ids');

        PragmaticCookies::$plugin->categories->reorderCategories($ids);

        return $this->asJson(['success' => true]);
    }

    // ── Cookies ──

    public function actionCookies(): Response
    {
        $cookies = PragmaticCookies::$plugin->cookies->getAllCookies();
        $categories = PragmaticCookies::$plugin->categories->getAllCategories();

        return $this->renderTemplate('pragmatic-cookies/cookies', [
            'cookies' => $cookies,
            'categories' => $categories,
        ]);
    }

    public function actionSaveCookie(): ?Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $id = $request->getBodyParam('cookieId');

        if ($id) {
            $model = PragmaticCookies::$plugin->cookies->getCookieById($id);
            if (!$model) {
                throw new \yii\web\NotFoundHttpException('Cookie not found');
            }
        } else {
            $model = new CookieModel();
        }

        $model->name = $request->getBodyParam('name', '');
        $model->categoryId = $request->getBodyParam('categoryId') ?: null;
        $model->provider = $request->getBodyParam('provider', '');
        $model->description = $request->getBodyParam('description', '');
        $model->duration = $request->getBodyParam('duration', '');
        $model->isRegex = (bool)$request->getBodyParam('isRegex', false);

        if (!PragmaticCookies::$plugin->cookies->saveCookie($model)) {
            Craft::$app->getSession()->setError('Could not save cookie.');

            Craft::$app->getUrlManager()->setRouteParams([
                'cookie' => $model,
            ]);

            return null;
        }

        Craft::$app->getSession()->setNotice('Cookie saved.');

        return $this->redirectToPostedUrl($model);
    }

    public function actionDeleteCookie(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = Craft::$app->getRequest()->getRequiredBodyParam('id');

        PragmaticCookies::$plugin->cookies->deleteCookie($id);

        return $this->asJson(['success' => true]);
    }
}
