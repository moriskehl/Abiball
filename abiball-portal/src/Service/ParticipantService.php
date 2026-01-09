<?php
// src/Service/ParticipantService.php

require_once __DIR__ . '/../Repository/ParticipantsRepository.php';

final class ParticipantService
{
    public static function getMainAndCompanions(string $mainId): array
    {
        return ParticipantsRepository::getGroupByMainId($mainId);
    }
}
