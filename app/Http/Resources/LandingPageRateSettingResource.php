<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LandingPageRateSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'server_dedicated_price' => (float) $this->server_dedicated_price,
            'server_shared_price' => (float) $this->server_shared_price,
            'design_dedicated_price' => (float) $this->design_dedicated_price,
            'design_template_price' => (float) $this->design_template_price,
            'default_rate_developer' => (float) $this->default_rate_developer,
            'margin_percent' => (float) $this->margin_percent,
            'updated_at' => $this->updated_at,
        ];
    }
}
