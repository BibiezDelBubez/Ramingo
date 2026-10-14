<?php
declare(strict_types=1);

namespace Ramingo\Services\Catalog;

/**
 * Quanto sono recenti i DATI di un report (non quando è stato modificato il report).
 * Calcolo unico usato da schede del catalogo e pagina del report.
 */
final class Freshness
{
    public const FRESH   = 'fresh';    // aggiornati entro la soglia
    public const STALE   = 'stale';    // più vecchi della soglia
    public const FAILED  = 'failed';   // l'ultimo aggiornamento non è riuscito
    public const UNKNOWN = 'unknown';  // Ramingo non lo sa (vedi reason)

    /**
     * @param array $row con data_refreshed_at, data_last_status, data_last_attempt_at, data_has_history, data_accessible
     * @return array{state:string, label:?string, title:string, reason:?string}
     */
    public static function of(array $row, int $staleDays): array
    {
        $success = $row['data_refreshed_at'] ?? null;
        $status  = $row['data_last_status'] ?? null;

        if ($success === null) {
            $reason = match (true) {
                !array_key_exists('data_accessible', $row) || $row['data_accessible'] === null
                    => 'Non ancora controllato: verrà letto alla prossima sincronizzazione.',
                (int) $row['data_accessible'] === 0
                    => 'Ramingo-Sync non è membro del workspace del modello semantico, quindi Power BI non gli mostra gli aggiornamenti.',
                (int) ($row['data_has_history'] ?? 0) === 0
                    => 'Power BI non registra aggiornamenti per questo modello: di solito significa che i dati sono letti in tempo reale (DirectQuery) oppure che il modello non è mai stato aggiornato online.',
                default => 'Nessun aggiornamento riuscito tra gli ultimi registrati da Power BI.',
            };
            return ['state' => $status === 'Failed' ? self::FAILED : self::UNKNOWN,
                    'label' => $status === 'Failed' ? 'Aggiornamento dei dati non riuscito' : null,
                    'title' => $reason, 'reason' => $reason];
        }

        $label = 'Dati aggiornati ' . self::when($success);
        if ($status === 'Failed') {
            return ['state' => self::FAILED, 'label' => $label,
                    'title' => 'L\'ultimo aggiornamento dei dati (' . fmt_date($row['data_last_attempt_at'] ?? null, true) . ') non è riuscito: i dati sono quelli del ' . fmt_date($success, true) . '.',
                    'reason' => null];
        }
        $age = (time() - strtotime($success)) / 86400;
        return $age > $staleDays
            ? ['state' => self::STALE, 'label' => $label, 'title' => "Dati più vecchi di $staleDays giorni.", 'reason' => null]
            : ['state' => self::FRESH, 'label' => $label, 'title' => 'Ultimo aggiornamento dei dati: ' . fmt_date($success, true) . '.', 'reason' => null];
    }

    /** «oggi alle 02:04», «ieri alle 02:04», «al 17/07/2025». */
    private static function when(string $iso): string
    {
        $tz  = new \DateTimeZone(date_default_timezone_get());
        $d   = (new \DateTimeImmutable($iso))->setTimezone($tz);
        $day = $d->format('Y-m-d');
        if ($day === (new \DateTimeImmutable('today', $tz))->format('Y-m-d')) {
            return 'oggi alle ' . $d->format('H:i');
        }
        if ($day === (new \DateTimeImmutable('yesterday', $tz))->format('Y-m-d')) {
            return 'ieri alle ' . $d->format('H:i');
        }
        return 'al ' . $d->format('d/m/Y');
    }
}
