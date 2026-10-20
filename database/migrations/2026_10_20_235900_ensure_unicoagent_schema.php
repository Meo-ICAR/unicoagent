<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Schema completo del database `unicoagent`, rigenerato dal server MySQL.
 *
 * Idempotente: crea solo le tabelle, colonne, indici e chiavi esterne che mancano,
 * quindi si puo' rieseguire su un database gia' popolato senza errori e senza toccare i dati.
 * Le chiavi esterne che i dati storici non rispettano vengono saltate e segnalate nel log.
 */
return new class extends Migration
{
    public function getConnection()
    {
        return null;
    }

    public function up(): void
    {
        $db = DB::connection($this->getConnection());
        $schema = $db->getDatabaseName();
        $db->statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach ($this->tables() as $table => $def) {
                $exists = $db->selectOne('select 1 as x from information_schema.tables where table_schema = ? and table_name = ?', [$schema, $table]);
                if (! $exists) {
                    $db->statement($def['create']);

                    continue;
                }
                foreach ($def['columns'] as $column => $sql) {
                    $has = $db->selectOne('select 1 as x from information_schema.columns where table_schema = ? and table_name = ? and column_name = ?', [$schema, $table, $column]);
                    if (! $has) {
                        if (stripos($sql, 'AUTO_INCREMENT') !== false && ! $db->selectOne("select 1 as x from information_schema.statistics where table_schema = ? and table_name = ? and index_name = 'PRIMARY'", [$schema, $table])) {
                            $sql .= ", ADD PRIMARY KEY (`{$column}`)";
                        }
                        $db->statement("ALTER TABLE `{$table}` ADD COLUMN {$sql}");
                    }
                }
                foreach ($def['indexes'] as $index => $sql) {
                    $has = $db->selectOne('select 1 as x from information_schema.statistics where table_schema = ? and table_name = ? and index_name = ?', [$schema, $table, $index]);
                    if (! $has) {
                        $this->tryStatement($db, "ALTER TABLE `{$table}` ADD {$sql}", "{$table}.{$index}");
                    }
                }
            }

            foreach ($this->tables() as $table => $def) {
                foreach ($def['foreign'] as $name => $sql) {
                    $has = $db->selectOne("select 1 as x from information_schema.table_constraints where table_schema = ? and table_name = ? and constraint_name = ? and constraint_type = 'FOREIGN KEY'", [$schema, $table, $name]);
                    if (! $has) {
                        $this->tryStatement($db, "ALTER TABLE `{$table}` ADD {$sql}", "{$table}.{$name}");
                    }
                }
            }
        } finally {
            $db->statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    public function down(): void
    {
        // Non si elimina nulla: lo schema e i dati sono condivisi.
    }

    private function tryStatement($db, string $sql, string $label): void
    {
        try {
            $db->statement($sql);
        } catch (\Throwable $e) {
            Log::warning("Migration unicoagent: saltato {$label}: ".$e->getMessage());
        }
    }

    /** @return array<string, array{create: string, columns: array<string, string>, indexes: array<string, string>, foreign: array<string, string>}> */
    private function tables(): array
    {
        return [
        'attachments' => [
            'create' => 'CREATE TABLE `attachments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `loan_request_id` bigint unsigned NOT NULL,
  `pratica_document_id` bigint unsigned DEFAULT NULL,
  `kind` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'ricevuto\',
  `analysis` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `pending_checks` json DEFAULT NULL,
  `wa_media_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `received_at` timestamp NOT NULL,
  `crm_sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT NULL,
  `ai_model` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ai_input_tokens` int unsigned DEFAULT NULL,
  `ai_output_tokens` int unsigned DEFAULT NULL,
  `ai_cost` decimal(10,6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `attachments_loan_request_id_foreign` (`loan_request_id`),
  KEY `attachments_pratica_document_id_foreign` (`pratica_document_id`),
  KEY `attachments_company_id_foreign` (`company_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'loan_request_id' => '`loan_request_id` bigint unsigned NOT NULL AFTER `id`',
                'pratica_document_id' => '`pratica_document_id` bigint unsigned DEFAULT NULL AFTER `loan_request_id`',
                'kind' => '`kind` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `pratica_document_id`',
                'path' => '`path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `kind`',
                'mime' => '`mime` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `path`',
                'status' => '`status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'ricevuto\' AFTER `mime`',
                'analysis' => '`analysis` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci AFTER `status`',
                'pending_checks' => '`pending_checks` json DEFAULT NULL AFTER `analysis`',
                'wa_media_id' => '`wa_media_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `pending_checks`',
                'received_at' => '`received_at` timestamp NOT NULL AFTER `wa_media_id`',
                'crm_sent_at' => '`crm_sent_at` timestamp NULL DEFAULT NULL AFTER `received_at`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `crm_sent_at`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
                'company_id' => '`company_id` bigint unsigned DEFAULT NULL AFTER `updated_at`',
                'ai_model' => '`ai_model` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `company_id`',
                'ai_input_tokens' => '`ai_input_tokens` int unsigned DEFAULT NULL AFTER `ai_model`',
                'ai_output_tokens' => '`ai_output_tokens` int unsigned DEFAULT NULL AFTER `ai_input_tokens`',
                'ai_cost' => '`ai_cost` decimal(10,6) DEFAULT NULL AFTER `ai_output_tokens`',
            ],
            'indexes' => [
                'attachments_loan_request_id_foreign' => 'KEY `attachments_loan_request_id_foreign` (`loan_request_id`)',
                'attachments_pratica_document_id_foreign' => 'KEY `attachments_pratica_document_id_foreign` (`pratica_document_id`)',
                'attachments_company_id_foreign' => 'KEY `attachments_company_id_foreign` (`company_id`)',
            ],
            'foreign' => [
                'attachments_company_id_foreign' => 'CONSTRAINT `attachments_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL',
                'attachments_loan_request_id_foreign' => 'CONSTRAINT `attachments_loan_request_id_foreign` FOREIGN KEY (`loan_request_id`) REFERENCES `loan_requests` (`id`) ON DELETE CASCADE',
                'attachments_pratica_document_id_foreign' => 'CONSTRAINT `attachments_pratica_document_id_foreign` FOREIGN KEY (`pratica_document_id`) REFERENCES `pratica_documents` (`id`) ON DELETE SET NULL',
            ],
        ],
        'cache' => [
            'create' => 'CREATE TABLE `cache` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'key' => '`key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL FIRST',
                'value' => '`value` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `key`',
                'expiration' => '`expiration` bigint NOT NULL AFTER `value`',
            ],
            'indexes' => [
                'cache_expiration_index' => 'KEY `cache_expiration_index` (`expiration`)',
            ],
            'foreign' => [
            ],
        ],
        'cache_locks' => [
            'create' => 'CREATE TABLE `cache_locks` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'key' => '`key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL FIRST',
                'owner' => '`owner` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `key`',
                'expiration' => '`expiration` bigint NOT NULL AFTER `owner`',
            ],
            'indexes' => [
                'cache_locks_expiration_index' => 'KEY `cache_locks_expiration_index` (`expiration`)',
            ],
            'foreign' => [
            ],
        ],
        'clientis' => [
            'create' => 'CREATE TABLE `clientis` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'ID univoco del cliente/banca\',
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nome` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `piva` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stipulated_at` date DEFAULT NULL COMMENT \'Data stipula contratto convenzione\',
  `abi` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Abi per banche o numero RUI ISVASS\',
  `abi_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Nome ufficiale banca\',
  `dismissed_at` date DEFAULT NULL COMMENT \'Data cessazione rapporto convenzione\',
  `type` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Banca / Assicurazione / Utility\',
  `oam` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Codice di iscrizione OAM\',
  `oam_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Denominazione OAM\',
  `oam_at` date DEFAULT NULL COMMENT \'Data iscrizione OAM\',
  `numero_iscrizione_rui` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Numero iscrizione OAM\',
  `ivass` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Codice di iscrizione IVASS\',
  `ivass_at` date DEFAULT NULL COMMENT \'Data iscrizione IVASS\',
  `ivass_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Denominazione OAM\',
  `ivass_section` enum(\'A\',\'B\',\'C\',\'D\',\'E\') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Sezione IVASS\',
  `mandate_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Numero di protocollo o identificativo del contratto di mandato\',
  `start_date` date DEFAULT NULL COMMENT \'Data di decorrenza del mandato\',
  `end_date` date DEFAULT NULL COMMENT \'Data di scadenza (NULL se a tempo indeterminato)\',
  `is_exclusive` tinyint(1) NOT NULL DEFAULT \'0\' COMMENT \'Indica se il mandato prevede l\'\'esclusiva per quella categoria\',
  `status` enum(\'ATTIVO\',\'SCADUTO\',\'RECEDUTO\',\'SOSPESO\') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'ATTIVO\' COMMENT \'Stato operativo del mandato\',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT \'Note su provvigioni particolari o patti specifici\',
  `principal_type` enum(\'--\',\'banca\',\'broker\',\'captive\',\'assicurazione\') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'banca\' COMMENT \'Tipologia del mandante\',
  `submission_type` enum(\'--\',\'accesso portale\',\'inoltro\',\'entrambi\') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'accesso portale\' COMMENT \'Modalità inoltro pratiche\',
  `cf` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Codice fiscale\',
  `website` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_reported` tinyint(1) NOT NULL DEFAULT \'0\' COMMENT \'Accordi di segnalazione\',
  `privacy_contact_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Email contatto privacy\',
  `dpo_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Email DPO\',
  `coge` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Codice contabile COGE\',
  `codice` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `regione` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `citta` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT NULL COMMENT \'ID dell\'\'azienda di riferimento\',
  `customertype_id` bigint unsigned DEFAULT NULL COMMENT \'Riferimento al tipo di cliente (chiave esterna)\',
  `is_active` tinyint unsigned DEFAULT \'1\',
  `is_dummy` tinyint(1) NOT NULL DEFAULT \'0\',
  `deleted_at` timestamp NULL DEFAULT NULL COMMENT \'Timestamp di cancellazione (soft delete)\',
  `created_at` timestamp NULL DEFAULT NULL COMMENT \'Timestamp di creazione del record\',
  `updated_at` timestamp NULL DEFAULT NULL COMMENT \'Timestamp di ultimo aggiornamento\',
  PRIMARY KEY (`id`),
  KEY `clientis_customertype_id_foreign` (`customertype_id`),
  KEY `clientis_company_id_index` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'Anagrafica Banche ed Istituti Finanziari (Clienti del mediatore)\'',
            'columns' => [
                'id' => '`id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'ID univoco del cliente/banca\' FIRST',
                'name' => '`name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `id`',
                'nome' => '`nome` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `name`',
                'piva' => '`piva` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `nome`',
                'stipulated_at' => '`stipulated_at` date DEFAULT NULL COMMENT \'Data stipula contratto convenzione\' AFTER `piva`',
                'abi' => '`abi` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Abi per banche o numero RUI ISVASS\' AFTER `stipulated_at`',
                'abi_name' => '`abi_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Nome ufficiale banca\' AFTER `abi`',
                'dismissed_at' => '`dismissed_at` date DEFAULT NULL COMMENT \'Data cessazione rapporto convenzione\' AFTER `abi_name`',
                'type' => '`type` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Banca / Assicurazione / Utility\' AFTER `dismissed_at`',
                'oam' => '`oam` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Codice di iscrizione OAM\' AFTER `type`',
                'oam_name' => '`oam_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Denominazione OAM\' AFTER `oam`',
                'oam_at' => '`oam_at` date DEFAULT NULL COMMENT \'Data iscrizione OAM\' AFTER `oam_name`',
                'numero_iscrizione_rui' => '`numero_iscrizione_rui` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Numero iscrizione OAM\' AFTER `oam_at`',
                'ivass' => '`ivass` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Codice di iscrizione IVASS\' AFTER `numero_iscrizione_rui`',
                'ivass_at' => '`ivass_at` date DEFAULT NULL COMMENT \'Data iscrizione IVASS\' AFTER `ivass`',
                'ivass_name' => '`ivass_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Denominazione OAM\' AFTER `ivass_at`',
                'ivass_section' => '`ivass_section` enum(\'A\',\'B\',\'C\',\'D\',\'E\') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Sezione IVASS\' AFTER `ivass_name`',
                'mandate_number' => '`mandate_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Numero di protocollo o identificativo del contratto di mandato\' AFTER `ivass_section`',
                'start_date' => '`start_date` date DEFAULT NULL COMMENT \'Data di decorrenza del mandato\' AFTER `mandate_number`',
                'end_date' => '`end_date` date DEFAULT NULL COMMENT \'Data di scadenza (NULL se a tempo indeterminato)\' AFTER `start_date`',
                'is_exclusive' => '`is_exclusive` tinyint(1) NOT NULL DEFAULT \'0\' COMMENT \'Indica se il mandato prevede l\'\'esclusiva per quella categoria\' AFTER `end_date`',
                'status' => '`status` enum(\'ATTIVO\',\'SCADUTO\',\'RECEDUTO\',\'SOSPESO\') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'ATTIVO\' COMMENT \'Stato operativo del mandato\' AFTER `is_exclusive`',
                'notes' => '`notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT \'Note su provvigioni particolari o patti specifici\' AFTER `status`',
                'principal_type' => '`principal_type` enum(\'--\',\'banca\',\'broker\',\'captive\',\'assicurazione\') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'banca\' COMMENT \'Tipologia del mandante\' AFTER `notes`',
                'submission_type' => '`submission_type` enum(\'--\',\'accesso portale\',\'inoltro\',\'entrambi\') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'accesso portale\' COMMENT \'Modalità inoltro pratiche\' AFTER `principal_type`',
                'cf' => '`cf` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Codice fiscale\' AFTER `submission_type`',
                'website' => '`website` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `cf`',
                'is_reported' => '`is_reported` tinyint(1) NOT NULL DEFAULT \'0\' COMMENT \'Accordi di segnalazione\' AFTER `website`',
                'privacy_contact_email' => '`privacy_contact_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Email contatto privacy\' AFTER `is_reported`',
                'dpo_email' => '`dpo_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Email DPO\' AFTER `privacy_contact_email`',
                'coge' => '`coge` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Codice contabile COGE\' AFTER `dpo_email`',
                'codice' => '`codice` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `coge`',
                'email' => '`email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `codice`',
                'regione' => '`regione` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `email`',
                'citta' => '`citta` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `regione`',
                'company_id' => '`company_id` bigint unsigned DEFAULT NULL COMMENT \'ID dell\'\'azienda di riferimento\' AFTER `citta`',
                'customertype_id' => '`customertype_id` bigint unsigned DEFAULT NULL COMMENT \'Riferimento al tipo di cliente (chiave esterna)\' AFTER `company_id`',
                'is_active' => '`is_active` tinyint unsigned DEFAULT \'1\' AFTER `customertype_id`',
                'is_dummy' => '`is_dummy` tinyint(1) NOT NULL DEFAULT \'0\' AFTER `is_active`',
                'deleted_at' => '`deleted_at` timestamp NULL DEFAULT NULL COMMENT \'Timestamp di cancellazione (soft delete)\' AFTER `is_dummy`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL COMMENT \'Timestamp di creazione del record\' AFTER `deleted_at`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL COMMENT \'Timestamp di ultimo aggiornamento\' AFTER `created_at`',
            ],
            'indexes' => [
                'clientis_customertype_id_foreign' => 'KEY `clientis_customertype_id_foreign` (`customertype_id`)',
                'clientis_company_id_index' => 'KEY `clientis_company_id_index` (`company_id`)',
            ],
            'foreign' => [
            ],
        ],
        'companies' => [
            'create' => 'CREATE TABLE `companies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'FINANCE\',
  `address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_care_phone` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_care_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `istruttoria_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url_preventivatore` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `preventivatore_passkey` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `url_istruttoria` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `istruttoria_passkey` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `crm_driver` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `crm_config` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `dpo_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `retention_perfected` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_trial` tinyint(1) NOT NULL DEFAULT \'0\',
  `trialend_at` date DEFAULT NULL,
  `trial_activated_at` date DEFAULT NULL,
  `activated_at` date DEFAULT NULL COMMENT \'Attivazione del contratto\',
  `whatsapp_number` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `menu` json DEFAULT NULL,
  `produce_documents` tinyint(1) NOT NULL DEFAULT \'0\' COMMENT \'Produce e fa firmare i moduli con unico-core al perfezionamento\',
  `core_company_id` bigint unsigned DEFAULT NULL COMMENT \'Id della company nel DB core\',
  `core_tipo_prodotto` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Tipo di pratica del core per scegliere i task (es. Utenza)\',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'name' => '`name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `id`',
                'type' => '`type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'FINANCE\' AFTER `name`',
                'address' => '`address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `type`',
                'email' => '`email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `address`',
                'customer_care_phone' => '`customer_care_phone` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `email`',
                'customer_care_email' => '`customer_care_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `customer_care_phone`',
                'istruttoria_email' => '`istruttoria_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `customer_care_email`',
                'url_preventivatore' => '`url_preventivatore` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `istruttoria_email`',
                'preventivatore_passkey' => '`preventivatore_passkey` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci AFTER `url_preventivatore`',
                'url_istruttoria' => '`url_istruttoria` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `preventivatore_passkey`',
                'istruttoria_passkey' => '`istruttoria_passkey` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci AFTER `url_istruttoria`',
                'crm_driver' => '`crm_driver` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `istruttoria_passkey`',
                'crm_config' => '`crm_config` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci AFTER `crm_driver`',
                'dpo_email' => '`dpo_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `crm_config`',
                'retention_perfected' => '`retention_perfected` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci AFTER `dpo_email`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `retention_perfected`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
                'is_trial' => '`is_trial` tinyint(1) NOT NULL DEFAULT \'0\' AFTER `updated_at`',
                'trialend_at' => '`trialend_at` date DEFAULT NULL AFTER `is_trial`',
                'trial_activated_at' => '`trial_activated_at` date DEFAULT NULL AFTER `trialend_at`',
                'activated_at' => '`activated_at` date DEFAULT NULL COMMENT \'Attivazione del contratto\' AFTER `trial_activated_at`',
                'whatsapp_number' => '`whatsapp_number` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `activated_at`',
                'logo' => '`logo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `whatsapp_number`',
                'menu' => '`menu` json DEFAULT NULL AFTER `logo`',
                'produce_documents' => '`produce_documents` tinyint(1) NOT NULL DEFAULT \'0\' COMMENT \'Produce e fa firmare i moduli con unico-core al perfezionamento\' AFTER `menu`',
                'core_company_id' => '`core_company_id` bigint unsigned DEFAULT NULL COMMENT \'Id della company nel DB core\' AFTER `produce_documents`',
                'core_tipo_prodotto' => '`core_tipo_prodotto` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Tipo di pratica del core per scegliere i task (es. Utenza)\' AFTER `core_company_id`',
            ],
            'indexes' => [
            ],
            'foreign' => [
            ],
        ],
        'company_products' => [
            'create' => 'CREATE TABLE `company_products` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_products_company_id_product_id_unique` (`company_id`,`product_id`),
  KEY `company_products_product_id_foreign` (`product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'company_id' => '`company_id` bigint unsigned NOT NULL AFTER `id`',
                'product_id' => '`product_id` bigint unsigned NOT NULL AFTER `company_id`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `product_id`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
                'company_products_company_id_product_id_unique' => 'UNIQUE KEY `company_products_company_id_product_id_unique` (`company_id`,`product_id`)',
                'company_products_product_id_foreign' => 'KEY `company_products_product_id_foreign` (`product_id`)',
            ],
            'foreign' => [
                'company_products_company_id_foreign' => 'CONSTRAINT `company_products_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE',
                'company_products_product_id_foreign' => 'CONSTRAINT `company_products_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE',
            ],
        ],
        'conversations' => [
            'create' => 'CREATE TABLE `conversations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `wa_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `flow` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `node` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `loan_request_id` bigint unsigned DEFAULT NULL,
  `data` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `history` json DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'attiva\',
  `is_test` tinyint(1) NOT NULL DEFAULT \'0\',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `conversations_loan_request_id_foreign` (`loan_request_id`),
  KEY `conversations_wa_number_index` (`wa_number`),
  KEY `conversations_status_index` (`status`),
  KEY `conversations_company_id_foreign` (`company_id`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'wa_number' => '`wa_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `id`',
                'flow' => '`flow` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `wa_number`',
                'node' => '`node` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `flow`',
                'loan_request_id' => '`loan_request_id` bigint unsigned DEFAULT NULL AFTER `node`',
                'data' => '`data` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci AFTER `loan_request_id`',
                'history' => '`history` json DEFAULT NULL AFTER `data`',
                'status' => '`status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'attiva\' AFTER `history`',
                'is_test' => '`is_test` tinyint(1) NOT NULL DEFAULT \'0\' AFTER `status`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `is_test`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
                'company_id' => '`company_id` bigint unsigned DEFAULT NULL AFTER `updated_at`',
            ],
            'indexes' => [
                'conversations_loan_request_id_foreign' => 'KEY `conversations_loan_request_id_foreign` (`loan_request_id`)',
                'conversations_wa_number_index' => 'KEY `conversations_wa_number_index` (`wa_number`)',
                'conversations_status_index' => 'KEY `conversations_status_index` (`status`)',
                'conversations_company_id_foreign' => 'KEY `conversations_company_id_foreign` (`company_id`)',
            ],
            'foreign' => [
                'conversations_company_id_foreign' => 'CONSTRAINT `conversations_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL',
                'conversations_loan_request_id_foreign' => 'CONSTRAINT `conversations_loan_request_id_foreign` FOREIGN KEY (`loan_request_id`) REFERENCES `loan_requests` (`id`) ON DELETE SET NULL',
            ],
        ],
        'energy_suppliers' => [
            'create' => 'CREATE TABLE `energy_suppliers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Nome normalizzato per il confronto con quanto scrive l\'\'agente\',
  `vat` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Partita IVA (o identificativo fiscale estero)\',
  `arera_id` int unsigned DEFAULT NULL,
  `sells_electricity` tinyint(1) NOT NULL DEFAULT \'0\',
  `sells_gas` tinyint(1) NOT NULL DEFAULT \'0\',
  `website` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `energy_suppliers_vat_unique` (`vat`),
  KEY `energy_suppliers_name_key_index` (`name_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'name' => '`name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `id`',
                'name_key' => '`name_key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Nome normalizzato per il confronto con quanto scrive l\'\'agente\' AFTER `name`',
                'vat' => '`vat` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Partita IVA (o identificativo fiscale estero)\' AFTER `name_key`',
                'arera_id' => '`arera_id` int unsigned DEFAULT NULL AFTER `vat`',
                'sells_electricity' => '`sells_electricity` tinyint(1) NOT NULL DEFAULT \'0\' AFTER `arera_id`',
                'sells_gas' => '`sells_gas` tinyint(1) NOT NULL DEFAULT \'0\' AFTER `sells_electricity`',
                'website' => '`website` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `sells_gas`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `website`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
                'energy_suppliers_vat_unique' => 'UNIQUE KEY `energy_suppliers_vat_unique` (`vat`)',
                'energy_suppliers_name_key_index' => 'KEY `energy_suppliers_name_key_index` (`name_key`)',
            ],
            'foreign' => [
            ],
        ],
        'failed_jobs' => [
            'create' => 'CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
  KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'uuid' => '`uuid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `id`',
                'connection' => '`connection` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `uuid`',
                'queue' => '`queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `connection`',
                'payload' => '`payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `queue`',
                'exception' => '`exception` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `payload`',
                'failed_at' => '`failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `exception`',
            ],
            'indexes' => [
                'failed_jobs_uuid_unique' => 'UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)',
                'failed_jobs_connection_queue_failed_at_index' => 'KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`)',
            ],
            'foreign' => [
            ],
        ],
        'finanziamento_documents' => [
            'create' => 'CREATE TABLE `finanziamento_documents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Titolo mostrato su WhatsApp: max 24 caratteri\',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `requirement` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'obbligatorio\',
  `ai_kind` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` smallint unsigned NOT NULL DEFAULT \'0\',
  `is_active` tinyint(1) NOT NULL DEFAULT \'1\',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `finanziamento_documents_product_code_unique` (`product`,`code`)
) ENGINE=InnoDB AUTO_INCREMENT=56 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'product' => '`product` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `id`',
                'code' => '`code` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `product`',
                'name' => '`name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Titolo mostrato su WhatsApp: max 24 caratteri\' AFTER `code`',
                'description' => '`description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci AFTER `name`',
                'requirement' => '`requirement` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'obbligatorio\' AFTER `description`',
                'ai_kind' => '`ai_kind` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `requirement`',
                'sort_order' => '`sort_order` smallint unsigned NOT NULL DEFAULT \'0\' AFTER `ai_kind`',
                'is_active' => '`is_active` tinyint(1) NOT NULL DEFAULT \'1\' AFTER `sort_order`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `is_active`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
                'finanziamento_documents_product_code_unique' => 'UNIQUE KEY `finanziamento_documents_product_code_unique` (`product`,`code`)',
            ],
            'foreign' => [
            ],
        ],
        'flow_checks' => [
            'create' => 'CREATE TABLE `flow_checks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Nome con cui le domande agganciano il controllo\',
  `class` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Classe che implementa App\\\\Services\\\\Checks\\\\NodeCheck\',
  `is_active` tinyint(1) NOT NULL DEFAULT \'1\',
  `sort_order` smallint unsigned NOT NULL DEFAULT \'0\',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `flow_checks_code_unique` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'code' => '`code` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Nome con cui le domande agganciano il controllo\' AFTER `id`',
                'class' => '`class` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Classe che implementa App\\\\Services\\\\Checks\\\\NodeCheck\' AFTER `code`',
                'is_active' => '`is_active` tinyint(1) NOT NULL DEFAULT \'1\' AFTER `class`',
                'sort_order' => '`sort_order` smallint unsigned NOT NULL DEFAULT \'0\' AFTER `is_active`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `sort_order`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
                'flow_checks_code_unique' => 'UNIQUE KEY `flow_checks_code_unique` (`code`)',
            ],
            'foreign' => [
            ],
        ],
        'flow_node_jumps' => [
            'create' => 'CREATE TABLE `flow_node_jumps` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `flow_node_id` bigint unsigned NOT NULL,
  `when_value` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Valore che fa scattare il salto; * = qualsiasi altro\',
  `go_to` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Codice della domanda di destinazione\',
  `sort_order` smallint unsigned NOT NULL DEFAULT \'0\',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `flow_node_jumps_flow_node_id_when_value_unique` (`flow_node_id`,`when_value`),
  KEY `flow_node_jumps_company_id_foreign` (`company_id`)
) ENGINE=InnoDB AUTO_INCREMENT=334 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'flow_node_id' => '`flow_node_id` bigint unsigned NOT NULL AFTER `id`',
                'when_value' => '`when_value` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Valore che fa scattare il salto; * = qualsiasi altro\' AFTER `flow_node_id`',
                'go_to' => '`go_to` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Codice della domanda di destinazione\' AFTER `when_value`',
                'sort_order' => '`sort_order` smallint unsigned NOT NULL DEFAULT \'0\' AFTER `go_to`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `sort_order`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
                'company_id' => '`company_id` bigint unsigned DEFAULT NULL AFTER `updated_at`',
            ],
            'indexes' => [
                'flow_node_jumps_flow_node_id_when_value_unique' => 'UNIQUE KEY `flow_node_jumps_flow_node_id_when_value_unique` (`flow_node_id`,`when_value`)',
                'flow_node_jumps_company_id_foreign' => 'KEY `flow_node_jumps_company_id_foreign` (`company_id`)',
            ],
            'foreign' => [
                'flow_node_jumps_company_id_foreign' => 'CONSTRAINT `flow_node_jumps_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL',
                'flow_node_jumps_flow_node_id_foreign' => 'CONSTRAINT `flow_node_jumps_flow_node_id_foreign` FOREIGN KEY (`flow_node_id`) REFERENCES `flow_nodes` (`id`) ON DELETE CASCADE',
            ],
        ],
        'flow_node_options' => [
            'create' => 'CREATE TABLE `flow_node_options` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `flow_node_id` bigint unsigned NOT NULL,
  `code` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Titolo mostrato su WhatsApp: max 24 caratteri\',
  `sort_order` smallint unsigned NOT NULL DEFAULT \'0\',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `flow_node_options_flow_node_id_code_unique` (`flow_node_id`,`code`),
  KEY `flow_node_options_company_id_foreign` (`company_id`)
) ENGINE=InnoDB AUTO_INCREMENT=461 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'flow_node_id' => '`flow_node_id` bigint unsigned NOT NULL AFTER `id`',
                'code' => '`code` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `flow_node_id`',
                'title' => '`title` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Titolo mostrato su WhatsApp: max 24 caratteri\' AFTER `code`',
                'sort_order' => '`sort_order` smallint unsigned NOT NULL DEFAULT \'0\' AFTER `title`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `sort_order`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
                'company_id' => '`company_id` bigint unsigned DEFAULT NULL AFTER `updated_at`',
            ],
            'indexes' => [
                'flow_node_options_flow_node_id_code_unique' => 'UNIQUE KEY `flow_node_options_flow_node_id_code_unique` (`flow_node_id`,`code`)',
                'flow_node_options_company_id_foreign' => 'KEY `flow_node_options_company_id_foreign` (`company_id`)',
            ],
            'foreign' => [
                'flow_node_options_company_id_foreign' => 'CONSTRAINT `flow_node_options_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL',
                'flow_node_options_flow_node_id_foreign' => 'CONSTRAINT `flow_node_options_flow_node_id_foreign` FOREIGN KEY (`flow_node_id`) REFERENCES `flow_nodes` (`id`) ON DELETE CASCADE',
            ],
        ],
        'flow_nodes' => [
            'create' => 'CREATE TABLE `flow_nodes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `flow_id` bigint unsigned NOT NULL,
  `code` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Etichetta nel riepilogo\',
  `prompt` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` smallint unsigned NOT NULL DEFAULT \'0\',
  `skippable` tinyint(1) NOT NULL DEFAULT \'0\',
  `can_modify` tinyint(1) NOT NULL DEFAULT \'0\',
  `save` tinyint(1) NOT NULL DEFAULT \'1\' COMMENT \'Se la risposta entra nei dati della pratica\',
  `jump_by` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'answer\',
  `params` json DEFAULT NULL,
  `checks` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `flow_nodes_flow_id_code_unique` (`flow_id`,`code`),
  KEY `flow_nodes_company_id_foreign` (`company_id`)
) ENGINE=InnoDB AUTO_INCREMENT=267 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'flow_id' => '`flow_id` bigint unsigned NOT NULL AFTER `id`',
                'code' => '`code` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `flow_id`',
                'type' => '`type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `code`',
                'label' => '`label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Etichetta nel riepilogo\' AFTER `type`',
                'prompt' => '`prompt` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `label`',
                'sort_order' => '`sort_order` smallint unsigned NOT NULL DEFAULT \'0\' AFTER `prompt`',
                'skippable' => '`skippable` tinyint(1) NOT NULL DEFAULT \'0\' AFTER `sort_order`',
                'can_modify' => '`can_modify` tinyint(1) NOT NULL DEFAULT \'0\' AFTER `skippable`',
                'save' => '`save` tinyint(1) NOT NULL DEFAULT \'1\' COMMENT \'Se la risposta entra nei dati della pratica\' AFTER `can_modify`',
                'jump_by' => '`jump_by` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'answer\' AFTER `save`',
                'params' => '`params` json DEFAULT NULL AFTER `jump_by`',
                'checks' => '`checks` json DEFAULT NULL AFTER `params`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `checks`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
                'company_id' => '`company_id` bigint unsigned DEFAULT NULL AFTER `updated_at`',
            ],
            'indexes' => [
                'flow_nodes_flow_id_code_unique' => 'UNIQUE KEY `flow_nodes_flow_id_code_unique` (`flow_id`,`code`)',
                'flow_nodes_company_id_foreign' => 'KEY `flow_nodes_company_id_foreign` (`company_id`)',
            ],
            'foreign' => [
                'flow_nodes_company_id_foreign' => 'CONSTRAINT `flow_nodes_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL',
                'flow_nodes_flow_id_foreign' => 'CONSTRAINT `flow_nodes_flow_id_foreign` FOREIGN KEY (`flow_id`) REFERENCES `flows` (`id`) ON DELETE CASCADE',
            ],
        ],
        'flows' => [
            'create' => 'CREATE TABLE `flows` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `header` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `start` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `restart` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `labels` json DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT \'1\',
  `is_test` tinyint(1) NOT NULL DEFAULT \'0\',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `flows_company_code_is_test_unique` (`company_id`,`code`,`is_test`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'code' => '`code` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `id`',
                'name' => '`name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `code`',
                'header' => '`header` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci AFTER `name`',
                'start' => '`start` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `header`',
                'restart' => '`restart` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `start`',
                'labels' => '`labels` json DEFAULT NULL AFTER `restart`',
                'is_active' => '`is_active` tinyint(1) NOT NULL DEFAULT \'1\' AFTER `labels`',
                'is_test' => '`is_test` tinyint(1) NOT NULL DEFAULT \'0\' AFTER `is_active`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `is_test`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
                'company_id' => '`company_id` bigint unsigned DEFAULT NULL AFTER `updated_at`',
            ],
            'indexes' => [
                'flows_company_code_is_test_unique' => 'UNIQUE KEY `flows_company_code_is_test_unique` (`company_id`,`code`,`is_test`)',
            ],
            'foreign' => [
                'flows_company_id_foreign' => 'CONSTRAINT `flows_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL',
            ],
        ],
        'fornitoris' => [
            'create' => 'CREATE TABLE `fornitoris` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'ID univoco del fornitore/agente\',
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nome` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Nome del referente\',
  `stipulated_at` date DEFAULT NULL COMMENT \'Data stipula contratto collaborazione\',
  `pec` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Indirizzo Posta Elettronica Certificata\',
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Descrizione\',
  `email_private` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `supervisor_type` enum(\'no\',\'si\',\'filiale\') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'no\' COMMENT \'Se supervisore indicare e specificare se di filiale\',
  `oam` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Oam\',
  `oam_at` date DEFAULT NULL COMMENT \'Data iscrizione OAM\',
  `oam_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Denominazione sociale registrata in OAM\',
  `numero_iscrizione_rui` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Numero iscrizione OAM\',
  `ivass` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Codice di iscrizione IVASS\',
  `ivass_at` date DEFAULT NULL COMMENT \'Data iscrizione IVASS\',
  `dismissed_at` date DEFAULT NULL COMMENT \'Data cessazione rapporto\',
  `ivass_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Denominazione IVASS\',
  `ivass_section` enum(\'A\',\'B\',\'C\',\'D\',\'E\') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Sezione IVASS\',
  `type` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Agente / Mediatore / Consulente / Call Center\',
  `is_active` tinyint(1) NOT NULL DEFAULT \'1\' COMMENT \'Indica se agente è attualmente convenzionato\',
  `is_art108` tinyint(1) NOT NULL DEFAULT \'0\' COMMENT \'Esente art. 108 - ex art. 128-novies TUB\',
  `company_branch_id` int unsigned DEFAULT NULL COMMENT \'Filiale di riferimento\',
  `coordinated_type` int unsigned DEFAULT NULL COMMENT \'ID del dipendente coordinatore\',
  `coordinated_id` int unsigned DEFAULT NULL COMMENT \'ID dell\'\'agente coordinatore\',
  `user_id` bigint unsigned DEFAULT NULL COMMENT \'ID dell\'\'utente collegato\',
  `oam_dismissed_at` date DEFAULT NULL COMMENT \'Data revoca OAM\',
  `welcome_bonus` decimal(10,2) DEFAULT NULL COMMENT \'Premio benvenuto\',
  `campagna` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Codice campagna\',
  `available_at` date DEFAULT NULL COMMENT \'Data disponibilità agente\',
  `budget` decimal(10,2) DEFAULT NULL COMMENT \'Budget agente\',
  `codice` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `coge` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `natoil` date DEFAULT NULL COMMENT \'Data di nascita\',
  `indirizzo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Indirizzo\',
  `comune` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Comune di residenza\',
  `cap` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Codice di avviamento postale\',
  `prov` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Provincia\',
  `tel` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Numero di telefono\',
  `coordinatore` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Nome del coordinatore di riferimento\',
  `piva` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Partita IVA\',
  `cf` char(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Codice fiscale\',
  `nomecoge` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Nome per la contabilità\',
  `nomefattura` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Nome da utilizzare in fattura\',
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `anticipo` decimal(15,2) DEFAULT NULL COMMENT \'Quota fissa mensile erogata come anticipo provvigionale\',
  `enasarco` enum(\'no\',\'monomandatario\',\'plurimandatario\',\'societa\') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT \'plurimandatario\' COMMENT \'Tipo di mandato ENASARCO\',
  `anticipo_residuo` decimal(15,2) DEFAULT NULL COMMENT \'Debito residuo dell\'\'agente verso il mediatore per anticipi da recuperare\',
  `contributo` decimal(15,2) DEFAULT NULL COMMENT \'Importo del contributo spese\',
  `contributo_description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT \'Contributo spese\' COMMENT \'Descrizione del contributo spese\',
  `anticipo_description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT \'Anticipo attuale\' COMMENT \'Descrizione dell\'\'anticipo\',
  `issubfornitore` tinyint DEFAULT NULL COMMENT \'1 se l\'\'agente opera tramite un altro intermediario\',
  `operatore` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `iscollaboratore` tinyint(1) DEFAULT NULL COMMENT \'Indica se è un collaboratore (1) o meno (0)\',
  `isdipendente` tinyint(1) DEFAULT \'0\' COMMENT \'Indica se è un dipendente (1) o meno (0)\',
  `regione` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `citta` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL COMMENT \'Timestamp di cancellazione (soft delete)\',
  `created_at` timestamp NULL DEFAULT NULL COMMENT \'Timestamp di creazione del record\',
  `updated_at` timestamp NULL DEFAULT NULL COMMENT \'Timestamp di ultimo aggiornamento\',
  `company_id` bigint unsigned DEFAULT NULL,
  `contributoperiodicita` smallint DEFAULT NULL COMMENT \'Frequenza addebito costi (1=Mensile, 3=Trimestrale, etc.)\',
  `contributodalmese` date DEFAULT NULL,
  `fornitorirole_id` int DEFAULT NULL,
  `employee_roles` json DEFAULT NULL COMMENT \'Ruoli (nomi EmployeeType) associati a questo fornitore, stessa struttura di employees.employee_roles\',
  `branch_id` bigint DEFAULT NULL,
  `tenant_company_id` bigint unsigned DEFAULT NULL,
  `sigla` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `piva` (`piva`),
  KEY `company_id` (`company_id`),
  KEY `fornitorirole_id` (`fornitorirole_id`),
  KEY `fornitoris_tenant_company_id_foreign` (`tenant_company_id`),
  KEY `fornitoris_sigla_index` (`sigla`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'Anagrafica Agenti di commercio / Collaboratori (Fornitori di servizi)\'',
            'columns' => [
                'id' => '`id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'ID univoco del fornitore/agente\' FIRST',
                'name' => '`name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `id`',
                'nome' => '`nome` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Nome del referente\' AFTER `name`',
                'stipulated_at' => '`stipulated_at` date DEFAULT NULL COMMENT \'Data stipula contratto collaborazione\' AFTER `nome`',
                'pec' => '`pec` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Indirizzo Posta Elettronica Certificata\' AFTER `stipulated_at`',
                'description' => '`description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Descrizione\' AFTER `pec`',
                'email_private' => '`email_private` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `description`',
                'supervisor_type' => '`supervisor_type` enum(\'no\',\'si\',\'filiale\') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'no\' COMMENT \'Se supervisore indicare e specificare se di filiale\' AFTER `email_private`',
                'oam' => '`oam` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Oam\' AFTER `supervisor_type`',
                'oam_at' => '`oam_at` date DEFAULT NULL COMMENT \'Data iscrizione OAM\' AFTER `oam`',
                'oam_name' => '`oam_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Denominazione sociale registrata in OAM\' AFTER `oam_at`',
                'numero_iscrizione_rui' => '`numero_iscrizione_rui` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Numero iscrizione OAM\' AFTER `oam_name`',
                'ivass' => '`ivass` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Codice di iscrizione IVASS\' AFTER `numero_iscrizione_rui`',
                'ivass_at' => '`ivass_at` date DEFAULT NULL COMMENT \'Data iscrizione IVASS\' AFTER `ivass`',
                'dismissed_at' => '`dismissed_at` date DEFAULT NULL COMMENT \'Data cessazione rapporto\' AFTER `ivass_at`',
                'ivass_name' => '`ivass_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Denominazione IVASS\' AFTER `dismissed_at`',
                'ivass_section' => '`ivass_section` enum(\'A\',\'B\',\'C\',\'D\',\'E\') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Sezione IVASS\' AFTER `ivass_name`',
                'type' => '`type` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Agente / Mediatore / Consulente / Call Center\' AFTER `ivass_section`',
                'is_active' => '`is_active` tinyint(1) NOT NULL DEFAULT \'1\' COMMENT \'Indica se agente è attualmente convenzionato\' AFTER `type`',
                'is_art108' => '`is_art108` tinyint(1) NOT NULL DEFAULT \'0\' COMMENT \'Esente art. 108 - ex art. 128-novies TUB\' AFTER `is_active`',
                'company_branch_id' => '`company_branch_id` int unsigned DEFAULT NULL COMMENT \'Filiale di riferimento\' AFTER `is_art108`',
                'coordinated_type' => '`coordinated_type` int unsigned DEFAULT NULL COMMENT \'ID del dipendente coordinatore\' AFTER `company_branch_id`',
                'coordinated_id' => '`coordinated_id` int unsigned DEFAULT NULL COMMENT \'ID dell\'\'agente coordinatore\' AFTER `coordinated_type`',
                'user_id' => '`user_id` bigint unsigned DEFAULT NULL COMMENT \'ID dell\'\'utente collegato\' AFTER `coordinated_id`',
                'oam_dismissed_at' => '`oam_dismissed_at` date DEFAULT NULL COMMENT \'Data revoca OAM\' AFTER `user_id`',
                'welcome_bonus' => '`welcome_bonus` decimal(10,2) DEFAULT NULL COMMENT \'Premio benvenuto\' AFTER `oam_dismissed_at`',
                'campagna' => '`campagna` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Codice campagna\' AFTER `welcome_bonus`',
                'available_at' => '`available_at` date DEFAULT NULL COMMENT \'Data disponibilità agente\' AFTER `campagna`',
                'budget' => '`budget` decimal(10,2) DEFAULT NULL COMMENT \'Budget agente\' AFTER `available_at`',
                'codice' => '`codice` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `budget`',
                'coge' => '`coge` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `codice`',
                'natoil' => '`natoil` date DEFAULT NULL COMMENT \'Data di nascita\' AFTER `coge`',
                'indirizzo' => '`indirizzo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Indirizzo\' AFTER `natoil`',
                'comune' => '`comune` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Comune di residenza\' AFTER `indirizzo`',
                'cap' => '`cap` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Codice di avviamento postale\' AFTER `comune`',
                'prov' => '`prov` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Provincia\' AFTER `cap`',
                'tel' => '`tel` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Numero di telefono\' AFTER `prov`',
                'coordinatore' => '`coordinatore` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Nome del coordinatore di riferimento\' AFTER `tel`',
                'piva' => '`piva` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Partita IVA\' AFTER `coordinatore`',
                'cf' => '`cf` char(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Codice fiscale\' AFTER `piva`',
                'nomecoge' => '`nomecoge` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Nome per la contabilità\' AFTER `cf`',
                'nomefattura' => '`nomefattura` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Nome da utilizzare in fattura\' AFTER `nomecoge`',
                'email' => '`email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `nomefattura`',
                'anticipo' => '`anticipo` decimal(15,2) DEFAULT NULL COMMENT \'Quota fissa mensile erogata come anticipo provvigionale\' AFTER `email`',
                'enasarco' => '`enasarco` enum(\'no\',\'monomandatario\',\'plurimandatario\',\'societa\') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT \'plurimandatario\' COMMENT \'Tipo di mandato ENASARCO\' AFTER `anticipo`',
                'anticipo_residuo' => '`anticipo_residuo` decimal(15,2) DEFAULT NULL COMMENT \'Debito residuo dell\'\'agente verso il mediatore per anticipi da recuperare\' AFTER `enasarco`',
                'contributo' => '`contributo` decimal(15,2) DEFAULT NULL COMMENT \'Importo del contributo spese\' AFTER `anticipo_residuo`',
                'contributo_description' => '`contributo_description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT \'Contributo spese\' COMMENT \'Descrizione del contributo spese\' AFTER `contributo`',
                'anticipo_description' => '`anticipo_description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT \'Anticipo attuale\' COMMENT \'Descrizione dell\'\'anticipo\' AFTER `contributo_description`',
                'issubfornitore' => '`issubfornitore` tinyint DEFAULT NULL COMMENT \'1 se l\'\'agente opera tramite un altro intermediario\' AFTER `anticipo_description`',
                'operatore' => '`operatore` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `issubfornitore`',
                'iscollaboratore' => '`iscollaboratore` tinyint(1) DEFAULT NULL COMMENT \'Indica se è un collaboratore (1) o meno (0)\' AFTER `operatore`',
                'isdipendente' => '`isdipendente` tinyint(1) DEFAULT \'0\' COMMENT \'Indica se è un dipendente (1) o meno (0)\' AFTER `iscollaboratore`',
                'regione' => '`regione` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `isdipendente`',
                'citta' => '`citta` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `regione`',
                'deleted_at' => '`deleted_at` timestamp NULL DEFAULT NULL COMMENT \'Timestamp di cancellazione (soft delete)\' AFTER `citta`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL COMMENT \'Timestamp di creazione del record\' AFTER `deleted_at`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL COMMENT \'Timestamp di ultimo aggiornamento\' AFTER `created_at`',
                'company_id' => '`company_id` bigint unsigned DEFAULT NULL AFTER `updated_at`',
                'contributoperiodicita' => '`contributoperiodicita` smallint DEFAULT NULL COMMENT \'Frequenza addebito costi (1=Mensile, 3=Trimestrale, etc.)\' AFTER `company_id`',
                'contributodalmese' => '`contributodalmese` date DEFAULT NULL AFTER `contributoperiodicita`',
                'fornitorirole_id' => '`fornitorirole_id` int DEFAULT NULL AFTER `contributodalmese`',
                'employee_roles' => '`employee_roles` json DEFAULT NULL COMMENT \'Ruoli (nomi EmployeeType) associati a questo fornitore, stessa struttura di employees.employee_roles\' AFTER `fornitorirole_id`',
                'branch_id' => '`branch_id` bigint DEFAULT NULL AFTER `employee_roles`',
                'tenant_company_id' => '`tenant_company_id` bigint unsigned DEFAULT NULL AFTER `branch_id`',
                'sigla' => '`sigla` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `tenant_company_id`',
            ],
            'indexes' => [
                'piva' => 'KEY `piva` (`piva`)',
                'company_id' => 'KEY `company_id` (`company_id`)',
                'fornitorirole_id' => 'KEY `fornitorirole_id` (`fornitorirole_id`)',
                'fornitoris_tenant_company_id_foreign' => 'KEY `fornitoris_tenant_company_id_foreign` (`tenant_company_id`)',
                'fornitoris_sigla_index' => 'KEY `fornitoris_sigla_index` (`sigla`)',
            ],
            'foreign' => [
                'fornitoris_tenant_company_id_foreign' => 'CONSTRAINT `fornitoris_tenant_company_id_foreign` FOREIGN KEY (`tenant_company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL',
            ],
        ],
        'job_batches' => [
            'create' => 'CREATE TABLE `job_batches` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL FIRST',
                'name' => '`name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `id`',
                'total_jobs' => '`total_jobs` int NOT NULL AFTER `name`',
                'pending_jobs' => '`pending_jobs` int NOT NULL AFTER `total_jobs`',
                'failed_jobs' => '`failed_jobs` int NOT NULL AFTER `pending_jobs`',
                'failed_job_ids' => '`failed_job_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `failed_jobs`',
                'options' => '`options` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci AFTER `failed_job_ids`',
                'cancelled_at' => '`cancelled_at` int DEFAULT NULL AFTER `options`',
                'created_at' => '`created_at` int NOT NULL AFTER `cancelled_at`',
                'finished_at' => '`finished_at` int DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
            ],
            'foreign' => [
            ],
        ],
        'jobs' => [
            'create' => 'CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` smallint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'queue' => '`queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `id`',
                'payload' => '`payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `queue`',
                'attempts' => '`attempts` smallint unsigned NOT NULL AFTER `payload`',
                'reserved_at' => '`reserved_at` int unsigned DEFAULT NULL AFTER `attempts`',
                'available_at' => '`available_at` int unsigned NOT NULL AFTER `reserved_at`',
                'created_at' => '`created_at` int unsigned NOT NULL AFTER `available_at`',
            ],
            'indexes' => [
                'jobs_queue_index' => 'KEY `jobs_queue_index` (`queue`)',
            ],
            'foreign' => [
            ],
        ],
        'loan_requests' => [
            'create' => 'CREATE TABLE `loan_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` bigint unsigned DEFAULT NULL,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `agent_wa_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `product` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'richiesta\',
  `is_test` tinyint(1) NOT NULL DEFAULT \'0\',
  `answers` json NOT NULL,
  `personal` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cadastre` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `privacy_received_at` timestamp NULL DEFAULT NULL,
  `privacy_verified_at` timestamp NULL DEFAULT NULL,
  `perfected_at` timestamp NULL DEFAULT NULL,
  `emailed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT NULL,
  `direct_contact` tinyint(1) NOT NULL DEFAULT \'0\',
  `customer_phone` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `customer_email` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `crm_lead_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `documents_archived_at` timestamp NULL DEFAULT NULL,
  `signature_status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `signed_at` timestamp NULL DEFAULT NULL,
  `documents_sent_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `loan_requests_code_unique` (`code`),
  KEY `loan_requests_agent_wa_number_index` (`agent_wa_number`),
  KEY `loan_requests_is_test_index` (`is_test`),
  KEY `loan_requests_company_id_foreign` (`company_id`),
  KEY `loan_requests_parent_id_foreign` (`parent_id`),
  KEY `loan_requests_signature_status_index` (`signature_status`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'parent_id' => '`parent_id` bigint unsigned DEFAULT NULL AFTER `id`',
                'code' => '`code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `parent_id`',
                'agent_wa_number' => '`agent_wa_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `code`',
                'product' => '`product` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `agent_wa_number`',
                'status' => '`status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'richiesta\' AFTER `product`',
                'is_test' => '`is_test` tinyint(1) NOT NULL DEFAULT \'0\' AFTER `status`',
                'answers' => '`answers` json NOT NULL AFTER `is_test`',
                'personal' => '`personal` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci AFTER `answers`',
                'cadastre' => '`cadastre` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci AFTER `personal`',
                'privacy_received_at' => '`privacy_received_at` timestamp NULL DEFAULT NULL AFTER `cadastre`',
                'privacy_verified_at' => '`privacy_verified_at` timestamp NULL DEFAULT NULL AFTER `privacy_received_at`',
                'perfected_at' => '`perfected_at` timestamp NULL DEFAULT NULL AFTER `privacy_verified_at`',
                'emailed_at' => '`emailed_at` timestamp NULL DEFAULT NULL AFTER `perfected_at`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `emailed_at`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
                'company_id' => '`company_id` bigint unsigned DEFAULT NULL AFTER `updated_at`',
                'direct_contact' => '`direct_contact` tinyint(1) NOT NULL DEFAULT \'0\' AFTER `company_id`',
                'customer_phone' => '`customer_phone` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci AFTER `direct_contact`',
                'customer_email' => '`customer_email` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci AFTER `customer_phone`',
                'crm_lead_id' => '`crm_lead_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `customer_email`',
                'documents_archived_at' => '`documents_archived_at` timestamp NULL DEFAULT NULL AFTER `crm_lead_id`',
                'signature_status' => '`signature_status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `documents_archived_at`',
                'signed_at' => '`signed_at` timestamp NULL DEFAULT NULL AFTER `signature_status`',
                'documents_sent_at' => '`documents_sent_at` timestamp NULL DEFAULT NULL AFTER `signed_at`',
            ],
            'indexes' => [
                'loan_requests_code_unique' => 'UNIQUE KEY `loan_requests_code_unique` (`code`)',
                'loan_requests_agent_wa_number_index' => 'KEY `loan_requests_agent_wa_number_index` (`agent_wa_number`)',
                'loan_requests_is_test_index' => 'KEY `loan_requests_is_test_index` (`is_test`)',
                'loan_requests_company_id_foreign' => 'KEY `loan_requests_company_id_foreign` (`company_id`)',
                'loan_requests_parent_id_foreign' => 'KEY `loan_requests_parent_id_foreign` (`parent_id`)',
                'loan_requests_signature_status_index' => 'KEY `loan_requests_signature_status_index` (`signature_status`)',
            ],
            'foreign' => [
                'loan_requests_company_id_foreign' => 'CONSTRAINT `loan_requests_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL',
                'loan_requests_parent_id_foreign' => 'CONSTRAINT `loan_requests_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `loan_requests` (`id`) ON DELETE SET NULL',
            ],
        ],
        'password_reset_tokens' => [
            'create' => 'CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'email' => '`email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL FIRST',
                'token' => '`token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `email`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `token`',
            ],
            'indexes' => [
            ],
            'foreign' => [
            ],
        ],
        'pratica_documents' => [
            'create' => 'CREATE TABLE `pratica_documents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `loan_request_id` bigint unsigned NOT NULL,
  `finanziamento_document_id` bigint unsigned DEFAULT NULL,
  `code` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `requirement` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'obbligatorio\',
  `status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'da_ricevere\',
  `annotations` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `sort_order` smallint unsigned NOT NULL DEFAULT \'0\',
  `received_at` timestamp NULL DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pratica_documents_loan_request_id_code_unique` (`loan_request_id`,`code`),
  KEY `pratica_documents_finanziamento_document_id_foreign` (`finanziamento_document_id`),
  KEY `pratica_documents_status_index` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=65 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'loan_request_id' => '`loan_request_id` bigint unsigned NOT NULL AFTER `id`',
                'finanziamento_document_id' => '`finanziamento_document_id` bigint unsigned DEFAULT NULL AFTER `loan_request_id`',
                'code' => '`code` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `finanziamento_document_id`',
                'name' => '`name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `code`',
                'requirement' => '`requirement` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'obbligatorio\' AFTER `name`',
                'status' => '`status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'da_ricevere\' AFTER `requirement`',
                'annotations' => '`annotations` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci AFTER `status`',
                'sort_order' => '`sort_order` smallint unsigned NOT NULL DEFAULT \'0\' AFTER `annotations`',
                'received_at' => '`received_at` timestamp NULL DEFAULT NULL AFTER `sort_order`',
                'reviewed_at' => '`reviewed_at` timestamp NULL DEFAULT NULL AFTER `received_at`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `reviewed_at`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
                'pratica_documents_loan_request_id_code_unique' => 'UNIQUE KEY `pratica_documents_loan_request_id_code_unique` (`loan_request_id`,`code`)',
                'pratica_documents_finanziamento_document_id_foreign' => 'KEY `pratica_documents_finanziamento_document_id_foreign` (`finanziamento_document_id`)',
                'pratica_documents_status_index' => 'KEY `pratica_documents_status_index` (`status`)',
            ],
            'foreign' => [
                'pratica_documents_finanziamento_document_id_foreign' => 'CONSTRAINT `pratica_documents_finanziamento_document_id_foreign` FOREIGN KEY (`finanziamento_document_id`) REFERENCES `finanziamento_documents` (`id`) ON DELETE SET NULL',
                'pratica_documents_loan_request_id_foreign' => 'CONSTRAINT `pratica_documents_loan_request_id_foreign` FOREIGN KEY (`loan_request_id`) REFERENCES `loan_requests` (`id`) ON DELETE CASCADE',
            ],
        ],
        'pratica_fields' => [
            'create' => 'CREATE TABLE `pratica_fields` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `loan_request_id` bigint unsigned NOT NULL,
  `key` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'proposto\',
  `source_code` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attachment_id` bigint unsigned DEFAULT NULL,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pratica_fields_loan_request_id_key_unique` (`loan_request_id`,`key`),
  KEY `pratica_fields_attachment_id_foreign` (`attachment_id`),
  KEY `pratica_fields_status_index` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'loan_request_id' => '`loan_request_id` bigint unsigned NOT NULL AFTER `id`',
                'key' => '`key` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `loan_request_id`',
                'value' => '`value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `key`',
                'status' => '`status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'proposto\' AFTER `value`',
                'source_code' => '`source_code` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `status`',
                'attachment_id' => '`attachment_id` bigint unsigned DEFAULT NULL AFTER `source_code`',
                'confirmed_at' => '`confirmed_at` timestamp NULL DEFAULT NULL AFTER `attachment_id`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `confirmed_at`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
                'pratica_fields_loan_request_id_key_unique' => 'UNIQUE KEY `pratica_fields_loan_request_id_key_unique` (`loan_request_id`,`key`)',
                'pratica_fields_attachment_id_foreign' => 'KEY `pratica_fields_attachment_id_foreign` (`attachment_id`)',
                'pratica_fields_status_index' => 'KEY `pratica_fields_status_index` (`status`)',
            ],
            'foreign' => [
                'pratica_fields_attachment_id_foreign' => 'CONSTRAINT `pratica_fields_attachment_id_foreign` FOREIGN KEY (`attachment_id`) REFERENCES `attachments` (`id`) ON DELETE SET NULL',
                'pratica_fields_loan_request_id_foreign' => 'CONSTRAINT `pratica_fields_loan_request_id_foreign` FOREIGN KEY (`loan_request_id`) REFERENCES `loan_requests` (`id`) ON DELETE CASCADE',
            ],
        ],
        'products' => [
            'create' => 'CREATE TABLE `products` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT \'1\',
  `params` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `products_type_index` (`type`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'type' => '`type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `id`',
                'code' => '`code` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `type`',
                'name' => '`name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `code`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `name`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
                'description' => '`description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci AFTER `updated_at`',
                'is_active' => '`is_active` tinyint(1) NOT NULL DEFAULT \'1\' AFTER `description`',
                'params' => '`params` json DEFAULT NULL AFTER `is_active`',
            ],
            'indexes' => [
                'products_type_index' => 'KEY `products_type_index` (`type`)',
            ],
            'foreign' => [
            ],
        ],
        'quote_band_bounds' => [
            'create' => 'CREATE TABLE `quote_band_bounds` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `dimension` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `low` int unsigned NOT NULL,
  `high` int unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `quote_band_bounds_dimension_code_unique` (`dimension`,`code`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'dimension' => '`dimension` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `id`',
                'code' => '`code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `dimension`',
                'label' => '`label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `code`',
                'low' => '`low` int unsigned NOT NULL AFTER `label`',
                'high' => '`high` int unsigned NOT NULL AFTER `low`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `high`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
                'quote_band_bounds_dimension_code_unique' => 'UNIQUE KEY `quote_band_bounds_dimension_code_unique` (`dimension`,`code`)',
            ],
            'foreign' => [
            ],
        ],
        'quote_contract_types' => [
            'create' => 'CREATE TABLE `quote_contract_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `product` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `quote_contract_types_value_unique` (`value`),
  UNIQUE KEY `quote_contract_types_product_unique` (`product`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'value' => '`value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `id`',
                'label' => '`label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `value`',
                'product' => '`product` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `label`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `product`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
                'quote_contract_types_value_unique' => 'UNIQUE KEY `quote_contract_types_value_unique` (`value`)',
                'quote_contract_types_product_unique' => 'UNIQUE KEY `quote_contract_types_product_unique` (`product`)',
            ],
            'foreign' => [
            ],
        ],
        'quote_durations' => [
            'create' => 'CREATE TABLE `quote_durations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `contract` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `months` smallint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `quote_durations_contract_months_unique` (`contract`,`months`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'contract' => '`contract` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `id`',
                'months' => '`months` smallint unsigned NOT NULL AFTER `contract`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `months`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
                'quote_durations_contract_months_unique' => 'UNIQUE KEY `quote_durations_contract_months_unique` (`contract`,`months`)',
            ],
            'foreign' => [
            ],
        ],
        'quote_employment_map' => [
            'create' => 'CREATE TABLE `quote_employment_map` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `lavoro` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ente_pensione` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dimensione_azienda` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo_rapporto` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `lead_tipologia` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `priority` smallint unsigned NOT NULL DEFAULT \'0\',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'lavoro' => '`lavoro` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `id`',
                'ente_pensione' => '`ente_pensione` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `lavoro`',
                'dimensione_azienda' => '`dimensione_azienda` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `ente_pensione`',
                'tipo_rapporto' => '`tipo_rapporto` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `dimensione_azienda`',
                'lead_tipologia' => '`lead_tipologia` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `tipo_rapporto`',
                'priority' => '`priority` smallint unsigned NOT NULL DEFAULT \'0\' AFTER `lead_tipologia`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `priority`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
            ],
            'foreign' => [
            ],
        ],
        'quote_employment_types' => [
            'create' => 'CREATE TABLE `quote_employment_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `contracts` json DEFAULT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `quote_employment_types_value_unique` (`value`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'value' => '`value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `id`',
                'contracts' => '`contracts` json DEFAULT NULL AFTER `value`',
                'note' => '`note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci AFTER `contracts`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `note`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
                'quote_employment_types_value_unique' => 'UNIQUE KEY `quote_employment_types_value_unique` (`value`)',
            ],
            'foreign' => [
            ],
        ],
        'quote_simulations' => [
            'create' => 'CREATE TABLE `quote_simulations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `loan_request_id` bigint unsigned NOT NULL,
  `scenario` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `request` json NOT NULL,
  `response` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `offers_count` smallint unsigned NOT NULL DEFAULT \'0\',
  `erogato_min` decimal(12,2) DEFAULT NULL,
  `erogato_max` decimal(12,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `quote_simulations_loan_request_id_foreign` (`loan_request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'loan_request_id' => '`loan_request_id` bigint unsigned NOT NULL AFTER `id`',
                'scenario' => '`scenario` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `loan_request_id`',
                'request' => '`request` json NOT NULL AFTER `scenario`',
                'response' => '`response` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci AFTER `request`',
                'offers_count' => '`offers_count` smallint unsigned NOT NULL DEFAULT \'0\' AFTER `response`',
                'erogato_min' => '`erogato_min` decimal(12,2) DEFAULT NULL AFTER `offers_count`',
                'erogato_max' => '`erogato_max` decimal(12,2) DEFAULT NULL AFTER `erogato_min`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `erogato_max`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
                'quote_simulations_loan_request_id_foreign' => 'KEY `quote_simulations_loan_request_id_foreign` (`loan_request_id`)',
            ],
            'foreign' => [
                'quote_simulations_loan_request_id_foreign' => 'CONSTRAINT `quote_simulations_loan_request_id_foreign` FOREIGN KEY (`loan_request_id`) REFERENCES `loan_requests` (`id`) ON DELETE CASCADE',
            ],
        ],
        'sessions' => [
            'create' => 'CREATE TABLE `sessions` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL FIRST',
                'user_id' => '`user_id` bigint unsigned DEFAULT NULL AFTER `id`',
                'ip_address' => '`ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `user_id`',
                'user_agent' => '`user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci AFTER `ip_address`',
                'payload' => '`payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `user_agent`',
                'last_activity' => '`last_activity` int NOT NULL AFTER `payload`',
            ],
            'indexes' => [
                'sessions_user_id_index' => 'KEY `sessions_user_id_index` (`user_id`)',
                'sessions_last_activity_index' => 'KEY `sessions_last_activity_index` (`last_activity`)',
            ],
            'foreign' => [
            ],
        ],
        'socialite_users' => [
            'create' => 'CREATE TABLE `socialite_users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `provider` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `socialite_users_provider_provider_id_unique` (`provider`,`provider_id`),
  KEY `socialite_users_user_id_foreign` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'user_id' => '`user_id` bigint unsigned NOT NULL AFTER `id`',
                'provider' => '`provider` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `user_id`',
                'provider_id' => '`provider_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `provider`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `provider_id`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
                'socialite_users_provider_provider_id_unique' => 'UNIQUE KEY `socialite_users_provider_provider_id_unique` (`provider`,`provider_id`)',
                'socialite_users_user_id_foreign' => 'KEY `socialite_users_user_id_foreign` (`user_id`)',
            ],
            'foreign' => [
                'socialite_users_user_id_foreign' => 'CONSTRAINT `socialite_users_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE',
            ],
        ],
        'users' => [
            'create' => 'CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `whatsapp_number` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_whatsapp_number_index` (`whatsapp_number`),
  KEY `users_company_id_foreign` (`company_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'name' => '`name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `id`',
                'email' => '`email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `name`',
                'whatsapp_number' => '`whatsapp_number` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `email`',
                'email_verified_at' => '`email_verified_at` timestamp NULL DEFAULT NULL AFTER `whatsapp_number`',
                'password' => '`password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `email_verified_at`',
                'remember_token' => '`remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `password`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `remember_token`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
                'company_id' => '`company_id` bigint unsigned DEFAULT NULL AFTER `updated_at`',
            ],
            'indexes' => [
                'users_email_unique' => 'UNIQUE KEY `users_email_unique` (`email`)',
                'users_whatsapp_number_index' => 'KEY `users_whatsapp_number_index` (`whatsapp_number`)',
                'users_company_id_foreign' => 'KEY `users_company_id_foreign` (`company_id`)',
            ],
            'foreign' => [
                'users_company_id_foreign' => 'CONSTRAINT `users_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL',
            ],
        ],
        ];
    }
};
