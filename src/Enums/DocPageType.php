<?php

namespace Platform\Dev\Enums;

enum DocPageType: string
{
    case Overview = 'overview';
    case Architecture = 'architecture';
    case Setup = 'setup';
    case Api = 'api';
    case DataModel = 'data_model';
    case Testing = 'testing';
    case Deployment = 'deployment';
    case Changelog = 'changelog';
    case Contributing = 'contributing';
    case Troubleshooting = 'troubleshooting';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Overview => 'Uebersicht',
            self::Architecture => 'Architektur',
            self::Setup => 'Setup & Konfiguration',
            self::Api => 'API-Referenz',
            self::DataModel => 'Datenmodell',
            self::Testing => 'Testing',
            self::Deployment => 'Deployment',
            self::Changelog => 'Changelog',
            self::Contributing => 'Contributing',
            self::Troubleshooting => 'Troubleshooting',
            self::Custom => 'Custom',
        };
    }

    public function defaultTitle(): string
    {
        return match ($this) {
            self::Overview => 'Uebersicht',
            self::Architecture => 'Architektur',
            self::Setup => 'Setup & Konfiguration',
            self::Api => 'API-Referenz',
            self::DataModel => 'Datenmodell',
            self::Testing => 'Testing',
            self::Deployment => 'Deployment',
            self::Changelog => 'Changelog',
            self::Contributing => 'Contributing',
            self::Troubleshooting => 'Troubleshooting',
            self::Custom => 'Custom',
        };
    }

    public function position(): int
    {
        return match ($this) {
            self::Overview => 0,
            self::Architecture => 1,
            self::Setup => 2,
            self::Api => 3,
            self::DataModel => 4,
            self::Testing => 5,
            self::Deployment => 6,
            self::Changelog => 7,
            self::Contributing => 8,
            self::Troubleshooting => 9,
            self::Custom => 99,
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Overview => 'Allgemeine Beschreibung des Packages: Zweck, Hauptfeatures, Zielgruppe und Abhaengigkeiten.',
            self::Architecture => 'Technische Architektur: Komponenten, Schichten, Design-Patterns, Datenfluss und Systemdiagramme.',
            self::Setup => 'Installations- und Konfigurationsanleitung: Voraussetzungen, Umgebungsvariablen, erste Schritte.',
            self::Api => 'API-Dokumentation: Endpunkte, Parameter, Responses, Authentifizierung und Beispiel-Requests.',
            self::DataModel => 'Datenmodell: Tabellen, Felder, Beziehungen, Indizes und Migrationshistorie.',
            self::Testing => 'Teststrategie: Unit-Tests, Feature-Tests, Testabdeckung und Ausfuehrung.',
            self::Deployment => 'Deployment-Prozess: Build, Release, Umgebungen, CI/CD und Rollback.',
            self::Changelog => 'Aenderungshistorie: Versionen, neue Features, Bugfixes und Breaking Changes.',
            self::Contributing => 'Beitragsrichtlinien: Code-Style, Branch-Strategie, Review-Prozess und Konventionen.',
            self::Troubleshooting => 'Fehlerbehebung: Haeufige Probleme, Loesungen, Debugging-Tipps und FAQ.',
            self::Custom => 'Benutzerdefiniertes Kapitel.',
        };
    }

    public function defaultContent(): string
    {
        return match ($this) {
            self::Overview => "# Uebersicht\n\n## Zweck\n\n_Beschreibe hier den Zweck dieses Packages._\n\n## Hauptfeatures\n\n- _Feature 1_\n- _Feature 2_\n\n## Abhaengigkeiten\n\n- _Abhaengigkeit 1_",
            self::Architecture => "# Architektur\n\n## Komponenten\n\n_Beschreibe die Hauptkomponenten._\n\n## Datenfluss\n\n_Beschreibe den Datenfluss zwischen Komponenten._\n\n## Design-Patterns\n\n_Welche Patterns werden verwendet?_",
            self::Setup => "# Setup & Konfiguration\n\n## Voraussetzungen\n\n- _Voraussetzung 1_\n\n## Installation\n\n```bash\n# Installationsschritte\n```\n\n## Konfiguration\n\n_Umgebungsvariablen und Einstellungen._",
            self::Api => "# API-Referenz\n\n## Endpunkte\n\n### GET /api/...\n\n_Beschreibung._\n\n**Parameter:**\n\n| Name | Typ | Beschreibung |\n|------|-----|-------------|\n| ... | ... | ... |\n\n**Response:**\n\n```json\n{}\n```",
            self::DataModel => "# Datenmodell\n\n## Tabellen\n\n### Tabelle: ...\n\n| Feld | Typ | Beschreibung |\n|------|-----|-------------|\n| id | bigint | Primaerschluessel |\n\n## Beziehungen\n\n_Beschreibe die Beziehungen zwischen Tabellen._",
            self::Testing => "# Testing\n\n## Teststrategie\n\n_Beschreibe die Teststrategie._\n\n## Tests ausfuehren\n\n```bash\nphp artisan test --filter=...\n```\n\n## Testabdeckung\n\n_Aktuelle Abdeckung und Ziele._",
            self::Deployment => "# Deployment\n\n## Umgebungen\n\n- **Production:** ...\n- **Staging:** ...\n\n## Deployment-Prozess\n\n_Beschreibe den Deployment-Prozess._\n\n## Rollback\n\n_Schritte fuer einen Rollback._",
            self::Changelog => "# Changelog\n\n## [Unreleased]\n\n### Hinzugefuegt\n\n- _Neues Feature_\n\n### Geaendert\n\n- _Aenderung_\n\n### Behoben\n\n- _Bugfix_",
            self::Contributing => "# Contributing\n\n## Code-Style\n\n_Beschreibe den Code-Style._\n\n## Branch-Strategie\n\n_Beschreibe die Branch-Strategie._\n\n## Review-Prozess\n\n_Beschreibe den Review-Prozess._",
            self::Troubleshooting => "# Troubleshooting\n\n## Haeufige Probleme\n\n### Problem: ...\n\n**Symptom:** _Beschreibung_\n\n**Loesung:** _Loesung_\n\n## Debugging\n\n_Debugging-Tipps._",
            self::Custom => '',
        };
    }

    /**
     * Returns all predefined types (excluding Custom).
     */
    public static function predefined(): array
    {
        return array_filter(self::cases(), fn (self $type) => $type !== self::Custom);
    }
}
