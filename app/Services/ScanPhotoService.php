<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ScanPhotoService
{
    public const MAX_BASE64_LENGTH = 7_000_000;

    public const MAX_IMAGE_BYTES = 5_000_000;

    public function storeBase64(string $base64Image, string $bienCode): string
    {
        if (strlen($base64Image) > self::MAX_BASE64_LENGTH
            || ! preg_match('/^data:image\/(?:jpeg|png);base64,([A-Za-z0-9+\/=\r\n]+)$/', $base64Image, $matches)) {
            throw ValidationException::withMessages([
                'photo' => 'La photo doit être une image JPEG ou PNG valide.',
            ]);
        }

        $image = base64_decode($matches[1], true);

        if ($image === false || strlen($image) > self::MAX_IMAGE_BYTES) {
            throw ValidationException::withMessages([
                'photo' => 'La photo est invalide ou dépasse 5 Mo.',
            ]);
        }

        $imageInfo = getimagesizefromstring($image);
        $extension = match ($imageInfo['mime'] ?? null) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            default => throw ValidationException::withMessages([
                'photo' => 'Le contenu de la photo ne correspond pas à une image JPEG ou PNG.',
            ]),
        };

        $filename = 'scan_'.$bienCode.'_'.now()->format('YmdHis').'_'.Str::random(16).'.'.$extension;
        $path = 'photos/'.now()->format('Y/m').'/'.$filename;

        Storage::disk('public')->put($path, $image);

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path !== null) {
            Storage::disk('public')->delete($path);
        }
    }
}
