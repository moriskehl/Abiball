<?php

declare(strict_types=1);

/**
 * TicketValidationService - Ticketentwertung am Einlass
 * 
 * Prüft Tickets auf Gültigkeit, Bezahlstatus und verhindert Mehrfachnutzung.
 * Protokolliert alle Entwertungen für spätere Nachverfolgung.
 */

require_once __DIR__ . '/../Config.php';
require_once __DIR__ . '/../Repository/CsvRepository.php';
require_once __DIR__ . '/../Repository/ParticipantsRepository.php';
require_once __DIR__ . '/PricingService.php';

final class TicketValidationService
{
    /**
     * Validiert ein Ticket und entwertet es bei Erfolg.
     * Prüft Existenz, Bezahlung und ob das Ticket bereits verwendet wurde.
     */
    public static function validateAndInvalidateTicket(string $pid, string $doorPersonId): array
    {
        $pid = trim($pid);
        if ($pid === '') {
            return [
                'success' => false,
                'message' => 'Ticket-ID ungültig',
            ];
        }

        $person = ParticipantsRepository::findById($pid);
        if (!$person) {
            return [
                'success' => false,
                'message' => 'Ticket nicht gefunden',
            ];
        }

        // Doppelte Entwertung verhindern
        $validatedStatus = trim((string)($person['ticket_validated'] ?? ''));
        if ($validatedStatus !== '' && $validatedStatus !== '0') {
            $validationTime = (string)($person['validation_time'] ?? '');
            return [
                'success' => false,
                'message' => 'Ticket bereits entwertet',
                'person_name' => (string)($person['name'] ?? ''),
                'person_id' => $pid,
                'valid_until' => $validationTime,
            ];
        }

        $mainId = trim((string)($person['main_id'] ?? ''));
        if ($mainId === '') {
            return [
                'success' => false,
                'message' => 'Ticketdaten unvollständig',
            ];
        }

        $group = ParticipantsRepository::getGroupByMainId($mainId);
        $main = $group['main'] ?? null;
        if (!$main) {
            return [
                'success' => false,
                'message' => 'Hauptgast nicht gefunden',
            ];
        }

        // Ohne vollständige Bezahlung kein Einlass
        $paid = (int)ParticipantsRepository::amountPaidForMainId($mainId);
        $dueInfo = PricingService::amountDueForMainId($mainId);
        $due = (int)($dueInfo['amount_due'] ?? 0);
        $open = max(0, $due - $paid);

        if ($due > 0 && $open > 0) {
            return [
                'success' => false,
                'message' => 'Zahlung unvollständig – Ticket ist ungültig',
                'person_name' => (string)($person['name'] ?? ''),
                'person_id' => $pid,
                'main_name' => (string)($main['name'] ?? ''),
            ];
        }

        $validationTime = date('Y-m-d H:i:s');
        if (self::markTicketAsValidated($pid, $validationTime, $doorPersonId)) {
            self::auditLog($pid, $mainId, $doorPersonId, 'VALIDATED');

            return [
                'success' => true,
                'message' => 'Ticket erfolgreich entwertet',
                'person_name' => (string)($person['name'] ?? ''),
                'person_id' => $pid,
                'main_name' => (string)($main['name'] ?? ''),
                'valid_until' => $validationTime,
            ];
        }

        return [
            'success' => false,
            'message' => 'Fehler beim Speichern',
        ];
    }

    /**
     * Schreibt die Entwertung direkt in die Teilnehmer-CSV.
     */
    private static function markTicketAsValidated(string $pid, string $validationTime, string $doorPersonId): bool
    {
        $csvPath = Config::participantsCsvPath();

        try {
            CsvRepository::updateAssocAtomic($csvPath, static function (array $header, array $rows) use ($pid, $validationTime, $doorPersonId): array {
                // Sicherstellen dass die Validierungs-Spalten existieren
                $needCols = ['ticket_validated', 'validation_time', 'validation_person'];
                foreach ($needCols as $col) {
                    if (!in_array($col, $header, true)) {
                        $header[] = $col;
                    }
                }

                $found = false;
                foreach ($rows as &$r) {
                    if (trim((string)($r['id'] ?? '')) !== $pid) {
                        continue;
                    }

                    // Innerhalb des Locks nochmal prüfen ob bereits entwertet
                    $status = trim((string)($r['ticket_validated'] ?? ''));
                    if ($status !== '' && $status !== '0') {
                        throw new RuntimeException('ALREADY_VALIDATED');
                    }

                    $r['ticket_validated'] = '1';
                    $r['validation_time'] = $validationTime;
                    $r['validation_person'] = $doorPersonId;
                    $found = true;
                    break;
                }
                unset($r);

                if (!$found) {
                    throw new RuntimeException('Participant not found for id=' . $pid);
                }

                return [$header, $rows];
            }, ';');

            return true;
        } catch (RuntimeException $e) {
            // Bereits entwertet oder nicht gefunden
            return false;
        }
    }

    /**
     * Protokolliert Entwertungen für spätere Nachverfolgung und Statistiken.
     */
    private static function auditLog(string $ticketId, string $mainId, string $doorPersonId, string $action): void
    {
        $logDir = dirname(Config::participantsCsvPath()) . '/audit';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $logFile = $logDir . '/validation_log.csv';
        $exists = file_exists($logFile);

        $timestamp = date('Y-m-d H:i:s');
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        $line = implode(';', [
            $timestamp,
            $ticketId,
            $mainId,
            $doorPersonId,
            $action,
            $ipAddress,
        ]);

        if (!$exists) {
            $header = "timestamp;ticket_id;main_id;door_person_id;action;ip_address\n";
            file_put_contents($logFile, $header . $line . "\n", FILE_APPEND);
        } else {
            file_put_contents($logFile, $line . "\n", FILE_APPEND);
        }
    }
}
