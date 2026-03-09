<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmailLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'to' => $this->to,
            'subject' => $this->subject,
            'template_slug' => $this->template_slug,
            'status' => $this->status,
            'sent_at' => $this->sent_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'error' => $this->error,
        ];
    }
}
