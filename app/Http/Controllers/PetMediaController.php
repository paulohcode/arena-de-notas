<?php

namespace App\Http\Controllers;

use App\Models\Pet;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PetMediaController extends Controller
{
    public function show(Pet $pet): BinaryFileResponse
    {
        $path = str_replace('\\', '/', (string) $pet->gif_path);

        abort_unless(
            $path !== ''
            && ! str_contains($path, '..')
            && str_starts_with($path, 'pets/'),
            404
        );

        $disk = Storage::disk('public');
        abort_unless($disk->exists($path), 404);

        $mime = $disk->mimeType($path) ?: 'application/octet-stream';

        return response()->file($disk->path($path), [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
