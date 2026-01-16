<?php
declare(strict_types=1);

// src/Service/TicketValidationService.php

require_once __DIR__ . '/../Config.php';
require_once __DIR__ . '/../Repository/ParticipantsRepository.php';
require_once __DIR__ . '/PricingService.php';

final class TicketValidationService
{
    /**
     * Validiert und entwertet ein Ticket
     * 
     * @return array{success: bool, message: string, person_name?: string, person_id?: string, main_name?: string, valid_until?: string}
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

        // Person laden
        $person = ParticipantsRepository::findById($pid);
        if (!$person) {
            return [
                'success' => false,
                'message' => 'Ticket nicht gefunden',
            ];
        }

        // Prüfe ob bereits entwertet
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

        // Hauptgast laden
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

        // Zahlung prüfen
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

        // Ticket entwertet als gültig markieren
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
     * Markiert Ticket als entwertet in der CSV
     */
    private static function markTicketAsValidated(string $pid, string $validationTime, string $doorPersonId): bool
    {
        $csvPath = Config::participantsCsvPath();
        $rows = file($csvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($rows === false) {
            return false;
        }

        // Header + Daten
        if (empty($rows)) {
            return false;
        }

        $header = array_shift($rows);
        $headerArray = str_getcsv($header, ';');

        // Finde Spalten-Indizes
        $idIdx = array_search('id', $headerArray, true);
        $validatedIdx = array_search('ticket_validated', $headerArray, true);
        $validationTimeIdx = array_search('validation_time', $headerArray, true);
        $validationPersonIdx = array_search('validation_person', $headerArray, true);

        if ($idIdx === false) {
            return false;
        }

        $found = false;
        foreach ($rows as &$row) {
            $fields = str_getcsv($row, ';');
            if ($fields[$idIdx] === $pid) {
                // Markiere als entwertet
                if ($validatedIdx !== false) {
                    $fields[$validatedIdx] = '1';
                }
                if ($validationTimeIdx !== false) {
                    $fields[$validationTimeIdx] = $validationTime;
                }
                if ($validationPersonIdx !== false) {
                    $fields[$validationPersonIdx] = $doorPersonId;
                }
                $row = implode(';', $fields);
                $found = true;
                break;
            }
        }

        if (!$found) {
            return false;
        }

        // Schreibe zurück
        $content = $header . "\n" . implode("\n", $rows) . "\n";
        return file_put_contents($csvPath, $content) !== false;
    }

    /**
     * Audit-Log für Entwertungen
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
