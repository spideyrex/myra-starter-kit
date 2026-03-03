<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'file_name' => $this->file_name,
            'name' => $this->name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'human_size' => $this->humanFileSize($this->size),
            'url' => $this->getFullUrl(),
            'thumbnail' => $this->hasGeneratedConversion('thumb') ? $this->getFullUrl('thumb') : $this->getFullUrl(),
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }

    private function humanFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2).' '.$units[$i];
    }
}
