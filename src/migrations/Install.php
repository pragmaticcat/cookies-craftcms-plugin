<?php

namespace pragmatic\cookies\migrations;

use craft\db\Migration;

class Install extends Migration
{
    public function safeUp(): bool
    {
        $this->createTables();
        $this->createIndexes();
        $this->addForeignKeys();
        $this->seedCategories();

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%pragmatic_cookies_scan_results}}');
        $this->dropTableIfExists('{{%pragmatic_cookies_scans}}');
        $this->dropTableIfExists('{{%pragmatic_cookies_consent_logs}}');
        $this->dropTableIfExists('{{%pragmatic_cookies_site_settings}}');
        $this->dropTableIfExists('{{%pragmatic_cookies_cookies}}');
        $this->dropTableIfExists('{{%pragmatic_cookies_categories}}');

        return true;
    }

    private function createTables(): void
    {
        $this->createTable('{{%pragmatic_cookies_categories}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'handle' => $this->string()->notNull(),
            'description' => $this->text(),
            'isRequired' => $this->boolean()->notNull()->defaultValue(false),
            'sortOrder' => $this->integer()->notNull()->defaultValue(0),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%pragmatic_cookies_cookies}}', [
            'id' => $this->primaryKey(),
            'categoryId' => $this->integer(),
            'name' => $this->string()->notNull(),
            'provider' => $this->string(),
            'description' => $this->text(),
            'duration' => $this->string(),
            'isRegex' => $this->boolean()->notNull()->defaultValue(false),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%pragmatic_cookies_scans}}', [
            'id' => $this->primaryKey(),
            'status' => $this->string()->notNull()->defaultValue('pending'),
            'totalPages' => $this->integer()->notNull()->defaultValue(0),
            'pagesScanned' => $this->integer()->notNull()->defaultValue(0),
            'cookiesFound' => $this->integer()->notNull()->defaultValue(0),
            'errorMessage' => $this->text(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%pragmatic_cookies_scan_results}}', [
            'id' => $this->primaryKey(),
            'scanId' => $this->integer()->notNull(),
            'cookieName' => $this->string()->notNull(),
            'cookieDomain' => $this->string(),
            'cookiePath' => $this->string(),
            'pageUrl' => $this->string(),
            'cookieId' => $this->integer(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%pragmatic_cookies_consent_logs}}', [
            'id' => $this->primaryKey(),
            'visitorId' => $this->string()->notNull(),
            'consent' => $this->text(),
            'ipAddress' => $this->string(),
            'userAgent' => $this->text(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%pragmatic_cookies_site_settings}}', [
            'id' => $this->primaryKey(),
            'siteId' => $this->integer()->notNull(),
            'popupTitle' => $this->string()->notNull(),
            'popupDescription' => $this->text(),
            'acceptAllLabel' => $this->string()->notNull(),
            'rejectAllLabel' => $this->string()->notNull(),
            'savePreferencesLabel' => $this->string()->notNull(),
            'cookiePolicyUrl' => $this->string(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);
    }

    private function createIndexes(): void
    {
        $this->createIndex(null, '{{%pragmatic_cookies_categories}}', 'handle', true);
        $this->createIndex(null, '{{%pragmatic_cookies_cookies}}', 'categoryId');
        $this->createIndex(null, '{{%pragmatic_cookies_scan_results}}', 'scanId');
        $this->createIndex(null, '{{%pragmatic_cookies_scan_results}}', 'cookieId');
        $this->createIndex(null, '{{%pragmatic_cookies_consent_logs}}', 'visitorId');
        $this->createIndex(null, '{{%pragmatic_cookies_site_settings}}', 'siteId', true);
    }

    private function addForeignKeys(): void
    {
        $this->addForeignKey(
            null,
            '{{%pragmatic_cookies_cookies}}',
            'categoryId',
            '{{%pragmatic_cookies_categories}}',
            'id',
            'SET NULL',
        );

        $this->addForeignKey(
            null,
            '{{%pragmatic_cookies_scan_results}}',
            'scanId',
            '{{%pragmatic_cookies_scans}}',
            'id',
            'CASCADE',
        );

        $this->addForeignKey(
            null,
            '{{%pragmatic_cookies_scan_results}}',
            'cookieId',
            '{{%pragmatic_cookies_cookies}}',
            'id',
            'SET NULL',
        );
    }

    private function seedCategories(): void
    {
        $categories = [
            ['name' => 'Necessary', 'handle' => 'necessary', 'description' => 'Essential cookies required for the website to function properly.', 'isRequired' => true, 'sortOrder' => 1],
            ['name' => 'Analytics', 'handle' => 'analytics', 'description' => 'Cookies used to analyze website traffic and usage patterns.', 'isRequired' => false, 'sortOrder' => 2],
            ['name' => 'Marketing', 'handle' => 'marketing', 'description' => 'Cookies used for advertising and tracking across websites.', 'isRequired' => false, 'sortOrder' => 3],
            ['name' => 'Preferences', 'handle' => 'preferences', 'description' => 'Cookies that remember user preferences and settings.', 'isRequired' => false, 'sortOrder' => 4],
        ];

        foreach ($categories as $category) {
            $this->insert('{{%pragmatic_cookies_categories}}', $category);
        }
    }
}
