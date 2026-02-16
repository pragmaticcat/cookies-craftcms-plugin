<?php

namespace pragmatic\cookies\services;

use Craft;
use craft\base\Component;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use pragmatic\cookies\models\SiteSettingsModel;
use pragmatic\cookies\PragmaticCookies;
use yii\db\Query;
use yii\db\Schema;

class SiteSettingsService extends Component
{
    private const TABLE = '{{%pragmatic_cookies_site_settings}}';
    private static bool $tableReady = false;

    public function getSiteSettings(int $siteId): SiteSettingsModel
    {
        $this->ensureTable();
        $defaults = $this->defaultModel();

        $row = (new Query())
            ->from(self::TABLE)
            ->where(['siteId' => $siteId])
            ->one();

        if (!$row) {
            return $defaults;
        }

        $model = new SiteSettingsModel();
        $model->popupTitle = trim((string)($row['popupTitle'] ?? $defaults->popupTitle));
        $model->popupDescription = trim((string)($row['popupDescription'] ?? $defaults->popupDescription));
        $model->acceptAllLabel = trim((string)($row['acceptAllLabel'] ?? $defaults->acceptAllLabel));
        $model->rejectAllLabel = trim((string)($row['rejectAllLabel'] ?? $defaults->rejectAllLabel));
        $model->savePreferencesLabel = trim((string)($row['savePreferencesLabel'] ?? $defaults->savePreferencesLabel));
        $model->cookiePolicyUrl = trim((string)($row['cookiePolicyUrl'] ?? $defaults->cookiePolicyUrl));

        return $model;
    }

    public function saveSiteSettings(int $siteId, array $input): bool
    {
        $this->ensureTable();

        $current = $this->getSiteSettings($siteId);
        $model = new SiteSettingsModel();
        $model->popupTitle = trim((string)($input['popupTitle'] ?? $current->popupTitle));
        $model->popupDescription = trim((string)($input['popupDescription'] ?? $current->popupDescription));
        $model->acceptAllLabel = trim((string)($input['acceptAllLabel'] ?? $current->acceptAllLabel));
        $model->rejectAllLabel = trim((string)($input['rejectAllLabel'] ?? $current->rejectAllLabel));
        $model->savePreferencesLabel = trim((string)($input['savePreferencesLabel'] ?? $current->savePreferencesLabel));
        $model->cookiePolicyUrl = trim((string)($input['cookiePolicyUrl'] ?? $current->cookiePolicyUrl));

        if (!$model->validate()) {
            return false;
        }

        $now = Db::prepareDateForDb(new \DateTime());
        $data = [
            'siteId' => $siteId,
            'popupTitle' => $model->popupTitle,
            'popupDescription' => $model->popupDescription,
            'acceptAllLabel' => $model->acceptAllLabel,
            'rejectAllLabel' => $model->rejectAllLabel,
            'savePreferencesLabel' => $model->savePreferencesLabel,
            'cookiePolicyUrl' => $model->cookiePolicyUrl,
        ];

        Craft::$app->getDb()->createCommand()->upsert(self::TABLE, [
            ...$data,
            'dateCreated' => $now,
            'dateUpdated' => $now,
            'uid' => StringHelper::UUID(),
        ], [
            ...$data,
            'dateUpdated' => $now,
        ])->execute();

        return true;
    }

    private function defaultModel(): SiteSettingsModel
    {
        $pluginSettings = PragmaticCookies::$plugin->getSettings();
        $model = new SiteSettingsModel();
        $model->popupTitle = $pluginSettings->popupTitle;
        $model->popupDescription = $pluginSettings->popupDescription;
        $model->acceptAllLabel = $pluginSettings->acceptAllLabel;
        $model->rejectAllLabel = $pluginSettings->rejectAllLabel;
        $model->savePreferencesLabel = $pluginSettings->savePreferencesLabel;
        $model->cookiePolicyUrl = $pluginSettings->cookiePolicyUrl;

        return $model;
    }

    private function ensureTable(): void
    {
        if (self::$tableReady) {
            return;
        }
        self::$tableReady = true;

        $db = Craft::$app->getDb();
        if ($db->tableExists(self::TABLE)) {
            return;
        }

        $db->createCommand()->createTable(self::TABLE, [
            'id' => Schema::TYPE_PK,
            'siteId' => Schema::TYPE_INTEGER . ' NOT NULL',
            'popupTitle' => Schema::TYPE_STRING . '(255) NOT NULL',
            'popupDescription' => Schema::TYPE_TEXT,
            'acceptAllLabel' => Schema::TYPE_STRING . '(255) NOT NULL',
            'rejectAllLabel' => Schema::TYPE_STRING . '(255) NOT NULL',
            'savePreferencesLabel' => Schema::TYPE_STRING . '(255) NOT NULL',
            'cookiePolicyUrl' => Schema::TYPE_STRING . '(1024)',
            'dateCreated' => Schema::TYPE_DATETIME . ' NOT NULL',
            'dateUpdated' => Schema::TYPE_DATETIME . ' NOT NULL',
            'uid' => 'char(36) NOT NULL',
        ])->execute();

        $db->createCommand()->createIndex(
            'pragmatic_cookies_site_settings_siteId_unique',
            self::TABLE,
            ['siteId'],
            true
        )->execute();
    }
}
