<?php
declare(strict_types=1);

namespace Ramingo\Domain;

/**
 * Provenienza di ogni informazione mostrata da Ramingo.
 * È il cuore del principio "distinguere sempre": ogni dato a video
 * porta con sé un badge generato da qui (views/partials/source_badge.php).
 */
enum Source: string
{
    /** 1. Metadati restituiti direttamente dalle API di Power BI (nome, date, autori, permessi). */
    case PowerBi = 'pbi';
    /** 2. Estratto da definizioni tecniche: schema del modello, DAX, M, layout del report. */
    case Definition = 'def';
    /** 3. Scritto a mano da una persona in Ramingo. */
    case Manual = 'manual';
    /** 4. Interpretazione generata automaticamente (euristiche o modelli AI). Da verificare. */
    case Auto = 'auto';

    public function label(): string
    {
        return match ($this) {
            self::PowerBi    => 'Power BI',
            self::Definition => 'Definizione tecnica',
            self::Manual     => 'Scritto a mano',
            self::Auto       => 'Interpretazione automatica',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::PowerBi    => 'PBI',
            self::Definition => 'DEF',
            self::Manual     => 'MAN',
            self::Auto       => 'AUTO',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::PowerBi    => 'fa-solid fa-chart-simple',
            self::Definition => 'fa-solid fa-code',
            self::Manual     => 'fa-solid fa-pen',
            self::Auto       => 'fa-solid fa-wand-magic-sparkles',
        };
    }

    public function explanation(): string
    {
        return match ($this) {
            self::PowerBi    => 'Dato letto direttamente dalle API di Power BI durante l\'ultima sincronizzazione.',
            self::Definition => 'Dato estratto dalla definizione tecnica (modello semantico, DAX, Power Query o layout del report).',
            self::Manual     => 'Testo scritto da una persona in Ramingo.',
            self::Auto       => 'Interpretazione generata automaticamente: può essere imprecisa, va verificata.',
        };
    }

    public function cssClass(): string
    {
        return 'src-' . $this->value;
    }
}
