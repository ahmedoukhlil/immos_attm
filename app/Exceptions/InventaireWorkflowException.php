<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Erreur métier du workflow d'inventaire.
 *
 * Son message est destiné à l'utilisateur et peut être renvoyé tel quel
 * par l'API, contrairement aux exceptions techniques (SQL, framework).
 */
class InventaireWorkflowException extends \LogicException
{
    /**
     * Message à afficher pour une exception attrapée par un écran : les
     * erreurs métier gardent leur message, les erreurs techniques sont
     * journalisées et remplacées par le message générique fourni.
     */
    public static function messagePour(Throwable $exception, string $messageParDefaut): string
    {
        if ($exception instanceof self || $exception instanceof AuthorizationException) {
            return $exception->getMessage();
        }

        if ($exception instanceof ValidationException) {
            return collect($exception->errors())->flatten()->first() ?? $messageParDefaut;
        }

        report($exception);

        return $messageParDefaut;
    }
}
