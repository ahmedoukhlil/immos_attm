<?php

namespace App\Support;

use Illuminate\Support\Facades\Validator;

final class SelectedIds
{
    /**
     * Normalise une liste d'identifiants issue d'un tableau ou d'une chaîne CSV.
     * Une valeur absente signifie « sans filtre » ; une valeur présente mais
     * invalide déclenche une erreur de validation au lieu d'élargir la requête.
     *
     * @return list<int>|null
     */
    public static function parse(mixed $value, string $field = 'ids', int $max = 5000): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        $values = is_string($value) ? explode(',', $value) : $value;

        $validated = Validator::make(
            [$field => $values],
            [
                $field => ['required', 'array', 'min:1', 'max:'.$max],
                $field.'.*' => ['bail', 'required', 'integer', 'min:1', 'distinct'],
            ],
            [
                $field.'.max' => "La sélection ne peut pas dépasser {$max} éléments.",
                $field.'.*.integer' => 'Chaque identifiant sélectionné doit être un entier.',
                $field.'.*.distinct' => 'La sélection contient des identifiants en double.',
            ],
        )->validate();

        return array_map('intval', array_values($validated[$field]));
    }
}
