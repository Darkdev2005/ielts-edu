<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AiRequestResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'request_id' => $this->id,
            'status' => $this->status,
            'output' => $this->output_json,
            'error' => $this->error_text,
            'started_at' => $this->started_at,
            'finished_at' => $this->finished_at,
        ];
    }
}
